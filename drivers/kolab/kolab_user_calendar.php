<?php

/**
 * Kolab calendar storage class simulating a virtual user calendar
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2014-2016, Kolab Systems AG <contact@kolabsys.com>
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

class kolab_user_calendar extends kolab_calendar
{
    public $id            = 'unknown';
    public $ready         = false;
    public $editable      = false;
    public $attachments   = false;
    public $subscriptions = false;

    protected $userdata  = [];
    protected $timeindex = [];


    /**
     * Default constructor
     */
    public function __construct($user_or_folder, $calendar)
    {
        $this->cal  = $calendar;
        $this->imap = $calendar->rc->get_storage();

        // full user record is provided
        if (is_array($user_or_folder)) {
            $this->userdata = $user_or_folder;
            $this->storage = new kolab_storage_folder_user($this->userdata['kolabtargetfolder'], '', $this->userdata);
        }
        else if ($user_or_folder instanceof kolab_storage_folder_user) {
            $this->storage  = $user_or_folder;
            $this->userdata = $this->storage->ldaprec;
        }
        else {
            // get user record from LDAP
            $this->storage  = new kolab_storage_folder_user($user_or_folder);
            $this->userdata = $this->storage->ldaprec;
        }

        $this->ready = !empty($this->userdata['kolabtargetfolder']);
        $this->storage->type = 'event';

        if ($this->ready) {
            // ID is derrived from the user's kolabtargetfolder attribute
            $this->id          = kolab_storage::folder_id($this->userdata['kolabtargetfolder'], true);
            $this->imap_folder = $this->userdata['kolabtargetfolder'];
            $this->name        = $this->storage->name;
            $this->parent      = '';  // user calendars are top level

            // user-specific alarms settings win
            $prefs = $this->cal->rc->config->get('kolab_calendars', []);
            if (isset($prefs[$this->id]['showalarms'])) {
                $this->alarms = $prefs[$this->id]['showalarms'];
            }
        }
    }

    /**
     * Getter for a nice and human readable name for this calendar
     *
     * @return string Name of this calendar
     */
    public function get_name()
    {
        if (!empty($this->userdata['displayname'])) {
            return $this->userdata['displayname'];
        }

        return !empty($this->userdata['name']) ? $this->userdata['name'] : $this->userdata['mail'];
    }

    /**
     * Getter for the IMAP folder owner
     *
     * @param bool Return a fully qualified owner name (unused)
     *
     * @return string Name of the folder owner
     */
    public function get_owner($fully_qualified = false)
    {
        return $this->userdata['mail'];
    }

    /**
     *
     */
    public function get_title()
    {
        $title = [];

        if (!empty($this->userdata['displayname'])) {
            $title[] = $this->userdata['displayname'];
        }

        $title[] = $this->userdata['mail'];

        return implode('; ', $title);
    }

    /**
     * Getter for the name of the namespace to which the IMAP folder belongs
     *
     * @return string Name of the namespace (personal, other, shared)
     */
    public function get_namespace()
    {
        return 'other user';
    }

    /**
     * Getter for the top-end calendar folder name (not the entire path)
     *
     * @return string Name of this calendar
     */
    public function get_foldername()
    {
        return $this->get_name();
    }

    /**
     * Return color to display this calendar
     */
    public function get_color($default = null)
    {
        // calendar color is stored in local user prefs
        $prefs = $this->cal->rc->config->get('kolab_calendars', []);

        if (!empty($prefs[$this->id]) && !empty($prefs[$this->id]['color'])) {
            return $prefs[$this->id]['color'];
        }

        return $default ?: 'cc0000';
    }

    /**
     * Compose an URL for CalDAV access to this calendar (if configured)
     */
    public function get_caldav_url()
    {
        return false;
    }

    /**
     * Check subscription status of this folder
     *
     * @return boolean True if subscribed, false if not
     */
    public function is_subscribed()
    {
        return $this->storage->is_subscribed();
    }

    /**
     * Update properties of this calendar folder
     *
     * @see calendar_driver::edit_calendar()
     */
    public function update(&$prop)
    {
        // don't change anything.
        // let kolab_driver save props in local prefs
        return $prop['id'];
    }

    /**
     * Getter for a single event object
     */
    public function get_event($id)
    {
        // TODO: implement this
        return isset($this->events[$id]) ? $this->events[$id] : null;
    }

    /**
     * Get attachment body
     * @see calendar_driver::get_attachment_body()
     */
    public function get_attachment_body($id, $event)
    {
        if (empty($event['calendar']) && ($ev = $this->get_event($event['id']))) {
            $event['calendar'] = $ev['calendar'];
        }

        if (!empty($event['calendar']) && ($cal = $this->cal->get_calendar($event['calendar']))) {
            return $cal->get_attachment_body($id, $event);
        }

        return false;
    }

    /**
     * @param int    Event's new start (unix timestamp)
     * @param int    Event's new end (unix timestamp)
     * @param string Search query (optional)
     * @param bool   Include virtual events (optional)
     * @param array  Additional parameters to query storage
     * @param array  Additional query to filter events
     *
     * @return array A list of event records
     */
    public function list_events($start, $end, $search = null, $virtual = 1, $query = [], $filter_query = null)
    {
        // convert to DateTime for comparisons
        try {
            $start_dt = new DateTime('@'.$start);
        }
        catch (Exception $e) {
            $start_dt = new DateTime('@0');
        }
        try {
            $end_dt = new DateTime('@'.$end);
        }
        catch (Exception $e) {
            $end_dt = new DateTime('today +10 years');
        }

        $limit_changed = null;

        if (!empty($query)) {
            foreach ($query as $q) {
                if ($q[0] == 'changed' && $q[1] == '>=') {
                    try { $limit_changed = new DateTime('@'.$q[2]); }
                    catch (Exception $e) { /* ignore */ }
                }
            }
        }

        // aggregate all calendar folders the user shares (but are not activated)
        foreach (kolab_storage::list_user_folders($this->userdata, 'event', 2) as $foldername) {
            $cal = new kolab_calendar($foldername, $this->cal);
            foreach ($cal->list_events($start, $end, $search, 1) as $event) {
                $uid = !empty($event['id']) ? $event['id'] : $event['uid'];
                $this->events[$uid] = $event;
                $this->timeindex[$this->time_key($event)] = $uid;
            }
        }

        // get events from the user's free/busy feed (for quickview only)
        $fbview = $this->cal->rc->config->get('calendar_include_freebusy_data', 1);
        if ($fbview && ($fbview == 1 || !empty($_REQUEST['_quickview'])) && empty($search)) {
            $this->fetch_freebusy($limit_changed);
        }

        $events = [];
        foreach ($this->events as $event) {
            // list events in requested time window
            if (
                $event['start'] <= $end_dt
                && $event['end'] >= $start_dt
                && (!$limit_changed || empty($event['changed']) || $event['changed'] >= $limit_changed)
            ) {
                $events[] = $event;
            }
        }

        // avoid session race conditions that will loose temporary subscriptions
        $this->cal->rc->session->nowrite = true;

        return $events;
    }

    /**
     * Get number of events in the given calendar
     *
     * @param int   Date range start (unix timestamp)
     * @param int   Date range end (unix timestamp)
     * @param array Additional query to filter events
     *
     * @return integer Count
     */
    public function count_events($start, $end = null, $filter_query = null)
    {
        // not implemented
        return 0;
    }

    /**
     * Helper method to fetch free/busy data for the user and turn it into calendar data
     */
    private function fetch_freebusy($limit_changed = null)
    {
        // ask kolab server first
        try {
            $request_config = [
                'store_body'       => true,
                'follow_redirects' => true,
            ];
            $request  = libkolab::http_request(kolab_storage::get_freebusy_url($this->userdata['mail']), 'GET', $request_config);
            $response = $request->send();

            // authentication required
            if ($response->getStatus() == 401) {
                $request->setAuth($this->cal->rc->user->get_username(), $this->cal->rc->decrypt($_SESSION['password']));
                $response = $request->send();
            }

            if ($response->getStatus() == 200) {
                $fbdata = $response->getBody();
            }

            unset($request, $response);
        }
        catch (Exception $e) {
            rcube::raise_error([
                    'code' => 900, 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Error fetching free/busy information: " . $e->getMessage()
                ],
                true, false
            );

            return false;
        }

        $statusmap = [
            'FREE'            => 'free',
            'BUSY'            => 'busy',
            'BUSY-TENTATIVE'  => 'tentative',
            'X-OUT-OF-OFFICE' => 'outofoffice',
            'OOF'             => 'outofoffice',
        ];

        $titlemap = [
            'FREE'            => $this->cal->gettext('availfree'),
            'BUSY'            => $this->cal->gettext('availbusy'),
            'BUSY-TENTATIVE'  => $this->cal->gettext('availtentative'),
            'X-OUT-OF-OFFICE' => $this->cal->gettext('availoutofoffice'),
        ];

        // rcube::console('_fetch_freebusy', kolab_storage::get_freebusy_url($this->userdata['mail']), $fbdata);

        $count = 0;

        // parse free-busy information
        if (!empty($fbdata)) {
            $ical = $this->cal->get_ical();
            $ical->import($fbdata);
            if ($fb = $ical->freebusy) {
                // consider 'changed >= X' queries
                if ($limit_changed && !empty($fb['created']) && $fb['created'] < $limit_changed) {
                    return 0;
                }

                foreach ($fb['periods'] as $tuple) {
                    list($from, $to, $type) = $tuple;
                    $event = [
                        'uid'       => md5($this->id . $from->format('U') . '/' . $to->format('U')),
                        'calendar'  => $this->id,
                        'changed'   => !empty($fb['created']) ? $fb['created'] : new DateTime(),
                        'title'     => $this->get_name() . ' ' . (!empty($titlemap[$type]) ? $titlemap[$type] : $type),
                        'start'     => $from,
                        'end'       => $to,
                        'free_busy' => !empty($statusmap[$type]) ? $statusmap[$type] : 'busy',
                        'className' => 'fc-type-freebusy',
                        'organizer' => [
                            'email' => $this->userdata['mail'],
                            'name'  => isset($this->userdata['displayname']) ? $this->userdata['displayname'] : null,
                        ],
                    ];

                    // avoid duplicate entries
                    $key = $this->time_key($event);
                    if (empty($this->timeindex[$key])) {
                        $this->events[$event['uid']] = $event;
                        $this->timeindex[$key] = $event['uid'];
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Helper to build a key for the absolute time slot the given event convers
     */
    private function time_key($event)
    {
        return sprintf('%s/%s', $event['start']->format('U'), is_object($event['end']) ? $event['end']->format('U') : '0');
    }

    /**
     * Create a new event record
     *
     * @see calendar_driver::new_event()
     *
     * @return mixed The created record ID on success, False on error
     */
    public function insert_event($event)
    {
        return false;
    }

    /**
     * Update a specific event record
     *
     * @see calendar_driver::new_event()
     * @return bool True on success, False on error
     */
    public function update_event($event, $exception_id = null)
    {
        return false;
    }

    /**
     * Delete an event record
     *
     * @see calendar_driver::remove_event()
     * @return bool True on success, False on error
     */
    public function delete_event($event, $force = true)
    {
        return false;
    }

    /**
     * Restore deleted event record
     *
     * @see calendar_driver::undelete_event()
     * @return bool True on success, False on error
     */
    public function restore_event($event)
    {
        return false;
    }
}
