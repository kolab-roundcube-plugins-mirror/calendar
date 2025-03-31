<?php

/**
 * Calendar plugin for Roundcube webmail
 *
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2014-2015, Kolab Systems AG <contact@kolabsys.com>
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
 * Calendar plugin
 *
 * @property calendar_driver          $driver
 * @property libcalendaring_vcalendar $ical
 * @property libcalendaring_itip      $itip
 */
#[AllowDynamicProperties]
class calendar extends rcube_plugin
{
    public const FREEBUSY_UNKNOWN   = 0;
    public const FREEBUSY_FREE      = 1;
    public const FREEBUSY_BUSY      = 2;
    public const FREEBUSY_TENTATIVE = 3;
    public const FREEBUSY_OOF       = 4;

    public const SESSION_KEY = 'calendar_temp';

    public $task = '?(?!logout).*';

    /** @var rcmail */
    public $rc;
    public $lib;
    public $resources_dir;
    public $home;  // declare public to be used in other classes
    public $urlbase;
    public $timezone;
    public $timezone_offset;
    public $gmt_offset;
    public $dst_active;
    public $ui;
    public $event;
    public $invitestatus;

    public $defaults = [
        'calendar_default_view' => "agendaWeek",
        'calendar_timeslots'    => 2,
        'calendar_work_start'   => 6,
        'calendar_work_end'     => 18,
        'calendar_agenda_range' => 60,
        'calendar_show_weekno'  => 0,
        'calendar_first_day'    => 1,
        'calendar_first_hour'   => 6,
        'calendar_time_format'  => null,
        'calendar_event_coloring'      => 0,
        'calendar_time_indicator'      => true,
        'calendar_allow_invite_shared' => false,
        'calendar_itip_send_option'    => 3,
        'calendar_itip_after_action'   => 0,
    ];

    private $token;


    /**
     * Plugin initialization.
     */
    public function init()
    {
        $this->rc = rcmail::get_instance();

        $this->register_task('calendar');

        // load calendar configuration
        $this->load_config();

        // catch iTIP confirmation requests that don're require a valid session
        if ($this->rc->action == 'attend' && !empty($_REQUEST['_t'])) {
            $this->add_hook('startup', [$this, 'itip_attend_response']);
        } elseif ($this->rc->action == 'feed' && !empty($_REQUEST['_cal'])) {
            $this->add_hook('startup', [$this, 'ical_feed_export']);
        } elseif ($this->rc->task != 'login') {
            // default startup routine
            $this->add_hook('startup', [$this, 'startup']);
        }

        $this->add_hook('user_delete', [$this, 'user_delete']);
    }

    /**
     * Setup basic plugin environment and UI
     */
    protected function setup()
    {
        $this->require_plugin('libcalendaring');
        $this->require_plugin('libkolab');

        require $this->home . '/lib/calendar_ui.php';

        // load localizations
        $this->add_texts('localization/', $this->rc->task == 'calendar' && (!$this->rc->action || $this->rc->action == 'print'));

        $this->lib             = libcalendaring::get_instance();
        $this->timezone        = $this->lib->timezone;
        $this->gmt_offset      = $this->lib->gmt_offset;
        $this->dst_active      = $this->lib->dst_active;
        $this->timezone_offset = $this->gmt_offset / 3600 - $this->dst_active;
        $this->ui              = new calendar_ui($this);
    }

    /**
     * Startup hook
     */
    public function startup($args)
    {
        // the calendar module can be enabled/disabled by the kolab_auth plugin
        if ($this->rc->config->get('calendar_disabled', false)
            || !$this->rc->config->get('calendar_enabled', true)
        ) {
            return;
        }

        $this->setup();

        // load Calendar user interface
        if (!$this->rc->output->ajax_call
            && (empty($this->rc->output->env['framed']) || $args['action'] == 'preview')
        ) {
            $this->ui->init();

            // settings are required in (almost) every GUI step
            if ($args['action'] != 'attend') {
                $this->rc->output->set_env('calendar_settings', $this->load_settings());
            }

            // A hack to replace "Edit/Share Calendar" label with "Edit calendar", for non-Kolab driver
            if ($args['task'] == 'calendar' && $this->rc->config->get('calendar_driver', 'database') !== 'kolab') {
                $merge = ['calendar.editcalendar' => $this->gettext('edcalendar')];
                $this->rc->load_language(null, [], $merge);
                $this->rc->output->command('add_label', $merge);
            }
        }

        if ($args['task'] == 'calendar' && $args['action'] != 'save-pref') {
            if ($args['action'] != 'upload') {
                $this->load_driver();
            }

            // register calendar actions
            $this->register_action('index', [$this, 'calendar_view']);
            $this->register_action('event', [$this, 'event_action']);
            $this->register_action('calendar', [$this, 'calendar_action']);
            $this->register_action('count', [$this, 'count_events']);
            $this->register_action('load_events', [$this, 'load_events']);
            $this->register_action('export_events', [$this, 'export_events']);
            $this->register_action('import_events', [$this, 'import_events']);
            $this->register_action('upload', [$this, 'attachment_upload']);
            $this->register_action('get-attachment', [$this, 'attachment_get']);
            $this->register_action('freebusy-status', [$this, 'freebusy_status']);
            $this->register_action('freebusy-times', [$this, 'freebusy_times']);
            $this->register_action('randomdata', [$this, 'generate_randomdata']);
            $this->register_action('print', [$this,'print_view']);
            $this->register_action('mailimportitip', [$this, 'mail_import_itip']);
            $this->register_action('mailimportattach', [$this, 'mail_import_attachment']);
            $this->register_action('dialog-ui', [$this, 'mail_message2event']);
            $this->register_action('check-recent', [$this, 'check_recent']);
            $this->register_action('itip-status', [$this, 'event_itip_status']);
            $this->register_action('itip-remove', [$this, 'event_itip_remove']);
            $this->register_action('itip-decline-reply', [$this, 'mail_itip_decline_reply']);
            $this->register_action('itip-delegate', [$this, 'mail_itip_delegate']);
            $this->register_action('resources-list', [$this, 'resources_list']);
            $this->register_action('resources-owner', [$this, 'resources_owner']);
            $this->register_action('resources-calendar', [$this, 'resources_calendar']);
            $this->register_action('resources-autocomplete', [$this, 'resources_autocomplete']);
            $this->register_action('talk-room-create', [$this, 'talk_room_create']);

            $this->rc->plugins->register_action('plugin.share-invitation', $this->ID, [$this, 'share_invitation']);

            $this->add_hook('refresh', [$this, 'refresh']);

            // remove undo information...
            if (!empty($_SESSION['calendar_event_undo'])) {
                $undo = $_SESSION['calendar_event_undo'];
                // ...after timeout
                $undo_time = $this->rc->config->get('undo_timeout', 0);
                if ($undo['ts'] < time() - $undo_time) {
                    $this->rc->session->remove('calendar_event_undo');
                    // @TODO: do EXPUNGE on kolab objects?
                }
            }
        } elseif ($args['task'] == 'settings') {
            // add hooks for Calendar settings
            $this->add_hook('preferences_sections_list', [$this, 'preferences_sections_list']);
            $this->add_hook('preferences_list', [$this, 'preferences_list']);
            $this->add_hook('preferences_save', [$this, 'preferences_save']);
        } elseif ($args['task'] == 'mail') {
            // hooks to catch event invitations on incoming mails
            if ($args['action'] == 'show' || $args['action'] == 'preview') {
                $this->add_hook('template_object_messagebody', [$this, 'mail_messagebody_html']);
            }

            // add 'Create event' item to message menu
            if ($this->api->output->type == 'html' && (empty($_GET['_rel']) || $_GET['_rel'] != 'event')) {
                $this->api->output->add_label('calendar.createfrommail');
                $this->api->add_content(
                    html::tag(
                        'li',
                        ['role' => 'menuitem'],
                        $this->api->output->button([
                            'command'  => 'calendar-create-from-mail',
                            'label'    => 'calendar.createfrommail',
                            'type'     => 'link',
                            'classact' => 'icon calendarlink active',
                            'class'    => 'icon calendarlink disabled',
                            'innerclass' => 'icon calendar',
                        ])
                    ),
                    'messagemenu'
                );
            }

            $this->add_hook('messages_list', [$this, 'mail_messages_list']);
            $this->add_hook('message_compose', [$this, 'mail_message_compose']);
        } elseif ($args['task'] == 'addressbook') {
            if ($this->rc->config->get('calendar_contact_birthdays')) {
                $this->add_hook('contact_update', [$this, 'contact_update']);
                $this->add_hook('contact_create', [$this, 'contact_update']);
            }
        }

        // add hooks to display alarms
        $this->add_hook('pending_alarms', [$this, 'pending_alarms']);
        $this->add_hook('dismiss_alarms', [$this, 'dismiss_alarms']);
    }

    /**
     * Helper method to load the backend driver according to local config
     */
    private function load_driver()
    {
        if (!empty($this->driver)) {
            return;
        }

        $driver_name = $this->rc->config->get('calendar_driver', 'database');
        $driver_class = $driver_name . '_driver';

        require_once $this->home . '/drivers/calendar_driver.php';
        require_once $this->home . '/drivers/' . $driver_name . '/' . $driver_class . '.php';

        $this->driver = new $driver_class($this);

        if ($this->driver->undelete) {
            $this->driver->undelete = $this->rc->config->get('undo_timeout', 0) > 0;
        }
    }

    /**
     * Load iTIP functions
     */
    private function load_itip()
    {
        if (empty($this->itip)) {
            require_once $this->home . '/lib/calendar_itip.php';

            $this->itip = new calendar_itip($this);

            $rsvp_actions = ['accepted','tentative','declined','delegated'];

            if ($this->rc->config->get('kolab_invitation_calendars')) {
                $rsvp_actions[] = 'needs-action';
            }

            $this->itip->set_rsvp_actions($this->rc->config->get('calendar_rsvp_actions', $rsvp_actions));
        }

        return $this->itip;
    }

    /**
     * Load iCalendar functions
     */
    public function get_ical()
    {
        if (empty($this->ical)) {
            $this->ical = libcalendaring::get_ical();
        }

        return $this->ical;
    }

    /**
     * Get properties of the calendar this user has specified as default
     */
    public function get_default_calendar($calendars = null)
    {
        if ($calendars === null) {
            $filter    = calendar_driver::FILTER_PERSONAL | calendar_driver::FILTER_WRITEABLE;
            $calendars = $this->driver->list_calendars($filter);
        }

        $default_id = $this->rc->config->get('calendar_default_calendar');
        $calendar   = !empty($calendars[$default_id]) ? $calendars[$default_id] : null;
        $first      = null;

        if (!$calendar) {
            foreach ($calendars as $cal) {
                if (!empty($cal['default']) && $cal['editable']) {
                    $calendar = $cal;
                }
                if ($cal['editable']) {
                    $first = $cal;
                }
            }
        }

        return $calendar ?: $first;
    }

    /**
     * Render the main calendar view from skin template
     */
    public function calendar_view()
    {
        $this->rc->output->set_pagetitle($this->gettext('calendar'));

        // Add JS files to the page header
        $this->ui->addJS();

        $this->ui->init_templates();
        $this->rc->output->add_label(
            'lowest',
            'low',
            'normal',
            'high',
            'highest',
            'delete',
            'cancel',
            'uploading',
            'noemailwarning',
            'close'
        );

        // initialize attendees autocompletion
        $this->rc->autocomplete_init();

        $this->rc->output->set_env('timezone', $this->timezone->getName());
        $this->rc->output->set_env('calendar_driver', $this->rc->config->get('calendar_driver'), false);
        $this->rc->output->set_env('calendar_resources', (bool)$this->rc->config->get('calendar_resources_driver'));
        $this->rc->output->set_env('calendar_resources_freebusy', !empty($this->rc->config->get('kolab_freebusy_server')));
        $this->rc->output->set_env('identities-selector', $this->ui->identity_select([
                'id'         => 'edit-identities-list',
                'aria-label' => $this->gettext('roleorganizer'),
                'class'      => 'form-control custom-select',
        ]));

        $view = rcube_utils::get_input_value('view', rcube_utils::INPUT_GPC);
        if (in_array($view, ['agendaWeek', 'agendaDay', 'month', 'list'])) {
            $this->rc->output->set_env('view', $view);
        }

        if ($date = rcube_utils::get_input_value('date', rcube_utils::INPUT_GPC)) {
            $this->rc->output->set_env('date', $date);
        }

        if ($msgref = rcube_utils::get_input_value('itip', rcube_utils::INPUT_GPC)) {
            $this->rc->output->set_env('itip_events', $this->itip_events($msgref));
        }

        $this->rc->output->send('calendar.calendar');
    }

    /**
     * Handler for preferences_sections_list hook.
     * Adds Calendar settings sections into preferences sections list.
     *
     * @param array $p Original parameters
     *
     * @return array Modified parameters
     */
    public function preferences_sections_list($p)
    {
        $p['list']['calendar'] = [
            'id'      => 'calendar',
            'section' => $this->gettext('calendar'),
        ];

        return $p;
    }

