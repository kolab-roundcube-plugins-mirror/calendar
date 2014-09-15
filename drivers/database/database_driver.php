<?php

/**
 * Database driver for the Calendar plugin
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


class database_driver extends calendar_driver
{
  const DB_DATE_FORMAT = 'Y-m-d H:i:s';

  // features this backend supports
  public $alarms = true;
  public $attendees = true;
  public $freebusy = false;
  public $attachments = true;
  public $alarm_types = array('DISPLAY');

  private $rc;
  private $cal;
  private $cache = array();
  private $calendars = array();
  private $calendar_ids = '';
  private $free_busy_map = array('free' => 0, 'busy' => 1, 'out-of-office' => 2, 'outofoffice' => 2, 'tentative' => 3);
  private $sensitivity_map = array('public' => 0, 'private' => 1, 'confidential' => 2);
  private $server_timezone;
  
  private $db_events = 'events';
  private $db_calendars = 'calendars';
  private $db_attachments = 'attachments';


  /**
   * Default constructor
   */
  public function __construct($cal)
  {
    $this->cal = $cal;
    $this->rc = $cal->rc;
    $this->server_timezone = new DateTimeZone(date_default_timezone_get());
    
    // load library classes
    require_once($this->cal->home . '/lib/Horde_Date_Recurrence.php');
    
    // read database config
    $db = $this->rc->get_dbh();
    $this->db_events = $this->rc->config->get('db_table_events', $db->table_name($this->db_events));
    $this->db_calendars = $this->rc->config->get('db_table_calendars', $db->table_name($this->db_calendars));
    $this->db_attachments = $this->rc->config->get('db_table_attachments', $db->table_name($this->db_attachments));
    
    $this->_read_calendars();
  }

  /**
   * Read available calendars for the current user and store them internally
   */
  private function _read_calendars()
  {
    $hidden = array_filter(explode(',', $this->rc->config->get('hidden_calendars', '')));
    
    if (!empty($this->rc->user->ID)) {
      $calendar_ids = array();
      $result = $this->rc->db->query(
        "SELECT *, calendar_id AS id FROM " . $this->db_calendars . "
         WHERE user_id=?
         ORDER BY name",
         $this->rc->user->ID
      );
      while ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
        $arr['showalarms'] = intval($arr['showalarms']);
        $arr['active']     = !in_array($arr['id'], $hidden);
        $arr['name']       = html::quote($arr['name']);
        $arr['listname']   = html::quote($arr['name']);
        $this->calendars[$arr['calendar_id']] = $arr;
        $calendar_ids[] = $this->rc->db->quote($arr['calendar_id']);
      }
      $this->calendar_ids = join(',', $calendar_ids);
    }
  }

  /**
   * Get a list of available calendars from this source
   *
   * @param bool $active   Return only active calendars
   * @param bool $personal Return only personal calendars
   *
   * @return array List of calendars
   */
  public function list_calendars($active = false, $personal = false)
  {
    // attempt to create a default calendar for this user
    if (empty($this->calendars)) {
      if ($this->create_calendar(array('name' => 'Default', 'color' => 'cc0000')))
        $this->_read_calendars();
    }

    $calendars = $this->calendars;

    // filter active calendars
    if ($active) {
      foreach ($calendars as $idx => $cal) {
        if (!$cal['active']) {
          unset($calendars[$idx]);
        }
      }
    }

    // 'personal' is unsupported in this driver

    return $calendars;
  }

  /**
   * Create a new calendar assigned to the current user
   *
   * @param array Hash array with calendar properties
   *    name: Calendar name
   *   color: The color of the calendar
   * @return mixed ID of the calendar on success, False on error
   */
  public function create_calendar($prop)
  {
    $result = $this->rc->db->query(
      "INSERT INTO " . $this->db_calendars . "
       (user_id, name, color, showalarms)
       VALUES (?, ?, ?, ?)",
       $this->rc->user->ID,
       $prop['name'],
       $prop['color'],
       $prop['showalarms']?1:0
    );
    
    if ($result)
      return $this->rc->db->insert_id($this->db_calendars);
    
    return false;
  }

  /**
   * Update properties of an existing calendar
   *
   * @see calendar_driver::edit_calendar()
   */
  public function edit_calendar($prop)
  {
    $query = $this->rc->db->query(
      "UPDATE " . $this->db_calendars . "
       SET   name=?, color=?, showalarms=?
       WHERE calendar_id=?
       AND   user_id=?",
      $prop['name'],
      $prop['color'],
      $prop['showalarms']?1:0,
      $prop['id'],
      $this->rc->user->ID
    );
    
    return $this->rc->db->affected_rows($query);
  }

  /**
   * Set active/subscribed state of a calendar
   * Save a list of hidden calendars in user prefs
   *
   * @see calendar_driver::subscribe_calendar()
   */
  public function subscribe_calendar($prop)
  {
    $hidden = array_flip(explode(',', $this->rc->config->get('hidden_calendars', '')));
    
    if ($prop['active'])
      unset($hidden[$prop['id']]);
    else
      $hidden[$prop['id']] = 1;
    
    return $this->rc->user->save_prefs(array('hidden_calendars' => join(',', array_keys($hidden))));
  }

  /**
   * Delete the given calendar with all its contents
   *
   * @see calendar_driver::remove_calendar()
   */
  public function remove_calendar($prop)
  {
    if (!$this->calendars[$prop['id']])
      return false;

    // events and attachments will be deleted by foreign key cascade

    $query = $this->rc->db->query(
      "DELETE FROM " . $this->db_calendars . "
       WHERE calendar_id=?",
       $prop['id']
    );

    return $this->rc->db->affected_rows($query);
  }

  /**
   * Add a single event to the database
   *
   * @param array Hash array with event properties
   * @see calendar_driver::new_event()
   */
  public function new_event($event)
  {
    if (!$this->validate($event))
      return false;

    if (!empty($this->calendars)) {
      if ($event['calendar'] && !$this->calendars[$event['calendar']])
        return false;
      if (!$event['calendar'])
        $event['calendar'] = reset(array_keys($this->calendars));

      $event = $this->_save_preprocess($event);

      $this->rc->db->query(sprintf(
        "INSERT INTO " . $this->db_events . "
         (calendar_id, created, changed, uid, %s, %s, all_day, recurrence, title, description, location, categories, url, free_busy, priority, sensitivity, attendees, alarms, notifyat)
         VALUES (?, %s, %s, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
          $this->rc->db->quote_identifier('start'),
          $this->rc->db->quote_identifier('end'),
          $this->rc->db->now(),
          $this->rc->db->now()
        ),
        $event['calendar'],
        strval($event['uid']),
        $event['start']->format(self::DB_DATE_FORMAT),
        $event['end']->format(self::DB_DATE_FORMAT),
        intval($event['all_day']),
        $event['_recurrence'],
        strval($event['title']),
        strval($event['description']),
        strval($event['location']),
        strval($event['categories']),
        strval($event['url']),
        intval($event['free_busy']),
        intval($event['priority']),
        intval($event['sensitivity']),
        $event['attendees'],
        $event['alarms'],
        $event['notifyat']
      );

      $event_id = $this->rc->db->insert_id($this->db_events);

      if ($event_id) {
        $event['id'] = $event_id;

        // add attachments
        if (!empty($event['attachments'])) {
          foreach ($event['attachments'] as $attachment) {
            $this->add_attachment($attachment, $event_id);
            unset($attachment);
          }
        }

        $this->_update_recurring($event);
      }

      return $event_id;
    }
    
    return false;
  }

  /**
   * Update an event entry with the given data
   *
   * @param array Hash array with event properties
   * @see calendar_driver::edit_event()
   */
  public function edit_event($event)
  {
    if (!empty($this->calendars)) {
      $update_master = false;
      $update_recurring = true;
      $old = $this->get_event($event);
      
      // increment sequence number
      if ($old['sequence'] && empty($event['sequence']))
        $event['sequence'] = max($event['sequence'], $old['sequence']+1);
      
      // modify a recurring event, check submitted savemode to do the right things
      if ($old['recurrence'] || $old['recurrence_id']) {
        $master = $old['recurrence_id'] ? $this->get_event(array('id' => $old['recurrence_id'])) : $old;
        
        // keep saved exceptions (not submitted by the client)
        if ($old['recurrence']['EXDATE'])
          $event['recurrence']['EXDATE'] = $old['recurrence']['EXDATE'];
        
        switch ($event['_savemode']) {
          case 'new':
            $event['uid'] = $this->cal->generate_uid();
            return $this->new_event($event);
          
          case 'current':
            // add exception to master event
            $master['recurrence']['EXDATE'][] = $old['start'];
            $update_master = true;
            
            // just update this occurence (decouple from master)
            $update_recurring = false;
            $event['recurrence_id'] = 0;
            $event['recurrence'] = array();
            break;
          
          case 'future':
            if ($master['id'] != $event['id']) {
              // set until-date on master event, then save this instance as new recurring event
              $master['recurrence']['UNTIL'] = clone $event['start'];
              $master['recurrence']['UNTIL']->sub(new DateInterval('P1D'));
              unset($master['recurrence']['COUNT']);
              $update_master = true;
              
              // if recurrence COUNT, update value to the correct number of future occurences
              if ($event['recurrence']['COUNT']) {
                $fromdate = clone $event['start'];
                $fromdate->setTimezone($this->server_timezone);
                $sqlresult = $this->rc->db->query(sprintf(
                  "SELECT event_id FROM " . $this->db_events . "
                   WHERE calendar_id IN (%s)
                   AND %s >= ?
                   AND recurrence_id=?",
                  $this->calendar_ids,
                  $this->rc->db->quote_identifier('start')
                  ),
                  $fromdate->format(self::DB_DATE_FORMAT),
                  $master['id']);
                if ($count = $this->rc->db->num_rows($sqlresult))
                  $event['recurrence']['COUNT'] = $count;
              }
            
              $update_recurring = true;
              $event['recurrence_id'] = 0;
              break;
            }
            // else: 'future' == 'all' if modifying the master event
          
          default:  // 'all' is default
            $event['id'] = $master['id'];
            $event['recurrence_id'] = 0;
            
            // use start date from master but try to be smart on time or duration changes
            $old_start_date = $old['start']->format('Y-m-d');
            $old_start_time = $old['allday'] ? '' : $old['start']->format('H:i');
            $old_duration = $old['end']->format('U') - $old['start']->format('U');
            
            $new_start_date = $event['start']->format('Y-m-d');
            $new_start_time = $event['allday'] ? '' : $event['start']->format('H:i');
            $new_duration = $event['end']->format('U') - $event['start']->format('U');
            
            $diff = $old_start_date != $new_start_date || $old_start_time != $new_start_time || $old_duration != $new_duration;
            
            // shifted or resized
            if ($diff && ($old_start_date == $new_start_date || $old_duration == $new_duration)) {
              $event['start'] = $master['start']->add($old['start']->diff($event['start']));
              $event['end'] = clone $event['start'];
              $event['end']->add(new DateInterval('PT'.$new_duration.'S'));
            }
            break;
        }
      }
      
      $success = $this->_update_event($event, $update_recurring);
      if ($success && $update_master)
        $this->_update_event($master, true);
      
      return $success;
    }
    
    return false;
  }

  /**
   * Convert save data to be used in SQL statements
   */
  private function _save_preprocess($event)
  {
    // shift dates to server's timezone
    $event['start'] = clone $event['start'];
    $event['start']->setTimezone($this->server_timezone);
    $event['end'] = clone $event['end'];
    $event['end']->setTimezone($this->server_timezone);
    
    // compose vcalendar-style recurrencue rule from structured data
    $rrule = $event['recurrence'] ? libcalendaring::to_rrule($event['recurrence']) : '';
    $event['_recurrence'] = rtrim($rrule, ';');
    $event['free_busy'] = intval($this->free_busy_map[strtolower($event['free_busy'])]);
    $event['sensitivity'] = intval($this->sensitivity_map[strtolower($event['sensitivity'])]);
    
    if (isset($event['allday'])) {
      $event['all_day'] = $event['allday'] ? 1 : 0;
    }
    
    // compute absolute time to notify the user
    $event['notifyat'] = $this->_get_notification($event);
    
    // process event attendees
    $_attendees = '';
    foreach ((array)$event['attendees'] as $attendee) {
      if (!$attendee['name'] && !$attendee['email'])
        continue;
      $_attendees .= 'NAME="'.addcslashes($attendee['name'], '"') . '"' .
        ';STATUS=' . $attendee['status'].
        ';ROLE=' . $attendee['role'] .
        ';EMAIL=' . $attendee['email'] .
        "\n";
    }
    $event['attendees'] = rtrim($_attendees);

    return $event;
  }
  
  /**
   * Compute absolute time to notify the user
   */
  private function _get_notification($event)
  {
    if ($event['alarms'] && $event['start'] > new DateTime()) {
      $alarm = libcalendaring::get_next_alarm($event);

      if ($alarm['time'] && $alarm['action'] == 'DISPLAY')
        return date('Y-m-d H:i:s', $alarm['time']);
    }

    return null;
  }

  /**
   * Save the given event record to database
   *
   * @param array Event data, already passed through self::_save_preprocess()
   * @param boolean True if recurring events instances should be updated, too
   */
  private function _update_event($event, $update_recurring = true)
  {
    $event = $this->_save_preprocess($event);
    $sql_set = array();
    $set_cols = array('start', 'end', 'all_day', 'recurrence_id', 'sequence', 'title', 'description', 'location', 'categories', 'url', 'free_busy', 'priority', 'sensitivity', 'attendees', 'alarms', 'notifyat');
    foreach ($set_cols as $col) {
      if (is_object($event[$col]) && is_a($event[$col], 'DateTime'))
        $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . $this->rc->db->quote($event[$col]->format(self::DB_DATE_FORMAT));
      else if (isset($event[$col]))
        $sql_set[] = $this->rc->db->quote_identifier($col) . '=' . $this->rc->db->quote($event[$col]);
    }
    
    if ($event['_recurrence'])
      $sql_set[] = $this->rc->db->quote_identifier('recurrence') . '=' . $this->rc->db->quote($event['_recurrence']);
    
    if ($event['_fromcalendar'] && $event['_fromcalendar'] != $event['calendar'])
        $sql_set[] = 'calendar_id=' . $this->rc->db->quote($event['calendar']);
    
    $query = $this->rc->db->query(sprintf(
      "UPDATE " . $this->db_events . "
       SET   changed=%s %s
       WHERE event_id=?
       AND   calendar_id IN (" . $this->calendar_ids . ")",
        $this->rc->db->now(),
        ($sql_set ? ', ' . join(', ', $sql_set) : '')
      ),
      $event['id']
    );

    $success = $this->rc->db->affected_rows($query);

    // add attachments
    if ($success && !empty($event['attachments'])) {
      foreach ($event['attachments'] as $attachment) {
        $this->add_attachment($attachment, $event['id']);
        unset($attachment);
      }
    }

    // remove attachments
    if ($success && !empty($event['deleted_attachments'])) {
      foreach ($event['deleted_attachments'] as $attachment) {
        $this->remove_attachment($attachment, $event['id']);
      }
    }

    if ($success) {
      unset($this->cache[$event['id']]);
      if ($update_recurring)
        $this->_update_recurring($event);
    }

    return $success;
  }

  /**
   * Insert "fake" entries for recurring occurences of this event
   */
  private function _update_recurring($event)
  {
    if (empty($this->calendars))
      return;
    
    // clear existing recurrence copies
    $this->rc->db->query(
      "DELETE FROM " . $this->db_events . "
       WHERE recurrence_id=?
       AND calendar_id IN (" . $this->calendar_ids . ")",
       $event['id']
    );
    
    // create new fake entries
    if ($event['recurrence']) {
      // include library class
      require_once($this->cal->home . '/lib/calendar_recurrence.php');
      
      $recurrence = new calendar_recurrence($this->cal, $event);

      $count = 0;
      $duration = $event['start']->diff($event['end']);
      while ($next_start = $recurrence->next_start()) {
        $next_start->setTimezone($this->server_timezone);
        $next_end = clone $next_start;
        $next_end->add($duration);
        $notify_at = $this->_get_notification(array('alarms' => $event['alarms'], 'start' => $next_start, 'end' => $next_end));
        $query = $this->rc->db->query(sprintf(
          "INSERT INTO " . $this->db_events . "
           (calendar_id, recurrence_id, created, changed, uid, %s, %s, all_day, recurrence, title, description, location, categories, url, free_busy, priority, sensitivity, alarms, notifyat)
            SELECT calendar_id, ?, %s, %s, uid, ?, ?, all_day, recurrence, title, description, location, categories, url, free_busy, priority, sensitivity, alarms, ?
            FROM  " . $this->db_events . " WHERE event_id=? AND calendar_id IN (" . $this->calendar_ids . ")",
            $this->rc->db->quote_identifier('start'),
            $this->rc->db->quote_identifier('end'),
            $this->rc->db->now(),
            $this->rc->db->now()
          ),
          $event['id'],
          $next_start->format(self::DB_DATE_FORMAT),
          $next_end->format(self::DB_DATE_FORMAT),
          $notify_at,
          $event['id']
        );
        
        if (!$this->rc->db->affected_rows($query))
          break;
        
        // stop adding events for inifinite recurrence after 20 years
        if (++$count > 999 || (!$recurrence->recurEnd && !$recurrence->recurCount && $next_start->format('Y') > date('Y') + 20))
          break;
      }
    }
  }

  /**
   * Move a single event
   *
   * @param array Hash array with event properties
   * @see calendar_driver::move_event()
   */
  public function move_event($event)
  {
    // let edit_event() do all the magic
    return $this->edit_event($event + (array)$this->get_event($event));
  }

  /**
   * Resize a single event
   *
   * @param array Hash array with event properties
   * @see calendar_driver::resize_event()
   */
  public function resize_event($event)
  {
    // let edit_event() do all the magic
    return $this->edit_event($event + (array)$this->get_event($event));
  }

  /**
   * Remove a single event from the database
   *
   * @param array   Hash array with event properties
   * @param boolean Remove record irreversible (@TODO)
   *
   * @see calendar_driver::remove_event()
   */
  public function remove_event($event, $force = true)
  {
    if (!empty($this->calendars)) {
      $event += (array)$this->get_event($event);
      $master = $event;
      $update_master = false;
      $savemode = 'all';

      // read master if deleting a recurring event
      if ($event['recurrence'] || $event['recurrence_id']) {
        $master = $event['recurrence_id'] ? $this->get_event(array('id' => $event['recurrence_id'])) : $event;
        $savemode = $event['_savemode'];
      }

      switch ($savemode) {
        case 'current':
          // add exception to master event
          $master['recurrence']['EXDATE'][] = $event['start'];
          $update_master = true;
          
          // just delete this single occurence
          $query = $this->rc->db->query(
            "DELETE FROM " . $this->db_events . "
             WHERE calendar_id IN (" . $this->calendar_ids . ")
             AND event_id=?",
            $event['id']
          );
          break;

        case 'future':
          if ($master['id'] != $event['id']) {
            // set until-date on master event
            $master['recurrence']['UNTIL'] = clone $event['start'];
            $master['recurrence']['UNTIL']->sub(new DateInterval('P1D'));
            unset($master['recurrence']['COUNT']);
            $update_master = true;
            
            // delete this and all future instances
            $fromdate = clone $event['start'];
            $fromdate->setTimezone($this->server_timezone);
            $query = $this->rc->db->query(
              "DELETE FROM " . $this->db_events . "
               WHERE calendar_id IN (" . $this->calendar_ids . ")
               AND " . $this->rc->db->quote_identifier('start') . " >= ?
               AND recurrence_id=?",
              $fromdate->format(self::DB_DATE_FORMAT),
              $master['id']
            );
            break;
          }
          // else: future == all if modifying the master event

        default:  // 'all' is default
          $query = $this->rc->db->query(
            "DELETE FROM " . $this->db_events . "
             WHERE (event_id=? OR recurrence_id=?)
             AND calendar_id IN (" . $this->calendar_ids . ")",
             $master['id'],
             $master['id']
          );
          break;
      }

      $success = $this->rc->db->affected_rows($query);
      if ($success && $update_master)
        $this->_update_event($master, true);

      return $success;
    }
    
    return false;
  }

  /**
   * Return data of a specific event
   * @param mixed  Hash array with event properties or event UID
   * @param boolean Only search in writeable calendars (ignored)
   * @param boolean Only search in active calendars
   * @param boolean Only search in personal calendars (ignored)
   * @return array Hash array with event properties
   */
  public function get_event($event, $writeable = false, $active = false, $personal = false)
  {
    $id = is_array($event) ? ($event['id'] ? $event['id'] : $event['uid']) : $event;
    $col = is_array($event) && is_numeric($id) ? 'event_id' : 'uid';

    if ($this->cache[$id])
      return $this->cache[$id];

    if ($active) {
      $calendars = $this->calendars;
      foreach ($calendars as $idx => $cal) {
        if (!$cal['active']) {
          unset($calendars[$idx]);
        }
      }
      $cals = join(',', $calendars);
    }
    else {
      $cals = $this->calendar_ids;
    }

    $result = $this->rc->db->query(sprintf(
      "SELECT e.*, (SELECT COUNT(attachment_id) FROM " . $this->db_attachments . " 
         WHERE event_id = e.event_id OR event_id = e.recurrence_id) AS _attachments
       FROM " . $this->db_events . " AS e
       WHERE e.calendar_id IN (%s)
       AND e.$col=?",
       $cals
      ),
      $id);

    if ($result && ($event = $this->rc->db->fetch_assoc($result)) && $event['event_id']) {
      $this->cache[$id] = $this->_read_postprocess($event);
      return $this->cache[$id];
    }

    return false;
  }

  /**
   * Get event data
   *
   * @see calendar_driver::load_events()
   */
  public function load_events($start, $end, $query = null, $calendars = null, $virtual = 1, $modifiedsince = null)
  {
    if (empty($calendars))
      $calendars = array_keys($this->calendars);
    else if (is_string($calendars))
      $calendars = explode(',', $calendars);
      
    // only allow to select from calendars of this use
    $calendar_ids = array_map(array($this->rc->db, 'quote'), array_intersect($calendars, array_keys($this->calendars)));
    
    // compose (slow) SQL query for searching
    // FIXME: improve searching using a dedicated col and normalized values
    if ($query) {
      foreach (array('title','location','description','categories','attendees') as $col)
        $sql_query[] = $this->rc->db->ilike($col, '%'.$query.'%');
      $sql_add = 'AND (' . join(' OR ', $sql_query) . ')';
    }
    
    if (!$virtual)
      $sql_arr .= ' AND e.recurrence_id = 0';
    
    if ($modifiedsince)
      $sql_add .= ' AND e.changed >= ' . $this->rc->db->quote(date('Y-m-d H:i:s', $modifiedsince));
    
    $events = array();
    if (!empty($calendar_ids)) {
      $result = $this->rc->db->query(sprintf(
        "SELECT e.*, (SELECT COUNT(attachment_id) FROM " . $this->db_attachments . " 
           WHERE event_id = e.event_id OR event_id = e.recurrence_id) AS _attachments
         FROM " . $this->db_events . " AS e
         WHERE e.calendar_id IN (%s)
         AND e.start <= %s AND e.end >= %s
         %s
         GROUP BY e.event_id",
         join(',', $calendar_ids),
         $this->rc->db->fromunixtime($end),
         $this->rc->db->fromunixtime($start),
         $sql_add
       ));

      while ($result && ($event = $this->rc->db->fetch_assoc($result))) {
        $events[] = $this->_read_postprocess($event);
      }
    }
    
    return $events;
  }

  /**
   * Convert sql record into a rcube style event object
   */
  private function _read_postprocess($event)
  {
    $free_busy_map = array_flip($this->free_busy_map);
    $sensitivity_map = array_flip($this->sensitivity_map);
    
    $event['id'] = $event['event_id'];
    $event['start'] = new DateTime($event['start']);
    $event['end'] = new DateTime($event['end']);
    $event['allday'] = intval($event['all_day']);
    $event['created'] = new DateTime($event['created']);
    $event['changed'] = new DateTime($event['changed']);
    $event['free_busy'] = $free_busy_map[$event['free_busy']];
    $event['sensitivity'] = $sensitivity_map[$event['sensitivity']];
    $event['calendar'] = $event['calendar_id'];
    $event['recurrence_id'] = intval($event['recurrence_id']);
    
    // parse recurrence rule
    if ($event['recurrence'] && preg_match_all('/([A-Z]+)=([^;]+);?/', $event['recurrence'], $m, PREG_SET_ORDER)) {
      $event['recurrence'] = array();
      foreach ($m as $rr) {
        if (is_numeric($rr[2]))
          $rr[2] = intval($rr[2]);
        else if ($rr[1] == 'UNTIL')
          $rr[2] = date_create($rr[2]);
        else if ($rr[1] == 'RDATE')
          $rr[2] = array_map('date_create', explode(',', $rr[2]));
        else if ($rr[1] == 'EXDATE')
          $rr[2] = array_map('date_create', explode(',', $rr[2]));
        $event['recurrence'][$rr[1]] = $rr[2];
      }
    }
    
    if ($event['_attachments'] > 0)
      $event['attachments'] = (array)$this->list_attachments($event);
    
    // decode serialized event attendees
    if ($event['attendees']) {
      $attendees = array();
      foreach (explode("\n", $event['attendees']) as $line) {
        $att = array();
        foreach (rcube_utils::explode_quoted_string(';', $line) as $prop) {
          list($key, $value) = explode("=", $prop);
          $att[strtolower($key)] = stripslashes(trim($value, '""'));
        }
        $attendees[] = $att;
      }
      $event['attendees'] = $attendees;
    }

    unset($event['event_id'], $event['calendar_id'], $event['notifyat'], $event['all_day'], $event['_attachments']);
    return $event;
  }

  /**
   * Get a list of pending alarms to be displayed to the user
   *
   * @see calendar_driver::pending_alarms()
   */
  public function pending_alarms($time, $calendars = null)
  {
    if (empty($calendars))
      $calendars = array_keys($this->calendars);
    else if (is_string($calendars))
      $calendars = explode(',', $calendars);
    
    // only allow to select from calendars with activated alarms
    $calendar_ids = array();
    foreach ($calendars as $cid) {
      if ($this->calendars[$cid] && $this->calendars[$cid]['showalarms'])
        $calendar_ids[] = $cid;
    }
    $calendar_ids = array_map(array($this->rc->db, 'quote'), $calendar_ids);
    
    $alarms = array();
    if (!empty($calendar_ids)) {
      $result = $this->rc->db->query(sprintf(
        "SELECT * FROM " . $this->db_events . "
         WHERE calendar_id IN (%s)
         AND notifyat <= %s AND %s > %s",
         join(',', $calendar_ids),
         $this->rc->db->fromunixtime($time),
         $this->rc->db->quote_identifier('end'),
         $this->rc->db->fromunixtime($time)
       ));

      while ($result && ($event = $this->rc->db->fetch_assoc($result)))
        $alarms[] = $this->_read_postprocess($event);
    }

    return $alarms;
  }

  /**
   * Feedback after showing/sending an alarm notification
   *
   * @see calendar_driver::dismiss_alarm()
   */
  public function dismiss_alarm($event_id, $snooze = 0)
  {
    // set new notifyat time or unset if not snoozed
    $notify_at = $snooze > 0 ? date(self::DB_DATE_FORMAT, time() + $snooze) : null;
    
    $query = $this->rc->db->query(sprintf(
      "UPDATE " . $this->db_events . "
       SET   changed=%s, notifyat=?
       WHERE event_id=?
       AND calendar_id IN (" . $this->calendar_ids . ")",
        $this->rc->db->now()),
      $notify_at,
      $event_id
    );
    
    return $this->rc->db->affected_rows($query);
  }

  /**
   * Save an attachment related to the given event
   */
  private function add_attachment($attachment, $event_id)
  {
    $data = $attachment['data'] ? $attachment['data'] : file_get_contents($attachment['path']);
    
    $query = $this->rc->db->query(
      "INSERT INTO " . $this->db_attachments .
      " (event_id, filename, mimetype, size, data)" .
      " VALUES (?, ?, ?, ?, ?)",
      $event_id,
      $attachment['name'],
      $attachment['mimetype'],
      strlen($data),
      base64_encode($data)
    );

    return $this->rc->db->affected_rows($query);
  }

  /**
   * Remove a specific attachment from the given event
   */
  private function remove_attachment($attachment_id, $event_id)
  {
    $query = $this->rc->db->query(
      "DELETE FROM " . $this->db_attachments .
      " WHERE attachment_id = ?" .
        " AND event_id IN (SELECT event_id FROM " . $this->db_events .
          " WHERE event_id = ?"  .
            " AND calendar_id IN (" . $this->calendar_ids . "))",
      $attachment_id,
      $event_id
    );

    return $this->rc->db->affected_rows($query);
  }

  /**
   * List attachments of specified event
   */
  public function list_attachments($event)
  {
    $attachments = array();

    if (!empty($this->calendar_ids)) {
      $result = $this->rc->db->query(
        "SELECT attachment_id AS id, filename AS name, mimetype, size " .
        " FROM " . $this->db_attachments .
        " WHERE event_id IN (SELECT event_id FROM " . $this->db_events .
          " WHERE event_id=?"  .
            " AND calendar_id IN (" . $this->calendar_ids . "))".
        " ORDER BY filename",
        $event['recurrence_id'] ? $event['recurrence_id'] : $event['event_id']
      );

      while ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
        $attachments[] = $arr;
      }
    }

    return $attachments;
  }

  /**
   * Get attachment properties
   */
  public function get_attachment($id, $event)
  {
    if (!empty($this->calendar_ids)) {
      $result = $this->rc->db->query(
        "SELECT attachment_id AS id, filename AS name, mimetype, size " .
        " FROM " . $this->db_attachments .
        " WHERE attachment_id=?".
          " AND event_id=?",
        $id,
        $event['recurrence_id'] ? $event['recurrence_id'] : $event['id']
      );

      if ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
        return $arr;
      }
    }

    return null;
  }

  /**
   * Get attachment body
   */
  public function get_attachment_body($id, $event)
  {
    if (!empty($this->calendar_ids)) {
      $result = $this->rc->db->query(
        "SELECT data " .
        " FROM " . $this->db_attachments .
        " WHERE attachment_id=?".
          " AND event_id=?",
        $id,
        $event['id']
      );

      if ($result && ($arr = $this->rc->db->fetch_assoc($result))) {
        return base64_decode($arr['data']);
      }
    }

    return null;
  }

  /**
   * Remove the given category
   */
  public function remove_category($name)
  {
    $query = $this->rc->db->query(
      "UPDATE " . $this->db_events . "
       SET   categories=''
       WHERE categories=?
       AND   calendar_id IN (" . $this->calendar_ids . ")",
      $name
    );
    
    return $this->rc->db->affected_rows($query);
  }

  /**
   * Update/replace a category
   */
  public function replace_category($oldname, $name, $color)
  {
    $query = $this->rc->db->query(
      "UPDATE " . $this->db_events . "
       SET   categories=?
       WHERE categories=?
       AND   calendar_id IN (" . $this->calendar_ids . ")",
      $name,
      $oldname
    );
    
    return $this->rc->db->affected_rows($query);
  }

}
