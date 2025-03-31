<?php

/**
 * CalDAV calendar storage class simulating a virtual calendar listing pedning/declined invitations
 *
 * @author Aleksander Machniak <machniak@apheleia-it.ch>
 *
 * Copyright (C) 2014-2024, Apheleia IT AG <contact@apheleia-it.ch>
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

require_once __DIR__ . '/../kolab/kolab_invitation_calendar.php';

class caldav_invitation_calendar extends kolab_invitation_calendar
{
    public $id = '__caldav_invitation__';

    /**
     * Get attachment body
     *
     * @see calendar_driver::get_attachment_body()
     */
    public function get_attachment_body($id, $event)
    {
        // find the actual folder this event resides in
        if (!empty($event['_folder_id'])) {
            $cal = $this->cal->driver->get_calendar($event['_folder_id']);
        } else {
            $cal = null;
            foreach ($this->_list_calendars() as $cal_id) {
                $cal = $this->cal->driver->get_calendar($cal_id);

                if ($cal->get_event($event['id'])) {
                    break;
                }
            }
        }

        if ($cal && $cal->storage) {
            return $cal->get_attachment_body($id, $event);
        }

        return false;
    }

    /**
     * @param int    $start   Event's new start (unix timestamp)
     * @param int    $end     Event's new end (unix timestamp)
     * @param string $search  Search query (optional)
     * @param bool   $virtual Include virtual events (optional)
     * @param array  $query   Additional parameters to query storage
     *
     * @return array A list of event records
     */
    public function list_events($start, $end, $search = null, $virtual = true, $query = [])
    {
        // get email addresses of the current user
        $user_emails = $this->cal->get_user_emails();
        $subquery    = [];

        foreach ($user_emails as $email) {
            foreach ($this->partstats as $partstat) {
                $subquery[] = ['tags', '=', 'x-partstat:' . $email . ':' . strtolower($partstat)];
            }
        }

        $events = [];

        // aggregate events from all calendar folders
        foreach ($this->_list_calendars() as $cal_id) {
            $cal = $this->cal->driver->get_calendar($cal_id);

            foreach ($cal->list_events($start, $end, $search, 1, $query, [[$subquery, 'OR']]) as $event) {
                $match = false;

                // post-filter events to match out partstats
                if (!empty($event['attendees'])) {
                    foreach ($event['attendees'] as $attendee) {
                        if (
                            !empty($attendee['email']) && in_array_nocase($attendee['email'], $user_emails)
                            && !empty($attendee['status']) && in_array($attendee['status'], $this->partstats)
                        ) {
                            $match = true;
                            break;
                        }
                    }
                }

                if ($match) {
                    $uid = !empty($event['id']) ? $event['id'] : $event['uid'];
                    $events[$uid] = $this->_mod_event($event, $cal->id);
                }
            }

            // merge list of event categories (really?)
            $this->categories += $cal->categories;
        }

        return $events;
    }

    /**
     * Get number of events in the given calendar
     *
     * @param int   $start  Date range start (unix timestamp)
     * @param int   $end    Date range end (unix timestamp)
     * @param array $filter Additional query to filter events
     *
     * @return int Count
     */
    public function count_events($start, $end = null, $filter = null)
    {
        // get email addresses of the current user
        $user_emails = $this->cal->get_user_emails();
        $subquery    = [];

        foreach ($user_emails as $email) {
            foreach ($this->partstats as $partstat) {
                $subquery[] = ['tags', '=', 'x-partstat:' . $email . ':' . strtolower($partstat)];
            }
        }

        $filter = [
            ['tags', '!=', 'x-status:cancelled'],
            [$subquery, 'OR'],
        ];

        // aggregate counts from all calendar folders
        $count = 0;
        foreach ($this->_list_calendars() as $cal_id) {
            $cal = $this->cal->driver->get_calendar($cal_id);
            $count += $cal->count_events($start, $end, $filter);
        }

        return $count;
    }

    /**
     * Get IDs of all personal DAV calendars
     */
    private function _list_calendars()
    {
        $exceptions = [
            caldav_driver::INVITATIONS_CALENDAR_PENDING,
            caldav_driver::INVITATIONS_CALENDAR_DECLINED,
            caldav_driver::BIRTHDAY_CALENDAR_ID,
        ];

        $list = array_filter(
            $this->cal->driver->list_calendars(),
            function ($prop) use ($exceptions) {
                return !in_array($prop['id'], $exceptions) && strpos($prop['group'] ?? '', 'other') === false;
            }
        );

        return array_keys($list);
    }
}
