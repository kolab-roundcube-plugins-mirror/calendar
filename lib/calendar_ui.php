<?php

/**
 * User Interface class for the Calendar plugin
 *
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
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


class calendar_ui
{
    public $screen;
    public $action;
    public $calendar;

    /** @var rcmail */
    private $rc;

    /** @var calendar Calendar plugin */
    private $cal;

    /** @var bool */
    private $ready = false;



    /**
     * Object constructor
     *
     * @param calendar $cal Calendar plugin
     */
    public function __construct($cal)
    {
        $this->cal    = $cal;
        $this->rc     = $cal->rc;
        $this->screen = $this->rc->task == 'calendar' ? ($this->rc->action ?: 'calendar') : 'other';
    }

    /**
     * Calendar UI initialization and requests handlers
     */
    public function init()
    {
        if ($this->ready) {
            // already done
            return;
        }

        // add taskbar button
        $this->cal->add_button(
            [
                'command'    => 'calendar',
                'class'      => 'button-calendar',
                'classsel'   => 'button-calendar button-selected',
                'innerclass' => 'button-inner',
                'label'      => 'calendar.calendar',
                'type'       => 'link',
            ],
            'taskbar'
        );

        // load basic client script
        if ($this->rc->action != 'print') {
            $this->cal->include_script('calendar_base.js');
        }

        $this->addCSS();

        $this->ready = true;
    }

    /**
     * Register handler methods for the template engine
     */
    public function init_templates()
    {
        $this->cal->register_handler('plugin.calendar_css', [$this, 'calendar_css']);
        $this->cal->register_handler('plugin.calendar_list', [$this, 'calendar_list']);
        $this->cal->register_handler('plugin.calendar_select', [$this, 'calendar_select']);
        $this->cal->register_handler('plugin.identity_select', [$this, 'identity_select']);
        $this->cal->register_handler('plugin.category_select', [$this, 'category_select']);
        $this->cal->register_handler('plugin.status_select', [$this, 'status_select']);
        $this->cal->register_handler('plugin.freebusy_select', [$this, 'freebusy_select']);
        $this->cal->register_handler('plugin.priority_select', [$this, 'priority_select']);
        $this->cal->register_handler('plugin.alarm_select', [$this, 'alarm_select']);
        $this->cal->register_handler('plugin.recurrence_form', [$this->cal->lib, 'recurrence_form']);
        $this->cal->register_handler('plugin.attendees_list', [$this, 'attendees_list']);
        $this->cal->register_handler('plugin.attendees_form', [$this, 'attendees_form']);
        $this->cal->register_handler('plugin.resources_form', [$this, 'resources_form']);
        $this->cal->register_handler('plugin.resources_list', [$this, 'resources_list']);
        $this->cal->register_handler('plugin.resources_searchform', [$this, 'resources_search_form']);
        $this->cal->register_handler('plugin.resource_info', [$this, 'resource_info']);
        $this->cal->register_handler('plugin.resource_calendar', [$this, 'resource_calendar']);
        $this->cal->register_handler('plugin.attendees_freebusy_table', [$this, 'attendees_freebusy_table']);
        $this->cal->register_handler('plugin.edit_attendees_notify', [$this, 'edit_attendees_notify']);
        $this->cal->register_handler('plugin.edit_recurrence_sync', [$this, 'edit_recurrence_sync']);
        $this->cal->register_handler('plugin.edit_recurring_warning', [$this, 'recurring_event_warning']);
        $this->cal->register_handler('plugin.event_rsvp_buttons', [$this, 'event_rsvp_buttons']);
        $this->cal->register_handler('plugin.agenda_options', [$this, 'agenda_options']);
        $this->cal->register_handler('plugin.events_import_form', [$this, 'events_import_form']);
        $this->cal->register_handler('plugin.events_export_form', [$this, 'events_export_form']);
        $this->cal->register_handler('plugin.object_changelog_table', ['libkolab', 'object_changelog_table']);
        $this->cal->register_handler('plugin.searchform', [$this->rc->output, 'search_form']);

        kolab_attachments_handler::ui();
    }

    /**
     * Adds CSS stylesheets to the page header
     */
    public function addCSS()
    {
        $skin_path = $this->cal->local_skin_path();

        if (
            $this->rc->task == 'calendar'
            && (!$this->rc->action || in_array($this->rc->action, ['index', 'print']))
        ) {
            // Include fullCalendar style before skin file for simpler style overriding
            $this->cal->include_stylesheet($skin_path . '/fullcalendar.css');
        }

        $this->cal->include_stylesheet($skin_path . '/calendar.css');

        if ($this->rc->task == 'calendar' && $this->rc->action == 'print') {
            $this->cal->include_stylesheet($skin_path . '/print.css');
        }
    }

    /**
     * Adds JS files to the page header
     */
    public function addJS()
    {
        $this->cal->include_script('lib/js/moment.js');
        $this->cal->include_script('lib/js/fullcalendar.js');

        if ($this->rc->task == 'calendar' && $this->rc->action == 'print') {
            $this->cal->include_script('print.js');
        } else {
            $this->rc->output->include_script('treelist.js');
            $this->cal->api->include_script('libkolab/libkolab.js');
            $this->cal->include_script('calendar_ui.js');
            jqueryui::miniColors();
        }
    }

    /**
     * Add custom style for the calendar UI
     */
    public function calendar_css($attrib = [])
    {
        $categories    = $this->cal->driver->list_categories();
        $calendars     = $this->cal->driver->list_calendars();
        $js_categories = [];

        $mode = $this->rc->config->get('calendar_event_coloring', $this->cal->defaults['calendar_event_coloring']);
        $css  = "\n";

        foreach ((array) $categories as $class => $color) {
            if (!empty($color)) {
                $js_categories[$class] = $color;

                $color = ltrim($color, '#');
                $class = 'cat-' . asciiwords(strtolower($class), true);
                $css  .= ".$class { color: #$color; }\n";
            }
        }

        $this->rc->output->set_env('calendar_categories', $js_categories);

        foreach ((array) $calendars as $id => $prop) {
            if (!empty($prop['color'])) {
                $css .= $this->calendar_css_classes($id, $prop, $mode, $attrib);
            }
        }

        return html::tag('style', ['type' => 'text/css'], $css);
    }

    /**
     * Calendar folder specific CSS classes
     */
    public function calendar_css_classes($id, $prop, $mode, $attrib = [])
    {
        $color = $folder_color = $prop['color'];

        // replace white with skin-defined color
        if (!empty($attrib['folder-fallback-color']) && preg_match('/^f+$/i', $folder_color)) {
            $folder_color = ltrim($attrib['folder-fallback-color'], '#');
        }

        $class = 'cal-' . asciiwords($id, true);
        $css   = "li .$class";
        if (!empty($attrib['folder-class'])) {
            $css = str_replace('$class', $class, $attrib['folder-class']);
        }
        $css  .= " { color: #$folder_color; }\n";

        return $css . ".$class .handle { background-color: #$color; }\n";
    }

    /**
     * Generate HTML content of the calendars list (or metadata only)
     */
    public function calendar_list($attrib = [], $js_only = false)
    {
        $html      = '';
        $jsenv     = [];
        $tree      = true;
        $calendars = $this->cal->driver->list_calendars(0, $tree);

        // walk folder tree
        if (is_object($tree)) {
            $html = $this->list_tree_html($tree, $calendars, $jsenv, $attrib);

            // append birthdays calendar which isn't part of $tree
            if (!empty($calendars[calendar_driver::BIRTHDAY_CALENDAR_ID])) {
                $bdaycal = $calendars[calendar_driver::BIRTHDAY_CALENDAR_ID];
                $calendars = [calendar_driver::BIRTHDAY_CALENDAR_ID => $bdaycal];
            } else {
                $calendars = [];  // clear array for flat listing
            }
        } elseif (isset($attrib['class'])) {
            // fall-back to flat folder listing
            $attrib['class'] .= ' flat';
        }

        foreach ((array) $calendars as $id => $prop) {
            if (!empty($attrib['activeonly']) && empty($prop['active'])) {
                continue;
            }

            $li_content = $this->calendar_list_item($id, $prop, $jsenv, !empty($attrib['activeonly']));
            $li_attr = [
                'id'    => 'rcmlical' . $id,
                'class' => $prop['group'] ?? null,
            ];

            $html .= html::tag('li', $li_attr, $li_content);
        }

        $this->rc->output->set_env('calendars', $jsenv);

        if ($js_only) {
            return;
        }

        $this->rc->output->set_env('source', rcube_utils::get_input_value('source', rcube_utils::INPUT_GET));
        $this->rc->output->add_gui_object('calendarslist', !empty($attrib['id']) ? $attrib['id'] : 'rccalendarlist');

        return html::tag('ul', $attrib, $html, html::$common_attrib);
    }

    /**
     * Return html for a structured list <ul> for the folder tree
     */
    public function list_tree_html($node, $data, &$jsenv, $attrib)
    {
        $out = '';
        foreach ($node->children as $folder) {
            $id   = $folder->id;
            $prop = $data[$id];
            $is_collapsed = false; // TODO: determine this somehow?

            $content = $this->calendar_list_item($id, $prop, $jsenv, !empty($attrib['activeonly']));

            if (!empty($folder->children)) {
                $content .= html::tag(
                    'ul',
                    ['style' => $is_collapsed ? "display:none;" : null], // @phpstan-ignore-line
                    $this->list_tree_html($folder, $data, $jsenv, $attrib)
                );
            }

            if (strlen($content)) {
                $li_attr = [
                    'id'    => 'rcmlical' . rcube_utils::html_identifier($id),
                    'class' => $prop['group'] . (!empty($prop['virtual']) ? ' virtual' : ''),
                ];
                $out .= html::tag('li', $li_attr, $content);
            }
        }

        return $out;
    }

    /**
     * Helper method to build a calendar list item (HTML content and js data)
     */
    public function calendar_list_item($id, $prop, &$jsenv, $activeonly = false)
    {
        // enrich calendar properties with settings from the driver
        if (empty($prop['virtual'])) {
            unset($prop['user_id']);
            $feed = ['_cal' => $this->cal->ical_feed_hash($id) . '.ics', 'action' => 'feed'];

            $prop['alarms']      = $this->cal->driver->alarms;
            $prop['attendees']   = $this->cal->driver->attendees;
            $prop['freebusy']    = $this->cal->driver->freebusy;
            $prop['attachments'] = $this->cal->driver->attachments;
            $prop['undelete']    = $this->cal->driver->undelete;
            $prop['feedurl']     = $this->cal->get_url($feed);

            $jsenv[$id] = $prop;
        }

        if (!empty($prop['title'])) {
            $title = $prop['title'];
        } elseif ($prop['name'] != $prop['listname'] || strlen($prop['name']) > 25) {
            $title = html_entity_decode($prop['name'], ENT_COMPAT, RCUBE_CHARSET);
        } else {
            $title = '';
        }

        $classes = ['calendar', 'cal-' . asciiwords($id, true)];

        if (!empty($prop['virtual'])) {
            $classes[] = 'virtual';
        } elseif (!empty($prop['rights']) && strpos($prop['rights'], 'i') === false && strpos($prop['rights'], 'w') === false) {
            $classes[] = 'readonly';
        }
        if (!empty($prop['subscribed'])) {
            $classes[] = 'subscribed';

            if ($prop['subscribed'] === 2) {
                $classes[] = 'partial';
            }
        }
        if (!empty($prop['class'])) {
            $classes[] = $prop['class'];
        }

        $content = '';

        if (!$activeonly || !empty($prop['active'])) {
            $label_id = 'cl:' . $id;
            $content = html::a(
                ['class' => 'calname', 'id' => $label_id, 'title' => $title, 'href' => '#'],
                rcube::Q(!empty($prop['listname']) ? $prop['listname'] : $prop['name'])
            );

            if (empty($prop['virtual'])) {
                $color   = !empty($prop['color']) ? $prop['color'] : 'f00';
                $actions = '';

                if (!empty($prop['removable'])) {
                    $actions .= html::a(
                        [
                            'href'  => '#',
                            'class' => 'remove',
                            'title' => $this->cal->gettext('removelist'),
                        ],
                        ' '
                    );
                }

                $actions .= html::a(
                    [
                        'href'  => '#',
                        'class' => 'quickview',
                        'title' => $this->cal->gettext('quickview'),
                        'role'  => 'checkbox',
                        'aria-checked' => 'false',
                        'style' => !empty($prop['share_invitation']) ? 'display:none' : null,
                    ],
                    ' '
                );

                if (!isset($prop['subscriptions']) || $prop['subscriptions'] !== false) {
                    if (!empty($prop['subscribed'])) {
                        $actions .= html::a(
                            [
                                'href'  => '#',
                                'class' => 'subscribed',
                                'title' => $this->cal->gettext('calendarsubscribe'),
                                'role'  => 'checkbox',
                                'aria-checked' => !empty($prop['subscribed']) ? 'true' : 'false',
                            ],
                            ' '
                        );
                    }
                }

                $content .= html::tag('input', [
                        'type'    => 'checkbox',
                        'name'    => '_cal[]',
                        'value'   => $id,
                        'checked' => !empty($prop['active']) && empty($prop['share_invitation']),
                        'aria-labelledby' => $label_id,
                    ])
                    . html::span('actions', $actions)
                    . html::span(['class' => 'handle', 'style' => "background-color: #$color"], '&nbsp;');
            }

            $content = html::div(implode(' ', $classes), $content);
        }

        return $content;
    }

    /**
     * Render a HTML for agenda options form
     */
    public function agenda_options($attrib = [])
    {
        $attrib += ['id' => 'agendaoptions'];
        $attrib['style'] = 'display:none';

        $select_range = new html_select(['name' => 'listrange', 'id' => 'agenda-listrange', 'class' => 'form-control custom-select']);
        $select_range->add(1 . ' ' . preg_replace('/\(.+\)/', '', $this->cal->lib->gettext('days')), '');

        foreach ([2,5,7,14,30,60,90,180,365] as $days) {
            $select_range->add($days . ' ' . preg_replace('/\(|\)/', '', $this->cal->lib->gettext('days')), $days);
        }

        $html = html::span(
            'input-group',
            html::label(
                ['for' => 'agenda-listrange', 'class' => 'input-group-prepend'],
                html::span('input-group-text', $this->cal->gettext('listrange'))
            )
            . $select_range->show($this->rc->config->get('calendar_agenda_range', $this->cal->defaults['calendar_agenda_range']))
        );

        return html::div($attrib, $html);
    }

    /**
     * Render a HTML select box for calendar selection
     */
    public function calendar_select($attrib = [])
    {
        $attrib['name']       = 'calendar';
        $attrib['is_escaped'] = true;

        $select = new html_select($attrib);

        foreach ((array) $this->cal->driver->list_calendars() as $id => $prop) {
            if (!empty($prop['rights']) && strpos($prop['rights'], 'i') !== false) {
                $select->add($prop['name'], $id);
            }
        }

        return $select->show(null);
    }

    /**
     * Render a HTML select box for user identity selection
     */
    public function identity_select($attrib = [])
    {
        $attrib['name'] = 'identity';

        $select     = new html_select($attrib);
        $identities = $this->rc->user->list_emails();

        foreach ($identities as $ident) {
            $select->add(format_email_recipient($ident['email'], $ident['name']), $ident['identity_id']);
        }

        return $select->show(null);
    }

    /**
     * Render a HTML select box to select an event category
     */
    public function category_select($attrib = [])
    {
        $attrib['name'] = 'categories';

        $select = new html_select($attrib);
        $select->add('---', '');
        foreach (array_keys((array) $this->cal->driver->list_categories()) as $cat) {
            $select->add($cat, $cat);
        }

        return $select->show(null);
    }

    /**
     * Render a HTML select box for status property
     */
    public function status_select($attrib = [])
    {
        $attrib['name'] = 'status';

        $select = new html_select($attrib);
        $select->add('---', '');
        $select->add($this->cal->gettext('status-confirmed'), 'CONFIRMED');
        $select->add($this->cal->gettext('status-cancelled'), 'CANCELLED');
        $select->add($this->cal->gettext('status-tentative'), 'TENTATIVE');

        return $select->show(null);
    }

    /**
     * Render a HTML select box for free/busy/out-of-office property
     */
    public function freebusy_select($attrib = [])
    {
        $attrib['name'] = 'freebusy';

        $select = new html_select($attrib);
        $select->add($this->cal->gettext('free'), 'free');
        $select->add($this->cal->gettext('busy'), 'busy');
        // out-of-office is not supported by libkolabxml (#3220)
        // $select->add($this->cal->gettext('outofoffice'), 'outofoffice');
        $select->add($this->cal->gettext('tentative'), 'tentative');

        return $select->show(null);
    }

    /**
     * Render a HTML select for event priorities
     */
    public function priority_select($attrib = [])
    {
        $attrib['name'] = 'priority';

        $select = new html_select($attrib);
        $select->add('---', '0');
        $select->add('1 ' . $this->cal->gettext('highest'), '1');
        $select->add('2 ' . $this->cal->gettext('high'), '2');
        $select->add('3 ', '3');
        $select->add('4 ', '4');
        $select->add('5 ' . $this->cal->gettext('normal'), '5');
        $select->add('6 ', '6');
        $select->add('7 ', '7');
        $select->add('8 ' . $this->cal->gettext('low'), '8');
        $select->add('9 ' . $this->cal->gettext('lowest'), '9');

        return $select->show(null);
    }

    /**
     * Render HTML form for alarm configuration
     */
    public function alarm_select($attrib = [])
    {
        return $this->cal->lib->alarm_select($attrib, $this->cal->driver->alarm_types, $this->cal->driver->alarm_absolute);
    }

    /**
     * Render HTML for attendee notification warning
     */
    public function edit_attendees_notify($attrib = [])
    {
        $checkbox = new html_checkbox(['name' => '_notify', 'id' => 'edit-attendees-donotify', 'value' => 1, 'class' => 'pretty-checkbox']);
        return html::div($attrib, html::label(null, $checkbox->show(1) . ' ' . $this->cal->gettext('sendnotifications')));
    }

    /**
     * Render HTML for recurrence option to align start date with the recurrence rule
     */
    public function edit_recurrence_sync($attrib = [])
    {
        $checkbox = new html_checkbox(['name' => '_start_sync', 'value' => 1, 'class' => 'pretty-checkbox']);
        return html::div($attrib, html::label(null, $checkbox->show(1) . ' ' . $this->cal->gettext('eventstartsync')));
    }

    /**
     * Generate the form for recurrence settings
     */
    public function recurring_event_warning($attrib = [])
    {
        $attrib['id'] = 'edit-recurring-warning';

        $radio = new html_radiobutton(['name' => '_savemode', 'class' => 'edit-recurring-savemode']);

        $form = html::label(null, $radio->show('', ['value' => 'current']) . $this->cal->gettext('currentevent')) . ' '
            . html::label(null, $radio->show('', ['value' => 'future']) . $this->cal->gettext('futurevents')) . ' '
            . html::label(null, $radio->show('all', ['value' => 'all']) . $this->cal->gettext('allevents')) . ' '
            . html::label(null, $radio->show('', ['value' => 'new']) . $this->cal->gettext('saveasnew'));

        return html::div(
            $attrib,
            html::div('message', $this->cal->gettext('changerecurringeventwarning'))
            . html::div('savemode', $form)
        );
    }

    /**
     * Form for uploading and importing events
     */
    public function events_import_form($attrib = [])
    {
        if (empty($attrib['id'])) {
            $attrib['id'] = 'rcmImportForm';
        }

        // Get max filesize, enable upload progress bar
        $max_filesize = $this->rc->upload_init();

        $accept = '.ics, text/calendar, text/x-vcalendar, application/ics';
        if (class_exists('ZipArchive', false)) {
            $accept .= ', .zip, application/zip';
        }

        $input = new html_inputfield([
                'id'     => 'importfile',
                'type'   => 'file',
                'name'   => '_data',
                'size'   => !empty($attrib['uploadfieldsize']) ? $attrib['uploadfieldsize'] : null,
                'accept' => $accept,
        ]);

        $select = new html_select(['name' => '_range', 'id' => 'event-import-range']);
        $select->add(
            [
                $this->cal->gettext('onemonthback'),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 2]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 3]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 6]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 12]]),
                $this->cal->gettext('all'),
            ],
            ['1','2','3','6','12',0]
        );

        $html = html::div(
            'form-section form-group row',
            html::label(
                ['class' => 'col-sm-4 col-form-label', 'for' => 'importfile'],
                rcube::Q($this->rc->gettext('importfromfile'))
            )
            . html::div(
                'col-sm-8',
                $input->show()
                . html::div('hint', $this->rc->gettext(['name' => 'maxuploadsize', 'vars' => ['size' => $max_filesize]]))
            )
        );

        $html .= html::div(
            'form-section form-group row',
            html::label(
                ['for' => 'event-import-calendar', 'class' => 'col-form-label col-sm-4'],
                $this->cal->gettext('calendar')
            )
            . html::div('col-sm-8', $this->calendar_select(['name' => 'calendar', 'id' => 'event-import-calendar']))
        );

        $html .= html::div(
            'form-section form-group row',
            html::label(
                ['for' => 'event-import-range', 'class' => 'col-form-label col-sm-4'],
                $this->cal->gettext('importrange')
            )
            . html::div('col-sm-8', $select->show(1))
        );

        $this->rc->output->add_gui_object('importform', $attrib['id']);
        $this->rc->output->add_label('import');

        return html::tag('p', null, $this->cal->gettext('importtext'))
            . html::tag(
                'form',
                [
                    'action'  => $this->rc->url(['task' => 'calendar', 'action' => 'import_events']),
                    'method'  => 'post',
                    'enctype' => 'multipart/form-data',
                    'id'      => $attrib['id'],
                ],
                $html
            );
    }

    /**
     * Form to select options for exporting events
     */
    public function events_export_form($attrib = [])
    {
        if (empty($attrib['id'])) {
            $attrib['id'] = 'rcmExportForm';
        }

        $html = html::div(
            'form-section form-group row',
            html::label(
                ['for' => 'event-export-calendar', 'class' => 'col-sm-4 col-form-label'],
                $this->cal->gettext('calendar')
            )
            . html::div('col-sm-8', $this->calendar_select(['name' => 'calendar', 'id' => 'event-export-calendar', 'class' => 'form-control custom-select']))
        );

        $select = new html_select([
                'name'  => 'range',
                'id'    => 'event-export-range',
                'class' => 'form-control custom-select rounded-right',
        ]);

        $select->add(
            [
                $this->cal->gettext('all'),
                $this->cal->gettext('onemonthback'),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 2]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 3]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 6]]),
                $this->cal->gettext(['name' => 'nmonthsback', 'vars' => ['nr' => 12]]),
                $this->cal->gettext('customdate'),
            ],
            [0,'1','2','3','6','12','custom']
        );

        $startdate = new html_inputfield([
                'name'  => 'start',
                'size'  => 11,
                'id'    => 'event-export-startdate',
                'style' => 'display:none',
        ]);

        $html .= html::div(
            'form-section form-group row',
            html::label(
                ['for' => 'event-export-range', 'class' => 'col-sm-4 col-form-label'],
                $this->cal->gettext('exportrange')
            )
            . html::div('col-sm-8 input-group', $select->show(0) . $startdate->show())
        );

        $checkbox = new html_checkbox([
                'name'  => 'attachments',
                'id'    => 'event-export-attachments',
                'value' => 1,
                'class' => 'form-check-input pretty-checkbox',
        ]);

        $html .= html::div(
            'form-section form-check row',
            html::label(
                ['for' => 'event-export-attachments', 'class' => 'col-sm-4 col-form-label'],
                $this->cal->gettext('exportattachments')
            )
            . html::div('col-sm-8', $checkbox->show(1))
        );

        $this->rc->output->add_gui_object('exportform', $attrib['id']);

        return html::tag(
            'form',
            $attrib + [
                'action' => $this->rc->url(['task' => 'calendar', 'action' => 'export_events']),
                'method' => 'post',
                'id'     => $attrib['id'],
            ],
            $html
        );
    }

    /**
     * Handler for calendar form template.
     * The form content could be overriden by the driver
     */
    public function calendar_editform($action, $calendar = [])
    {
        $this->action   = $action;
        $this->calendar = $calendar;

        // load miniColors js/css files
        jqueryui::miniColors();

        $this->rc->output->set_env('pagetitle', $this->cal->gettext('calendarprops'));
        $this->rc->output->add_handler('folderform', [$this, 'calendarform']);
        $this->rc->output->send('libkolab.folderform');
    }

    /**
     * Handler for calendar form template.
     * The form content could be overriden by the driver
     */
    public function calendarform($attrib)
    {
        // compose default calendar form fields
        $input_name  = new html_inputfield(['name' => 'name', 'id' => 'calendar-name', 'size' => 20]);
        $input_color = new html_inputfield(['name' => 'color', 'id' => 'calendar-color', 'size' => 7, 'class' => 'colors']);

        $formfields = [
            'name' => [
                'label' => $this->cal->gettext('name'),
                'value' => $input_name->show($this->calendar['name'] ?? ''),
                'id'    => 'calendar-name',
            ],
            'color' => [
                'label' => $this->cal->gettext('color'),
                'value' => $input_color->show($this->calendar['color'] ?? ''),
                'id'    => 'calendar-color',
            ],
        ];

        if (!empty($this->cal->driver->alarms)) {
            $checkbox = new html_checkbox(['name' => 'showalarms', 'id' => 'calendar-showalarms', 'value' => 1]);

            $formfields['showalarms'] = [
                'label' => $this->cal->gettext('showalarms'),
                'value' => $checkbox->show(!empty($this->calendar['showalarms']) ? 1 : 0),
                'id'    => 'calendar-showalarms',
            ];
        }

        // allow driver to extend or replace the form content
        return html::tag(
            'form',
            $attrib + ['action' => '#', 'method' => 'get', 'id' => 'calendarpropform'],
            $this->cal->driver->calendar_form($this->action, $this->calendar, $formfields)
        );
    }

    /**
     * Render HTML for attendees table
     */
    public function attendees_list($attrib = [])
    {
        // add "noreply" checkbox to attendees table only
        $invitations = strpos($attrib['id'], 'attend') !== false;

        $invite = new html_checkbox(['value' => 1, 'id' => 'edit-attendees-invite']);
        $table  = new html_table(['cols' => 5 + intval($invitations), 'border' => 0, 'cellpadding' => 0, 'class' => 'rectable']);

        $table->add_header('role', $this->cal->gettext('role'));
        $table->add_header('name', $this->cal->gettext(!empty($attrib['coltitle']) ? $attrib['coltitle'] : 'attendee'));
        $table->add_header('availability', $this->cal->gettext('availability'));
        $table->add_header('confirmstate', $this->cal->gettext('confirmstate'));

        if ($invitations) {
            $table->add_header(
                ['class' => 'invite', 'title' => $this->cal->gettext('sendinvitations')],
                $invite->show(1)
                . html::label('edit-attendees-invite', html::span('inner', $this->cal->gettext('sendinvitations')))
            );
        }

        $table->add_header('options', '');

        // hide invite column if disabled by config
        $itip_notify = (int)$this->rc->config->get('calendar_itip_send_option', $this->cal->defaults['calendar_itip_send_option']);
        if ($invitations && !($itip_notify & 2)) {
            $css = sprintf('#%s td.invite, #%s th.invite { display:none !important }', $attrib['id'], $attrib['id']);
            $this->rc->output->add_footer(html::tag('style', ['type' => 'text/css'], $css));
        }

        return $table->show($attrib);
    }

    /**
     * Render HTML for attendees adding form
     */
    public function attendees_form($attrib = [])
    {
        $input = new html_inputfield([
                'name'  => 'participant',
                'id'    => 'edit-attendee-name',
                'class' => 'form-control',
        ]);
        $textarea = new html_textarea([
                'name'  => 'comment',
                'id'    => 'edit-attendees-comment',
                'class' => 'form-control',
                'rows'  => 4,
                'cols'  => 55,
                'title' => $this->cal->gettext('itipcommenttitle'),
        ]);

        return html::div(
            $attrib,
            html::div(
                'form-searchbar',
                $input->show()
                . ' ' .
                html::tag('input', [
                        'type'  => 'button',
                        'class' => 'button',
                        'id'    => 'edit-attendee-add',
                        'value' => $this->cal->gettext('addattendee'),
                ])
                . ' ' .
                html::tag('input', [
                        'type'  => 'button',
                        'class' => 'button',
                        'id'    => 'edit-attendee-schedule',
                        'value' => $this->cal->gettext('scheduletime') . '...',
                ])
            )
            . html::p('attendees-commentbox', html::label('edit-attendees-comment', $this->cal->gettext('itipcomment')) . $textarea->show())
        );
    }

    /**
     * Render HTML for resources adding form
     */
    public function resources_form($attrib = [])
    {
        $input = new html_inputfield(['name' => 'resource', 'id' => 'edit-resource-name', 'class' => 'form-control']);

        return html::div(
            $attrib,
            html::div(
                'form-searchbar',
                $input->show()
                . ' ' .
                html::tag('input', [
                        'type'  => 'button',
                        'class' => 'button',
                        'id'    => 'edit-resource-add',
                        'value' => $this->cal->gettext('addresource'),
                ])
                . ' ' .
                html::tag('input', [
                        'type'  => 'button',
                        'class' => 'button',
                        'id'    => 'edit-resource-find',
                        'value' => $this->cal->gettext('findresources') . '...',
                ])
            )
        );
    }

    /**
     * Render HTML for resources list
     */
    public function resources_list($attrib = [])
    {
        $attrib += ['id' => 'calendar-resources-list'];

        $this->rc->output->add_gui_object('resourceslist', $attrib['id']);

        return html::tag('ul', $attrib, '', html::$common_attrib);
    }

    /**
     *
     */
    public function resource_info($attrib = [])
    {
        $attrib += ['id' => 'calendar-resources-info'];

        $this->rc->output->add_gui_object('resourceinfo', $attrib['id']);
        $this->rc->output->add_gui_object('resourceownerinfo', $attrib['id'] . '-owner');

        // copy address book labels for owner details to client
        $this->rc->output->add_label('name', 'firstname', 'surname', 'department', 'jobtitle', 'email', 'phone', 'address');

        $table_attrib = ['id','class','style','width','summary','cellpadding','cellspacing','border'];

        return html::tag('table', $attrib, html::tag('tbody', null, ''), $table_attrib)
            . html::tag(
                'table',
                ['id' => $attrib['id'] . '-owner', 'style' => 'display:none'] + $attrib,
                html::tag(
                    'thead',
                    null,
                    html::tag(
                        'tr',
                        null,
                        html::tag('td', ['colspan' => 2], rcube::Q($this->cal->gettext('resourceowner')))
                    )
                )
                . html::tag('tbody', null, ''),
                $table_attrib
            );
    }

    /**
     *
     */
    public function resource_calendar($attrib = [])
    {
        $attrib += ['id' => 'calendar-resources-calendar'];

        $this->rc->output->add_gui_object('resourceinfocalendar', $attrib['id']);

        return html::div($attrib, '');
    }

    /**
     * GUI object 'searchform' for the resource finder dialog
     *
     * @param array $attrib Named parameters
     *
     * @return string HTML code for the gui object
     */
    public function resources_search_form($attrib)
    {
        $attrib += [
            'command'       => 'search-resource',
            'reset-command' => 'reset-resource-search',
            'id'            => 'rcmcalresqsearchbox',
            'autocomplete'  => 'off',
            'form-name'     => 'rcmcalresoursqsearchform',
            'gui-object'    => 'resourcesearchform',
        ];

        // add form tag around text field
        return $this->rc->output->search_form($attrib);
    }

    /**
     *
     */
    public function attendees_freebusy_table($attrib = [])
    {
        $table = new html_table(['cols' => 2, 'border' => 0, 'cellspacing' => 0]);
        $table->add(
            'attendees',
            html::tag('h3', 'boxtitle', $this->cal->gettext('tabattendees'))
            . html::div('timesheader', '&nbsp;')
            . html::div(['id' => 'schedule-attendees-list', 'class' => 'attendees-list'], '')
        );
        $table->add(
            'times',
            html::div(
                'scroll',
                html::tag(
                    'table',
                    ['id' => 'schedule-freebusy-times', 'border' => 0, 'cellspacing' => 0],
                    html::tag('thead') . html::tag('tbody')
                )
                . html::div(['id' => 'schedule-event-time', 'style' => 'display:none'], '&nbsp;')
            )
        );

        return $table->show($attrib);
    }

    /**
     *
     */
    public function event_invitebox($attrib = [])
    {
        if (!empty($this->cal->event)) {
            return html::div(
                $attrib,
                $this->cal->itip->itip_object_details_table($this->cal->event, $this->cal->itip->gettext('itipinvitation'))
                . $this->cal->invitestatus
            );
        }

        return '';
    }

    public function event_rsvp_buttons($attrib = [])
    {
        $actions = ['accepted', 'tentative', 'declined'];

        if (empty($attrib['delegate']) || $attrib['delegate'] !== 'false') {
            $actions[] = 'delegated';
        }

        return $this->cal->itip->itip_rsvp_buttons($attrib, $actions);
    }
}
