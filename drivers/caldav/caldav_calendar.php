<?php

/**
 * CalDAV calendar storage class
 *
 * @author Aleksander Machniak <machniak@apheleia-it.ch>
 *
 * Copyright (C) 2012-2022, Apheleia IT AG <contact@apheleia-it.ch>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class caldav_calendar extends kolab_storage_dav_folder
{
    public $ready         = false;
    public $rights        = 'lrs';
    public $editable      = false;
    public $attachments   = true;
    public $alarms        = false;
    public $history       = false;
    public $subscriptions = false;
    public $share_invitation = null;
    public $categories    = [];
    public $storage;

    public $type = 'event';

    protected $cal;
    protected $events = [];
    protected $search_fields = ['title', 'description', 'location', 'attendees', 'categories'];

    /**
     * Factory method to instantiate a caldav_calendar object
     *
     * @param string   $id       Calendar ID (encoded IMAP folder name)
     * @param calendar $calendar Calendar plugin object
     *
     * @return caldav_calendar Self instance
     */
    public static function factory($id, $calendar)
    {
        return new caldav_calendar($id, $calendar);
    }

    /**
     * Default constructor
     */
    public function __construct($folder_or_id, $calendar)
    {
        if ($folder_or_id instanceof kolab_storage_dav_folder) {
            $this->storage = $folder_or_id;
        } else {
            // $this->storage = kolab_storage_dav::get_folder($folder_or_id, 'event');
        }

        $this->cal   = $calendar;
        $this->ready = !empty($this->storage);

        // Set writeable and alarms flags according to folder permissions
        if ($this->ready) {
            $this->id         = $this->storage->id;
            $this->attributes = $this->storage->attributes;
            $this->default    = $this->storage->default;
            $this->subtype    = $this->storage->subtype;
            $this->alarms     = !isset($this->attributes['alarms']) || $this->attributes['alarms'];

            if ($this->storage->get_namespace() == 'personal') {
                $this->rights = 'lrswikxteav';
            } else {
                $this->rights = $this->storage->get_myrights();
            }

            $this->editable = strpos($this->rights, 'i') !== false;

            if (!empty($this->attributes['invitation'])) {
                $this->share_invitation = $this->attributes['invitation'];
            }
        }
    }

    /**
     * Getter for the folder name
     *
     * @return string Name of the folder
     */
    public function get_realname()
    {
        return $this->get_name();
    }

    /**
     * Return color to display this calendar
     */
    public function get_color($default = null)
    {
        if ($color = $this->storage->get_color()) {
            return $color;
        }

        return $default ?: 'cc0000';
    }

    /**
     * Compose an URL for CalDAV access to this calendar (if configured)
     */
    public function get_caldav_url()
    {
        /*
                if ($template = $this->cal->rc->config->get('calendar_caldav_url', null)) {
                    return strtr($template, [
                            '%h' => $_SERVER['HTTP_HOST'],
                            '%u' => urlencode($this->cal->rc->get_user_name()),
                            '%i' => urlencode($this->storage->get_uid()),
                            '%n' => urlencode($this->name),
                    ]);
                }
        */
        return false;
    }

    /**
     * Update properties of this calendar folder
     *
     * @see caldav_driver::edit_calendar()
     */
    public function update(&$prop)
    {
        return false; // NOP
    }

    /**
     * Getter for a single event object
     */
    public function get_event($id)
    {
        // remove our occurrence identifier if it's there
        $master_id = preg_replace('/-\d{8}(T\d{6})?$/', '', $id);

        // directly access storage object
        if (empty($this->events[$id]) && $master_id == $id && ($record = $this->storage->get_object($id))) {
            $this->events[$id] = $record = $this->_to_driver_event($record, true);
        }

        // maybe a recurring instance is requested
        if (empty($this->events[$id]) && $master_id != $id) {
            $instance_id = substr($id, strlen($master_id) + 1);

            if ($record = $this->storage->get_object($master_id)) {
                $master = $record = $this->_to_driver_event($record);
            }

            if (!empty($master)) {
                // check for match on the first instance already
                if (!empty($master['_instance']) && $master['_instance'] == $instance_id) {
                    $this->events[$id] = $master;
                } elseif (!empty($master['recurrence'])) {
                    $start_date = $master['start'];
                    // For performance reasons we'll get only the specific instance
                    if (($date = substr($id, strlen($master_id) + 1, 8)) && strlen($date) == 8 && is_numeric($date)) {
                        $start_date = new DateTime($date . 'T000000', $master['start']->getTimezone());
                    }

                    $this->get_recurring_events($record, $start_date, null, $id, 1);
                }
            }
        }

        return $this->events[$id] ?? null;
    }

    /**
     * Get attachment body
     * @see calendar_driver::get_attachment_body()
     */
    public function get_attachment_body($id, $event)
    {
        $event = $this->get_event($event['id']);
        $data = $this->storage->get_attachment($id, $event);
        /*
                if ($data === false) {
                    // try again with master UID
                    $uid = preg_replace('/-\d+(T\d{6})?$/', '', $event['id']);
                    if ($uid != $event['id']) {
                        $data = $this->storage->get_attachment($uid, $id); // TODO
                    }
                }
        */
        return $data;
    }

    /**
     * @param int    $start        Event's new start (unix timestamp)
     * @param int    $end          Event's new end (unix timestamp)
     * @param string $search       Search query (optional)
     * @param bool   $virtual      Include virtual events (optional)
     * @param array  $query        Additional parameters to query storage
     * @param array  $filter_query Additional query to filter events
     *
     * @return array A list of event records
     */
    public function list_events($start, $end, $search = null, $virtual = true, $query = [], $filter_query = null)
    {
        // convert to DateTime for comparisons
        // #5190: make the range a little bit wider
        // to workaround possible timezone differences
        try {
            $start = new DateTime('@' . ($start - 12 * 3600));
        } catch (Exception $e) {
            $start = new DateTime('@0');
        }
        try {
            $end = new DateTime('@' . ($end + 12 * 3600));
        } catch (Exception $e) {
            $end = new DateTime('today +10 years');
        }

        // get email addresses of the current user
        $user_emails = $this->cal->get_user_emails();

        // query Kolab storage
        $query[] = ['dtstart', '<=', $end];
        $query[] = ['dtend',   '>=', $start];

        if (is_array($filter_query)) {
            $query = array_merge($query, $filter_query);
        }

        $words = [];
        $partstat_exclude = [];
        $events = [];

        if (!empty($search)) {
            $search = mb_strtolower($search);
            $words  = rcube_utils::tokenize_string($search, 1);
            foreach (rcube_utils::normalize_string($search, true) as $word) {
                $query[] = ['words', 'LIKE', $word];
            }
        }

        // set partstat filter to skip pending and declined invitations
        if (empty($filter_query)
            && $this->cal->rc->config->get('kolab_invitation_calendars')
            && $this->get_namespace() != 'other'
        ) {
            $partstat_exclude = ['NEEDS-ACTION', 'DECLINED'];
        }

        foreach ($this->storage->select($query) as $record) {
            if (empty($record)) {
                continue;
            }

            $event = $this->_to_driver_event($record, !$virtual, false);

            // remember seen categories
            if (!empty($event['categories'])) {
                $cat = is_array($event['categories']) ? $event['categories'][0] : $event['categories'];
                $this->categories[$cat]++;
            }

            // list events in requested time window
            if ($event['start'] <= $end && $event['end'] >= $start) {
                unset($event['_attendees']);
                $add = true;

                // skip the first instance of a recurring event if listed in exdate
                if ($virtual && !empty($event['recurrence']['EXDATE'])) {
                    $event_date = $event['start']->format('Ymd');
                    $event_tz   = $event['start']->getTimezone();

                    foreach ((array) $event['recurrence']['EXDATE'] as $exdate) {
                        $ex = clone $exdate;
                        $ex->setTimezone($event_tz);

                        if ($ex->format('Ymd') == $event_date) {
                            $add = false;
                            break;
                        }
                    }
                }

                // find and merge exception for the first instance
                if ($virtual && !empty($event['recurrence']) && !empty($event['recurrence']['EXCEPTIONS'])) {
                    foreach ($event['recurrence']['EXCEPTIONS'] as $exception) {
                        if (libcalendaring::is_recurrence_exception($event, $exception)) {
                            unset($exception['calendar'], $exception['className'], $exception['_folder_id']);
                            // clone date objects from main event before adjusting them with exception data
                            if (is_object($event['start'])) {
                                $event['start'] = clone $record['start'];
                            }
                            if (is_object($event['end'])) {
                                $event['end'] = clone $record['end'];
                            }

                            kolab_driver::merge_exception_data($event, $exception);
                        }
                    }
                }

                if ($add) {
                    $events[] = $event;
                }
            }

            // resolve recurring events
            if (!empty($event['recurrence']) && $virtual == 1) {
                $events = array_merge($events, $this->get_recurring_events($event, $start, $end));
            }
            // add top-level exceptions (aka loose single occurrences)
            elseif (!empty($record['exceptions'])) {
                foreach ($record['exceptions'] as $ex) {
                    $component = $this->_to_driver_event($ex, false, false, $record);
                    if ($component['start'] <= $end && $component['end'] >= $start) {
                        $events[] = $component;
                    }
                }
            }
        }

        // post-filter all events by fulltext search and partstat values
        $me = $this;
        $events = array_filter($events, function ($event) use ($words, $partstat_exclude, $user_emails, $me) {
            // fulltext search
            if (count($words)) {
                $hits = 0;
                foreach ($words as $word) {
                    $hits += $me->fulltext_match($event, $word, false);
                }
                if ($hits < count($words)) {
                    return false;
                }
            }

            // partstat filter
            if (count($partstat_exclude) && !empty($event['attendees'])) {
                foreach ($event['attendees'] as $attendee) {
                    if (
                        in_array($attendee['email'], $user_emails)
                        && in_array($attendee['status'], $partstat_exclude)
                    ) {
                        return false;
                    }
                }
            }

            return true;
        });

        // Apply event-to-mail relations
        // $config = kolab_storage_config::get_instance();
        // $config->apply_links($events);

        return $events;
    }

    /**
     * Get number of events in the given calendar
     *
     * @param int   $start        Date range start (unix timestamp)
     * @param int   $end          Date range end (unix timestamp)
     * @param array $filter_query Additional query to filter events
     *
     * @return int Number of events
     */
    public function count_events($start, $end = null, $filter_query = null)
    {
        // convert to DateTime for comparisons
        try {
            $start = new DateTime('@' . $start);
        } catch (Exception $e) {
            $start = new DateTime('@0');
        }
        if ($end) {
            try {
                $end = new DateTime('@' . $end);
            } catch (Exception $e) {
                $end = null;
            }
        }

        // query Kolab storage
        $query[] = ['dtend',   '>=', $start];

        if ($end) {
            $query[] = ['dtstart', '<=', $end];
        }

        // add query to exclude pending/declined invitations
        if (empty($filter_query)) {
            foreach ($this->cal->get_user_emails() as $email) {
                $query[] = ['tags', '!=', 'x-partstat:' . $email . ':needs-action'];
                $query[] = ['tags', '!=', 'x-partstat:' . $email . ':declined'];
            }
        } elseif (is_array($filter_query)) {
            $query = array_merge($query, $filter_query);
        }

        return $this->storage->count($query);
    }

    /**
     * Create a new event record
     *
     * @see calendar_driver::new_event()
     *
     * @return array|false The created record ID on success, False on error
     */
    public function insert_event($event)
    {
        if (!is_array($event)) {
            return false;
        }

        // email links are stored separately
        // $links = !empty($event['links']) ? $event['links'] : [];
        // unset($event['links']);

        // generate new event from RC input
        $object = $this->_from_driver_event($event);
        $saved  = $this->storage->save($object, 'event');

        if (!$saved) {
            rcube::raise_error(
                [
                    'code' => 600, 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Error saving event object to DAV server",
                ],
                true,
                false
            );
            return false;
        }

        // save links in configuration.relation object
        // if ($this->save_links($event['uid'], $links)) {
        //    $object['links'] = $links;
        // }

        $this->events = [$event['uid'] => $this->_to_driver_event($object, true)];

        return $saved;
    }

    /**
     * Update a specific event record
     *
     * @return bool True on success, False on error
     */
    public function update_event($event, $exception_id = null)
    {
        $updated = false;
        $old = $this->storage->get_object(!empty($event['uid']) ? $event['uid'] : $event['id']);

        if (!$old || PEAR::isError($old)) {
            return false;
        }

        // email links are stored separately
        // $links = !empty($event['links']) ? $event['links'] : [];
        // unset($event['links']);

        $object = $this->_from_driver_event($event, $old);

        $object['created'] = $old['created'] ?? null;

        $saved = $this->storage->save($object, 'event', $old['uid']);

        if (!$saved) {
            rcube::raise_error(
                [
                    'code' => 600, 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Error saving event object to CalDAV server",
                ],
                true,
                false
            );
        } else {
            // save links in configuration.relation object
            // if ($this->save_links($event['uid'], $links)) {
            //     $object['links'] = $links;
            // }

            $updated = true;
            $this->events = [$event['uid'] => $this->_to_driver_event($object, true)];

            // refresh local cache with recurring instances
            if ($exception_id) {
                $this->get_recurring_events($object, $event['start'], $event['end'], $exception_id);
            }
        }

        return $updated;
    }

    /**
     * Delete an event record
     *
     * @see calendar_driver::remove_event()
     *
     * @return bool True on success, False on error
     */
    public function delete_event($event, $force = true)
    {
        $uid = !empty($event['uid']) ? $event['uid'] : $event['id'];
        $deleted = $this->storage->delete($uid, $force);

        if (!$deleted) {
            rcube::raise_error(
                [
                    'code' => 600, 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Error deleting event '{$uid}' from CalDAV server",
                ],
                true,
                false
            );
        }

        return $deleted;
    }

    /**
     * Restore deleted event record
     *
     * @see calendar_driver::undelete_event()
     *
     * @return bool True on success, False on error
     */
    public function restore_event($event)
    {
        // TODO
        return false;
    }

    /**
     * Find messages linked with an event
     */
    protected function get_links($uid)
    {
        return []; // TODO
        /*
        $storage = kolab_storage_config::get_instance();
        return $storage->get_object_links($uid);
        */
    }

    /**
     * Save message references (links) to an event
     */
    protected function save_links($uid, $links)
    {
        return false; // TODO
        /*
        $storage = kolab_storage_config::get_instance();
        return $storage->save_object_links($uid, (array) $links);
        */
    }

    /**
     * Create instances of a recurring event
     *
     * @param array     $event    Hash array with event properties
     * @param DateTime  $start    Start date of the recurrence window
     * @param ?DateTime $end      End date of the recurrence window
     * @param string    $event_id ID of a specific recurring event instance
     * @param int       $limit    Max. number of instances to return
     *
     * @return array List of recurring event instances
     */
    public function get_recurring_events($event, $start, $end = null, $event_id = null, $limit = null)
    {
        // determine a reasonable end date if none given
        if (!$end) {
            $end = clone $event['start'];
            $end->add(new DateInterval('P100Y'));
        }

        // read recurrence exceptions first
        $events = [];
        $exdata = [];
        $futuredata = [];
        $recurrence_id_format = libcalendaring::recurrence_id_format($event);
        $recurrence_rule = null;

        if (!empty($event['recurrence'])) {
            // copy the recurrence rule from the master event (to be used in the UI)
            $recurrence_rule = $event['recurrence'];
            unset($recurrence_rule['EXCEPTIONS'], $recurrence_rule['EXDATE']);

            if (!empty($event['recurrence']['EXCEPTIONS'])) {
                foreach ($event['recurrence']['EXCEPTIONS'] as $exception) {
                    if (empty($exception['_instance'])) {
                        $exception['_instance'] = libcalendaring::recurrence_instance_identifier($exception, !empty($event['allday']));
                    }

                    $rec_event = $this->_to_driver_event($exception, false, false, $event);
                    $rec_event['id'] = $event['uid'] . '-' . $exception['_instance'];
                    $rec_event['isexception'] = 1;

                    // found the specifically requested instance: register exception (single occurrence wins)
                    if (
                        $rec_event['id'] == $event_id
                        && (empty($this->events[$event_id]) || !empty($this->events[$event_id]['thisandfuture']))
                    ) {
                        $rec_event['recurrence'] = $recurrence_rule;
                        $rec_event['recurrence_id'] = $event['uid'];
                        $this->events[$rec_event['id']] = $rec_event;
                    }

                    // remember this exception's date
                    $exdate = substr($exception['_instance'], 0, 8);
                    if (empty($exdata[$exdate]) || !empty($exdata[$exdate]['thisandfuture'])) {
                        $exdata[$exdate] = $rec_event;
                    }
                    if (!empty($rec_event['thisandfuture'])) {
                        $futuredata[$exdate] = $rec_event;
                    }
                }
            }
        }

        // found the specifically requested instance, exiting...
        if ($event_id && !empty($this->events[$event_id])) {
            return [$this->events[$event_id]];
        }

        // Check first occurrence, it might have been moved
        if (!empty($exdata[$event['start']->format('Ymd')])) {
            $first = $exdata[$event['start']->format('Ymd')];
            // return it only if not already in the result, but in the requested period
            if (!($event['start'] <= $end && $event['end'] >= $start)
                && ($first['start'] <= $end && $first['end'] >= $start)
            ) {
                $events[] = $first;
            }
        }

        if ($limit && count($events) >= $limit) {
            return $events;
        }

        // use libcalendaring to compute recurring events
        $recurrence = libcalendaring::get_recurrence($event);

        $i = 0;
        while ($next_event = $recurrence->next_instance()) {
            $datestr     = $next_event['start']->format('Ymd');
            $instance_id = $next_event['start']->format($recurrence_id_format);

            // use this event data for future recurring instances
            $overlay_data = $futuredata[$datestr] ?? null;

            $rec_id      = $event['uid'] . '-' . $instance_id;
            $exception   = !empty($exdata[$datestr]) ? $exdata[$datestr] : $overlay_data;
            $event_start = $next_event['start'];
            $event_end   = $next_event['end'];

            // copy some event from exception to get proper start/end dates
            if ($exception) {
                $event_copy = $next_event;
                caldav_driver::merge_exception_dates($event_copy, $exception);
                $event_start = $event_copy['start'];
                $event_end   = $event_copy['end'];
            }

            // add to output if in range
            if (($event_start <= $end && $event_end >= $start) || ($event_id && $rec_id == $event_id)) {
                $rec_event = $this->_to_driver_event($next_event, false, false, $event);
                $rec_event['_instance'] = $instance_id;
                $rec_event['_count'] = $i + 1;

                if ($exception) {
                    // copy data from exception
                    caldav_driver::merge_exception_data($rec_event, $exception);
                }

                $rec_event['id'] = $rec_id;
                $rec_event['recurrence_id'] = $event['uid'];
                $rec_event['recurrence'] = $recurrence_rule;
                unset($rec_event['_attendees']);
                $events[] = $rec_event;

                if ($rec_id == $event_id) {
                    $this->events[$rec_id] = $rec_event;
                    break;
                }

                if ($limit && count($events) >= $limit) {
                    return $events;
                }
            } elseif ($next_event['start'] > $end) {
                // stop loop if out of range
                break;
            }

            // avoid endless recursion loops
            if (++$i > 100000) {
                break;
            }
        }

        return $events;
    }

    /**
     * Convert from storage format to internal representation
     */
    private function _to_driver_event($record, $noinst = false, $links = true, $master_event = null)
    {
        $record['calendar'] = $this->id;

        // remove (possibly outdated) cached parameters
        unset($record['_folder_id'], $record['className']);

        // if ($links && !array_key_exists('links', $record)) {
        //    $record['links'] = $this->get_links($record['uid']);
        // }

        $ns = $this->get_namespace();

        if ($ns == 'other') {
            $record['className'] = 'fc-event-ns-other';
        }

        if ($ns == 'other' || !$this->cal->rc->config->get('kolab_invitation_calendars')) {
            $record = caldav_driver::add_partstat_class($record, ['NEEDS-ACTION', 'DECLINED'], $this->get_owner());

            // Modify invitation status class name, when invitation calendars are disabled
            // we'll use opacity only for declined/needs-action events
            $record['className'] = !empty($record['className']) ? str_replace('-invitation', '', $record['className']) : '';
        }

        // add instance identifier to first occurrence (master event)
        $recurrence_id_format = libcalendaring::recurrence_id_format($master_event ? $master_event : $record);
        if (!$noinst && !empty($record['recurrence']) && !empty($record['start'])
            && empty($record['recurrence_id']) && empty($record['_instance'])
        ) {
            $record['_instance'] = $record['start']->format($recurrence_id_format);
        } elseif (isset($record['recurrence_date']) && $record['recurrence_date'] instanceof DateTimeInterface) {
            $record['_instance'] = $record['recurrence_date']->format($recurrence_id_format);
        }

        // clean up exception data
        if (!empty($record['recurrence']) && !empty($record['recurrence']['EXCEPTIONS'])) {
            array_walk($record['recurrence']['EXCEPTIONS'], function (&$exception) {
                unset($exception['_attachments']);
            });
        }

        return $record;
    }

    /**
     * Convert the given event record into a data structure that can be passed to the storage backend for saving
     * (opposite of self::_to_driver_event())
     */
    private function _from_driver_event($event, $old = [])
    {
        // set current user as ORGANIZER
        if ($identity = $this->cal->rc->user->list_emails(true)) {
            $event['attendees'] = !empty($event['attendees']) ? $event['attendees'] : [];
            $found = false;

            // there can be only resources on attendees list (T1484)
            // let's check the existence of an organizer
            foreach ($event['attendees'] as $attendee) {
                if (!empty($attendee['role']) && $attendee['role'] == 'ORGANIZER') {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $event['attendees'][] = ['role' => 'ORGANIZER', 'name' => $identity['name'], 'email' => $identity['email']];
            }

            $event['_owner'] = $identity['email'];
        }

        // remove EXDATE values if RDATE is given
        if (!empty($event['recurrence']['RDATE'])) {
            $event['recurrence']['EXDATE'] = [];
        }

        // remove recurrence information (e.g. EXDATES and EXCEPTIONS) entirely
        if (!empty($event['recurrence']) && empty($event['recurrence']['FREQ']) && empty($event['recurrence']['RDATE'])) {
            $event['recurrence'] = [];
        }

        // keep 'comment' from initial itip invitation
        if (!empty($old['comment'])) {
            $event['comment'] = $old['comment'];
        }

        // remove some internal properties which should not be cached
        $cleanup_fn = function (&$event) {
            unset($event['_savemode'], $event['_fromcalendar'], $event['_identity'], $event['_folder_id'],
                $event['calendar'], $event['className'], $event['recurrence_id'],
                $event['attachments'], $event['deleted_attachments']);
        };

        $cleanup_fn($event);

        // clean up exception data
        if (!empty($event['exceptions'])) {
            array_walk($event['exceptions'], $cleanup_fn);
        }

        // copy meta data (starting with _) from old object
        foreach ((array) $old as $key => $val) {
            if (!isset($event[$key]) && $key[0] == '_') {
                $event[$key] = $val;
            }
        }

        return $event;
    }

    /**
     * Match the given word in the event contents
     */
    public function fulltext_match($event, $word, $recursive = true)
    {
        $hits = 0;
        foreach ($this->search_fields as $col) {
            if (empty($event[$col])) {
                continue;
            }

            $sval = is_array($event[$col]) ? self::_complex2string($event[$col]) : $event[$col];
            if (empty($sval)) {
                continue;
            }

            // do a simple substring matching (to be improved)
            $val = mb_strtolower($sval);
            if (strpos($val, $word) !== false) {
                $hits++;
                break;
            }
        }

        return $hits;
    }

    /**
     * Convert a complex event attribute to a string value
     */
    private static function _complex2string($prop)
    {
        static $ignorekeys = ['role', 'status', 'rsvp'];

        $out = '';
        if (is_array($prop)) {
            foreach ($prop as $key => $val) {
                if (is_numeric($key)) {
                    $out .= self::_complex2string($val);
                } elseif (!in_array($key, $ignorekeys)) {
                    $out .= $val . ' ';
                }
            }
        } elseif (is_string($prop) || is_numeric($prop)) {
            $out .= $prop . ' ';
        }

        return rtrim($out);
    }
}
