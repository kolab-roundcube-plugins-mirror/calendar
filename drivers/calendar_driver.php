<?php

/**
 * Driver interface for the Calendar plugin
 *
 * @version @package_version@
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2012, Kolab Systems AG <contact@kolabsys.com>
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
 *    'categories' => 'Event category',
 *     'free_busy' => 'free|busy|outofoffice|tentative',  // Show time as
 *      'priority' => 0-9,     // Event priority (0=undefined, 1=highest, 9=lowest)
 *   'sensitivity' => 'public|private|confidential',   // Event sensitivity
 *        'alarms' => '-15M:DISPLAY',  // Reminder settings inspired by valarm definition (e.g. display alert 15 minutes before event)
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
  // features supported by backend
  public $alarms = false;
  public $attendees = false;
  public $freebusy = false;
  public $attachments = false;
  public $undelete = false; // event undelete action
  public $categoriesimmutable = false;
  public $alarm_types = array('DISPLAY');
  public $alarm_absolute = true;
  public $last_error;

  protected $default_categories = array(
    'Personal' => 'c0c0c0',
    'Work'     => 'ff0000',
    'Family'   => '00ff00',
    'Holiday'  => 'ff6600',
  );

  /**
   * Get a list of available calendars from this source
   *
   * @param bool $active   Return only active calendars
   * @param bool $personal Return only personal calendars
   *
   * @return array List of calendars
   */
  abstract function list_calendars($active = false, $personal = false);

  /**
   * Create a new calendar assigned to the current user
   *
   * @param array Hash array with calendar properties
   *        name: Calendar name
   *       color: The color of the calendar
   *  showalarms: True if alarms are enabled
   * @return mixed ID of the calendar on success, False on error
   */
  abstract function create_calendar($prop);

  /**
   * Update properties of an existing calendar
   *
   * @param array Hash array with calendar properties
   *          id: Calendar Identifier
   *        name: Calendar name
   *       color: The color of the calendar
   *  showalarms: True if alarms are enabled (if supported)
   * @return boolean True on success, Fales on failure
   */
  abstract function edit_calendar($prop);
  
  /**
   * Set active/subscribed state of a calendar
   *
   * @param array Hash array with calendar properties
   *          id: Calendar Identifier
   *      active: True if calendar is active, false if not
   * @return boolean True on success, Fales on failure
   */
  abstract function subscribe_calendar($prop);

  /**
   * Delete the given calendar with all its contents
   *
   * @param array Hash array with calendar properties
   *      id: Calendar Identifier
   * @return boolean True on success, Fales on failure
   */
  abstract function remove_calendar($prop);

  /**
   * Add a single event to the database
   *
   * @param array Hash array with event properties (see header of this file)
   * @return mixed New event ID on success, False on error
   */
  abstract function new_event($event);

  /**
   * Update an event entry with the given data
   *
   * @param array Hash array with event properties (see header of this file)
   * @return boolean True on success, False on error
   */
  abstract function edit_event($event);

  /**
   * Move a single event
   *
   * @param array Hash array with event properties:
   *      id: Event identifier
   *   start: Event start date/time as DateTime object
   *     end: Event end date/time as DateTime object
   *  allday: Boolean flag if this is an all-day event
   * @return boolean True on success, False on error
   */
  abstract function move_event($event);

  /**
   * Resize a single event
   *
   * @param array Hash array with event properties:
   *      id: Event identifier
   *   start: Event start date/time as DateTime object with timezone
   *     end: Event end date/time as DateTime object with timezone
   * @return boolean True on success, False on error
   */
  abstract function resize_event($event);

  /**
   * Remove a single event from the database
   *
   * @param array   Hash array with event properties:
   *      id: Event identifier
   * @param boolean Remove event irreversible (mark as deleted otherwise,
   *                if supported by the backend)
   *
   * @return boolean True on success, False on error
   */
  abstract function remove_event($event, $force = true);

  /**
   * Restores a single deleted event (if supported)
   *
   * @param array Hash array with event properties:
   *      id: Event identifier
   *
   * @return boolean True on success, False on error
   */
  public function restore_event($event)
  {
    return false;
  }

  /**
   * Return data of a single event
   *
   * @param mixed  UID string or hash array with event properties:
   *        id: Event identifier
   *  calendar: Calendar identifier (optional)
   * @param boolean If true, only writeable calendars shall be searched
   * @param boolean If true, only active calendars shall be searched
   * @param boolean If true, only personal calendars shall be searched
   *
   * @return array Event object as hash array
   */
  abstract function get_event($event, $writeable = false, $active = false, $personal = false);

  /**
   * Get events from source.
   *
   * @param  integer Event's new start (unix timestamp)
   * @param  integer Event's new end (unix timestamp)
   * @param  string  Search query (optional)
   * @param  mixed   List of calendar IDs to load events from (either as array or comma-separated string)
   * @return array A list of event objects (see header of this file for struct of an event)
   */
  abstract function load_events($start, $end, $query = null, $calendars = null);

  /**
   * Get a list of pending alarms to be displayed to the user
   *
   * @param  integer Current time (unix timestamp)
   * @param  mixed   List of calendar IDs to show alarms for (either as array or comma-separated string)
   * @return array A list of alarms, each encoded as hash array:
   *         id: Event identifier
   *        uid: Unique identifier of this event
   *      start: Event start date/time as DateTime object
   *        end: Event end date/time as DateTime object
   *     allday: Boolean flag if this is an all-day event
   *      title: Event title/summary
   *   location: Location string
   */
  abstract function pending_alarms($time, $calendars = null);

  /**
   * (User) feedback after showing an alarm notification
   * This should mark the alarm as 'shown' or snooze it for the given amount of time
   *
   * @param  string  Event identifier
   * @param  integer Suspend the alarm for this number of seconds
   */
  abstract function dismiss_alarm($event_id, $snooze = 0);

  /**
   * Check the given event object for validity
   *
   * @param array Event object as hash array
   * @return boolean True if valid, false if not
   */
  public function validate($event)
  {
    $valid = true;

    if (!is_object($event['start']) || !is_a($event['start'], 'DateTime'))
      $valid = false;
    if (!is_object($event['end']) || !is_a($event['end'], 'DateTime'))
      $valid = false;

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
  public function list_attachments($event) { }

  /**
   * Get attachment properties
   *
   * @param string $id    Attachment identifier
   * @param array  $event Hash array with event properties:
   *         id: Event identifier
   *   calendar: Calendar identifier
   *
   * @return array Hash array with attachment properties:
   *         id: Attachment identifier
   *       name: Attachment name
   *   mimetype: MIME content type of the attachment
   *       size: Attachment size
   */
  public function get_attachment($id, $event) { }

  /**
   * Get attachment body
   *
   * @param string $id    Attachment identifier
   * @param array  $event Hash array with event properties:
   *         id: Event identifier
   *   calendar: Calendar identifier
   *
   * @return string Attachment body
   */
  public function get_attachment_body($id, $event) { }

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
  public function add_category($name, $color) { }

  /**
   * Remove the given category
   */
  public function remove_category($name) { }

  /**
   * Update/replace a category
   */
  public function replace_category($oldname, $name, $color) { }

  /**
   * Fetch free/busy information from a person within the given range
   *
   * @param string  E-mail address of attendee
   * @param integer Requested period start date/time as unix timestamp
   * @param integer Requested period end date/time as unix timestamp
   *
   * @return array  List of busy timeslots within the requested range
   */
  public function get_freebusy_list($email, $start, $end)
  {
    return false;
  }

  /**
   * Callback function to produce driver-specific calendar create/edit form
   *
   * @param string Request action 'form-edit|form-new'
   * @param array  Calendar properties (e.g. id, color)
   * @param array  Edit form fields
   *
   * @return string HTML content of the form
   */
  public function calendar_form($action, $calendar, $formfields)
  {
    $html = '';
    foreach ($formfields as $field) {
      $html .= html::div('form-section',
        html::label($field['id'], $field['label']) .
        $field['value']);
    }

    return $html;
  }

  /**
   * Return a (limited) list of color values to be used for calendar and category coloring
   *
   * @return mixed List for colors as hex values or false if no presets should be shown
   */
  public function get_color_values()
  {
      return false;
  }

}
