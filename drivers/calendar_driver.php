<?php

/**
 * Driver interface for the Calendar plugin
 *
 * @version @package_version@
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2012-2015, Kolab Systems AG <contact@kolabsys.com>
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


/**
 * Struct of an internal event object how it is passed from/to the driver classes:
 *
 *  $event = array(
 *            'id' => 'Event ID used for editing',
 *           'uid' => 'Unique identifier of this event',
 *      'calendar' => 'Calendar identifier to add event to or where the event is stored',
 *         'start' => DateTime,  // Event start date/time as DateTime object
 *           'end' => DateTime,  // Event end date/time as DateTime object
 *        'allday' => true|false,  // Boolean flag if this is an all-day event
 *       'changed' => DateTime,    // Last modification date of event
 *         'title' => 'Event title/summary',
 *      'location' => 'Location string',
 *   'description' => 'Event description',
 *           'url' => 'URL to more information',
 *    'recurrence' => array(   // Recurrence definition according to iCalendar (RFC 2445) specification as list of key-value pairs
 *            'FREQ' => 'DAILY|WEEKLY|MONTHLY|YEARLY',
 *        'INTERVAL' => 1...n,
 *           'UNTIL' => DateTime,
 *           'COUNT' => 1..n,   // number of times
 *                      // + more properties (see http://www.kanzaki.com/docs/ical/recur.html)
 *          'EXDATE' => array(),  // list of DateTime objects of exception Dates/Times
 *      'EXCEPTIONS' => array(<event>),  list of event objects which denote exceptions in the recurrence chain
 *    ),
 * 'recurrence_id' => 'ID of the recurrence group',   // usually the ID of the starting event
 *     '_instance' => 'ID of the recurring instance',   // identifies an instance within a recurrence chain
 *    'categories' => 'Event category',
 *     'free_busy' => 'free|busy|outofoffice|tentative',  // Show time as
 *        'status' => 'TENTATIVE|CONFIRMED|CANCELLED',    // event status according to RFC 2445
 *      'priority' => 0-9,     // Event priority (0=undefined, 1=highest, 9=lowest)
 *   'sensitivity' => 'public|private|confidential',   // Event sensitivity
 *        'alarms' => '-15M:DISPLAY',  // DEPRECATED Reminder settings inspired by valarm definition (e.g. display alert 15 minutes before event)
 *       'valarms' => array(           // List of reminders (new format), each represented as a hash array:
 *                  array(
 *                     'trigger' => '-PT90M',     // ISO 8601 period string prefixed with '+' or '-', or DateTime object
 *                      'action' => 'DISPLAY|EMAIL|AUDIO',
 *                    'duration' => 'PT15M',      // ISO 8601 period string
 *                      'repeat' => 0,            // number of repetitions
 *                 'description' => '',        // text to display for DISPLAY actions
 *                     'summary' => '',        // message text for EMAIL actions
 *                   'attendees' => array(),   // list of email addresses to receive alarm messages
 *                  ),
 *   ),
 *   'attachments' => array(   // List of attachments
 *            'name' => 'File name',
 *        'mimetype' => 'Content type',
 *            'size' => 1..n, // in bytes
 *              'id' => 'Attachment identifier'
 *   ),
 * 'deleted_attachments' => array(), // array of attachment identifiers to delete when event is updated
 *     'attendees' => array(   // List of event participants
 *            'name' => 'Participant name',
 *           'email' => 'Participant e-mail address',  // used as identifier
 *            'role' => 'ORGANIZER|REQ-PARTICIPANT|OPT-PARTICIPANT|CHAIR',
 *          'status' => 'NEEDS-ACTION|UNKNOWN|ACCEPTED|TENTATIVE|DECLINED'
 *            'rsvp' => true|false,
 *    ),
 *
 *     '_savemode' => 'all|future|current|new',   // How changes on recurring event should be handled
 *       '_notify' => true|false,  // whether to notify event attendees about changes
 * '_fromcalendar' => 'Calendar identifier where the event was stored before',
 *  );
 */

/**
 * Interface definition for calendar driver classes
 */
abstract class calendar_driver
{
    public const FILTER_ALL           = 0;
    public const FILTER_WRITEABLE     = 1;
    public const FILTER_INSERTABLE    = 2;
    public const FILTER_ACTIVE        = 4;
    public const FILTER_PERSONAL      = 8;
    public const FILTER_PRIVATE       = 16;
    public const FILTER_CONFIDENTIAL  = 32;
    public const FILTER_SHARED        = 64;
    public const BIRTHDAY_CALENDAR_ID = '__bdays__';

