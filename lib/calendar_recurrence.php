<?php

/**
 * Recurrence computation class for the Calendar plugin
 *
 * Uitility class to compute instances of recurring events.
 *
 * @version @package_version@
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 * @package @package_name@
 *
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
class calendar_recurrence
{
  private $cal;
  private $event;
  private $next;
  private $engine;
  private $duration;
  private $hour = 0;

  /**
   * Default constructor
   *
   * @param object calendar The calendar plugin instance
   * @param array The event object to operate on
   */
  function __construct($cal, $event)
  {
    // use Horde classes to compute recurring instances
    // TODO: replace with something that has less than 6'000 lines of code
    require_once(__DIR__ . '/Horde_Date_Recurrence.php');

    $this->cal = $cal;
    $this->event = $event;
    $this->next = new Horde_Date($event['start'], $cal->timezone->getName());
    $this->hour = $this->next->hour;

    if (is_object($event['start']) && is_object($event['end']))
      $this->duration = $event['start']->diff($event['end']);

    $this->engine = new Horde_Date_Recurrence($event['start']);
    $this->engine->fromRRule20(libcalendaring::to_rrule($event['recurrence']));

    if (is_array($event['recurrence']['EXDATE'])) {
      foreach ($event['recurrence']['EXDATE'] as $exdate)
        $this->engine->addException($exdate->format('Y'), $exdate->format('n'), $exdate->format('j'));
    }
  }

  /**
   * Get date/time of the next occurence of this event
   *
   * @return mixed DateTime object or False if recurrence ended
   */
  public function next_start()
  {
    $time = false;
    $after = clone $this->next;
    $after->mday = $after->mday + 1;
    if ($this->next && ($next = $this->engine->nextActiveRecurrence($after))) {
      if (!$next->after($this->next)) {
        // avoid endless loops if recurrence computation fails
        return false;
      }
      if ($this->event['allday']) {
        $next->hour = $this->hour;  # fix time for all-day events
        $next->min = 0;
      }

      $time = $next->toDateTime();
      $this->next = $next;
    }

    return $time;
  }

  /**
   * Get the next recurring instance of this event
   *
   * @return mixed Array with event properties or False if recurrence ended
   */
  public function next_instance()
  {
    if ($next_start = $this->next_start()) {
      $next_end = clone $next_start;
      $next_end->add($this->duration);

      $next = $this->event;
      $next['recurrence_id'] = $next_start->format('Y-m-d');
      $next['start'] = $next_start;
      $next['end'] = $next_end;
      unset($next['_formatobj']);

      return $next;
    }

    return false;
  }

}
