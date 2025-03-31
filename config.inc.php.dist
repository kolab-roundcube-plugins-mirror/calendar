<?php

/*
 +-------------------------------------------------------------------------+
 | Configuration for the Calendar plugin                                   |
 |                                                                         |
 | Copyright (C) 2010, Lazlo Westerhof - Netherlands                       |
 | Copyright (C) 2011-2014, Kolab Systems AG                               |
 |                                                                         |
 | This program is free software: you can redistribute it and/or modify    |
 | it under the terms of the GNU Affero General Public License as          |
 | published by the Free Software Foundation, either version 3 of the      |
 | License, or (at your option) any later version.                         |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the            |
 | GNU Affero General Public License for more details.                     |
 |                                                                         |
 | You should have received a copy of the GNU Affero General Public License|
 | along with this program. If not, see <http://www.gnu.org/licenses/>.    |
 |                                                                         |
 +-------------------------------------------------------------------------+
 | Author: Lazlo Westerhof <hello@lazlo.me>                                |
 |         Thomas Bruederli <bruederli@kolabsys.com>                       |
 +-------------------------------------------------------------------------+
*/

// backend type (database, kolab, caldav)
$config['calendar_driver'] = "database";

// CalDAV server location (required when calendar_driver = caldav)
$config['calendar_caldav_server'] = "http://localhost";

// default calendar view (agendaDay, agendaWeek, month)
$config['calendar_default_view'] = "agendaWeek";

// show a birthdays calendar from the user's address book(s)
$config['calendar_contact_birthdays'] = false;

// timeslots per hour (1, 2, 3, 4, 6)
$config['calendar_timeslots'] = 2;

// show this number of days in agenda view
$config['calendar_agenda_range'] = 60;

// first day of the week (0-6)
$config['calendar_first_day'] = 1;

// first hour of the calendar (0-23)
$config['calendar_first_hour'] = 6;

// working hours begin
$config['calendar_work_start'] = 6;

// working hours end
$config['calendar_work_end'] = 18;

// show line at current time of the day
$config['calendar_time_indicator'] = true;

// Display week numbers:
// -1: don't display week numbers
//  0: in datepicker only (default)
//  1: in both datepicker and calendar
$config['calendar_show_weekno'] = 0;

// default alarm settings for new events.
// this is only a preset when a new event dialog opens
// possible values are <empty>, DISPLAY, EMAIL
$config['calendar_default_alarm_type'] = '';

// default alarm offset for new events.
// use ical-style offset values like "-1H" (one hour before) or "+30M" (30 minutes after)
$config['calendar_default_alarm_offset'] = '-15M';

// how to colorize events:
// 0: according to calendar color
// 1: according to category color
// 2: calendar for outer, category for inner color
// 3: category for outer, calendar for inner color
$config['calendar_event_coloring'] = 0;

// event categories
$config['calendar_categories'] = [
  'Personal' => 'c0c0c0',
      'Work' => 'ff0000',
    'Family' => '00ff00',
   'Holiday' => 'ff6600',
];

// enable users to invite/edit attendees for shared events organized by others
$config['calendar_allow_invite_shared'] = false;

// allow users to accecpt iTip invitations who are no explicitly listed as attendee.
// this can be the case if invitations are sent to mailing lists or alias email addresses.
$config['calendar_allow_itip_uninvited'] = true;

// controls the visibility/default of the checkbox controlling the sending of iTip invitations
// 0 = hidden  + disabled
// 1 = hidden  + active
// 2 = visible + unchecked
// 3 = visible + active
$config['calendar_itip_send_option'] = 3;

// Action taken after iTip request is handled. Possible values:
// 0 - no action
// 1 - move to Trash
// 2 - delete the message
// 3 - flag as deleted
// folder_name - move the message to the specified folder
$config['calendar_itip_after_action'] = 0;

// enable asynchronous free-busy triggering after data changed
$config['calendar_freebusy_trigger'] = false;

// free-busy information will be displayed for user calendars if available
// 0 - no free-busy information
// 1 - enabled in all views
// 2 - only in quickview
$config['calendar_include_freebusy_data'] = 1;

// SMTP server host used to send (anonymous) itip messages.
// To override the SMTP port or connection method, provide a full URL like 'tls://somehost:587'
// This will add a link to invitation messages to allow users from outside
// to reply when their mail clients do not support iTip format.
$config['calendar_itip_smtp_server'] = null;

// SMTP username used to send (anonymous) itip messages
$config['calendar_itip_smtp_user'] = 'smtpauth';

// SMTP password used to send (anonymous) itip messages
$config['calendar_itip_smtp_pass'] = '123456';

// show virtual invitation calendars (Kolab driver only)
$config['kolab_invitation_calendars'] = false;

// Base URL to build fully qualified URIs to access calendars via CALDAV
// The following replacement variables are supported:
// %h - Current HTTP host
// %u - Current webmail user name
// %n - Calendar name
// %i - Calendar UUID
// $config['calendar_caldav_url'] = 'http://%h/iRony/calendars/%u/%i';

// Driver to provide a resource directory ('ldap' is the only implementation yet).
// Leave empty or commented to disable resources support.
// $config['calendar_resources_driver'] = 'ldap';

// LDAP directory configuration to find avilable resources for events
// $config['calendar_resources_directory'] = array(/* ldap_public-like address book configuration */);

// Enables displaying of free-busy URL with token-based authentication
// Set it to the prefix URL, e.g. 'https://hostname/freebusy' or just '/freebusy'.
// See freebusy_session_auth in configuration of kolab_auth plugin.
$config['calendar_freebusy_session_auth_url'] = null;

// Nextcloud installation URL (for Talk integration).
$config['calendar_nextcloud_url'] = null;