    // features supported by backend
    public $alarms      = false;
    public $attendees   = false;
    public $freebusy    = false;
    public $attachments = false;
    public $undelete    = false;
    public $history     = false;
    public $alarm_types = ['DISPLAY'];
    public $alarm_absolute      = true;
    public $categoriesimmutable = false;
    public $last_error;

    protected $default_categories = [
        'Personal' => 'c0c0c0',
        'Work'     => 'ff0000',
        'Family'   => '00ff00',
        'Holiday'  => 'ff6600',
    ];

    /**
     * Get a list of available calendars from this source
     *
     * @param int $filter Bitmask defining filter criterias.
     *                    See FILTER_* constants for possible values.
     * @param ?kolab_storage_folder_virtual $tree Reference to hierarchical folder tree object
     *
     * @return array List of calendars
     */
    abstract public function list_calendars($filter = 0, &$tree = null);

    /**
     * Create a new calendar assigned to the current user
     *
     * @param array $prop Hash array with calendar properties
     *        name: Calendar name
     *       color: The color of the calendar
     *  showalarms: True if alarms are enabled
     *
     * @return mixed ID of the calendar on success, False on error
     */
    abstract public function create_calendar($prop);

    /**
     * Update properties of an existing calendar
     *
     * @param array $prop Hash array with calendar properties
     *          id: Calendar Identifier
     *        name: Calendar name
     *       color: The color of the calendar
     *  showalarms: True if alarms are enabled (if supported)
     *
     * @return bool True on success, Fales on failure
     */
    abstract public function edit_calendar($prop);

    /**
     * Get a calendar name for the given calendar ID
     *
     * @param string $id Calendar identifier
     *
     * @return string|null Calendar name if found
     */
    abstract public function get_calendar_name($id);

    /**
     * Set active/subscribed state of a calendar
     *
     * @param array $prop Hash array with calendar properties
     *          id: Calendar Identifier
     *      active: True if calendar is active, false if not
     *
     * @return bool True on success, Fales on failure
     */
    abstract public function subscribe_calendar($prop);

    /**
     * Delete the given calendar with all its contents
     *
     * @param array $prop Hash array with calendar properties
     *      id: Calendar Identifier
     *
     * @return bool True on success, Fales on failure
     */
    abstract public function delete_calendar($prop);

    /**
     * Search for shared or otherwise not listed calendars the user has access
     *
     * @param string $query  Search string
     * @param string $source Section/source to search
     *
     * @return array List of calendars
     */
    abstract public function search_calendars($query, $source);

    /**
     * Add a single event to the database
     *
     * @param array $event Hash array with event properties (see header of this file)
     *
     * @return mixed New event ID on success, False on error
     */
    abstract public function new_event($event);

    /**
     * Update an event entry with the given data
     *
     * @param array $event Hash array with event properties (see header of this file)
     *
     * @return bool True on success, False on error
     */
    abstract public function edit_event($event);

    /**
     * Extended event editing with possible changes to the argument
     *
     * @param array  &$event    Hash array with event properties
     * @param string $status    New participant status
     * @param array  $attendees List of hash arrays with updated attendees
     *
     * @return bool True on success, False on error
     */
    public function edit_rsvp(&$event, $status, $attendees)
    {
        return $this->edit_event($event);
    }

    /**
     * Update the participant status for the given attendee
     *
     * @param array &$event    Hash array with event properties
     * @param array $attendees List of hash arrays each represeting an updated attendee
     *
     * @return bool True on success, False on error
     */
    public function update_attendees(&$event, $attendees)
    {
        return $this->edit_event($event);
    }

    /**
     * Move a single event
     *
     * @param array $event Hash array with event properties:
     *      id: Event identifier
     *   start: Event start date/time as DateTime object
     *     end: Event end date/time as DateTime object
     *  allday: Boolean flag if this is an all-day event
     *
     * @return bool True on success, False on error
     */
    abstract public function move_event($event);

    /**
     * Resize a single event
     *
     * @param array $event Hash array with event properties:
     *      id: Event identifier
     *   start: Event start date/time as DateTime object with timezone
     *     end: Event end date/time as DateTime object with timezone
     *
     * @return bool True on success, False on error
     */
    abstract public function resize_event($event);