    /**
     * Handler for preferences_list hook.
     * Adds options blocks into Calendar settings sections in Preferences.
     *
     * @param array $p Original parameters
     *
     * @return array Modified parameters
     */
    public function preferences_list($p)
    {
        if ($p['section'] != 'calendar') {
            return $p;
        }

        $no_override = array_flip((array) $this->rc->config->get('dont_override'));

        $p['blocks']['view']['name'] = $this->gettext('mainoptions');

        if (!isset($no_override['calendar_default_view'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id = 'rcmfd_default_view';
            $view = $this->rc->config->get('calendar_default_view', $this->defaults['calendar_default_view']);

            $select = new html_select(['name' => '_default_view', 'id' => $field_id]);
            $select->add($this->gettext('day'), "agendaDay");
            $select->add($this->gettext('week'), "agendaWeek");
            $select->add($this->gettext('month'), "month");
            $select->add($this->gettext('agenda'), "list");

            $p['blocks']['view']['options']['default_view'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('default_view'))),
                'content' => $select->show($view == 'table' ? 'list' : $view),
            ];
        }

        if (!isset($no_override['calendar_timeslots'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id  = 'rcmfd_timeslots';
            $choices   = ['1', '2', '3', '4', '6'];
            $timeslots = $this->rc->config->get('calendar_timeslots', $this->defaults['calendar_timeslots']);

            $select = new html_select(['name' => '_timeslots', 'id' => $field_id]);
            $select->add($choices, $choices);

            $p['blocks']['view']['options']['timeslots'] = [
                'title' => html::label($field_id, rcube::Q($this->gettext('timeslots'))),
                'content' => $select->show(strval($timeslots)),
            ];
        }

        if (!isset($no_override['calendar_first_day'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id  = 'rcmfd_firstday';
            $first_day = $this->rc->config->get('calendar_first_day', $this->defaults['calendar_first_day']);

            $select = new html_select(['name' => '_first_day', 'id' => $field_id]);
            $select->add($this->gettext('sunday'), '0');
            $select->add($this->gettext('monday'), '1');
            $select->add($this->gettext('tuesday'), '2');
            $select->add($this->gettext('wednesday'), '3');
            $select->add($this->gettext('thursday'), '4');
            $select->add($this->gettext('friday'), '5');
            $select->add($this->gettext('saturday'), '6');

            $p['blocks']['view']['options']['first_day'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('first_day'))),
                'content' => $select->show(strval($first_day)),
            ];
        }

        if (!isset($no_override['calendar_first_hour'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $time_format = $this->rc->config->get('calendar_time_format', $this->defaults['calendar_time_format']);
            $time_format = $this->rc->config->get('time_format', libcalendaring::to_php_date_format($time_format));
            $first_hour  = $this->rc->config->get('calendar_first_hour', $this->defaults['calendar_first_hour']);
            $field_id    = 'rcmfd_firsthour';

            $select_hours = new html_select(['name' => '_first_hour', 'id' => $field_id]);
            for ($h = 0; $h < 24; $h++) {
                $select_hours->add(date($time_format, mktime($h, 0, 0)), $h);
            }

            $p['blocks']['view']['options']['first_hour'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('first_hour'))),
                'content' => $select_hours->show($first_hour),
            ];
        }

        if (!isset($no_override['calendar_work_start'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id   = 'rcmfd_workstart';
            $work_start = $this->rc->config->get('calendar_work_start', $this->defaults['calendar_work_start']);
            $work_end   = $this->rc->config->get('calendar_work_end', $this->defaults['calendar_work_end']);
            $time_format = $this->rc->config->get('calendar_time_format', $this->defaults['calendar_time_format']);
            $time_format = $this->rc->config->get('time_format', libcalendaring::to_php_date_format($time_format));

            $select_hours = new html_select(['name' => '_work_start', 'id' => $field_id]);
            for ($h = 0; $h < 24; $h++) {
                $select_hours->add(date($time_format, mktime($h, 0, 0)), $h);
            }

            $p['blocks']['view']['options']['workinghours'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('workinghours'))),
                'content' => html::div(
                    'input-group',
                    $select_hours->show($work_start)
                    . html::span('input-group-append input-group-prepend', html::span('input-group-text', ' &mdash; '))
                    . $select_hours->show($work_end, ['name' => '_work_end', 'id' => $field_id])
                ),
            ];
        }

        if (!isset($no_override['calendar_event_coloring'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id = 'rcmfd_coloring';
            $mode     = $this->rc->config->get('calendar_event_coloring', $this->defaults['calendar_event_coloring']);

            $select_colors = new html_select(['name' => '_event_coloring', 'id' => $field_id]);
            $select_colors->add($this->gettext('coloringmode0'), 0);
            $select_colors->add($this->gettext('coloringmode1'), 1);
            $select_colors->add($this->gettext('coloringmode2'), 2);
            $select_colors->add($this->gettext('coloringmode3'), 3);

            $p['blocks']['view']['options']['eventcolors'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('eventcoloring'))),
                'content' => $select_colors->show($mode),
            ];
        }

        // loading driver is expensive, don't do it if not needed
        $this->load_driver();

        if (!isset($no_override['calendar_default_alarm_type']) || !isset($no_override['calendar_default_alarm_offset'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $alarm_type = $alarm_offset = '';
            $field_id    = 'rcmfd_alarm';

            if (!isset($no_override['calendar_default_alarm_type'])) {
                $select_type = new html_select(['name' => '_alarm_type', 'id' => $field_id]);
                $select_type->add($this->gettext('none'), '');

                foreach ($this->driver->alarm_types as $type) {
                    $select_type->add($this->rc->gettext(strtolower("alarm{$type}option"), 'libcalendaring'), $type);
                }

                $alarm_type = $select_type->show($this->rc->config->get('calendar_default_alarm_type', ''));
            }

            if (!isset($no_override['calendar_default_alarm_offset'])) {
                $input_value   = new html_inputfield(['name' => '_alarm_value', 'id' => $field_id . 'value', 'size' => 3]);
                $select_offset = new html_select(['name' => '_alarm_offset', 'id' => $field_id . 'offset']);

                foreach (['-M','-H','-D','+M','+H','+D'] as $trigger) {
                    $select_offset->add($this->rc->gettext('trigger' . $trigger, 'libcalendaring'), $trigger);
                }

                $preset = libcalendaring::parse_alarm_value($this->rc->config->get('calendar_default_alarm_offset', '-15M'));
                $alarm_offset = $input_value->show($preset[0]) . ' ' . $select_offset->show($preset[1]);
            }

            $p['blocks']['view']['options']['alarmtype'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('defaultalarmtype'))),
                'content' => html::div('input-group', $alarm_type . ' ' . $alarm_offset),
            ];
        }

        if (!isset($no_override['calendar_default_calendar'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            // default calendar selection
            $field_id   = 'rcmfd_default_calendar';
            $filter     = calendar_driver::FILTER_PERSONAL | calendar_driver::FILTER_ACTIVE | calendar_driver::FILTER_INSERTABLE;
            $select_cal = new html_select(['name' => '_default_calendar', 'id' => $field_id, 'is_escaped' => true]);

            $default_calendar = null;
            foreach ((array) $this->driver->list_calendars($filter) as $id => $prop) {
                $select_cal->add($prop['name'], strval($id));
                if (!empty($prop['default'])) {
                    $default_calendar = $id;
                }
            }

            $p['blocks']['view']['options']['defaultcalendar'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('defaultcalendar'))),
                'content' => $select_cal->show($this->rc->config->get('calendar_default_calendar', $default_calendar)),
            ];
        }

        if (!isset($no_override['calendar_show_weekno'])) {
            if (empty($p['current'])) {
                $p['blocks']['view']['content'] = true;
                return $p;
            }

            $field_id   = 'rcmfd_show_weekno';
            $select = new html_select(['name' => '_show_weekno', 'id' => $field_id]);
            $select->add($this->gettext('weeknonone'), -1);
            $select->add($this->gettext('weeknodatepicker'), 0);
            $select->add($this->gettext('weeknoall'), 1);

            $p['blocks']['view']['options']['show_weekno'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('showweekno'))),
                'content' => $select->show(intval($this->rc->config->get('calendar_show_weekno'))),
            ];
        }

        $p['blocks']['itip']['name'] = $this->gettext('itipoptions');

        // Invitations handling
        if (!isset($no_override['calendar_itip_after_action'])) {
            if (empty($p['current'])) {
                $p['blocks']['itip']['content'] = true;
                return $p;
            }

            $field_id = 'rcmfd_after_action';
            $select   = new html_select([
                    'name'     => '_after_action',
                    'id'       => $field_id,
                    'onchange' => "\$('#{$field_id}_select')[this.value == 4 ? 'show' : 'hide']()",
            ]);

            $select->add($this->gettext('afternothing'), '');
            $select->add($this->gettext('aftertrash'), 1);
            $select->add($this->gettext('afterdelete'), 2);
            $select->add($this->gettext('afterflagdeleted'), 3);
            $select->add($this->gettext('aftermoveto'), 4);

            $val    = $this->rc->config->get('calendar_itip_after_action', $this->defaults['calendar_itip_after_action']);
            $folder = null;

            if ($val !== null && $val !== '' && !is_int($val)) {
                $folder = $val;
                $val    = 4;
            }

            $folders = $this->rc->folder_selector([
                    'id'            => $field_id . '_select',
                    'name'          => '_after_action_folder',
                    'maxlength'     => 30,
                    'folder_filter' => 'mail',
                    'folder_rights' => 'w',
                    'style'         => $val !== 4 ? 'display:none' : '',
            ]);

            $p['blocks']['itip']['options']['after_action'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('afteraction'))),
                'content' => html::div(
                    'input-group input-group-combo',
                    $select->show($val) . $folders->show($folder)
                ),
            ];
        }

        // category definitions
        if (empty($this->driver->nocategories) && !isset($no_override['calendar_categories'])) {
            $p['blocks']['categories']['name'] = $this->gettext('categories');

            if (empty($p['current'])) {
                $p['blocks']['categories']['content'] = true;
                return $p;
            }

            $categories      = (array) $this->driver->list_categories();
            $categories_list = '';

            foreach ($categories as $name => $color) {
                $key = md5($name);
                $field_class = 'rcmfd_category_' . str_replace(' ', '_', $name);
                $category_remove = html::span(
                    'input-group-append',
                    html::a(
                        [
                            'class'   => 'button icon delete input-group-text',
                            'onclick' => '$(this).parent().parent().remove()',
                            'title'   => $this->gettext('remove_category'),
                            'href'    => '#rcmfd_new_category',
                        ],
                        html::span('inner', $this->gettext('delete'))
                    )
                );

                $category_name  = new html_inputfield(['name' => "_categories[$key]", 'class' => $field_class, 'size' => 30, 'disabled' => $this->driver->categoriesimmutable]);
                $category_color = new html_inputfield(['name' => "_colors[$key]", 'class' => "$field_class colors", 'size' => 6]);
                $hidden         = '';

                if (!empty($this->driver->categoriesimmutable)) {
                    $hidden =  html::tag('input', ['type' => 'hidden', 'name' => "_categories[$key]", 'value' => $name]);
                }

                $categories_list .= $hidden
                    . html::div('input-group', $category_name->show($name) . $category_color->show($color) . $category_remove);
            }

            $p['blocks']['categories']['options']['categoriesdefault'] = [
                'content' => html::div(['id' => 'calendarcategories'], $categories_list),
            ];

            $field_id = 'rcmfd_new_category';
            $new_category = new html_inputfield(['name' => '_new_category', 'id' => $field_id, 'size' => 30]);
            $add_category = html::span(
                'input-group-append',
                html::a(
                    [
                        'type'    => 'button',
                        'class'   => 'button create input-group-text',
                        'title'   => $this->gettext('add_category'),
                        'onclick' => 'rcube_calendar_add_category()',
                        'href'    => '#rcmfd_new_category',
                    ],
                    html::span('inner', $this->gettext('add_category'))
                )
            );

            $p['blocks']['categories']['options']['categories'] = [
                'content' => html::div('input-group', $new_category->show('') . $add_category),
            ];

            $this->rc->output->add_label('delete', 'calendar.remove_category');
            $this->rc->output->add_script(
                '
function rcube_calendar_add_category() {
    var name = $("#rcmfd_new_category").val();
    if (name.length) {
        var button_label = rcmail.gettext("calendar.remove_category");
        var input = $("<input>").attr({type: "text", name: "_categories[]", size: 30, "class": "form-control"}).val(name);
        var color = $("<input>").attr({type: "text", name: "_colors[]", size: 6, "class": "colors form-control"}).val("000000");
        var button = $("<a>").attr({"class": "button icon delete input-group-text", title: button_label, href: "#rcmfd_new_category"})
            .click(function() { $(this).parent().parent().remove(); })
            .append($("<span>").addClass("inner").text(rcmail.gettext("delete")));

        $("<div>").addClass("input-group").append(input).append(color).append($("<span class=\'input-group-append\'>").append(button))
            .appendTo("#calendarcategories");
        color.minicolors(rcmail.env.minicolors_config || {});
        $("#rcmfd_new_category").val("");
    }
}',
                'foot'
            );

            $this->rc->output->add_script(
                '
$("#rcmfd_new_category").keypress(function(event) {
    if (event.which == 13) {
        rcube_calendar_add_category();
        event.preventDefault();
    }
});',
                'docready'
            );

            // load miniColors js/css files
            jqueryui::miniColors();
        }

        // virtual birthdays calendar
        if (!isset($no_override['calendar_contact_birthdays'])) {
            $p['blocks']['birthdays']['name'] = $this->gettext('birthdayscalendar');

            if (empty($p['current'])) {
                $p['blocks']['birthdays']['content'] = true;
                return $p;
            }

            $field_id = 'rcmfd_contact_birthdays';
            $input    = new html_checkbox([
                    'name'    => '_contact_birthdays',
                    'id'      => $field_id,
                    'value'   => 1,
                    'onclick' => '$(".calendar_birthday_props").prop("disabled",!this.checked)',
            ]);

            $p['blocks']['birthdays']['options']['contact_birthdays'] = [
                'title'   => html::label($field_id, $this->gettext('displaybirthdayscalendar')),
                'content' => $input->show($this->rc->config->get('calendar_contact_birthdays') ? 1 : 0),
            ];

            $input_attrib = [
                'class'    => 'calendar_birthday_props',
                'disabled' => !$this->rc->config->get('calendar_contact_birthdays'),
            ];

            $sources  = [];
            $checkbox = new html_checkbox(['name' => '_birthday_adressbooks[]'] + $input_attrib);

            foreach ($this->rc->get_address_sources(false, true) as $source) {
                // Roundcube >= 1.5, Ignore Collected Recipients and Trusted Senders sources
                if ((defined('rcube_addressbook::TYPE_RECIPIENT') && $source['id'] == (string) rcube_addressbook::TYPE_RECIPIENT)
                    || (defined('rcube_addressbook::TYPE_TRUSTED_SENDER') && $source['id'] == (string) rcube_addressbook::TYPE_TRUSTED_SENDER)
                ) {
                    continue;
                }

                $active = in_array($source['id'], (array) $this->rc->config->get('calendar_birthday_adressbooks')) ? $source['id'] : '';
                $sources[] = html::tag(
                    'li',
                    null,
                    html::label(
                        null,
                        $checkbox->show($active, ['value' => $source['id']])
                        . rcube::Q(!empty($source['realname']) ? $source['realname'] : $source['name'])
                    )
                );
            }

            $p['blocks']['birthdays']['options']['birthday_adressbooks'] = [
                'title'   => rcube::Q($this->gettext('birthdayscalendarsources')),
                'content' => html::tag('ul', 'proplist', implode("\n", $sources)),
            ];

            $field_id = 'rcmfd_birthdays_alarm';
            $select_type = new html_select(['name' => '_birthdays_alarm_type', 'id' => $field_id] + $input_attrib);
            $select_type->add($this->gettext('none'), '');

            foreach ($this->driver->alarm_types as $type) {
                $select_type->add($this->rc->gettext(strtolower("alarm{$type}option"), 'libcalendaring'), $type);
            }

            $input_value   = new html_inputfield(['name' => '_birthdays_alarm_value', 'id' => $field_id . 'value', 'size' => 3] + $input_attrib);
            $select_offset = new html_select(['name' => '_birthdays_alarm_offset', 'id' => $field_id . 'offset'] + $input_attrib);

            foreach (['-M','-H','-D'] as $trigger) {
                $select_offset->add($this->rc->gettext('trigger' . $trigger, 'libcalendaring'), $trigger);
            }

            $preset      = libcalendaring::parse_alarm_value($this->rc->config->get('calendar_birthdays_alarm_offset', '-1D'));
            $preset_type = $this->rc->config->get('calendar_birthdays_alarm_type', '');

            $p['blocks']['birthdays']['options']['birthdays_alarmoffset'] = [
                'title'   => html::label($field_id, rcube::Q($this->gettext('showalarms'))),
                'content' => html::div(
                    'input-group',
                    $select_type->show($preset_type)
                    . $input_value->show($preset[0]) . ' ' . $select_offset->show($preset[1])
                ),
            ];
        }

        return $p;
    }

    /**
     * Handler for preferences_save hook.
     * Executed on Calendar settings form submit.
     *
     * @param array $p Original parameters
     *
     * @return array Modified parameters
     */
    public function preferences_save($p)
    {
        if ($p['section'] == 'calendar') {
            $this->load_driver();

            // compose default alarm preset value
            $alarm_offset  = rcube_utils::get_input_value('_alarm_offset', rcube_utils::INPUT_POST);
            $alarm_value   = rcube_utils::get_input_value('_alarm_value', rcube_utils::INPUT_POST);
            $default_alarm = $alarm_offset[0] . intval($alarm_value) . $alarm_offset[1];

            $birthdays_alarm_offset = rcube_utils::get_input_value('_birthdays_alarm_offset', rcube_utils::INPUT_POST);
            $birthdays_alarm_value  = rcube_utils::get_input_value('_birthdays_alarm_value', rcube_utils::INPUT_POST);
            $birthdays_alarm_value  = $birthdays_alarm_offset[0] . intval($birthdays_alarm_value) . $birthdays_alarm_offset[1];

            $p['prefs'] = [
                'calendar_default_view' => rcube_utils::get_input_value('_default_view', rcube_utils::INPUT_POST),
                'calendar_timeslots'    => intval(rcube_utils::get_input_value('_timeslots', rcube_utils::INPUT_POST)),
                'calendar_first_day'    => intval(rcube_utils::get_input_value('_first_day', rcube_utils::INPUT_POST)),
                'calendar_first_hour'   => intval(rcube_utils::get_input_value('_first_hour', rcube_utils::INPUT_POST)),
                'calendar_work_start'   => intval(rcube_utils::get_input_value('_work_start', rcube_utils::INPUT_POST)),
                'calendar_work_end'     => intval(rcube_utils::get_input_value('_work_end', rcube_utils::INPUT_POST)),
                'calendar_show_weekno'  => intval(rcube_utils::get_input_value('_show_weekno', rcube_utils::INPUT_POST)),
                'calendar_event_coloring'       => intval(rcube_utils::get_input_value('_event_coloring', rcube_utils::INPUT_POST)),
                'calendar_default_alarm_type'   => rcube_utils::get_input_value('_alarm_type', rcube_utils::INPUT_POST),
                'calendar_default_alarm_offset' => $default_alarm,
                'calendar_default_calendar'     => rcube_utils::get_input_value('_default_calendar', rcube_utils::INPUT_POST),
                'calendar_date_format'          => null,  // clear previously saved values
                'calendar_time_format'          => null,
                'calendar_contact_birthdays'      => (bool) rcube_utils::get_input_value('_contact_birthdays', rcube_utils::INPUT_POST),
                'calendar_birthday_adressbooks'   => (array) rcube_utils::get_input_value('_birthday_adressbooks', rcube_utils::INPUT_POST),
                'calendar_birthdays_alarm_type'   => rcube_utils::get_input_value('_birthdays_alarm_type', rcube_utils::INPUT_POST),
                'calendar_birthdays_alarm_offset' => $birthdays_alarm_value ?: null,
                'calendar_itip_after_action'      => intval(rcube_utils::get_input_value('_after_action', rcube_utils::INPUT_POST)),
            ];

            if ($p['prefs']['calendar_itip_after_action'] == 4) {
                $p['prefs']['calendar_itip_after_action'] = rcube_utils::get_input_value('_after_action_folder', rcube_utils::INPUT_POST, true);
            }

            // categories
            if (empty($this->driver->nocategories)) {
                $old_categories = $new_categories = [];

                foreach ($this->driver->list_categories() as $name => $color) {
                    $old_categories[md5($name)] = $name;
                }

                $categories = (array) rcube_utils::get_input_value('_categories', rcube_utils::INPUT_POST);
                $colors     = (array) rcube_utils::get_input_value('_colors', rcube_utils::INPUT_POST);

                foreach ($categories as $key => $name) {
                    if (!isset($colors[$key])) {
                        continue;
                    }

                    $color = preg_replace('/^#/', '', strval($colors[$key]));

                    // rename categories in existing events -> driver's job
                    if (!empty($old_categories[$key])) {
                        $oldname = $old_categories[$key];
                        $this->driver->replace_category($oldname, $name, $color);
                        unset($old_categories[$key]);
                    } else {
                        $this->driver->add_category($name, $color);
                    }

                    $new_categories[$name] = $color;
                }

                // these old categories have been removed, alter events accordingly -> driver's job
                foreach ((array) $old_categories as $key => $name) {
                    $this->driver->remove_category($name);
                }

                $p['prefs']['calendar_categories'] = $new_categories;
            }
        }

        return $p;
    }

    /**
     * Dispatcher for calendar actions initiated by the client
     */
    public function calendar_action()
    {
        $action  = rcube_utils::get_input_value('action', rcube_utils::INPUT_GPC);
        $cal     = rcube_utils::get_input_value('c', rcube_utils::INPUT_GPC);
        $success = false;
        $reload  = false;
        $jsenv = [];

        if (isset($cal['showalarms'])) {
            $cal['showalarms'] = intval($cal['showalarms']);
        }

        switch ($action) {
            case "form-new":
            case "form-edit":
                echo $this->ui->calendar_editform($action, $cal);
                exit;

            case "new":
                $success = $this->driver->create_calendar($cal);
                $reload  = true;
                break;

            case "edit":
                $success = $this->driver->edit_calendar($cal);
                $reload  = true;
                break;

            case "delete":
                if ($success = $this->driver->delete_calendar($cal)) {
                    $this->rc->output->command('plugin.destroy_source', ['id' => $cal['id']]);
                }
                break;

            case "subscribe":
                if (!$this->driver->subscribe_calendar($cal)) {
                    $this->rc->output->show_message($this->gettext('errorsaving'), 'error');
                } else {
                    $calendars = $this->driver->list_calendars();
                    $calendar  = !empty($calendars[$cal['id']]) ? $calendars[$cal['id']] : null;

                    // find parent folder and check if it's a "user calendar"
                    // if it's also activated we need to refresh it (#5340)
                    while (!empty($calendar['parent'])) {
                        if (isset($calendars[$calendar['parent']])) {
                            $calendar = $calendars[$calendar['parent']];
                        } else {
                            break;
                        }
                    }

                    if ($calendar && $calendar['id'] != $cal['id']
                        && !empty($calendar['active'])
                        && $calendar['group'] == "other user"
                    ) {
                        $this->rc->output->command('plugin.refresh_source', $calendar['id']);
                    }
                }
                return;

            case "search":
                $results    = [];
                $color_mode = $this->rc->config->get('calendar_event_coloring', $this->defaults['calendar_event_coloring']);
                $query      = rcube_utils::get_input_value('q', rcube_utils::INPUT_GPC);
                $source     = rcube_utils::get_input_value('source', rcube_utils::INPUT_GPC);

                foreach ((array) $this->driver->search_calendars($query, $source) as $id => $prop) {
                    $editname = $prop['editname'] ?? '';
                    unset($prop['editname']);  // force full name to be displayed
                    $prop['active'] = false;

                    // let the UI generate HTML and CSS representation for this calendar
                    $html = $this->ui->calendar_list_item($id, $prop, $jsenv);
                    $cal  = $jsenv[$id] ?? []; // @phpstan-ignore-line
                    $cal['editname'] = $editname;
                    $cal['html']     = $html;

                    if (!empty($prop['color'])) {
                        $cal['css'] = $this->ui->calendar_css_classes($id, $prop, $color_mode);
                    }

                    $results[] = $cal;
                }

                // report more results available
                if (!empty($this->driver->search_more_results)) {
                    $this->rc->output->show_message('autocompletemore', 'notice');
                }

                $reqid = rcube_utils::get_input_value('_reqid', rcube_utils::INPUT_GPC);
                $this->rc->output->command('multi_thread_http_response', $results, $reqid);
                return;
        }

        if ($success) {
            $this->rc->output->show_message('successfullysaved', 'confirmation');
        } else {
            $error_msg = $this->gettext('errorsaving');
            if (!empty($this->driver->last_error)) {
                $error_msg .= ': ' . $this->driver->last_error;
            }
            $this->rc->output->show_message($error_msg, 'error');
        }

        $this->rc->output->command('plugin.unlock_saving');

        if ($success && $reload) {
            $this->rc->output->command('plugin.reload_view');
        }
    }

    /**
     * Dispatcher for event actions initiated by the client
     */
    public function event_action()
    {
        $action  = rcube_utils::get_input_value('action', rcube_utils::INPUT_GPC);
        $event   = rcube_utils::get_input_value('e', rcube_utils::INPUT_POST, true);
        $success = $reload = $got_msg = false;
        $old     = null;

        // read old event data in order to find changes
        if ((!empty($event['_notify']) || !empty($event['_decline'])) && $action != 'new') {
            $old = $this->driver->get_event($event);

            // load main event if savemode is 'all' or if deleting 'future' events
            if (!empty($old['recurrence_id'])
                && !empty($event['_savemode'])
                && ($event['_savemode'] == 'all' || ($event['_savemode'] == 'future' && $action == 'remove' && empty($event['_decline'])))
            ) {
                $old['id'] = $old['recurrence_id'];
                $old = $this->driver->get_event($old);
            }
        }

        switch ($action) {
            case "new":
                // create UID for new event
                $event['uid'] = $this->generate_uid();
                if (!$this->write_preprocess($event, $action)) {
                    $got_msg = true;
                } elseif ($success = $this->driver->new_event($event)) {
                    $event['id']        = $event['uid'];
                    $event['_savemode'] = 'all';

                    $this->cleanup_event($event);
                    $this->event_save_success($event, null, $action, true);
                    $this->talk_room_update($event);
                }

                $reload = $success && !empty($event['recurrence']) ? 2 : 1;
                break;

            case "edit":
                if (!$this->write_preprocess($event, $action)) {
                    $got_msg = true;
                } elseif ($success = $this->driver->edit_event($event)) {
                    $this->cleanup_event($event);
                    $this->event_save_success($event, $old, $action, $success);
                    $this->talk_room_update($event);
                }

                $reload = $success && (!empty($event['recurrence']) || !empty($event['_savemode']) || !empty($event['_fromcalendar'])) ? 2 : 1;
                break;

            case "resize":
                if (!$this->write_preprocess($event, $action)) {
                    $got_msg = true;
                } elseif ($success = $this->driver->resize_event($event)) {
                    $this->event_save_success($event, $old, $action, $success);
                }

                $reload = !empty($event['_savemode']) ? 2 : 1;
                break;

            case "move":
                if (!$this->write_preprocess($event, $action)) {
                    $got_msg = true;
                } elseif ($success = $this->driver->move_event($event)) {
                    $this->event_save_success($event, $old, $action, $success);
                }

                $reload = $success && !empty($event['_savemode']) ? 2 : 1;
                break;

            case "remove":
                // remove previous deletes
                $undo_time = $this->driver->undelete ? $this->rc->config->get('undo_timeout', 0) : 0;

                // search for event if only UID is given
                if (!isset($event['calendar']) && !empty($event['uid'])) {
                    if (!($event = $this->driver->get_event($event, calendar_driver::FILTER_WRITEABLE))) {
                        break;
                    }
                    $undo_time = 0;
                }

                // Note: the driver is responsible for setting $_SESSION['calendar_event_undo']
                //       containing 'ts' and 'data' elements
                $success = $this->driver->remove_event($event, $undo_time < 1);
                $reload = (!$success || !empty($event['_savemode'])) ? 2 : 1;

                if ($undo_time > 0 && $success) {
                    // display message with Undo link.
                    $onclick = sprintf(
                        "%s.http_request('event', 'action=undo', %s.display_message('', 'loading'))",
                        rcmail_output::JS_OBJECT_NAME,
                        rcmail_output::JS_OBJECT_NAME
                    );
                    $msg = html::span(null, $this->gettext('successremoval'))
                        . ' ' . html::a(['onclick' => $onclick], $this->gettext('undo'));

                    $this->rc->output->show_message($msg, 'confirmation', null, true, $undo_time);
                    $got_msg = true;
                } elseif ($success) {
                    $this->rc->output->show_message('calendar.successremoval', 'confirmation');
                    $got_msg = true;
                }

                // send cancellation for the main event
                if (isset($event['_savemode']) && $event['_savemode'] == 'all') {
                    unset($old['_instance'], $old['recurrence_date'], $old['recurrence_id']);
                }
                // send an update for the main event's recurrence rule instead of a cancellation message
                elseif (isset($event['_savemode']) && $event['_savemode'] == 'future' && !is_bool($success)) {
                    $event['_savemode'] = 'all';  // force event_save_success() to load master event
                    $action  = 'edit';
                    $success = true;
                }

                // send iTIP reply that participant has declined the event
                if ($success && !empty($event['_decline'])) {
                    $emails    = $this->get_user_emails();
                    $organizer = null;
                    $reply_sender = null;

                    foreach ($old['attendees'] as $i => $attendee) {
                        if ($attendee['role'] == 'ORGANIZER') {
                            $organizer = $attendee;
                        } elseif (!empty($attendee['email']) && in_array(strtolower($attendee['email']), $emails)) {
                            $old['attendees'][$i]['status'] = 'DECLINED';
                            $reply_sender = $attendee['email'];
                        }
                    }

                    if ($event['_savemode'] == 'future' && $event['id'] != $old['id']) {
                        $old['thisandfuture'] = true;
                    }

                    $itip = $this->load_itip();
                    $itip->set_sender_email($reply_sender);

                    if ($organizer && $itip->send_itip_message($old, 'REPLY', $organizer, 'itipsubjectdeclined', 'itipmailbodydeclined')) {
                        $mailto = !empty($organizer['name']) ? $organizer['name'] : $organizer['email'];
                        $msg    = $this->gettext(['name' => 'sentresponseto', 'vars' => ['mailto' => $mailto]]);

                        $this->rc->output->command('display_message', $msg, 'confirmation');
                    } else {
                        $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
                    }
                } elseif ($success) {
                    $this->event_save_success($event, $old, $action, $success);
                }

                break;

            case "undo":
                // Restore deleted event
                if (!empty($_SESSION['calendar_event_undo']['data'])) {
                    $event   = $_SESSION['calendar_event_undo']['data'];
                    $success = $this->driver->restore_event($event);
                }

                if ($success) {
                    $this->rc->session->remove('calendar_event_undo');
                    $this->rc->output->show_message('calendar.successrestore', 'confirmation');
                    $got_msg = true;
                    $reload  = 2;
                }

                break;

            case "rsvp":
                $itip_sending  = $this->rc->config->get('calendar_itip_send_option', $this->defaults['calendar_itip_send_option']);
                $status        = rcube_utils::get_input_value('status', rcube_utils::INPUT_POST);
                $attendees     = rcube_utils::get_input_value('attendees', rcube_utils::INPUT_POST);
                $reply_comment = $event['comment'];

                $this->write_preprocess($event, 'edit');
                $ev = $this->driver->get_event($event);
                $ev['attendees'] = $event['attendees'];
                $ev['free_busy'] = $event['free_busy'];
                $ev['_savemode'] = $event['_savemode'];
                $ev['comment']   = $reply_comment;

                // send invitation to delegatee + add it as attendee
                if ($status == 'delegated' && !empty($event['to'])) {
                    $itip = $this->load_itip();
                    if ($itip->delegate_to($ev, $event['to'], !empty($event['rsvp']), $attendees)) {
                        $this->rc->output->show_message('calendar.itipsendsuccess', 'confirmation');
                        $noreply = false;
                    }
                }

                $event = $ev;

                // compose a list of attendees affected by this change
                $updated_attendees = array_filter(array_map(
                    function ($j) use ($event) {
                        return $event['attendees'][$j];
                    },
                    $attendees
                ));

                if ($success = $this->driver->edit_rsvp($event, $status, $updated_attendees)) {
                    $noreply = rcube_utils::get_input_value('noreply', rcube_utils::INPUT_GPC);
                    $noreply = intval($noreply) || $status == 'needs-action' || $itip_sending === 0;
                    $reload  = $event['calendar'] != $ev['calendar'] || !empty($event['recurrence']) ? 2 : 1;
                    $emails  = $this->get_user_emails();
                    $ownedResourceEmails = $this->owned_resources_emails();
                    $organizer = null;
                    $resourceConfirmation = false;
                    $reply_sender = null;

                    foreach ($event['attendees'] as $i => $attendee) {
                        if ($attendee['role'] == 'ORGANIZER') {
                            $organizer = $attendee;
                        } elseif (!empty($attendee['email']) && in_array_nocase($attendee['email'], $emails)) {
                            $reply_sender = $attendee['email'];
                        } elseif (!empty($attendee['cutype']) && $attendee['cutype'] == 'RESOURCE' && !empty($attendee['email']) && in_array_nocase($attendee['email'], $ownedResourceEmails)) {
                            $resourceConfirmation = true;
                            // Note on behalf of which resource this update is going to be sent out
                            $event['_resource'] = $attendee['email'];
                        }
                    }

                    if (!$noreply) {
                        $itip = $this->load_itip();
                        $itip->set_sender_email($reply_sender);
                        $event['thisandfuture'] = $event['_savemode'] == 'future';
                        $bodytextprefix = $resourceConfirmation ? 'itipmailbodyresource' : 'itipmailbody';

                        if ($organizer && $itip->send_itip_message($event, 'REPLY', $organizer, 'itipsubject' . $status, $bodytextprefix . $status)) {
                            $mailto = !empty($organizer['name']) ? $organizer['name'] : $organizer['email'];
                            $msg    = $this->gettext(['name' => 'sentresponseto', 'vars' => ['mailto' => $mailto]]);

                            $this->rc->output->command('display_message', $msg, 'confirmation');
                        } else {
                            $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
                        }
                    }

                    // refresh all calendars
                    if ($event['calendar'] != $ev['calendar']) {
                        $this->rc->output->command('plugin.refresh_calendar', ['source' => null, 'refetch' => true]);
                        $reload = 0;
                    }
                }

                break;

            case "dismiss":
                $event['ids'] = explode(',', $event['id']);
                $plugin  = $this->rc->plugins->exec_hook('dismiss_alarms', $event);
                $success = $plugin['success'];

                foreach ($event['ids'] as $id) {
                    if (strpos($id, 'cal:') === 0) {
                        $success |= $this->driver->dismiss_alarm(substr($id, 4), $event['snooze']);
                    }
                }

                break;

            case "changelog":
                $data = $this->driver->get_event_changelog($event);
                if (is_array($data) && !empty($data)) {
                    $lib = $this->lib;
                    $dtformat = $this->rc->config->get('date_format') . ' ' . $this->rc->config->get('time_format');
                    array_walk($data, function (&$change) use ($lib, $dtformat) {
                        if (!empty($change['date'])) {
                            $dt = $lib->adjust_timezone($change['date']);

                            if ($dt instanceof DateTimeInterface) {
                                $change['date'] = $this->rc->format_date($dt, $dtformat, false);
                            }
                        }
                    });

                    $this->rc->output->command('plugin.render_event_changelog', $data);
                } else {
                    $this->rc->output->command('plugin.render_event_changelog', false);
                }

                $got_msg = true;
                $reload  = false;

                break;

            case "diff":
                $data = $this->driver->get_event_diff($event, $event['rev1'], $event['rev2']);
                if (is_array($data)) {
                    // convert some properties, similar to self::_client_event()
                    $lib = $this->lib;
                    array_walk($data['changes'], function (&$change, $i) use ($lib) {
                        // convert date cols
                        foreach (['start', 'end', 'created', 'changed'] as $col) {
                            if ($change['property'] == $col) {
                                $change['old'] = $lib->adjust_timezone($change['old'], strlen($change['old']) == 10)->format('c');
                                $change['new'] = $lib->adjust_timezone($change['new'], strlen($change['new']) == 10)->format('c');
                            }
                        }
                        // create textual representation for alarms and recurrence
                        if ($change['property'] == 'alarms') {
                            if (is_array($change['old'])) {
                                $change['old_'] = libcalendaring::alarm_text($change['old']);
                            }
                            if (is_array($change['new'])) {
                                $change['new_'] = libcalendaring::alarm_text(array_merge((array)$change['old'], $change['new']));
                            }
                        }
                        if ($change['property'] == 'recurrence') {
                            if (is_array($change['old'])) {
                                $change['old_'] = $lib->recurrence_text($change['old']);
                            }
                            if (is_array($change['new'])) {
                                $change['new_'] = $lib->recurrence_text(array_merge((array)$change['old'], $change['new']));
                            }
                        }
                        if ($change['property'] == 'attachments') {
                            if (is_array($change['old'])) {
                                $change['old']['classname'] = rcube_utils::file2class($change['old']['mimetype'], $change['old']['name']);
                            }
                            if (is_array($change['new'])) {
                                $change['new']['classname'] = rcube_utils::file2class($change['new']['mimetype'], $change['new']['name']);
                            }
                        }
                        // compute a nice diff of description texts
                        if ($change['property'] == 'description') {
                            $change['diff_'] = libkolab::html_diff($change['old'], $change['new']);
                        }
                    });

                    $this->rc->output->command('plugin.event_show_diff', $data);
                } else {
                    $this->rc->output->command('display_message', $this->gettext('objectdiffnotavailable'), 'error');
                }

                $got_msg = true;
                $reload  = false;

                break;

            case "show":
                if ($event = $this->driver->get_event_revison($event, $event['rev'])) {
                    $this->rc->output->command('plugin.event_show_revision', $this->_client_event($event));
                } else {
                    $this->rc->output->command('display_message', $this->gettext('objectnotfound'), 'error');
                }

                $got_msg = true;
                $reload  = false;
                break;

            case "restore":
                if ($success = $this->driver->restore_event_revision($event, $event['rev'])) {
                    $_event = $this->driver->get_event($event);
                    $reload = $_event['recurrence'] ? 2 : 1;
                    $msg = $this->gettext(['name' => 'objectrestoresuccess', 'vars' => ['rev' => $event['rev']]]);
                    $this->rc->output->command('display_message', $msg, 'confirmation');
                    $this->rc->output->command('plugin.close_history_dialog');
                } else {
                    $this->rc->output->command('display_message', $this->gettext('objectrestoreerror'), 'error');
                    $reload = 0;
                }

                $got_msg = true;
                break;
        }

        // show confirmation/error message
        if (!$got_msg) {
            if ($success) {
                $this->rc->output->show_message('successfullysaved', 'confirmation');
            } else {
                $this->rc->output->show_message('calendar.errorsaving', 'error');
            }
        }

        // unlock client
        $this->rc->output->command('plugin.unlock_saving', $success);

        // update event object on the client or trigger a complete refresh if too complicated
        if ($reload && empty($_REQUEST['_framed'])) {
            $args = ['source' => $event['calendar']];
            if ($reload > 1) {
                $args['refetch'] = true;
            } elseif ($success && $action != 'remove') {
                $args['update'] = $this->_client_event($this->driver->get_event($event), true);
            }
            $this->rc->output->command('plugin.refresh_calendar', $args);
        }
    }

    /**
     * Helper method sending iTip notifications after successful event updates
     */
    private function event_save_success(&$event, $old, $action, $success)
    {
        // $success is a new event ID
        if ($success !== true) {
            // send update notification on the main event
            if (!empty($event['_savemode']) && $event['_savemode'] == 'future' && !empty($event['_notify'])
                && !empty($old['attendees']) && !empty($old['recurrence_id'])
            ) {
                $master = $this->driver->get_event(['id' => $old['recurrence_id'], 'calendar' => $old['calendar']], 0, true);
                unset($master['_instance'], $master['recurrence_date']);

                $sent = $this->notify_attendees($master, null, $action, $event['_comment'], false);
                if ($sent < 0) {
                    $this->rc->output->show_message('calendar.errornotifying', 'error');
                }

                $event['attendees'] = $master['attendees'];  // this tricks us into the next if clause
            }

            // delete old reference if saved as new
            if (!empty($event['_savemode']) && ($event['_savemode'] == 'future' || $event['_savemode'] == 'new')) {
                $old = null;
            }

            $event['id']        = $success;
            $event['_savemode'] = 'all';
        }

        // send out notifications
        if (!empty($event['_notify']) && (!empty($event['attendees']) || !empty($old['attendees']))) {
            $_savemode = $event['_savemode'] ?? null;

            // send notification for the main event when savemode is 'all'
            if ($action != 'remove' && $_savemode == 'all'
                && (!empty($event['recurrence_id']) || !empty($old['recurrence_id']) || ($old && $old['id'] != $event['id']))
            ) {
                if (!empty($event['recurrence_id'])) {
                    $event['id'] = $event['recurrence_id'];
                } elseif (!empty($old['recurrence_id'])) {
                    $event['id'] = $old['recurrence_id'];
                } else {
                    $event['id'] = $old['id'];
                }
                $event = $this->driver->get_event($event, 0, true);
                unset($event['_instance'], $event['recurrence_date']);
            } else {
                // make sure we have the complete record
                $event = $action == 'remove' ? $old : $this->driver->get_event($event, 0, true);
            }

            $event['_savemode'] = $_savemode;

            if ($old) {
                $old['thisandfuture'] = $_savemode == 'future';
            }

            // only notify if data really changed (TODO: do diff check on client already)
            if (!$old || $action == 'remove' || self::event_diff($event, $old)) {
                $comment = $event['_comment'] ?? null;
                $sent    = $this->notify_attendees($event, $old, $action, $comment);

                if ($sent > 0) {
                    $this->rc->output->show_message('calendar.itipsendsuccess', 'confirmation');
                } elseif ($sent < 0) {
                    $this->rc->output->show_message('calendar.errornotifying', 'error');
                }
            }
        }
    }

    /**
     * Handler for load-requests from fullcalendar
     * This will return pure JSON formatted output
     */
    public function load_events()
    {
        $start  = $this->input_timestamp('start', rcube_utils::INPUT_GET);
        $end    = $this->input_timestamp('end', rcube_utils::INPUT_GET);
        $query  = rcube_utils::get_input_value('q', rcube_utils::INPUT_GET);
        $source = rcube_utils::get_input_value('source', rcube_utils::INPUT_GET);

        $events = $this->driver->load_events($start, $end, $query, $source);
        echo $this->encode($events, !empty($query));
        exit;
    }

    /**
     * Handler for requests fetching event counts for calendars
     */
    public function count_events()
    {
        // don't update session on these requests (avoiding race conditions)
        $this->rc->session->nowrite = true;

        $start  = rcube_utils::get_input_value('start', rcube_utils::INPUT_GET);
        $source = rcube_utils::get_input_value('source', rcube_utils::INPUT_GET);
        $end    = rcube_utils::get_input_value('end', rcube_utils::INPUT_GET);

        if (!$start) {
            $start = new DateTime('today 00:00:00', $this->timezone);
            $start = $start->format('U');
        }

        $counts = $this->driver->count_events($source, $start, $end);

        $this->rc->output->command('plugin.update_counts', ['counts' => $counts]);
    }

    /**
     * Load event data from an iTip message attachment
     */
    public function itip_events($msgref)
    {
        $path = explode('/', $msgref);
        $msg  = array_pop($path);
        $mbox = implode('/', $path);
        [$uid, $mime_id] = explode('#', $msg);
        $events = [];

        if ($event = $this->lib->mail_get_itip_object($mbox, $uid, $mime_id, 'event')) {
            $partstat = 'NEEDS-ACTION';

            $event['id']        = $event['uid'];
            $event['temporary'] = true;
            $event['readonly']  = true;
            $event['calendar']  = '--invitation--itip';
            $event['className'] = 'fc-invitation-' . strtolower($partstat);
            $event['_mbox']     = $mbox;
            $event['_uid']      = $uid;
            $event['_part']     = $mime_id;

            $events[] = $this->_client_event($event, true);

            // add recurring instances
            if (!empty($event['recurrence'])) {
                // Some installations can't handle all occurrences (aborting the request w/o an error in log)
                $freq = !empty($event['recurrence']['FREQ']) ? $event['recurrence']['FREQ'] : null;
                $end  = clone $event['start'];
                $end->add(new DateInterval($freq == 'DAILY' ? 'P1Y' : 'P10Y'));

                foreach ($this->driver->get_recurring_events($event, $event['start'], $end) as $recurring) {
                    $recurring['temporary'] = true;
                    $recurring['readonly']  = true;
                    $recurring['calendar']  = '--invitation--itip';

                    $events[] = $this->_client_event($recurring, true);
                }
            }
        }

        return $events;
    }

    /**
     * Handle invitations to a shared folder
     */
    public function share_invitation()
    {
        $id = rcube_utils::get_input_value('id', rcube_utils::INPUT_POST);
        $invitation = rcube_utils::get_input_value('invitation', rcube_utils::INPUT_POST);

        if ($calendar = $this->driver->accept_share_invitation($invitation)) {
            $this->rc->output->command('plugin.share-invitation', ['id' => $id, 'source' => $calendar]);
        }
    }

    /**
     * Handler for keep-alive requests
     * This will check for updated data in active calendars and sync them to the client
     */
    public function refresh($attr)
    {
        // refresh the entire calendar every 10th time to also sync deleted events
        if (rand(0, 10) == 10) {
            $this->rc->output->command('plugin.refresh_calendar', ['refetch' => true]);
            return;
        }

        $counts = [];

        foreach ($this->driver->list_calendars(calendar_driver::FILTER_ACTIVE) as $cal) {
            $events = $this->driver->load_events(
                rcube_utils::get_input_value('start', rcube_utils::INPUT_GPC),
                rcube_utils::get_input_value('end', rcube_utils::INPUT_GPC),
                rcube_utils::get_input_value('q', rcube_utils::INPUT_GPC),
                $cal['id'],
                1,
                $attr['last']
            );

            foreach ($events as $event) {
                $this->rc->output->command(
                    'plugin.refresh_calendar',
                    ['source' => $cal['id'], 'update' => $this->_client_event($event)]
                );
            }

            // refresh count for this calendar
            if (!empty($cal['counts'])) {
                $today = new DateTime('today 00:00:00', $this->timezone);
                $counts += $this->driver->count_events($cal['id'], $today->format('U'));
            }
        }

        if (!empty($counts)) {
            $this->rc->output->command('plugin.update_counts', ['counts' => $counts]);
        }
    }

    /**
     * Handler for pending_alarms plugin hook triggered by the calendar module on keep-alive requests.
     * This will check for pending notifications and pass them to the client
     */
    public function pending_alarms($p)
    {
        $this->load_driver();

        $time = !empty($p['time']) ? $p['time'] : time();

        if ($alarms = $this->driver->pending_alarms($time)) {
            foreach ($alarms as $alarm) {
                $alarm['id'] = 'cal:' . $alarm['id'];  // prefix ID with cal:
                $p['alarms'][] = $alarm;
            }
        }

        // get alarms for birthdays calendar
        if (
            $this->rc->config->get('calendar_contact_birthdays')
            && $this->rc->config->get('calendar_birthdays_alarm_type') == 'DISPLAY'
        ) {
            $cache = $this->rc->get_cache('calendar.birthdayalarms', 'db');

            foreach ($this->driver->load_birthday_events($time, $time + 86400 * 60) as $e) {
                $alarm = libcalendaring::get_next_alarm($e);

                // overwrite alarm time with snooze value (or null if dismissed)
                if ($dismissed = $cache->get($e['id'])) {
                    $alarm['time'] = $dismissed['notifyat'];
                }

                // add to list if alarm is set
                if ($alarm && !empty($alarm['time']) && $alarm['time'] <= $time) {
                    $e['id']       = 'cal:bday:' . $e['id'];
                    $e['notifyat'] = $alarm['time'];
                    $p['alarms'][] = $e;
                }
            }
        }

        return $p;
    }

    /**
     * Handler for alarm dismiss hook triggered by libcalendaring
     */
    public function dismiss_alarms($p)
    {
        $this->load_driver();

        foreach ((array) $p['ids'] as $id) {
            if (strpos($id, 'cal:bday:') === 0) {
                $p['success'] |= $this->driver->dismiss_birthday_alarm(substr($id, 9), $p['snooze']);
            } elseif (strpos($id, 'cal:') === 0) {
                $p['success'] |= $this->driver->dismiss_alarm(substr($id, 4), $p['snooze']);
            }
        }

        return $p;
    }

    /**
     * Handler for check-recent requests which are accidentally sent to calendar
     */
    public function check_recent()
    {
        // NOP
        $this->rc->output->send();
    }

    /**
     * Hook triggered when a contact is saved
     */
    public function contact_update($p)
    {
        // clear birthdays calendar cache
        if (!empty($p['record']['birthday'])) {
            $cache = $this->rc->get_cache('calendar.birthdays', 'db');
            $cache->remove();
        }
    }

    /**
     *
     */
    public function import_events()
    {
        // Upload progress update
        if (!empty($_GET['_progress'])) {
            $this->rc->upload_progress();
        }

        @set_time_limit(0);

        // process uploaded file if there is no error
        $err = $_FILES['_data']['error'];

        if (!$err && !empty($_FILES['_data']['tmp_name'])) {
            $calendar   = rcube_utils::get_input_value('calendar', rcube_utils::INPUT_GPC);
            $rangestart = !empty($_REQUEST['_range']) ? date_create("now -" . intval($_REQUEST['_range']) . " months") : 0;

            // extract zip file
            if ($_FILES['_data']['type'] == 'application/zip') {
                $count = 0;
                if (class_exists('ZipArchive', false)) {
                    $zip = new ZipArchive();
                    if ($zip->open($_FILES['_data']['tmp_name'])) {
                        $randname = uniqid('zip-' . session_id(), true);
                        $tmpdir = slashify($this->rc->config->get('temp_dir', sys_get_temp_dir())) . $randname;
                        mkdir($tmpdir, 0700);

                        // extract each ical file from the archive and import it
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $filename = $zip->getNameIndex($i);
                            if (preg_match('/\.ics$/i', $filename)) {
                                $tmpfile = $tmpdir . '/' . basename($filename);
                                if (copy('zip://' . $_FILES['_data']['tmp_name'] . '#' . $filename, $tmpfile)) {
                                    $count += $this->import_from_file($tmpfile, $calendar, $rangestart, $errors);
                                    unlink($tmpfile);
                                }
                            }
                        }

                        rmdir($tmpdir);
                        $zip->close();
                    } else {
                        $errors = 1;
                        $msg = 'Failed to open zip file.';
                    }
                } else {
                    $errors = 1;
                    $msg = 'Zip files are not supported for import.';
                }
            } else {
                // attempt to import teh uploaded file directly
                $count = $this->import_from_file($_FILES['_data']['tmp_name'], $calendar, $rangestart, $errors);
            }

            if ($count) {
                $this->rc->output->command('display_message', $this->gettext(['name' => 'importsuccess', 'vars' => ['nr' => $count]]), 'confirmation');
                $this->rc->output->command('plugin.import_success', ['source' => $calendar, 'refetch' => true]);
            } elseif (empty($errors)) {
                $this->rc->output->command('display_message', $this->gettext('importnone'), 'notice');
                $this->rc->output->command('plugin.import_success', ['source' => $calendar]);
            } else {
                $this->rc->output->command('plugin.import_error', ['message' => $this->gettext('importerror')
                    . (!empty($msg) ? ': ' . $msg : '')]);
            }
        } else {
            if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                $max = $this->rc->show_bytes(parse_bytes(ini_get('upload_max_filesize')));
                $msg = $this->rc->gettext(['name' => 'filesizeerror', 'vars' => ['size' => $max]]);
            } else {
                $msg = $this->rc->gettext('fileuploaderror');
            }

            $this->rc->output->command('plugin.import_error', ['message' => $msg]);
        }

        $this->rc->output->send('iframe');
    }

    /**
     * Helper function to parse and import a single .ics file
     */
    private function import_from_file($filepath, $calendar, $rangestart, &$errors)
    {
        $user_email = $this->rc->user->get_username();
        $ical       = $this->get_ical();
        $errors     = !$ical->fopen($filepath);

        $count = $i = 0;

        foreach ($ical as $event) {
            // keep the browser connection alive on long import jobs
            if (++$i > 100 && $i % 100 == 0) {
                echo "<!-- -->";
                ob_flush();
            }

            // TODO: correctly handle recurring events which start before $rangestart
            if ($rangestart && $event['end'] < $rangestart
                && (empty($event['recurrence']) || (!empty($event['recurrence']['until']) && $event['recurrence']['until'] < $rangestart))
            ) {
                continue;
            }

            $event['_owner']   = $user_email;
            $event['calendar'] = $calendar;

            if ($this->driver->new_event($event)) {
                $count++;
            } else {
                $errors++;
            }
        }

        return $count;
    }

    /**
     * Construct the ics file for exporting events to iCalendar format;
     */
    public function export_events($terminate = true)
    {
        $start       = rcube_utils::get_input_value('start', rcube_utils::INPUT_GET);
        $end         = rcube_utils::get_input_value('end', rcube_utils::INPUT_GET);
        $event_id    = rcube_utils::get_input_value('id', rcube_utils::INPUT_GET);
        $attachments = rcube_utils::get_input_value('attachments', rcube_utils::INPUT_GET);
        $calid       = rcube_utils::get_input_value('source', rcube_utils::INPUT_GET);

        if (!isset($start)) {
            $start = 'today -1 year';
        }
        if (!is_numeric($start)) {
            $start = strtotime($start . ' 00:00:00');
        }
        if (!$end) {
            $end = 'today +10 years';
        }
        if (!is_numeric($end)) {
            $end = strtotime($end . ' 23:59:59');
        }

        $filename  = $calid;
        $calendars = $this->driver->list_calendars();
        $events    = [];

        if (!empty($calendars[$calid])) {
            $filename = !empty($calendars[$calid]['name']) ? $calendars[$calid]['name'] : $calid;
            $filename = asciiwords(html_entity_decode($filename));  // to 7bit ascii

            if (!empty($event_id)) {
                if ($event = $this->driver->get_event(['calendar' => $calid, 'id' => $event_id], 0, true)) {
                    if (!empty($event['recurrence_id'])) {
                        $event = $this->driver->get_event(['calendar' => $calid, 'id' => $event['recurrence_id']], 0, true);
                    }

                    $events   = [$event];
                    $filename = asciiwords($event['title']);

                    if (empty($filename)) {
                        $filename = 'event';
                    }
                }
            } else {
                $events = $this->driver->load_events($start, $end, null, $calid, 0);
                if (empty($filename)) {
                    $filename = $calid;
                }
            }
        }

        header("Content-Type: text/calendar");
        header("Content-Disposition: inline; filename=" . $filename . '.ics');

        $this->get_ical()->export($events, '', true, $attachments ? [$this->driver, 'get_attachment_body'] : null);

        if ($terminate) {
            exit;
        }
    }

    /**
     * Handler for iCal feed requests
     */
    public function ical_feed_export()
    {
        $session_exists = !empty($_SESSION['user_id']);

        // process HTTP auth info
        if (!empty($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
            $_POST['_user'] = $_SERVER['PHP_AUTH_USER']; // used for rcmail::autoselect_host()
            $auth = $this->rc->plugins->exec_hook('authenticate', [
                'host' => $this->rc->autoselect_host(),
                'user' => trim($_SERVER['PHP_AUTH_USER']),
                'pass' => $_SERVER['PHP_AUTH_PW'],
                'cookiecheck' => true,
                'valid'       => true,
            ]);

            if ($auth['valid'] && !$auth['abort']) {
                $this->rc->login($auth['user'], $auth['pass'], $auth['host']);
            }
        }

        // require HTTP auth
        if (empty($_SESSION['user_id'])) {
            header('WWW-Authenticate: Basic realm="Kolab Calendar"');
            header('HTTP/1.0 401 Unauthorized');
            exit;
        }

        // decode calendar feed hash
        $format  = 'ics';
        $calhash = rcube_utils::get_input_value('_cal', rcube_utils::INPUT_GET);

        if (preg_match(($suff_regex = '/\.([a-z0-9]{3,5})$/i'), $calhash, $m)) {
            $format  = strtolower($m[1]);
            $calhash = preg_replace($suff_regex, '', $calhash);
        }

        if (!strpos($calhash, ':')) {
            $calhash = base64_decode($calhash);
        }

        [$user, $_GET['source']] = explode(':', $calhash, 2);

        // sanity check user
        if ($this->rc->user->get_username() == $user) {
            $this->setup();
            $this->load_driver();
            $this->export_events(false);
        } else {
            header('HTTP/1.0 404 Not Found');
        }

        // don't save session data
        if (!$session_exists) {
            session_destroy();
        }

        exit;
    }

    /**
     *
     */
    public function load_settings()
    {
        $this->lib->load_settings();
        $this->defaults += $this->lib->defaults;

        $settings = [];

        // configuration
        $settings['default_view']     = (string) $this->rc->config->get('calendar_default_view', $this->defaults['calendar_default_view']);
        $settings['timeslots']        = (int) $this->rc->config->get('calendar_timeslots', $this->defaults['calendar_timeslots']);
        $settings['first_day']        = (int) $this->rc->config->get('calendar_first_day', $this->defaults['calendar_first_day']);
        $settings['first_hour']       = (int) $this->rc->config->get('calendar_first_hour', $this->defaults['calendar_first_hour']);
        $settings['work_start']       = (int) $this->rc->config->get('calendar_work_start', $this->defaults['calendar_work_start']);
        $settings['work_end']         = (int) $this->rc->config->get('calendar_work_end', $this->defaults['calendar_work_end']);
        $settings['agenda_range']     = (int) $this->rc->config->get('calendar_agenda_range', $this->defaults['calendar_agenda_range']);
        $settings['event_coloring']   = (int) $this->rc->config->get('calendar_event_coloring', $this->defaults['calendar_event_coloring']);
        $settings['time_indicator']   = (int) $this->rc->config->get('calendar_time_indicator', $this->defaults['calendar_time_indicator']);
        $settings['invite_shared']    = (int) $this->rc->config->get('calendar_allow_invite_shared', $this->defaults['calendar_allow_invite_shared']);
        $settings['itip_notify']      = (int) $this->rc->config->get('calendar_itip_send_option', $this->defaults['calendar_itip_send_option']);
        $settings['show_weekno']      = (int) $this->rc->config->get('calendar_show_weekno', $this->defaults['calendar_show_weekno']);
        $settings['default_calendar'] = $this->rc->config->get('calendar_default_calendar');
        $settings['invitation_calendars'] = (bool) $this->rc->config->get('kolab_invitation_calendars', false);

        // 'table' view has been replaced by 'list' view
        if ($settings['default_view'] == 'table') {
            $settings['default_view'] = 'list';
        }

        // get user identity to create default attendee
        if ($this->ui->screen == 'calendar') {
            foreach ($this->rc->user->list_emails() as $rec) {
                if (empty($identity)) {
                    $identity = $rec;
                }

                $identity['emails'][] = $rec['email'];
                $settings['identities'][$rec['identity_id']] = $rec['email'];
            }

            $identity['emails'][] = $this->rc->user->get_username();
            $identity['ownedResources'] = $this->owned_resources_emails();
            $settings['identity'] = [
                'name'   => $identity['name'],
                'email'  => strtolower($identity['email']),
                'emails' => ';' . strtolower(implode(';', $identity['emails'])),
                'ownedResources' => ';' . strtolower(implode(';', $identity['ownedResources'])),
            ];
        }

        // freebusy token authentication URL
        if (($url = $this->rc->config->get('calendar_freebusy_session_auth_url'))
            && ($uniqueid = $this->rc->config->get('kolab_uniqueid'))
        ) {
            if ($url === true) {
                $url = '/freebusy';
            }
            $url = rtrim(rcube_utils::resolve_url($url), '/ ');
            $url .= '/' . urlencode($this->rc->get_user_name());
            $url .= '/' . urlencode($uniqueid);

            $settings['freebusy_url'] = $url;
        }

        return $settings;
    }

    /**
     * Encode events as JSON
     *
     * @param array $events Events as array
     * @param bool  $addcss Add CSS class names according to calendar and categories
     *
     * @return string JSON encoded events
     */
    public function encode($events, $addcss = false)
    {
        $json = [];
        foreach ($events as $event) {
            $json[] = $this->_client_event($event, $addcss);
        }
        return rcube_output::json_serialize($json);
    }

    /**
     * Convert an event object to be used on the client
     */
    private function _client_event($event, $addcss = false)
    {
        // compose a human readable strings for alarms_text and recurrence_text
        if (!empty($event['valarms'])) {
            $event['alarms_text'] = libcalendaring::alarms_text($event['valarms']);
            $event['valarms'] = libcalendaring::to_client_alarms($event['valarms']);
        }

        if (!empty($event['recurrence'])) {
            $event['recurrence_text'] = $this->lib->recurrence_text($event['recurrence']);
            $event['recurrence'] = $this->lib->to_client_recurrence($event['recurrence'], $event['allday']);
            unset($event['recurrence_date']);
        }

        if (!empty($event['attachments'])) {
            foreach ($event['attachments'] as $k => $attachment) {
                $event['attachments'][$k]['classname'] = rcube_utils::file2class($attachment['mimetype'], $attachment['name']);

                unset($event['attachments'][$k]['data'], $event['attachments'][$k]['content']);

                if (empty($attachment['id'])) {
                    $event['attachments'][$k]['id'] = $k;
                }
            }
        }

        // convert link URIs references into structs
        if (array_key_exists('links', $event)) {
            foreach ((array) $event['links'] as $i => $link) {
                if (strpos($link, 'imap://') === 0 && ($msgref = $this->driver->get_message_reference($link))) {
                    $event['links'][$i] = $msgref;
                }
            }
        }

        // check for organizer in attendees list
        $organizer = null;
        if (!empty($event['attendees'])) {
            foreach ((array) $event['attendees'] as $i => $attendee) {
                if (!empty($attendee['role']) && $attendee['role'] == 'ORGANIZER') {
                    $organizer = $attendee;
                }
                if (!empty($attendee['status']) && $attendee['status'] == 'DELEGATED' && empty($attendee['rsvp'])) {
                    $event['attendees'][$i]['noreply'] = true;
                } else {
                    unset($event['attendees'][$i]['noreply']);
                }
            }
        }

        if ($organizer === null && !empty($event['organizer'])) {
            $organizer = $event['organizer'];
            $organizer['role'] = 'ORGANIZER';
            if (!isset($event['attendees']) || !is_array($event['attendees'])) {
                $event['attendees'] = [$organizer];
            }
        }

        // Convert HTML description into plain text
        if ($this->is_html($event)) {
            $h2t = new rcube_html2text($event['description'], false, true, 0);
            $event['description'] = trim($h2t->get_text());
        }

        // mapping url => vurl, allday => allDay because of the fullcalendar client script
        $event['vurl']   = $event['url'] ?? null;
        $event['allDay'] = !empty($event['allday']);
        unset($event['url']);
        unset($event['allday']);

        $event['className'] = !empty($event['className']) ? explode(' ', $event['className']) : [];

        if ($event['allDay']) {
            $event['end'] = $event['end']->add(new DateInterval('P1D'));
        }

        if (!empty($_GET['mode']) && $_GET['mode'] == 'print') {
            $event['editable'] = false;
        }

        return [
            '_id'     => $event['calendar'] . ':' . $event['id'],  // unique identifier for fullcalendar
            'start'   => $this->lib->adjust_timezone($event['start'], $event['allDay'])->format('c'),
            'end'     => $this->lib->adjust_timezone($event['end'], $event['allDay'])->format('c'),
            // 'changed' might be empty for event recurrences (Bug #2185)
            'changed' => !empty($event['changed']) ? $this->lib->adjust_timezone($event['changed'])->format('c') : null,
            'created' => !empty($event['created']) ? $this->lib->adjust_timezone($event['created'])->format('c') : null,
            'title'       => strval($event['title'] ?? null),
            'description' => strval($event['description'] ?? null),
            'location'    => strval($event['location'] ?? null),
        ] + $event;
    }

    /**
     * Generate a unique identifier for an event
     */
    public function generate_uid()
    {
        return strtoupper(md5(time() . uniqid(rand())) . '-' . substr(md5($this->rc->user->get_username()), 0, 16));
    }

    /**
     * TEMPORARY: generate random event data for testing
     * Create events by opening http://<roundcubeurl>/?_task=calendar&_action=randomdata&_num=500&_date=2014-08-01&_dev=120
     */
    public function generate_randomdata()
    {
        @set_time_limit(0);

        $num   = !empty($_REQUEST['_num']) ? intval($_REQUEST['_num']) : 100;
        $date  = !empty($_REQUEST['_date']) ? $_REQUEST['_date'] : 'now';
        $dev   = !empty($_REQUEST['_dev']) ? $_REQUEST['_dev'] : 30;
        $cats  = array_keys($this->driver->list_categories());
        $cals  = $this->driver->list_calendars(calendar_driver::FILTER_ACTIVE);
        $count = 0;

        while ($count++ < $num) {
            $spread   = intval($dev) * 86400; // days
            $refdate  = strtotime($date);
            $start    = round(($refdate + rand(-$spread, $spread)) / 600) * 600;
            $duration = round(rand(30, 360) / 30) * 30 * 60;
            $allday   = rand(0, 20) > 18;
            $alarm    = rand(-30, 12) * 5;
            $fb       = rand(0, 2);

            if (date('G', $start) > 23) {
                $start -= 3600;
            }

            if ($allday) {
                $start    = strtotime(date('Y-m-d 00:00:00', $start));
                $duration = 86399;
            }

            $title = '';
            $len = rand(2, 12);
            $words = explode(" ", "The Hough transform is named after Paul Hough who patented the method in 1962."
                . " It is a technique which can be used to isolate features of a particular shape within an image."
                . " Because it requires that the desired features be specified in some parametric form, the classical"
                . " Hough transform is most commonly used for the de- tection of regular curves such as lines, circles,"
                . " ellipses, etc. A generalized Hough transform can be employed in applications where a simple"
                . " analytic description of a feature(s) is not possible. Due to the computational complexity of"
                . " the generalized Hough algorithm, we restrict the main focus of this discussion to the classical"
                . " Hough transform. Despite its domain restrictions, the classical Hough transform (hereafter"
                . " referred to without the classical prefix ) retains many applications, as most manufac- tured"
                . " parts (and many anatomical parts investigated in medical imagery) contain feature boundaries"
                . " which can be described by regular curves. The main advantage of the Hough transform technique"
                . " is that it is tolerant of gaps in feature boundary descriptions and is relatively unaffected"
                . " by image noise.");
            // $chars = "!# abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ 1234567890";
            for ($i = 0; $i < $len; $i++) {
                $title .= $words[rand(0, count($words) - 1)] . " ";
            }

            $this->driver->new_event([
                'uid'        => $this->generate_uid(),
                'start'      => new DateTime('@' . $start),
                'end'        => new DateTime('@' . ($start + $duration)),
                'allday'     => $allday,
                'title'      => rtrim($title),
                'free_busy'  => $fb == 2 ? 'outofoffice' : ($fb ? 'busy' : 'free'),
                'categories' => $cats[array_rand($cats)],
                'calendar'   => array_rand($cals),
                'alarms'     => $alarm > 0 ? "-{$alarm}M:DISPLAY" : '',
                'priority'   => rand(0, 9),
            ]);
        }

        $this->rc->output->redirect('');
    }

    /**
     * Handler for attachments upload
     */
    public function attachment_upload()
    {
        $handler = new kolab_attachments_handler();
        $handler->attachment_upload(self::SESSION_KEY, 'cal-');
    }

    /**
     * Handler for attachments download/displaying
     */
    public function attachment_get()
    {
        $handler = new kolab_attachments_handler();

        // show loading page
        if (!empty($_GET['_preload'])) {
            return $handler->attachment_loading_page();
        }

        $event_id = rcube_utils::get_input_value('_event', rcube_utils::INPUT_GPC);
        $calendar = rcube_utils::get_input_value('_cal', rcube_utils::INPUT_GPC);
        $id       = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
        $rev      = rcube_utils::get_input_value('_rev', rcube_utils::INPUT_GPC);

        $event = ['id' => $event_id, 'calendar' => $calendar, 'rev' => $rev];

        if ($calendar == '--invitation--itip') {
            $uid  = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GPC);
            $part = rcube_utils::get_input_value('_part', rcube_utils::INPUT_GPC);
            $mbox = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GPC);

            $event      = $this->lib->mail_get_itip_object($mbox, $uid, $part, 'event');
            $attachment = $event['attachments'][$id];
            $attachment['body'] = &$attachment['data'];
        } else {
            $attachment = $this->driver->get_attachment($id, $event);
        }

        // show part page
        if (!empty($_GET['_frame'])) {
            $handler->attachment_page($attachment);
        }
        // deliver attachment content
        elseif ($attachment) {
            if ($calendar != '--invitation--itip') {
                $attachment['body'] = $this->driver->get_attachment_body($id, $event);
            }

            $handler->attachment_get($attachment);
        }

        // if we arrive here, the requested part was not found
        header('HTTP/1.1 404 Not Found');
        exit;
    }

    /**
     * Determine whether the given event description is HTML formatted
     */
    private function is_html($event)
    {
        // check for opening and closing <html> or <body> tags
        return !empty($event['description'])
            && preg_match('/<(html|body)(\s+[a-z]|>)/', $event['description'], $m)
            && strpos($event['description'], '</' . $m[1] . '>') > 0;
    }

    /**
     * Prepares new/edited event properties before save
     */
    private function write_preprocess(&$event, $action)
    {
        // Remove double timezone specification (T2313)
        $event['start'] = preg_replace('/\s*\(.*\)/', '', $event['start'] ?? '');
        $event['end']   = preg_replace('/\s*\(.*\)/', '', $event['end'] ?? '');

        // convert dates into DateTime objects in user's current timezone
        $event['start']  = new DateTime($event['start'], $this->timezone);
        $event['end']    = new DateTime($event['end'], $this->timezone);
        $event['allday'] = !empty($event['allDay']);
        unset($event['allDay']);

        // start/end is all we need for 'move' action (#1480)
        if ($action == 'move') {
            return true;
        }

        // convert the submitted recurrence settings
        if (!empty($event['recurrence'])) {
            $event['recurrence'] = $this->lib->from_client_recurrence($event['recurrence'], $event['start']);

            // align start date with the first occurrence
            if (!empty($event['recurrence']) && !empty($event['syncstart'])
                && (empty($event['_savemode']) || $event['_savemode'] == 'all')
            ) {
                $next = $this->find_first_occurrence($event);

                if (!$next) {
                    $this->rc->output->show_message('calendar.recurrenceerror', 'error');
                    return false;
                } elseif ($event['start'] != $next) {
                    $diff = $event['start']->diff($event['end'], true);

                    $event['start'] = $next;
                    $event['end']   = clone $next;
                    $event['end']->add($diff);
                }
            }
        }

        // convert the submitted alarm values
        if (!empty($event['valarms'])) {
            $event['valarms'] = libcalendaring::from_client_alarms($event['valarms']);
        }

        $eventid = 'cal-' . (!empty($event['id']) ? $event['id'] : 'new');
        $handler = new kolab_attachments_handler();
        $event['attachments'] = $handler->attachments_set(self::SESSION_KEY, $eventid, $event['attachments'] ?? []);

        // convert link references into simple URIs
        if (array_key_exists('links', $event)) {
            $event['links'] = array_map(
                function ($link) {
                    return is_array($link) ? $link['uri'] : strval($link);
                },
                (array) $event['links']
            );
        }

        // check for organizer in attendees
        if ($action == 'new' || $action == 'edit') {
            if (empty($event['attendees'])) {
                $event['attendees'] = [];
            }

            $emails = $this->get_user_emails();
            $organizer = $owner = false;

            foreach ((array) $event['attendees'] as $i => $attendee) {
                if ($attendee['role'] == 'ORGANIZER') {
                    $organizer = $i;
                }
                if (!empty($attendee['email']) && in_array(strtolower($attendee['email']), $emails)) {
                    $owner = $i;
                }
                if (!isset($attendee['rsvp'])) {
                    $event['attendees'][$i]['rsvp'] = true;
                } elseif (is_string($attendee['rsvp'])) {
                    $event['attendees'][$i]['rsvp'] = $attendee['rsvp'] == 'true' || $attendee['rsvp'] == '1';
                }
            }

            if (!empty($event['_identity'])) {
                $identity = $this->rc->user->get_identity($event['_identity']);
            }

            // set new organizer identity
            if ($organizer !== false && !empty($identity)) {
                $event['attendees'][$organizer]['name']  = $identity['name'];
                $event['attendees'][$organizer]['email'] = $identity['email'];
            }
            // set owner as organizer if yet missing
            elseif ($organizer === false && $owner !== false) {
                $event['attendees'][$owner]['role'] = 'ORGANIZER';
                unset($event['attendees'][$owner]['rsvp']);
            }
            // fallback to the selected identity
            elseif ($organizer === false && !empty($identity)) {
                $event['attendees'][] = [
                    'role'  => 'ORGANIZER',
                    'name'  => $identity['name'],
                    'email' => $identity['email'],
                ];
            }
        }

        // mapping url => vurl because of the fullcalendar client script
        if (array_key_exists('vurl', $event)) {
            $event['url'] = $event['vurl'];
            unset($event['vurl']);
        }

        return true;
    }

    /**
     * Releases some resources after successful event save
     */
    private function cleanup_event(&$event)
    {
        $handler = new kolab_attachments_handler();
        $handler->attachments_cleanup(self::SESSION_KEY);
    }

    /**
     * Send out an invitation/notification to all event attendees
     */
    private function notify_attendees($event, $old, $action = 'edit', $comment = null, $rsvp = null)
    {
        $is_cancelled = $action == 'remove'
            || (!empty($event['status']) && $event['status'] == 'CANCELLED' && ($old['status'] ?? '') != $event['status']);

        $event['cancelled'] = $is_cancelled;

        if ($rsvp === null) {
            $rsvp = !$old || ($event['sequence'] ?? 0) > ($old['sequence'] ?? 0);
        }

        $itip        = $this->load_itip();
        $emails      = $this->get_user_emails();
        $itip_notify = (int) $this->rc->config->get('calendar_itip_send_option', $this->defaults['calendar_itip_send_option']);

        // add comment to the iTip attachment
        $event['comment'] = $comment;

        // set a valid recurrence-id if this is a recurrence instance
        libcalendaring::identify_recurrence_instance($event);

        // compose multipart message using PEAR:Mail_Mime
        $method  = $action == 'remove' ? 'CANCEL' : 'REQUEST';
        $message = $itip->compose_itip_message($event, $method, $rsvp);

        // list existing attendees from $old event
        $old_attendees = [];
        if (!empty($old['attendees'])) {
            foreach ((array) $old['attendees'] as $attendee) {
                $old_attendees[] = $attendee['email'];
            }
        }

        // send to every attendee
        $sent    = 0;
        $current = [];
        foreach ((array) $event['attendees'] as $attendee) {
            // skip myself for obvious reasons
            if (empty($attendee['email']) || in_array(strtolower($attendee['email']), $emails)) {
                continue;
            }

            $current[] = strtolower($attendee['email']);

            // skip if notification is disabled for this attendee
            if (!empty($attendee['noreply']) && $itip_notify & 2) {
                continue;
            }

            // skip if this attendee has delegated and set RSVP=FALSE
            if ($attendee['status'] == 'DELEGATED' && $attendee['rsvp'] === false) {
                continue;
            }

            // which template to use for mail text
            $is_new   = !in_array($attendee['email'], $old_attendees);
            $is_rsvp  = $is_new || $event['sequence'] > $old['sequence'];
            $bodytext = $is_cancelled ? 'eventcancelmailbody' : ($is_new ? 'invitationmailbody' : 'eventupdatemailbody');
            $subject  = $is_cancelled ? 'eventcancelsubject' : ($is_new ? 'invitationsubject' : ($event['title'] ? 'eventupdatesubject' : 'eventupdatesubjectempty'));

            $event['comment'] = $comment;

            // finally send the message
            if ($itip->send_itip_message($event, $method, $attendee, $subject, $bodytext, $message, $is_rsvp)) {
                $sent++;
            } else {
                $sent = -100;
            }
        }

        // TODO: on change of a recurring (main) event, also send updates to differing attendess of recurrence exceptions

        // send CANCEL message to removed attendees
        if (!empty($old['attendees'])) {
            foreach ($old['attendees'] as $attendee) {
                if ($attendee['role'] == 'ORGANIZER'
                    || empty($attendee['email'])
                    || in_array(strtolower($attendee['email']), $current)
                ) {
                    continue;
                }

                $vevent = $old;
                $vevent['cancelled'] = $is_cancelled;
                $vevent['attendees'] = [$attendee];
                $vevent['comment']   = $comment;

                if ($itip->send_itip_message($vevent, 'CANCEL', $attendee, 'eventcancelsubject', 'eventcancelmailbody')) {
                    $sent++;
                } else {
                    $sent = -100;
                }
            }
        }

        return $sent;
    }

    /**
     * Echo simple free/busy status text for the given user and time range
     */
    public function freebusy_status()
    {
        $email = rcube_utils::get_input_value('email', rcube_utils::INPUT_GPC);
        $start = $this->input_timestamp('start', rcube_utils::INPUT_GPC);
        $end   = $this->input_timestamp('end', rcube_utils::INPUT_GPC);

        if (!$start) {
            $start = time();
        }
        if (!$end) {
            $end = $start + 3600;
        }

        $status = 'UNKNOWN';
        $fbtypemap = [
            calendar::FREEBUSY_UNKNOWN   => 'UNKNOWN',
            calendar::FREEBUSY_FREE      => 'FREE',
            calendar::FREEBUSY_BUSY      => 'BUSY',
            calendar::FREEBUSY_TENTATIVE => 'TENTATIVE',
            calendar::FREEBUSY_OOF       => 'OUT-OF-OFFICE',
        ];

        // if the backend has free-busy information
        $fblist = $this->driver->get_freebusy_list($email, $start, $end);

        if (is_array($fblist)) {
            $status = 'FREE';

            foreach ($fblist as $slot) {
                [$from, $to, $type] = $slot;
                if ($from < $end && $to > $start) {
                    $status = isset($type) && !empty($fbtypemap[$type]) ? $fbtypemap[$type] : 'BUSY';
                    break;
                }
            }
        }

        // let this information be cached for 5min
        $this->rc->output->future_expire_header(300);

        echo $status;
        exit;
    }

    /**
     * Return a list of free/busy time slots within the given period
     * Echo data in JSON encoding
     */
    public function freebusy_times()
    {
        $email = rcube_utils::get_input_value('email', rcube_utils::INPUT_GPC);
        $start = $this->input_timestamp('start', rcube_utils::INPUT_GPC);
        $end   = $this->input_timestamp('end', rcube_utils::INPUT_GPC);
        $interval  = intval(rcube_utils::get_input_value('interval', rcube_utils::INPUT_GPC));
        $strformat = $interval > 60 ? 'Ymd' : 'YmdHis';

        if (!$start) {
            $start = time();
        }
        if (!$end) {
            $end = $start + 86400 * 30;
        }
        if (!$interval) {
            $interval = 60;
        }  // 1 hour

        $fblist = $this->driver->get_freebusy_list($email, $start, $end);
        $slots  = '';

        // prepare freebusy list before use (for better performance)
        if (is_array($fblist)) {
            foreach ($fblist as $idx => $slot) {
                [$from, $to, ] = $slot;

                // check for possible all-day times
                if (gmdate('His', $from) == '000000' && gmdate('His', $to) == '235959') {
                    // shift into the user's timezone for sane matching
                    $fblist[$idx][0] -= $this->gmt_offset;
                    $fblist[$idx][1] -= $this->gmt_offset;
                }
            }
        }

        // build a list from $start till $end with blocks representing the fb-status
        $t_end = 0;
        for ($s = 0, $t = $start; $t <= $end; $s++) {
            $t_end = $t + $interval * 60;
            $dt = new DateTime('@' . $t);
            $dt->setTimezone($this->timezone);

            // determine attendee's status
            if (is_array($fblist)) {
                $status = self::FREEBUSY_FREE;

                foreach ($fblist as $slot) {
                    [$from, $to, $type] = $slot;

                    if ($from < $t_end && $to > $t) {
                        $status = $type ?? self::FREEBUSY_BUSY;
                        if ($status == self::FREEBUSY_BUSY) {
                            // can't get any worse :-)
                            break;
                        }
                    }
                }
            } else {
                $status = self::FREEBUSY_UNKNOWN;
            }

            // use most compact format, assume $status is one digit/character
            $slots .= $status;
            $t = $t_end;
        }

        $dts = new DateTime('@' . $start);
        $dts->setTimezone($this->timezone);
        $dte = new DateTime('@' . $t_end);
        $dte->setTimezone($this->timezone);

        // let this information be cached for 5min
        $this->rc->output->future_expire_header(300);

        echo rcube_output::json_serialize([
            'email' => $email,
            'start' => $dts->format('c'),
            'end'   => $dte->format('c'),
            'interval' => $interval,
            'slots' => $slots,
        ]);
        exit;
    }

    /**
     * Handler for printing calendars
     */
    public function print_view()
    {
        $title = $this->gettext('print');

        $view = rcube_utils::get_input_value('view', rcube_utils::INPUT_GPC);
        if (!in_array($view, ['agendaWeek', 'agendaDay', 'month', 'list'])) {
            $view = 'agendaDay';
        }

        $this->rc->output->set_env('view', $view);

        if ($date = rcube_utils::get_input_value('date', rcube_utils::INPUT_GPC)) {
            $this->rc->output->set_env('date', $date);
        }

        if ($range = rcube_utils::get_input_value('range', rcube_utils::INPUT_GPC)) {
            $this->rc->output->set_env('listRange', intval($range));
        }

        if ($search = rcube_utils::get_input_value('search', rcube_utils::INPUT_GPC)) {
            $this->rc->output->set_env('search', $search);
            $title .= ' "' . $search . '"';
        }

        // Add JS to the page
        $this->ui->addJS();

        $this->register_handler('plugin.calendar_css', [$this->ui, 'calendar_css']);
        $this->register_handler('plugin.calendar_list', [$this->ui, 'calendar_list']);

        $this->rc->output->set_pagetitle($title);
        $this->rc->output->send('calendar.print');
    }

    /**
     * Compare two event objects and return differing properties
     *
     * @param array $a Event A
     * @param array $b Event B
     *
     * @return array List of differing event properties
     */
    public static function event_diff($a, $b)
    {
        $diff   = [];
        $ignore = ['changed' => 1, 'attachments' => 1];

        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            if (empty($ignore[$key]) && $key[0] != '_') {
                $av = $a[$key] ?? null;
                $bv = $b[$key] ?? null;

                if ($av != $bv) {
                    $diff[] = $key;
                }
            }
        }

        // only compare number of attachments
        $ac = !empty($a['attachments']) ? count($a['attachments']) : 0;
        $bc = !empty($b['attachments']) ? count($b['attachments']) : 0;

        if ($ac != $bc) {
            $diff[] = 'attachments';
        }

        return $diff;
    }

    /**
     * Update attendee properties on the given event object
     *
     * @param array $event     The event object to be altered
     * @param array $attendees List of hash arrays each represeting an updated/added attendee
     * @param array $removed   List of attendees' addresses to remove
     */
    public static function merge_attendee_data(&$event, $attendees, $removed = [])
    {
        if (!empty($attendees) && !is_array($attendees[0])) {
            $attendees = [$attendees];
        }

        foreach ($attendees as $attendee) {
            $found = false;

            foreach ($event['attendees'] as $i => $candidate) {
                if ($candidate['email'] == $attendee['email']) {
                    $event['attendees'][$i] = $attendee;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $event['attendees'][] = $attendee;
            }
        }

        // filter out removed attendees
        if (!empty($removed)) {
            $event['attendees'] = array_filter($event['attendees'], function ($attendee) use ($removed) {
                return !in_array($attendee['email'], $removed);
            });
        }
    }

    /****  Resource management functions  ****/

    /**
     * Getter for the configured implementation of the resource directory interface
     */
    private function resources_directory()
    {
        if (!empty($this->resources_dir)) {
            return $this->resources_dir;
        }

        if ($driver_name = $this->rc->config->get('calendar_resources_driver')) {
            $driver_class = 'resources_driver_' . $driver_name;

            require_once $this->home . '/drivers/resources_driver.php';
            require_once $this->home . '/drivers/' . $driver_name . '/' . $driver_class . '.php';

            $this->resources_dir = new $driver_class($this);
        }

        return $this->resources_dir;
    }

    /**
     * Handler for resoruce autocompletion requests
     */
    public function resources_autocomplete()
    {
        $search  = rcube_utils::get_input_value('_search', rcube_utils::INPUT_GPC, true);
        $sid     = rcube_utils::get_input_value('_reqid', rcube_utils::INPUT_GPC);
        $maxnum  = (int)$this->rc->config->get('autocomplete_max', 15);
        $results = [];

        if ($directory = $this->resources_directory()) {
            foreach ($directory->load_resources($search, $maxnum) as $rec) {
                $results[]  = [
                    'name'  => $rec['name'],
                    'email' => $rec['email'],
                    'type'  => $rec['_type'],
                ];
            }
        }

        $this->rc->output->command('ksearch_query_results', $results, $search, $sid);
        $this->rc->output->send();
    }

    /**
     * Handler for load-requests for resource data
     */
    public function resources_list()
    {
        $data = [];

        if ($directory = $this->resources_directory()) {
            foreach ($directory->load_resources() as $rec) {
                $data[] = $rec;
            }
        }

        $this->rc->output->command('plugin.resource_data', $data);
        $this->rc->output->send();
    }

    /**
     * Handler for requests loading resource owner information
     */
    public function resources_owner()
    {
        if ($directory = $this->resources_directory()) {
            $id   = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
            $data = $directory->get_resource_owner($id);
        }

        $this->rc->output->command('plugin.resource_owner', $data ?? null);
        $this->rc->output->send();
    }

    /**
     * Deliver event data for a resource's calendar
     */
    public function resources_calendar()
    {
        $events = [];

        if ($directory = $this->resources_directory()) {
            $id    = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC);
            $start = $this->input_timestamp('start', rcube_utils::INPUT_GET);
            $end   = $this->input_timestamp('end', rcube_utils::INPUT_GET);

            $events = $directory->get_resource_calendar($id, $start, $end);
        }

        echo $this->encode($events);
        exit;
    }

    /**
     * List email addressed of owned resources
     */
    private function owned_resources_emails()
    {
        $results = [];
        if ($directory = $this->resources_directory()) {
            foreach ($directory->load_resources($_SESSION['kolab_dn'], 5000, 'owner') as $rec) {
                $results[] = $rec['email'];
            }
        }
        return $results;
    }


    /****  Event invitation plugin hooks ****/

    /**
     * Find an event in user calendars
     */
    protected function find_event($event, &$mode)
    {
        $this->load_driver();

        // We search for writeable calendars in personal namespace by default
        $mode   = calendar_driver::FILTER_WRITEABLE | calendar_driver::FILTER_PERSONAL;
        $result = $this->driver->get_event($event, $mode);
        // ... now check shared folders if not found
        if (!$result) {
            $result = $this->driver->get_event($event, calendar_driver::FILTER_WRITEABLE | calendar_driver::FILTER_SHARED);
            if ($result) {
                $mode |= calendar_driver::FILTER_SHARED;
            }
        }

        return $result;
    }

    /**
     * Handler for calendar/itip-status requests
     */
    public function event_itip_status()
    {
        $data = rcube_utils::get_input_value('data', rcube_utils::INPUT_POST, true);

        $this->load_driver();

        // find local copy of the referenced event (in personal namespace)
        $existing  = $this->find_event($data, $mode);
        $is_shared = $mode & calendar_driver::FILTER_SHARED;
        $itip      = $this->load_itip();
        $response  = $itip->get_itip_status($data, $existing);
        $calendars = null;

        // get a list of writeable calendars to save new events to
        if (
            (!$existing || $is_shared)
            && empty($data['nosave'])
            && ($response['action'] == 'rsvp' || $response['action'] == 'import')
        ) {
            $calendars       = $this->driver->list_calendars($mode);
            $calendar_select = new html_select([
                'name'       => 'calendar',
                'id'         => 'itip-saveto',
                'is_escaped' => true,
                'class'      => 'form-control custom-select',
            ]);

            $calendar_select->add('--', '');
            $numcals = 0;
            foreach ($calendars as $calendar) {
                if (!empty($calendar['editable'])) {
                    $calendar_select->add($calendar['name'], $calendar['id']);
                    $numcals++;
                }
            }
            if ($numcals < 1) {
                $calendar_select = null;
            }
        }

        if (!empty($calendar_select)) {
            $default_calendar   = $this->get_default_calendar($calendars);
            $response['select'] = html::span(
                'folder-select',
                $this->gettext('saveincalendar')
                . '&nbsp;'
                . $calendar_select->show($is_shared ? $existing['calendar'] : $default_calendar['id'])
            );
        } elseif (!empty($data['nosave'])) {
            $response['select'] = html::tag('input', ['type' => 'hidden', 'name' => 'calendar', 'id' => 'itip-saveto', 'value' => '']);
        }

        // render small agenda view for the respective day
        if ($data['method'] == 'REQUEST' && !empty($data['date']) && $response['action'] == 'rsvp') {
            $event_start = rcube_utils::anytodatetime($data['date']);
            $day_start   = new Datetime(gmdate('Y-m-d 00:00', $data['date']), $this->lib->timezone);
            $day_end     = new Datetime(gmdate('Y-m-d 23:59', $data['date']), $this->lib->timezone);

            // get events on that day from the user's personal calendars
            $calendars = $this->driver->list_calendars(calendar_driver::FILTER_PERSONAL);
            $events    = $this->driver->load_events($day_start->format('U'), $day_end->format('U'), null, array_keys($calendars));

            usort($events, function ($a, $b) { return $a['start'] > $b['start'] ? 1 : -1; });

            $before = $after = [];
            foreach ($events as $event) {
                // TODO: skip events with free_busy == 'free' ?
                if ($event['uid'] == $data['uid']
                    || $event['end'] < $day_start || $event['start'] > $day_end
                    || (!empty($event['status']) && $event['status'] == 'CANCELLED')
                    || (!empty($event['className']) && strpos($event['className'], 'declined') !== false)
                ) {
                    continue;
                }

                if ($event['start'] < $event_start) {
                    $before[] = $this->mail_agenda_event_row($event);
                } else {
                    $after[] = $this->mail_agenda_event_row($event);
                }
            }

            $response['append'] = [
                'selector' => '.calendar-agenda-preview',
                'replacements' => [
                    '%before%' => !empty($before) ? implode("\n", array_slice($before, -3)) : html::div('event-row no-event', $this->gettext('noearlierevents')),
                    '%after%'  => !empty($after) ? implode("\n", array_slice($after, 0, 3)) : html::div('event-row no-event', $this->gettext('nolaterevents')),
                ],
            ];
        }

        $this->rc->output->command('plugin.update_itip_object_status', $response);
    }

    /**
     * Handler for calendar/itip-remove requests
     */
    public function event_itip_remove()
    {
        $uid      = rcube_utils::get_input_value('uid', rcube_utils::INPUT_POST);
        $instance = rcube_utils::get_input_value('_instance', rcube_utils::INPUT_POST);
        $savemode = rcube_utils::get_input_value('_savemode', rcube_utils::INPUT_POST);
        $listmode = calendar_driver::FILTER_WRITEABLE | calendar_driver::FILTER_PERSONAL;
        $success  = false;

        // search for event if only UID is given
        if ($event = $this->driver->get_event(['uid' => $uid, '_instance' => $instance], $listmode)) {
            $event['_savemode'] = $savemode;
            $success = $this->driver->remove_event($event, true);
        }

        if ($success) {
            $this->rc->output->show_message('calendar.successremoval', 'confirmation');
        } else {
            $this->rc->output->show_message('calendar.errorsaving', 'error');
        }
    }

    /**
     * Handler for URLs that allow an invitee to respond on his invitation mail
     */
    public function itip_attend_response($p)
    {
        $this->setup();

        if ($p['action'] == 'attend') {
            $this->ui->init();

            $this->rc->output->set_env('task', 'calendar');  // override some env vars
            $this->rc->output->set_env('refresh_interval', 0);
            $this->rc->output->set_pagetitle($this->gettext('calendar'));

            $itip  = $this->load_itip();
            $token = rcube_utils::get_input_value('_t', rcube_utils::INPUT_GPC);

            // read event info stored under the given token
            if ($invitation = $itip->get_invitation($token)) {
                $this->token = $token;
                $this->event = $invitation['event'];

                // show message about cancellation
                if (!empty($invitation['cancelled'])) {
                    $this->invitestatus = html::div('rsvp-status declined', $itip->gettext('eventcancelled'));
                }
                // save submitted RSVP status
                elseif (!empty($_POST['rsvp'])) {
                    $status = null;
                    foreach (['accepted', 'tentative', 'declined'] as $method) {
                        if ($_POST['rsvp'] == $itip->gettext('itip' . $method)) {
                            $status = $method;
                            break;
                        }
                    }

                    // send itip reply to organizer
                    $invitation['event']['comment'] = rcube_utils::get_input_value('_comment', rcube_utils::INPUT_POST);
                    if ($status && $itip->update_invitation($invitation, $invitation['attendee'], strtoupper($status))) {
                        $this->invitestatus = html::div('rsvp-status ' . strtolower($status), $itip->gettext('youhave' . strtolower($status)));
                    } else {
                        $this->rc->output->command('display_message', $this->gettext('errorsaving'), 'error', -1);
                    }

                    // if user is logged in...
                    // FIXME: we should really consider removing this functionality
                    //        it's confusing that it creates/updates an event only for logged-in user
                    //        what if the logged-in user is not the same as the attendee?
                    if ($this->rc->user->ID) {
                        $this->load_driver();

                        $invitation = $itip->get_invitation($token);
                        $existing   = $this->driver->get_event($this->event);

                        // save the event to his/her default calendar if not yet present
                        if (!$existing && ($calendar = $this->get_default_calendar())) {
                            $invitation['event']['calendar'] = $calendar['id'];
                            if ($this->driver->new_event($invitation['event'])) {
                                $msg = $this->gettext(['name' => 'importedsuccessfully', 'vars' => ['calendar' => $calendar['name']]]);
                                $this->rc->output->command('display_message', $msg, 'confirmation');
                            } else {
                                $this->rc->output->command('display_message', $this->gettext('errorimportingevent'), 'error');
                            }
                        } elseif ($existing
                            && ($this->event['sequence'] >= $existing['sequence']
                                || $this->event['changed'] >= $existing['changed'])
                            && ($calendar = $this->driver->get_calendar_name($existing['calendar']))
                        ) {
                            $this->event       = $invitation['event'];
                            $this->event['id'] = $existing['id'];

                            unset($this->event['comment']);

                            // merge attendees status
                            // e.g. preserve my participant status for regular updates
                            $this->lib->merge_attendees($this->event, $existing, $status);

                            // update attachments list
                            $event['deleted_attachments'] = true;

                            // show me as free when declined (#1670)
                            if ($status == 'declined') {
                                $this->event['free_busy'] = 'free';
                            }

                            if ($this->driver->edit_event($this->event)) {
                                $msg = $this->gettext(['name' => 'updatedsuccessfully', 'vars' => ['calendar' => $calendar]]);
                                $this->rc->output->command('display_message', $msg, 'confirmation');
                            } else {
                                $this->rc->output->command('display_message', $this->gettext('errorimportingevent'), 'error');
                            }
                        }
                    }
                }

                $this->register_handler('plugin.event_inviteform', [$this, 'itip_event_inviteform']);
                $this->register_handler('plugin.event_invitebox', [$this->ui, 'event_invitebox']);

                if (empty($this->invitestatus)) {
                    $this->itip->set_rsvp_actions(['accepted', 'tentative', 'declined']);
                    $this->register_handler('plugin.event_rsvp_buttons', [$this->ui, 'event_rsvp_buttons']);
                }

                $this->rc->output->set_pagetitle($itip->gettext('itipinvitation') . ' ' . $this->event['title']);
            } else {
                $this->rc->output->command('display_message', $this->gettext('itipinvalidrequest'), 'error', -1);
            }

            $this->rc->output->send('calendar.itipattend');
        }
    }

    /**
     *
     */
    public function itip_event_inviteform($attrib)
    {
        $hidden = new html_hiddenfield(['name' => "_t", 'value' => $this->token]);

        return html::tag(
            'form',
            [
                'action' => $this->rc->url(['task' => 'calendar', 'action' => 'attend']),
                'method' => 'post',
                'noclose' => true,
            ] + $attrib
        ) . $hidden->show();
    }

    /**
     *
     */
    private function mail_agenda_event_row($event, $class = '')
    {
        if (!empty($event['allday'])) {
            $time = $this->gettext('all-day');
        } else {
            $start = is_object($event['start']) ? clone $event['start'] : $event['start'];
            $end = is_object($event['end']) ? clone $event['end'] : $event['end'];

            $time = $this->rc->format_date($start, $this->rc->config->get('time_format'))
                . ' - ' . $this->rc->format_date($end, $this->rc->config->get('time_format'));
        }

        return html::div(
            rtrim('event-row ' . ($class ?: ($event['className'] ?? ''))),
            html::span('event-date', $time)
            . html::span('event-title', rcube::Q($event['title']))
        );
    }

    /**
     *
     */
    public function mail_messages_list($p)
    {
        if (!empty($p['cols']) && in_array('attachment', (array) $p['cols']) && !empty($p['messages'])) {
            /** @var rcube_message_header $header */
            foreach ($p['messages'] as $header) {
                $part = new StdClass();
                $part->mimetype = $header->ctype;

                if (libcalendaring::part_is_vcalendar($part)) {
                    $header->list_flags['attachmentClass'] = 'ical';
                } elseif (in_array($header->ctype, ['multipart/alternative', 'multipart/mixed'])) {
                    // TODO: fetch bodystructure and search for ical parts. Maybe too expensive?
                    if (!empty($header->structure) && !empty($header->structure->parts)) {
                        foreach ($header->structure->parts as $part) {
                            if (libcalendaring::part_is_vcalendar($part)
                                && !empty($part->ctype_parameters['method'])
                            ) {
                                $header->list_flags['attachmentClass'] = 'ical';
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Add UI element to copy event invitations or updates to the calendar
     */
    public function mail_messagebody_html($p)
    {
        // load iCalendar functions (if necessary)
        if (!empty($this->lib->ical_parts)) {
            $this->get_ical();
            $this->load_itip();
        }

        $html = '';
        $has_events = false;
        $ical_objects = $this->lib->get_mail_ical_objects();

        // show a box for every event in the file
        foreach ($ical_objects as $idx => $event) {
            if ($event['_type'] != 'event') {
                // skip non-event objects (#2928)
                continue;
            }

            $has_events = true;

            // get prepared inline UI for this event object
            if ($ical_objects->method) {
                $append   = '';
                $date_str = $this->rc->format_date(clone $event['start'], $this->rc->config->get('date_format'), empty($event['start']->_dateonly));
                $date     = new DateTime($event['start']->format('Y-m-d') . ' 12:00:00', new DateTimeZone('UTC'));

                // prepare a small agenda preview to be filled with actual event data on async request
                if ($ical_objects->method == 'REQUEST') {
                    $append = html::div(
                        'calendar-agenda-preview',
                        html::tag('h3', 'preview-title', $this->gettext('agenda') . ' ' . html::span('date', $date_str))
                        . '%before%' . $this->mail_agenda_event_row($event, 'current') . '%after%'
                    );
                }

                $html .= html::div(
                    'calendar-invitebox invitebox boxinformation',
                    $this->itip->mail_itip_inline_ui(
                        $event,
                        $ical_objects->method,
                        $ical_objects->mime_id . ':' . $idx,
                        'calendar',
                        rcube_utils::anytodatetime($ical_objects->message_date),
                        $this->rc->url(['task' => 'calendar']) . '&view=agendaDay&date=' . $date->format('U')
                    ) . $append
                );
            }

            // limit listing
            if ($idx >= 3) {
                break;
            }
        }

        // prepend event boxes to message body
        if ($html) {
            $this->ui->init();
            $p['content'] = $html . $p['content'];
            $this->rc->output->add_label('calendar.savingdata', 'calendar.deleteventconfirm', 'calendar.declinedeleteconfirm');
        }

        // add "Save to calendar" button into attachment menu
        if ($has_events) {
            $this->add_button(
                [
                    'id'         => 'attachmentsavecal',
                    'name'       => 'attachmentsavecal',
                    'type'       => 'link',
                    'wrapper'    => 'li',
                    'command'    => 'attachment-save-calendar',
                    'class'      => 'icon calendarlink disabled',
                    'classact'   => 'icon calendarlink active',
                    'innerclass' => 'icon calendar',
                    'label'      => 'calendar.savetocalendar',
                ],
                'attachmentmenu'
            );
        }

        return $p;
    }

    /**
     * Handler for POST request to import an event attached to a mail message
     */
    public function mail_import_itip()
    {
        $itip_sending = $this->rc->config->get('calendar_itip_send_option', $this->defaults['calendar_itip_send_option']);

        $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox    = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $mime_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_POST);
        $status  = rcube_utils::get_input_value('_status', rcube_utils::INPUT_POST);
        $delete  = intval(rcube_utils::get_input_value('_del', rcube_utils::INPUT_POST));
        $noreply = intval(rcube_utils::get_input_value('_noreply', rcube_utils::INPUT_POST));
        $noreply = $noreply || $status == 'needs-action' || $itip_sending === 0;
        $instance = rcube_utils::get_input_value('_instance', rcube_utils::INPUT_POST);
        $savemode = rcube_utils::get_input_value('_savemode', rcube_utils::INPUT_POST);
        $comment  = rcube_utils::get_input_value('_comment', rcube_utils::INPUT_POST);

        $error_msg = $this->gettext('errorimportingevent');
        $success   = false;
        $deleted   = false;
        $dontsave  = false;
        $existing = null;
        $event_attendee = null;

        if ($status == 'delegated') {
            $to = rcube_utils::get_input_value('_to', rcube_utils::INPUT_POST, true);
            $delegates = rcube_mime::decode_address_list($to, 1, false);
            $delegate  = reset($delegates);

            if (empty($delegate) || empty($delegate['mailto'])) {
                $this->rc->output->command('display_message', $this->rc->gettext('libcalendaring.delegateinvalidaddress'), 'error');
                return;
            }
        }

        // successfully parsed events?
        if ($event = $this->lib->mail_get_itip_object($mbox, $uid, $mime_id, 'event')) {
            // forward iTip request to delegatee
            if (!empty($delegate)) {
                $rsvpme = rcube_utils::get_input_value('_rsvp', rcube_utils::INPUT_POST);
                $itip   = $this->load_itip();

                $event['comment'] = $comment;

                if ($itip->delegate_to($event, $delegate, !empty($rsvpme))) {
                    $this->rc->output->show_message('calendar.itipsendsuccess', 'confirmation');
                } else {
                    $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
                }

                unset($event['comment']);

                // the delegator is set to non-participant, thus save as non-blocking
                $event['free_busy'] = 'free';
            }

            $mode = calendar_driver::FILTER_PERSONAL
                | calendar_driver::FILTER_SHARED
                | calendar_driver::FILTER_WRITEABLE;

            // find writeable calendar to store event
            $cal_id    = rcube_utils::get_input_value('_folder', rcube_utils::INPUT_POST);
            $dontsave  = $cal_id === '' && $event['_method'] == 'REQUEST';
            $calendars = $this->driver->list_calendars($mode);
            $calendar  = $calendars[$cal_id] ?? null;

            // select default calendar except user explicitly selected 'none'
            if (!$calendar && !$dontsave) {
                $calendar = $this->get_default_calendar($calendars);
            }

            $metadata = [
                'uid'       => $event['uid'],
                '_instance' => $event['_instance'] ?? null,
                'changed'   => is_object($event['changed']) ? $event['changed']->format('U') : 0,
                'sequence'  => intval($event['sequence'] ?? 0),
                'fallback'  => strtoupper((string) $status),
                'method'    => $event['_method'],
                'task'      => 'calendar',
            ];

            // update my attendee status according to submitted method
            if (!empty($status)) {
                $organizer = null;
                $emails = $this->get_user_emails();
                foreach ($event['attendees'] as $i => $attendee) {
                    $attendee_role = $attendee['role'] ?? null;
                    $attendee_email = $attendee['email'] ?? null;

                    if ($attendee_role == 'ORGANIZER') {
                        $organizer = $attendee;
                    } elseif ($attendee_email && in_array(strtolower($attendee_email), $emails)) {
                        $event['attendees'][$i]['status'] = strtoupper($status);
                        if (!in_array($event['attendees'][$i]['status'], ['NEEDS-ACTION', 'DELEGATED'])) {
                            $event['attendees'][$i]['rsvp'] = false;  // unset RSVP attribute
                        }

                        $metadata['attendee'] = $attendee_email;
                        $metadata['rsvp']     = $attendee_role != 'NON-PARTICIPANT';

                        $reply_sender   = $attendee_email;
                        $event_attendee = $attendee;
                    }
                }

                // add attendee with this user's default identity if not listed
                if (empty($reply_sender)) {
                    $sender_identity = $this->rc->user->list_emails(true);
                    $event['attendees'][] = [
                        'name'   => $sender_identity['name'],
                        'email'  => $sender_identity['email'],
                        'role'   => 'OPT-PARTICIPANT',
                        'status' => strtoupper($status),
                    ];
                    $metadata['attendee'] = $sender_identity['email'];
                }
            }

            // save to calendar
            if ($calendar && !empty($calendar['editable'])) {
                // check for existing event with the same UID
                $existing = $this->find_event($event, $mode);

                // we'll create a new copy if user decided to change the calendar
                if ($existing && $cal_id && $calendar['id'] != $existing['calendar']) {
                    $existing = null;
                }

                // Use only free_busy values that make sense in this context (T853612)
                if (!in_array($event['free_busy'] ?? '', ['free', 'busy'])) {
                    unset($event['free_busy']);
                }

                $update_attendees = [];

                if ($existing) {
                    $calendar = $calendars[$existing['calendar']];

                    // forward savemode for correct updates of recurring events
                    $existing['_savemode'] = $savemode ?: (!empty($event['_savemode']) ? $event['_savemode'] : null);

                    // only update attendee status
                    if ($event['_method'] == 'REPLY') {
                        $existing_attendee_index  = -1;

                        if ($attendee = $this->itip->find_reply_attendee($event)) {
                            $event_attendee       = $attendee;
                            $update_attendees[]   = $attendee;
                            $metadata['fallback'] = $attendee['status'];
                            $metadata['attendee'] = $attendee['email'];
                            $metadata['rsvp']     = !empty($attendee['rsvp']) || empty($attendee['role']) || $attendee['role'] != 'NON-PARTICIPANT';

                            $existing_attendee_emails = [];

                            // Find the attendee to update
                            foreach ($existing['attendees'] as $i => $existing_attendee) {
                                $existing_attendee_emails[] = $existing_attendee['email'];
                                if ($this->itip->compare_email($existing_attendee['email'], $attendee['email'])) {
                                    $existing_attendee_index = $i;
                                }
                            }

                            if ($attendee['status'] == 'DELEGATED') {
                                //Also find and copy the delegatee
                                $delegatee_email = $attendee['email'];
                                $delegatees = array_filter($event['attendees'], function ($attendee) use ($delegatee_email) { return $attendee['role'] != 'ORGANIZER' && $this->itip->compare_email($attendee['delegated-from'], $delegatee_email); });

                                if ($delegatee = $this->itip->find_attendee_by_email($event['attendees'], 'delegated-from', $attendee['email'])) {
                                    $update_attendees[] = $delegatee;
                                    if (!in_array_nocase($delegatee['email'], $existing_attendee_emails)) {
                                        $existing['attendees'][] = $delegatee['email'];
                                    }
                                }
                            }
                        }

                        // if delegatee has declined, set delegator's RSVP=True
                        if ($event_attendee
                            && $event_attendee['status'] == 'DECLINED'
                            && !empty($event_attendee['delegated-from'])
                        ) {
                            foreach ($existing['attendees'] as $i => $attendee) {
                                if ($attendee['email'] == $event_attendee['delegated-from']) {
                                    $existing['attendees'][$i]['rsvp'] = true;
                                    break;
                                }
                            }
                        }

                        // found matching attendee entry in both existing and new events
                        if ($existing_attendee_index >= 0 && $event_attendee) {
                            $existing['attendees'][$existing_attendee_index] = $event_attendee;
                            $success = $this->driver->update_attendees($existing, $update_attendees);
                        }
                        // update the entire attendees block
                        elseif (
                            ($event['sequence'] >= $existing['sequence'] || $event['changed'] >= $existing['changed'])
                            && $event_attendee
                        ) {
                            $existing['attendees'][] = $event_attendee;
                            $success = $this->driver->update_attendees($existing, $update_attendees);
                        } elseif (!$event_attendee) {
                            $error_msg = $this->gettext('errorunknownattendee');
                        } else {
                            $error_msg = $this->gettext('newerversionexists');
                        }
                    }
                    // delete the event when declined (#1670)
                    elseif ($status == 'declined' && $delete) {
                        $deleted = $this->driver->remove_event($existing, true);
                        $success = true;
                    }
                    // import the (newer) event
                    elseif ($event['sequence'] >= $existing['sequence'] || $event['changed'] >= $existing['changed']) {
                        $event['id']       = $existing['id'];
                        $event['calendar'] = $existing['calendar'];

                        // merge attendees status
                        // e.g. preserve my participant status for regular updates
                        $this->lib->merge_attendees($event, $existing, $status);

                        // set status=CANCELLED on CANCEL messages
                        if ($event['_method'] == 'CANCEL') {
                            $event['status'] = 'CANCELLED';
                        }

                        // update attachments list, allow attachments update only on REQUEST (#5342)
                        if ($event['_method'] == 'REQUEST') {
                            $event['deleted_attachments'] = true;
                        } else {
                            unset($event['attachments']);
                        }

                        // show me as free when declined (#1670)
                        if ($status == 'declined'
                            || (!empty($event['status']) && $event['status'] == 'CANCELLED')
                            || ($event_attendee && ($event_attendee['role'] ?? '') == 'NON-PARTICIPANT')
                        ) {
                            $event['free_busy'] = 'free';
                        }

                        $success = $this->driver->edit_event($event);
                    } elseif (!empty($status)) {
                        $existing['attendees'] = $event['attendees'];
                        if ($status == 'declined' || ($event_attendee && ($event_attendee['role'] ?? '') == 'NON-PARTICIPANT')) {
                            // show me as free when declined (#1670)
                            $existing['free_busy'] = 'free';
                        }
                        $success = $this->driver->edit_event($existing);
                    } else {
                        $error_msg = $this->gettext('newerversionexists');
                    }
                } elseif (empty($existing) && ($status != 'declined' || $this->rc->config->get('kolab_invitation_calendars'))) {
                    if ($status == 'declined'
                        || ($event['status'] ?? '') == 'CANCELLED'
                        || ($event_attendee && ($event_attendee['role'] ?? '') == 'NON-PARTICIPANT')
                    ) {
                        $event['free_busy'] = 'free';
                    }

                    // if the RSVP reply only refers to a single instance:
                    // store unmodified master event with current instance as exception
                    if (!empty($instance) && !empty($savemode) && $savemode != 'all') {
                        $master = $this->lib->mail_get_itip_object($mbox, $uid, $mime_id, 'event');
                        if ($master['recurrence'] && empty($master['_instance'])) {
                            // compute recurring events until this instance's date
                            if ($recurrence_date = rcube_utils::anytodatetime($instance, $master['start']->getTimezone())) {
                                $recurrence_date->setTime(23, 59, 59);

                                foreach ($this->driver->get_recurring_events($master, $master['start'], $recurrence_date) as $recurring) {
                                    if ($recurring['_instance'] == $instance) {
                                        // copy attendees block with my partstat to exception
                                        $recurring['attendees'] = $event['attendees'];
                                        $master['recurrence']['EXCEPTIONS'][] = $recurring;
                                        $event = $recurring;  // set reference for iTip reply
                                        break;
                                    }
                                }

                                $master['calendar'] = $event['calendar'] = $calendar['id'];
                                $success = $this->driver->new_event($master);
                            } else {
                                $master = null;
                            }
                        } else {
                            $master = null;
                        }
                    }

                    // save to the selected/default calendar
                    if (empty($master)) {
                        $event['calendar'] = $calendar['id'];
                        $success = $this->driver->new_event($event);
                    }
                } elseif ($status == 'declined') {
                    $error_msg = null;
                }
            } elseif ($status == 'declined' || $dontsave) {
                $error_msg = null;
            } else {
                $error_msg = $this->gettext('nowritecalendarfound');
            }
        }

        if ($success) {
            if ($event['_method'] == 'REPLY') {
                $message = 'attendeupdateesuccess';
            } else {
                $message = $deleted ? 'successremoval' : ($existing ? 'updatedsuccessfully' : 'importedsuccessfully');
            }

            $msg = $this->gettext(['name' => $message, 'vars' => ['calendar' => $calendar['name'] ?? '']]);
            $this->rc->output->command('display_message', $msg, 'confirmation');
        }

        if ($success || $dontsave) {
            $metadata['calendar'] = $event['calendar'] ?? null;
            $metadata['nosave']   = $dontsave;
            $metadata['rsvp']     = !empty($metadata['rsvp']);

            $metadata['after_action'] = $this->rc->config->get('calendar_itip_after_action', $this->defaults['calendar_itip_after_action']);
            $this->rc->output->command('plugin.itip_message_processed', $metadata);
            $error_msg = null;
        } elseif ($error_msg) {
            $this->rc->output->command('display_message', $error_msg, 'error');
        }

        // send iTip reply
        if (!empty($event) && $event['_method'] == 'REQUEST' && !empty($organizer) && !$noreply && !$error_msg && !empty($reply_sender)
            && !in_array(strtolower($organizer['email']), $emails ?? [])
        ) {
            $event['comment'] = $comment;
            $itip = $this->load_itip();
            $itip->set_sender_email($reply_sender);

            if ($itip->send_itip_message($event, 'REPLY', $organizer, 'itipsubject' . $status, 'itipmailbody' . $status)) {
                $mailto = $organizer['name'] ? $organizer['name'] : $organizer['email'];
                $msg    = $this->gettext(['name' => 'sentresponseto', 'vars' => ['mailto' => $mailto]]);
                $this->rc->output->command('display_message', $msg, 'confirmation');
            } else {
                $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
            }
        }

        $this->rc->output->send();
    }

    /**
     * Handler for calendar/itip-remove requests
     */
    public function mail_itip_decline_reply()
    {
        $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox    = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $mime_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_POST);

        if (($event = $this->lib->mail_get_itip_object($mbox, $uid, $mime_id, 'event'))
            && $event['_method'] == 'REPLY'
        ) {
            $event['comment'] = rcube_utils::get_input_value('_comment', rcube_utils::INPUT_POST);

            foreach ($event['attendees'] as $_attendee) {
                if ($_attendee['role'] != 'ORGANIZER') {
                    $attendee = $_attendee;
                    break;
                }
            }

            $itip = $this->load_itip();

            if ($itip->send_itip_message($event, 'CANCEL', $attendee ?? null, 'itipsubjectcancel', 'itipmailbodycancel')) {
                $mailto = !empty($attendee['name']) ? $attendee['name'] : ($attendee['email'] ?? '');
                $msg    = $this->gettext(['name' => 'sentresponseto', 'vars' => ['mailto' => $mailto]]);
                $this->rc->output->command('display_message', $msg, 'confirmation');
            } else {
                $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
            }
        } else {
            $this->rc->output->command('display_message', $this->gettext('itipresponseerror'), 'error');
        }
    }

    /**
     * Handler for calendar/itip-delegate requests
     */
    public function mail_itip_delegate()
    {
        // forward request to mail_import_itip() with the right status
        $_POST['_status'] = $_REQUEST['_status'] = 'delegated';
        $this->mail_import_itip();
    }

    /**
     * Import the full payload from a mail message attachment
     */
    public function mail_import_attachment()
    {
        $uid     = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_POST);
        $mbox    = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_POST);
        $mime_id = rcube_utils::get_input_value('_part', rcube_utils::INPUT_POST);

        // establish imap connection
        $imap = $this->rc->get_storage();
        $imap->set_folder($mbox);

        if ($uid && $mime_id) {
            $part = $imap->get_message_part($uid, $mime_id);

            if ($part) {
                $events = $this->get_ical()->import($part);
            }
        }

        $success = $existing = 0;

        if (!empty($events)) {
            // find writeable calendar to store event
            $cal_id = !empty($_REQUEST['_calendar']) ? rcube_utils::get_input_value('_calendar', rcube_utils::INPUT_POST) : null;
            $calendars = $this->driver->list_calendars(calendar_driver::FILTER_PERSONAL);

            foreach ($events as $event) {
                // save to calendar
                $calendar = !empty($calendars[$cal_id]) ? $calendars[$cal_id] : $this->get_default_calendar();
                if ($calendar && $calendar['editable'] && $event['_type'] == 'event') {
                    $event['calendar'] = $calendar['id'];

                    if (!$this->driver->get_event($event['uid'], calendar_driver::FILTER_WRITEABLE)) {
                        $success += (bool)$this->driver->new_event($event);
                    } else {
                        $existing++;
                    }
                }
            }
        }

        if ($success) {
            $msg = $this->gettext(['name' => 'importsuccess', 'vars' => ['nr' => $success]]);
            $this->rc->output->command('display_message', $msg, 'confirmation');
        } elseif ($existing) {
            $this->rc->output->command('display_message', $this->gettext('importwarningexists'), 'warning');
        } else {
            $this->rc->output->command('display_message', $this->gettext('errorimportingevent'), 'error');
        }
    }

    /**
     * Read email message and return contents for a new event based on that message
     */
    public function mail_message2event()
    {
        $this->ui->init();
        $this->ui->addJS();
        $this->ui->init_templates();
        $this->ui->calendar_list([], true); // set env['calendars']

        $uid   = rcube_utils::get_input_value('_uid', rcube_utils::INPUT_GET);
        $mbox  = rcube_utils::get_input_value('_mbox', rcube_utils::INPUT_GET);
        $event = [];

        // establish imap connection
        $imap    = $this->rc->get_storage();
        $message = new rcube_message($uid, $mbox);

        if ($message->headers) {
            $event['title']       = trim($message->subject);
            $event['description'] = trim($message->first_text_part());

            $this->load_driver();

            // add a reference to the email message
            if ($msgref = $this->driver->get_message_reference($message->headers, $mbox)) {
                $event['links'] = [$msgref];
            }
            // copy mail attachments to event
            elseif (!empty($message->attachments) && !empty($this->driver->attachments)) {
                $handler = new kolab_attachments_handler();
                $event['attachments'] = $handler->copy_mail_attachments(self::SESSION_KEY, 'cal-', $message);
            }

            $this->rc->output->set_env('event_prop', $event);
        } else {
            $this->rc->output->command('display_message', $this->gettext('messageopenerror'), 'error');
        }

        $this->rc->output->send('calendar.dialog');
    }

    /**
     * Handler for the 'message_compose' plugin hook. This will check for
     * a compose parameter 'calendar_event' and create an attachment with the
     * referenced event in iCal format
     */
    public function mail_message_compose($args)
    {
        // set the submitted event ID as attachment
        if (!empty($args['param']['calendar_event'])) {
            $this->load_driver();

            [$cal, $id] = explode(':', $args['param']['calendar_event'], 2);

            if ($event = $this->driver->get_event(['id' => $id, 'calendar' => $cal])) {
                $filename = asciiwords($event['title']);
                if (empty($filename)) {
                    $filename = 'event';
                }

                // save ics to a temp file and register as attachment
                $tmp_path = tempnam($this->rc->config->get('temp_dir'), 'rcmAttmntCal');
                $export   = $this->get_ical()->export([$event], '', false, [$this->driver, 'get_attachment_body']);

                file_put_contents($tmp_path, $export);

                $args['attachments'][] = [
                    'path'     => $tmp_path,
                    'name'     => $filename . '.ics',
                    'mimetype' => 'text/calendar',
                    'size'     => filesize($tmp_path),
                ];
                $args['param']['subject'] = $event['title'];
            }
        }

        return $args;
    }

    /**
     * Create a Nextcould Talk room
     */
    public function talk_room_create()
    {
        require_once __DIR__ . '/lib/calendar_nextcloud_api.php';

        $api = new calendar_nextcloud_api();

        $name = (string) rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST);

        $room_url = $api->talk_room_create($name);

        if ($room_url) {
            $this->rc->output->command('plugin.talk_room_created', ['url' => $room_url]);
        } else {
            $this->rc->output->command('display_message', $this->gettext('talkroomcreateerror'), 'error');
        }
    }

    /**
     * Update a Nextcould Talk room
     */
    public function talk_room_update($event)
    {
        // If a room is assigned to the event...
        if (
            ($talk_url = $this->rc->config->get('calendar_nextcloud_url'))
            && isset($event['attendees'])
            && !empty($event['location'])
            && strpos($event['location'], unslashify($talk_url) . '/call/') === 0
        ) {
            $participants = [];
            $organizer = null;

            // ollect participants' and organizer's email addresses
            foreach ($event['attendees'] as $attendee) {
                if (!empty($attendee['email'])) {
                    if ($attendee['role'] == 'ORGANIZER') {
                        $organizer = $attendee['email'];
                    } elseif ($attendee['cutype'] == 'INDIVIDUAL') {
                        $participants[] = $attendee['email'];
                    }
                }
            }

            // If the event is owned by the current user update the room
            if ($organizer && in_array($organizer, $this->get_user_emails())) {
                require_once __DIR__ . '/lib/calendar_nextcloud_api.php';

                $api = new calendar_nextcloud_api();

                $api->talk_room_update($event['location'], $participants);
            }
        }
    }

    /**
     * Get a list of email addresses of the current user (from login and identities)
     */
    public function get_user_emails()
    {
        return $this->lib->get_user_emails();
    }

    /**
     * Build an absolute URL with the given parameters
     */
    public function get_url($param = [])
    {
        $param += ['task' => 'calendar'];
        return $this->rc->url($param, true, true);
    }

    public function ical_feed_hash($source)
    {
        return base64_encode($this->rc->user->get_username() . ':' . $source);
    }

    /**
     * Handler for user_delete plugin hook
     */
    public function user_delete($args)
    {
        // delete itipinvitations entries related to this user
        $db = $this->rc->get_dbh();
        $table_itipinvitations = $db->table_name('itipinvitations', true);

        $db->query("DELETE FROM $table_itipinvitations WHERE `user_id` = ?", $args['user']->ID);

        $this->setup();
        $this->load_driver();

        return $this->driver->user_delete($args);
    }

    /**
     * Find first occurrence of a recurring event excluding start date
     *
     * @param array $event Event data (with 'start' and 'recurrence')
     *
     * @return DateTime Date of the first occurrence
     */
    public function find_first_occurrence($event)
    {
        // Make sure libkolab/libcalendaring plugins are loaded
        $this->load_driver();

        $driver_name = $this->rc->config->get('calendar_driver', 'database');

        // Use kolabcalendaring/kolabformat to compute recurring events only with the Kolab driver
        if ($driver_name == 'kolab' && class_exists('kolabformat') && class_exists('kolabcalendaring')
            && class_exists('kolab_date_recurrence')
        ) {
            $object = kolab_format::factory('event', 3.0);
            $object->set($event);

            $recurrence = new kolab_date_recurrence($object);
        } else {
            // fallback to libcalendaring recurrence implementation
            $recurrence = new libcalendaring_recurrence($this->lib, $event);
        }

        return $recurrence->first_occurrence();
    }

    /**
     * Get date-time input from UI and convert to unix timestamp
     */
    protected function input_timestamp($name, $type)
    {
        $ts = rcube_utils::get_input_value($name, $type);

        if ($ts && (!is_numeric($ts) || strpos($ts, 'T'))) {
            $ts = new DateTime($ts, $this->timezone);
            $ts = $ts->getTimestamp();
        }

        return $ts;
    }

    /**
     * Magic getter for public access to protected members
     */
    public function __get($name)
    {
        switch ($name) {
            case 'ical':
                return $this->get_ical();

            case 'itip':
                return $this->load_itip();

            case 'driver':
                $this->load_driver();
                return $this->driver;
        }

        return null;
    }
}