    /**
     * Remove a single event from the database
     *
     * @param array $event Hash array with event properties:
     *                     id: Event identifier
     * @param bool  $force Remove event irreversible (mark as deleted otherwise,
     *                     if supported by the backend)
     *
     * @return bool True on success, False on error
     */
    abstract public function remove_event($event, $force = true);

    /**
     * Restores a single deleted event (if supported)
     *
     * @param array $event Hash array with event properties:
     *                     id: Event identifier
     *
     * @return bool True on success, False on error
     */
    public function restore_event($event)
    {
        return false;
    }

    /**
     * Return data of a single event
     *
     * @param mixed $event UID string or hash array with event properties:
     *         id: Event identifier
     *        uid: Event UID
     *  _instance: Instance identifier in combination with uid (optional)
     *   calendar: Calendar identifier (optional)
     * @param int   $scope Bitmask defining the scope to search events in.
     *                     See FILTER_* constants for possible values.
     * @param bool  $full  If true, recurrence exceptions shall be added
     *
     * @return ?array Event object as hash array
     */
    abstract public function get_event($event, $scope = 0, $full = false);

    /**
     * Get events from source.
     *
     * @param int    $start      Date range start (unix timestamp)
     * @param int    $end        Date range end (unix timestamp)
     * @param string $query      Search query (optional)
     * @param mixed  $calendars  List of calendar IDs to load events from (either as array or comma-separated string)
     * @param bool   $virtual    Include virtual/recurring events (optional)
     * @param int    $modifiedsince Only list events modified since this time (unix timestamp)
     *
     * @return array A list of event objects (see header of this file for struct of an event)
     */
    abstract public function load_events($start, $end, $query = null, $calendars = null, $virtual = true, $modifiedsince = null);

    /**
     * Get number of events in the given calendar
     *
     * @param mixed $calendars List of calendar IDs to count events (either as array or comma-separated string)
     * @param int   $start     Date range start (unix timestamp)
     * @param int   $end       Date range end (unix timestamp)
     *
     * @return array   Hash array with counts grouped by calendar ID
     */
    abstract public function count_events($calendars, $start, $end = null);

    /**
     * Get a list of pending alarms to be displayed to the user
     *
     * @param int   $time      Current time (unix timestamp)
     * @param mixed $calendars List of calendar IDs to show alarms for (either as array or comma-separated string)
     *
     * @return array A list of alarms, each encoded as hash array:
     *         id: Event identifier
     *        uid: Unique identifier of this event
     *      start: Event start date/time as DateTime object
     *        end: Event end date/time as DateTime object
     *     allday: Boolean flag if this is an all-day event
     *      title: Event title/summary
     *   location: Location string
     */
    abstract public function pending_alarms($time, $calendars = null);

    /**
     * (User) feedback after showing an alarm notification
     * This should mark the alarm as 'shown' or snooze it for the given amount of time
     *
     * @param string $event_id Event identifier
     * @param int    $snooze   Suspend the alarm for this number of seconds
     */
    abstract public function dismiss_alarm($event_id, $snooze = 0);

    /**
     * Check the given event object for validity
     *
     * @param array $event Event object as hash array
     *
     * @return boolean True if valid, false if not
     */
    public function validate($event)
    {
        $valid = true;

        if (empty($event['start']) || !is_object($event['start']) || !($event['start'] instanceof DateTimeInterface)) {
            $valid = false;
        }

        if (empty($event['end']) || !is_object($event['end']) || !($event['end'] instanceof DateTimeInterface)) {
            $valid = false;
        }

        return $valid;
    }

    /**
     * Get list of event's attachments.
     * Drivers can return list of attachments as event property.
     * If they will do not do this list_attachments() method will be used.
     *
     * @param array $event Hash array with event properties:
     *         id: Event identifier
     *   calendar: Calendar identifier
     *
     * @return array List of attachments, each as hash array:
     *         id: Attachment identifier
     *       name: Attachment name
     *   mimetype: MIME content type of the attachment
     *       size: Attachment size
     */
    public function list_attachments($event)
    {
        return [];
    }

    /**
     * Get attachment properties
     *
     * @param string $id    Attachment identifier
     * @param array  $event Hash array with event properties:
     *         id: Event identifier
     *   calendar: Calendar identifier
     *
     * @return array|false Hash array with attachment properties:
     *         id: Attachment identifier
     *       name: Attachment name
     *   mimetype: MIME content type of the attachment
     *       size: Attachment size
     */
    public function get_attachment($id, $event)
    {
        return false;
    }

    /**
     * Get attachment body
     *
     * @param string $id    Attachment identifier
     * @param array  $event Hash array with event properties:
     *         id: Event identifier
     *   calendar: Calendar identifier
     *
     * @return string|false Attachment body
     */
    public function get_attachment_body($id, $event)
    {
        return false;
    }

    /**
     * Build a struct representing the given message reference
     *
     * @param rcube_message_header|string $uri_or_headers An object holding the message headers
     *                                                    or an URI from a stored link referencing a mail message.
     * @param string                      $folder         IMAP folder the message resides in
     *
     * @return array|false An struct referencing the given IMAP message
     */
    public function get_message_reference($uri_or_headers, $folder = null)
    {
        // to be implemented by the derived classes
        return false;
    }

    /**
     * List availabale categories
     * The default implementation reads them from config/user prefs
     */
    public function list_categories()
    {
        $rcmail = rcube::get_instance();
        return $rcmail->config->get('calendar_categories', $this->default_categories);
    }

    /**
     * Create a new category
     */
    public function add_category($name, $color)
    {
    }

    /**
     * Remove the given category
     */
    public function remove_category($name)
    {
    }

    /**
     * Update/replace a category
     */
    public function replace_category($oldname, $name, $color)
    {
    }

    /**
     * Fetch free/busy information from a person within the given range
     *
     * @param string $email E-mail address of attendee
     * @param int    $start Requested period start date/time as unix timestamp
     * @param int    $end   Requested period end date/time as unix timestamp
     *
     * @return array|false List of busy timeslots within the requested range
     */
    public function get_freebusy_list($email, $start, $end)
    {
        return false;
    }

    /**
     * Create instances of a recurring event
     *
     * @param array    $event Hash array with event properties
     * @param DateTime $start Start date of the recurrence window
     * @param DateTime $end   End date of the recurrence window
     *
     * @return array List of recurring event instances
     */
    public function get_recurring_events($event, $start, $end = null)
    {
        $events = [];

        if (!empty($event['recurrence'])) {
            $rcmail = rcmail::get_instance();
            /** @var calendar $plugin */
            $plugin = $rcmail->plugins->get_plugin('calendar');
            $recurrence = new libcalendaring_recurrence($plugin->lib, $event);
            $recurrence_id_format = libcalendaring::recurrence_id_format($event);

            // determine a reasonable end date if none given
            if (!$end) {
                switch ($event['recurrence']['FREQ']) {
                    case 'YEARLY':  $intvl = 'P100Y';
                        break;
                    case 'MONTHLY': $intvl = 'P20Y';
                        break;
                    default:        $intvl = 'P10Y';
                        break;
                }

                $end = clone $event['start'];
                $end->add(new DateInterval($intvl));
            }

            $i = 0;
            while ($next_event = $recurrence->next_instance()) {
                // add to output if in range
                if (($next_event['start'] <= $end && $next_event['end'] >= $start)) {
                    $next_event['_instance'] = $next_event['start']->format($recurrence_id_format);
                    $next_event['id'] = $next_event['uid'] . '-' . $next_event['_instance'];
                    $next_event['recurrence_id'] = $event['uid'];
                    $events[] = $next_event;
                } elseif ($next_event['start'] > $end) {  // stop loop if out of range
                    break;
                }

                // avoid endless recursion loops
                if (++$i > 1000) {
                    break;
                }
            }
        }

        return $events;
    }

    /**
     * Provide a list of revisions for the given event
     *
     * @param array $event Hash array with event properties:
     *         id: Event identifier
     *   calendar: Calendar identifier
     *
     * @return array|false List of changes, each as a hash array:
     *         rev: Revision number
     *        type: Type of the change (create, update, move, delete)
     *        date: Change date
     *        user: The user who executed the change
     *          ip: Client IP
     * destination: Destination calendar for 'move' type
     */
    public function get_event_changelog($event)
    {
        return false;
    }

    /**
     * Get a list of property changes beteen two revisions of an event
     *
     * @param array $event Hash array with event properties:
     *         id: Event identifier
     *   calendar: Calendar identifier
     * @param mixed $rev1 Old Revision
     * @param mixed $rev2 New Revision
     *
     * @return array|false List of property changes, each as a hash array:
     *    property: Revision number
     *         old: Old property value
     *         new: Updated property value
     */
    public function get_event_diff($event, $rev1, $rev2)
    {
        return false;
    }

    /**
     * Return full data of a specific revision of an event
     *
     * @param mixed $event UID string or hash array with event properties:
     *        id: Event identifier
     *  calendar: Calendar identifier
     * @param mixed  $rev Revision number
     *
     * @return array|false Event object as hash array
     * @see self::get_event()
     */
    public function get_event_revison($event, $rev)
    {
        return false;
    }

    /**
     * Command the backend to restore a certain revision of an event.
     * This shall replace the current event with an older version.
     *
     * @param mixed $event UID string or hash array with event properties:
     *        id: Event identifier
     *  calendar: Calendar identifier
     * @param mixed  $rev Revision number
     *
     * @return bool True on success, False on failure
     */
    public function restore_event_revision($event, $rev)
    {
        return false;
    }

    /**
     * Callback function to produce driver-specific calendar create/edit form
     *
     * @param string $action     Request action 'form-edit|form-new'
     * @param array  $calendar   Calendar properties (e.g. id, color)
     * @param array  $formfields Edit form fields
     *
     * @return string HTML content of the form
     */
    public function calendar_form($action, $calendar, $formfields)
    {
        $rcmail = rcmail::get_instance();
        $table = new html_table(['cols' => 2, 'class' => 'propform']);

        foreach ($formfields as $col => $colprop) {
            $label = !empty($colprop['label']) ? $colprop['label'] : $rcmail->gettext("calendar.$col");

            $table->add('title', html::label($colprop['id'], rcube::Q($label)));
            $table->add(null, $colprop['value']);
        }

        return $table->show();
    }

    /**
     * Compose a list of birthday events from the contact records in the user's address books.
     *
     * This is a default implementation using Roundcube's address book API.
     * It can be overriden with a more optimized version by the individual drivers.
     *
     * @param int    $start         Event's new start (unix timestamp)
     * @param int    $end           Event's new end (unix timestamp)
     * @param string $search        Search query (optional)
     * @param int    $modifiedsince Only list events modified since this time (unix timestamp)
     *
     * @return array A list of event records
     */
    public function load_birthday_events($start, $end, $search = null, $modifiedsince = null)
    {
        // ignore update requests for simplicity reasons
        if (!empty($modifiedsince)) {
            return [];
        }

        // convert to DateTime for comparisons
        $start  = new DateTime('@' . $start);
        $end    = new DateTime('@' . $end);
        // extract the current year
        $year   = $start->format('Y');
        $year2  = $end->format('Y');

        $events = [];
        $search = mb_strtolower((string) $search);
        $rcmail = rcmail::get_instance();
        $cache  = $rcmail->get_cache('calendar.birthdays', 'db', 3600);
        $cache->expunge();

        $alarm_type   = $rcmail->config->get('calendar_birthdays_alarm_type', '');
        $alarm_offset = $rcmail->config->get('calendar_birthdays_alarm_offset', '-1D');
        $alarms       = $alarm_type ? $alarm_offset . ':' . $alarm_type : null;

        // let the user select the address books to consider in prefs
        $selected_sources = $rcmail->config->get('calendar_birthday_adressbooks');
        $sources = $selected_sources ?: array_keys($rcmail->get_address_sources(false, true));

        foreach ($sources as $source) {
            $abook = $rcmail->get_address_book($source);

            // skip LDAP address books unless selected by the user
            if (!$abook || ($abook instanceof rcube_ldap && empty($selected_sources))) {
                continue;
            }

            // skip collected recipients/senders addressbooks
            if (is_a($abook, 'rcube_addresses')) {
                continue;
            }

            $abook->set_pagesize(10000);

            // check for cached results
            $cache_records = [];
            $cached = $cache->get($source);

            // iterate over (cached) contacts
            foreach (($cached ?: $abook->search('*', '', 2, true, true, ['birthday'])) as $contact) {
                $event = self::parse_contact($contact, $source);

                if (empty($event)) {
                    continue;
                }

                // add stripped record to cache
                if (empty($cached)) {
                    $cache_records[] = [
                        'ID'       => $contact['ID'],
                        'name'     => $event['_displayname'],
                        'birthday' => $event['start']->format('Y-m-d'),
                    ];
                }

                // filter by search term (only name is involved here)
                if (!empty($search) && strpos(mb_strtolower($event['title']), $search) === false) {
                    continue;
                }

                $bday  = clone $event['start'];
                $byear = $bday->format('Y');

                // quick-and-dirty recurrence computation: just replace the year
                $bday->setDate($year, $bday->format('n'), $bday->format('j'));
                $bday->setTime(12, 0, 0);
                $this_year = $year;

                // date range reaches over multiple years: use end year if not in range
                if (($bday > $end || $bday < $start) && $year2 != $year) {
                    $bday->setDate($year2, $bday->format('n'), $bday->format('j'));
                    $this_year = $year2;
                }

                // birthday is within requested range
                if ($bday <= $end && $bday >= $start) {
                    unset($event['_displayname']);
                    $event['alarms'] = $alarms;

                    // if this is not the first occurence modify event details
                    // but not when this is "all birthdays feed" request
                    if ($year2 - $year < 10 && ($age = ($this_year - $byear))) {
                        $label = ['name' => 'birthdayage', 'vars' => ['age' => $age]];

                        $event['description'] = $rcmail->gettext($label, 'calendar');
                        $event['start']       = $bday;
                        $event['end']         = clone $bday;

                        unset($event['recurrence']);
                    }

                    // add the main instance
                    $events[] = $event;
                }
            }

            // store collected contacts in cache
            if (empty($cached)) {
                $cache->write($source, $cache_records);
            }
        }

        return $events;
    }

    /**
     * Get a single birthday calendar event
     */
    public function get_birthday_event($id)
    {
        // decode $id
        [, $source, $contact_id, $year] = explode(':', rcube_ldap::dn_decode($id));

        $rcmail = rcmail::get_instance();

        if (strlen($source) && $contact_id && ($abook = $rcmail->get_address_book($source))) {
            if ($contact = $abook->get_record($contact_id, true)) {
                return self::parse_contact($contact, $source);
            }
        }
    }

    /**
     * Parse contact and create an event for its birthday
     *
     * @param array  $contact Contact data
     * @param string $source  Addressbook source ID
     *
     * @return array|null Birthday event data
     */
    public static function parse_contact($contact, $source)
    {
        if (!is_array($contact)) {
            return null;
        }

        if (!empty($contact['birthday']) && is_array($contact['birthday'])) {
            $contact['birthday'] = reset($contact['birthday']);
        }

        if (empty($contact['birthday'])) {
            return null;
        }

        try {
            $bday = libcalendaring_datetime::createFromAny($contact['birthday'], true);
        } catch (Exception $e) {
            rcube::raise_error(
                [
                    'code' => 600,
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'message' => 'Failed to parse contact birthday: ' . $e->getMessage(),
                ],
                true,
                false
            );
            return null;
        }

        $rcmail       = rcmail::get_instance();
        $birthyear    = $bday->format('Y');
        $display_name = rcube_addressbook::compose_display_name($contact);
        $label        = ['name' => 'birthdayeventtitle', 'vars' => ['name' => $display_name]];
        $event_title  = $rcmail->gettext($label, 'calendar');
        $uid          = rcube_ldap::dn_encode('bday:' . $source . ':' . $contact['ID'] . ':' . $birthyear);

        return [
            'id'           => $uid,
            'uid'          => $uid,
            'calendar'     => self::BIRTHDAY_CALENDAR_ID,
            'title'        => $event_title,
            'description'  => '',
            'allday'       => true,
            'start'        => $bday,
            'end'          => clone $bday,
            'recurrence'   => ['FREQ' => 'YEARLY', 'INTERVAL' => 1],
            'free_busy'    => 'free',
            '_displayname' => $display_name,
        ];
    }

    /**
     * Store alarm dismissal for birtual birthay events
     *
     * @param string $event_id Event identifier
     * @param int    $snooze   Suspend the alarm for this number of seconds
     */
    public function dismiss_birthday_alarm($event_id, $snooze = 0)
    {
        $rcmail = rcmail::get_instance();
        $cache  = $rcmail->get_cache('calendar.birthdayalarms', 'db', 86400 * 30);
        $cache->remove($event_id);

        // compute new notification time or disable if not snoozed
        $notifyat = $snooze > 0 ? time() + $snooze : null;
        $cache->set($event_id, ['snooze' => $snooze, 'notifyat' => $notifyat]);

        return true;
    }

    /**
     * Accept an invitation to a shared folder
     *
     * @param string $href Invitation location href
     *
     * @return array|false
     */
    public function accept_share_invitation($href)
    {
        return false;
    }

    /**
     * Handler for user_delete plugin hook
     *
     * @param array $args Hash array with hook arguments
     *
     * @return array Return arguments for plugin hooks
     */
    public function user_delete($args)
    {
        // TO BE OVERRIDDEN
        return $args;
    }
}
