<?php
/**
 * User Interface class for the Calendar plugin
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


class calendar_ui
{
  private $rc;
  private $cal;
  private $ready = false;
  public $screen;

  function __construct($cal)
  {
    $this->cal = $cal;
    $this->rc = $cal->rc;
    $this->screen = $this->rc->task == 'calendar' ? ($this->rc->action ? $this->rc->action: 'calendar') : 'other';
  }
    
  /**
   * Calendar UI initialization and requests handlers
   */
  public function init()
  {
    if ($this->ready)  // already done
      return;
      
    // add taskbar button
    $this->cal->add_button(array(
      'command' => 'calendar',
      'class'   => 'button-calendar',
      'classsel' => 'button-calendar button-selected',
      'innerclass' => 'button-inner',
      'label'   => 'calendar.calendar',
      ), 'taskbar');
    
    // load basic client script
    $this->cal->include_script('calendar_base.js');
    
    $skin_path = $this->cal->local_skin_path();
    $this->cal->include_stylesheet($skin_path . '/calendar.css');
    
    $this->ready = true;
  }

  /**
   * Register handler methods for the template engine
   */
  public function init_templates()
  {
    $this->cal->register_handler('plugin.calendar_css', array($this, 'calendar_css'));
    $this->cal->register_handler('plugin.calendar_list', array($this, 'calendar_list'));
    $this->cal->register_handler('plugin.calendar_select', array($this, 'calendar_select'));
    $this->cal->register_handler('plugin.identity_select', array($this, 'identity_select'));
    $this->cal->register_handler('plugin.category_select', array($this, 'category_select'));
    $this->cal->register_handler('plugin.freebusy_select', array($this, 'freebusy_select'));
    $this->cal->register_handler('plugin.priority_select', array($this, 'priority_select'));
    $this->cal->register_handler('plugin.sensitivity_select', array($this, 'sensitivity_select'));
    $this->cal->register_handler('plugin.alarm_select', array($this, 'alarm_select'));
    $this->cal->register_handler('plugin.recurrence_form', array($this, 'recurrence_form'));
    $this->cal->register_handler('plugin.attachments_form', array($this, 'attachments_form'));
    $this->cal->register_handler('plugin.attachments_list', array($this, 'attachments_list'));
    $this->cal->register_handler('plugin.filedroparea', array($this, 'file_drop_area'));
    $this->cal->register_handler('plugin.attendees_list', array($this, 'attendees_list'));
    $this->cal->register_handler('plugin.attendees_form', array($this, 'attendees_form'));
    $this->cal->register_handler('plugin.attendees_freebusy_table', array($this, 'attendees_freebusy_table'));
    $this->cal->register_handler('plugin.edit_attendees_notify', array($this, 'edit_attendees_notify'));
    $this->cal->register_handler('plugin.edit_recurring_warning', array($this, 'recurring_event_warning'));
    $this->cal->register_handler('plugin.event_rsvp_buttons', array($this, 'event_rsvp_buttons'));
    $this->cal->register_handler('plugin.angenda_options', array($this, 'angenda_options'));
    $this->cal->register_handler('plugin.events_import_form', array($this, 'events_import_form'));
    $this->cal->register_handler('plugin.searchform', array($this->rc->output, 'search_form'));  // use generic method from rcube_template
  }

  /**
   * Adds CSS stylesheets to the page header
   */
  public function addCSS()
  {
    $skin_path = $this->cal->local_skin_path();
    $this->cal->include_stylesheet($skin_path . '/fullcalendar.css');
    $this->cal->include_stylesheet($skin_path . '/jquery.miniColors.css');
  }

  /**
   * Adds JS files to the page header
   */
  public function addJS()
  {
    $this->cal->include_script('calendar_ui.js');
    $this->cal->include_script('lib/js/fullcalendar.js');
    $this->cal->include_script('lib/js/jquery.miniColors.min.js');
  }

  /**
   *
   */
  function calendar_css($attrib = array())
  {
    $mode = $this->rc->config->get('calendar_event_coloring', $this->cal->defaults['calendar_event_coloring']);
    $categories = $this->cal->driver->list_categories();
    $css = "\n";
    
    foreach ((array)$categories as $class => $color) {
      if (empty($color))
        continue;
      
      $class = 'cat-' . asciiwords(strtolower($class), true);
      $css  .= ".$class { color: #$color }\n";
      if ($mode > 0) {
        if ($mode == 2) {
          $css .= ".fc-event-$class .fc-event-bg {";
          $css .= " opacity: 0.9;";
          $css .= " filter: alpha(opacity=90);";
        }
        else {
          $css .= ".fc-event-$class.fc-event-skin, ";
          $css .= ".fc-event-$class .fc-event-skin, ";
          $css .= ".fc-event-$class .fc-event-inner {";
        }
        $css .= " background-color: #" . $color . ";";
        if ($mode % 2)
          $css .= " border-color: #$color;";
        $css .= "}\n";
      }
    }
    
    $calendars = $this->cal->driver->list_calendars();
    foreach ((array)$calendars as $id => $prop) {
      if (!$prop['color'])
        continue;
      $color = $prop['color'];
      $class = 'cal-' . asciiwords($id, true);
      $css .= "li.$class, #eventshow .$class { color: #$color }\n";
      if ($mode != 1) {
        if ($mode == 3) {
          $css .= ".fc-event-$class .fc-event-bg {";
          $css .= " opacity: 0.9;";
          $css .= " filter: alpha(opacity=90);";
        }
        else {
          $css .= ".fc-event-$class, ";
          $css .= ".fc-event-$class .fc-event-inner {";
        }
        if (!$attrib['printmode'])
          $css .= " background-color: #$color;";
        if ($mode % 2 == 0)
        $css .= " border-color: #$color;";
        $css .= "}\n";
      }
      $css .= ".$class .handle { background-color: #$color; }";
    }
    
    return html::tag('style', array('type' => 'text/css'), $css);
  }

  /**
   *
   */
  function calendar_list($attrib = array())
  {
    $calendars = $this->cal->driver->list_calendars();

    $li = '';
    foreach ((array)$calendars as $id => $prop) {
      if ($attrib['activeonly'] && !$prop['active'])
        continue;
      
      unset($prop['user_id']);
      $prop['alarms'] = $this->cal->driver->alarms;
      $prop['attendees'] = $this->cal->driver->attendees;
      $prop['freebusy'] = $this->cal->driver->freebusy;
      $prop['attachments'] = $this->cal->driver->attachments;
      $prop['undelete'] = $this->cal->driver->undelete;
      $prop['feedurl'] = $this->cal->get_url(array('_cal' => $this->cal->ical_feed_hash($id) . '.ics', 'action' => 'feed'));
      $jsenv[$id] = $prop;

      $html_id = html_identifier($id);
      $class = 'cal-'  . asciiwords($id, true);

      if ($prop['readonly'])
        $class .= ' readonly';
      if ($prop['class_name'])
        $class .= ' '.$prop['class_name'];

      $li .= html::tag('li', array('id' => 'rcmlical' . $html_id, 'class' => $class),
        html::tag('input', array('type' => 'checkbox', 'name' => '_cal[]', 'value' => $id, 'checked' => $prop['active']), '') .
        html::span('handle', '&nbsp;') .
        html::span('calname', $prop['name']));
    }

    $this->rc->output->set_env('calendars', $jsenv);
    $this->rc->output->add_gui_object('folderlist', $attrib['id']);

    return html::tag('ul', $attrib, $li, html::$common_attrib);
  }

  /**
   *
   */
  function angenda_options($attrib = array())
  {
    $attrib += array('id' => 'agendaoptions');
    $attrib['style'] .= 'display:none';
    
    $select_range = new html_select(array('name' => 'listrange', 'id' => 'agenda-listrange'));
    $select_range->add(1 . ' ' . preg_replace('/\(.+\)/', '', $this->cal->gettext('days')), $days);
    foreach (array(2,5,7,14,30,60,90) as $days)
      $select_range->add($days . ' ' . preg_replace('/\(|\)/', '', $this->cal->gettext('days')), $days);
    
    $html .= html::label('agenda-listrange', $this->cal->gettext('listrange'));
    $html .= $select_range->show($this->rc->config->get('calendar_agenda_range', $this->cal->defaults['calendar_agenda_range']));
    
    $select_sections = new html_select(array('name' => 'listsections', 'id' => 'agenda-listsections'));
    $select_sections->add('---', '');
    foreach (array('day' => 'days', 'week' => 'weeks', 'month' => 'months', 'smart' => 'smartsections') as $val => $label)
      $select_sections->add(preg_replace('/\(|\)/', '', ucfirst($this->cal->gettext($label))), $val);
    
    $html .= html::span('spacer', '&nbsp;');
    $html .= html::label('agenda-listsections', $this->cal->gettext('listsections'));
    $html .= $select_sections->show($this->rc->config->get('calendar_agenda_sections', $this->cal->defaults['calendar_agenda_sections']));
    
    return html::div($attrib, $html);
  }

  /**
   * Render a HTML select box for calendar selection
   */
  function calendar_select($attrib = array())
  {
    $attrib['name']       = 'calendar';
    $attrib['is_escaped'] = true;
    $select = new html_select($attrib);

    foreach ((array)$this->cal->driver->list_calendars() as $id => $prop) {
      if (!$prop['readonly'])
        $select->add($prop['name'], $id);
    }

    return $select->show(null);
  }

  /**
   * Render a HTML select box for user identity selection
   */
  function identity_select($attrib = array())
  {
    $attrib['name'] = 'identity';
    $select         = new html_select($attrib);
    $identities     = $this->rc->user->list_identities();

    foreach ($identities as $ident) {
        $select->add(format_email_recipient($ident['email'], $ident['name']), $ident['identity_id']);
    }

    return $select->show(null);
  }

  /**
   * Render a HTML select box to select an event category
   */
  function category_select($attrib = array())
  {
    $attrib['name'] = 'categories';
    $select = new html_select($attrib);
    $select->add('---', '');
    foreach (array_keys((array)$this->cal->driver->list_categories()) as $cat) {
      $select->add($cat, $cat);
    }

    return $select->show(null);
  }

  /**
   * Render a HTML select box for free/busy/out-of-office property
   */
  function freebusy_select($attrib = array())
  {
    $attrib['name'] = 'freebusy';
    $select = new html_select($attrib);
    $select->add($this->cal->gettext('free'), 'free');
    $select->add($this->cal->gettext('busy'), 'busy');
    $select->add($this->cal->gettext('outofoffice'), 'outofoffice');
    $select->add($this->cal->gettext('tentative'), 'tentative');
    return $select->show(null);
  }

  /**
   * Render a HTML select for event priorities
   */
  function priority_select($attrib = array())
  {
    $attrib['name'] = 'priority';
    $select = new html_select($attrib);
    $select->add('---', '0');
    $select->add('1 '.$this->cal->gettext('highest'), '1');
    $select->add('2 '.$this->cal->gettext('high'),    '2');
    $select->add('3 ',                                '3');
    $select->add('4 ',                                '4');
    $select->add('5 '.$this->cal->gettext('normal'),  '5');
    $select->add('6 ',                                '6');
    $select->add('7 ',                                '7');
    $select->add('8 '.$this->cal->gettext('low'),     '8');
    $select->add('9 '.$this->cal->gettext('lowest'),  '9');
    return $select->show(null);
  }
  
  /**
   * Render HTML input for sensitivity selection
   */
  function sensitivity_select($attrib = array())
  {
    $attrib['name'] = 'sensitivity';
    $select = new html_select($attrib);
    $select->add($this->cal->gettext('public'), 'public');
    $select->add($this->cal->gettext('private'), 'private');
    $select->add($this->cal->gettext('confidential'), 'confidential');
    return $select->show(null);
  }
  
  /**
   * Render HTML form for alarm configuration
   */
  function alarm_select($attrib = array())
  {
    return $this->cal->lib->alarm_select($attrib, $this->cal->driver->alarm_types, $this->cal->driver->alarm_absolute);
  }

  /**
   *
   */
  function edit_attendees_notify($attrib = array())
  {
    $checkbox = new html_checkbox(array('name' => '_notify', 'id' => 'edit-attendees-donotify', 'value' => 1));
    return html::div($attrib, html::label(null, $checkbox->show(1) . ' ' . $this->cal->gettext('sendnotifications')));
  }

  /**
   * Generate the form for recurrence settings
   */
  function recurring_event_warning($attrib = array())
  {
    $attrib['id'] = 'edit-recurring-warning';
    
    $radio = new html_radiobutton(array('name' => '_savemode', 'class' => 'edit-recurring-savemode'));
    $form = html::label(null, $radio->show('', array('value' => 'current')) . $this->cal->gettext('currentevent')) . ' ' .
       html::label(null, $radio->show('', array('value' => 'future')) . $this->cal->gettext('futurevents')) . ' ' .
       html::label(null, $radio->show('all', array('value' => 'all')) . $this->cal->gettext('allevents')) . ' ' .
       html::label(null, $radio->show('', array('value' => 'new')) . $this->cal->gettext('saveasnew'));
       
    return html::div($attrib, html::div('message', html::span('ui-icon ui-icon-alert', '') . $this->cal->gettext('changerecurringeventwarning')) . html::div('savemode', $form));
  }
  
  /**
   * Generate the form for recurrence settings
   */
  function recurrence_form($attrib = array())
  {
    switch ($attrib['part']) {
      // frequency selector
      case 'frequency':
        $select = new html_select(array('name' => 'frequency', 'id' => 'edit-recurrence-frequency'));
        $select->add($this->cal->gettext('never'), '');
        $select->add($this->cal->gettext('daily'), 'DAILY');
        $select->add($this->cal->gettext('weekly'), 'WEEKLY');
        $select->add($this->cal->gettext('monthly'), 'MONTHLY');
        $select->add($this->cal->gettext('yearly'), 'YEARLY');
        $html = html::label('edit-frequency', $this->cal->gettext('frequency')) . $select->show('');
        break;

      // daily recurrence
      case 'daily':
        $select = $this->interval_selector(array('name' => 'interval', 'class' => 'edit-recurrence-interval', 'id' => 'edit-recurrence-interval-daily'));
        $html = html::div($attrib, html::label(null, $this->cal->gettext('every')) . $select->show(1) . html::span('label-after', $this->cal->gettext('days')));
        break;

      // weekly recurrence form
      case 'weekly':
        $select = $this->interval_selector(array('name' => 'interval', 'class' => 'edit-recurrence-interval', 'id' => 'edit-recurrence-interval-weekly'));
        $html = html::div($attrib, html::label(null, $this->cal->gettext('every')) . $select->show(1) . html::span('label-after', $this->cal->gettext('weeks')));
        // weekday selection
        $daymap = array('sun','mon','tue','wed','thu','fri','sat');
        $checkbox = new html_checkbox(array('name' => 'byday', 'class' => 'edit-recurrence-weekly-byday'));
        $first = $this->rc->config->get('calendar_first_day', 1);
        for ($weekdays = '', $j = $first; $j <= $first+6; $j++) {
            $d = $j % 7;
            $weekdays .= html::label(array('class' => 'weekday'), $checkbox->show('', array('value' => strtoupper(substr($daymap[$d], 0, 2)))) . $this->cal->gettext($daymap[$d])) . ' ';
        }
        $html .= html::div($attrib, html::label(null, $this->cal->gettext('bydays')) . $weekdays);
        break;

      // monthly recurrence form
      case 'monthly':
        $select = $this->interval_selector(array('name' => 'interval', 'class' => 'edit-recurrence-interval', 'id' => 'edit-recurrence-interval-monthly'));
        $html = html::div($attrib, html::label(null, $this->cal->gettext('every')) . $select->show(1) . html::span('label-after', $this->cal->gettext('months')));

        $checkbox = new html_checkbox(array('name' => 'bymonthday', 'class' => 'edit-recurrence-monthly-bymonthday'));
        for ($monthdays = '', $d = 1; $d <= 31; $d++) {
            $monthdays .= html::label(array('class' => 'monthday'), $checkbox->show('', array('value' => $d)) . $d);
            $monthdays .= $d % 7 ? ' ' : html::br();
        }

        // rule selectors
        $radio = new html_radiobutton(array('name' => 'repeatmode', 'class' => 'edit-recurrence-monthly-mode'));
        $table = new html_table(array('cols' => 2, 'border' => 0, 'cellpadding' => 0, 'class' => 'formtable'));
        $table->add('label', html::label(null, $radio->show('BYMONTHDAY', array('value' => 'BYMONTHDAY')) . ' ' . $this->cal->gettext('each')));
        $table->add(null, $monthdays);
        $table->add('label', html::label(null, $radio->show('', array('value' => 'BYDAY')) . ' ' . $this->cal->gettext('onevery')));
        $table->add(null, $this->rrule_selectors($attrib['part']));
        
        $html .= html::div($attrib, $table->show());

        break;

      // annually recurrence form
      case 'yearly':
        $select = $this->interval_selector(array('name' => 'interval', 'class' => 'edit-recurrence-interval', 'id' => 'edit-recurrence-interval-yearly'));
        $html = html::div($attrib, html::label(null, $this->cal->gettext('every')) . $select->show(1) . html::span('label-after', $this->cal->gettext('years')));
        // month selector
        $monthmap = array('','jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec');
        $checkbox = new html_checkbox(array('name' => 'bymonth', 'class' => 'edit-recurrence-yearly-bymonth'));
        for ($months = '', $m = 1; $m <= 12; $m++) {
            $months .= html::label(array('class' => 'month'), $checkbox->show(null, array('value' => $m)) . $this->cal->gettext($monthmap[$m]));
            $months .= $m % 4 ? ' ' : html::br();
        }
        $html .= html::div($attrib + array('id' => 'edit-recurrence-yearly-bymonthblock'), $months);
        
        // day rule selection
        $html .= html::div($attrib, html::label(null, $this->cal->gettext('onevery')) . $this->rrule_selectors($attrib['part'], '---'));
        break;

      // end of recurrence form
      case 'until':
        $radio = new html_radiobutton(array('name' => 'repeat', 'class' => 'edit-recurrence-until'));
        $select = $this->interval_selector(array('name' => 'times', 'id' => 'edit-recurrence-repeat-times'));
        $input = new html_inputfield(array('name' => 'untildate', 'id' => 'edit-recurrence-enddate', 'size' => "10"));

        $table = new html_table(array('cols' => 2, 'border' => 0, 'cellpadding' => 0, 'class' => 'formtable'));

        $table->add('label', ucfirst($this->cal->gettext('recurrencend')));
        $table->add(null, html::label(null, $radio->show('', array('value' => '', 'id' => 'edit-recurrence-repeat-forever')) . ' ' .
          $this->cal->gettext('forever')));

        $table->add('label', '');
        $table->add(null, $radio->show('', array('value' => 'count', 'id' => 'edit-recurrence-repeat-count')) . ' ' .
          $this->cal->gettext(array(
            'name' => 'forntimes',
            'vars' => array('nr' => $select->show(1)))
          ));

        $table->add('label', '');
        $table->add(null, $radio->show('', array('value' => 'until', 'id' => 'edit-recurrence-repeat-until')) . ' ' .
          $this->cal->gettext('untildate') . ' ' . $input->show(''));
        $html = $table->show();
        break;
    }

    return $html;
  }

  /**
   * Input field for interval selection
   */
  private function interval_selector($attrib)
  {
    $select = new html_select($attrib);
    $select->add(range(1,30), range(1,30));
    return $select;
  }
  
  /**
   * Drop-down menus for recurrence rules like "each last sunday of"
   */
  private function rrule_selectors($part, $noselect = null)
  {
    // rule selectors
    $select_prefix = new html_select(array('name' => 'bydayprefix', 'id' => "edit-recurrence-$part-prefix"));
    if ($noselect) $select_prefix->add($noselect, '');
    $select_prefix->add(array(
        $this->cal->gettext('first'),
        $this->cal->gettext('second'),
        $this->cal->gettext('third'),
        $this->cal->gettext('fourth'),
        $this->cal->gettext('last')
      ),
      array(1, 2, 3, 4, -1));
    
    $select_wday = new html_select(array('name' => 'byday', 'id' => "edit-recurrence-$part-byday"));
    if ($noselect) $select_wday->add($noselect, '');
    
    $daymap = array('sunday','monday','tuesday','wednesday','thursday','friday','saturday');
    $first = $this->rc->config->get('calendar_first_day', 1);
    for ($j = $first; $j <= $first+6; $j++) {
      $d = $j % 7;
      $select_wday->add($this->cal->gettext($daymap[$d]), strtoupper(substr($daymap[$d], 0, 2)));
    }
    
    return $select_prefix->show() . '&nbsp;' . $select_wday->show();
  }

  /**
   * Form for uploading and importing events
   */
  function events_import_form($attrib = array())
  {
    if (!$attrib['id'])
      $attrib['id'] = 'rcmImportForm';

    // Get max filesize, enable upload progress bar
    $max_filesize = rcube_upload_init();

    $input = new html_inputfield(array(
      'type' => 'file', 'name' => '_data', 'size' => $attrib['uploadfieldsize']));

    $select = new html_select(array('name' => '_range', 'id' => 'event-import-range'));
    $select->add(array(
        $this->cal->gettext('onemonthback'),
        $this->cal->gettext(array('name' => 'nmonthsback', 'vars' => array('nr'=>2))),
        $this->cal->gettext(array('name' => 'nmonthsback', 'vars' => array('nr'=>6))),
        $this->cal->gettext(array('name' => 'nmonthsback', 'vars' => array('nr'=>12))),
        $this->cal->gettext('all'),
      ),
      array('1','2','6','12',0));

    $html .= html::div('form-section',
      html::div(null, $input->show()) .
      html::div('hint', rcube_label(array('name' => 'maxuploadsize', 'vars' => array('size' => $max_filesize))))
    );

    $html .= html::div('form-section',
      html::label('event-import-calendar', $this->cal->gettext('calendar')) .
      $this->calendar_select(array('name' => 'calendar', 'id' => 'event-import-calendar'))
    );

    $html .= html::div('form-section',
      html::label('event-import-range', $this->cal->gettext('importrange')) .
      $select->show(1)
    );

    $this->rc->output->add_gui_object('importform', $attrib['id']);
    $this->rc->output->add_label('import');

    return html::tag('form', array('action' => $this->rc->url(array('task' => 'calendar', 'action' => 'import_events')),
      'method' => "post", 'enctype' => 'multipart/form-data', 'id' => $attrib['id']),
      $html
    );
  }

  /**
   * Generate the form for event attachments upload
   */
  function attachments_form($attrib = array())
  {
    // add ID if not given
    if (!$attrib['id'])
      $attrib['id'] = 'rcmUploadForm';

    // Get max filesize, enable upload progress bar
    $max_filesize = rcube_upload_init();

    $button = new html_inputfield(array('type' => 'button'));
    $input = new html_inputfield(array(
      'type' => 'file', 'name' => '_attachments[]',
      'multiple' => 'multiple', 'size' => $attrib['attachmentfieldsize']));

    return html::div($attrib,
      html::div(null, $input->show()) .
      html::div('formbuttons', $button->show(rcube_label('upload'), array('class' => 'button mainaction',
        'onclick' => JS_OBJECT_NAME . ".upload_file(this.form)"))) .
      html::div('hint', rcube_label(array('name' => 'maxuploadsize', 'vars' => array('size' => $max_filesize))))
    );
  }

  /**
   * Register UI object for HTML5 drag & drop file upload
   */
  function file_drop_area($attrib = array())
  {
      if ($attrib['id']) {
          $this->rc->output->add_gui_object('filedrop', $attrib['id']);
          $this->rc->output->set_env('filedrop', array('action' => 'upload', 'fieldname' => '_attachments'));
      }
  }

  /**
   * Generate HTML element for attachments list
   */
  function attachments_list($attrib = array())
  {
    if (!$attrib['id'])
      $attrib['id'] = 'rcmAttachmentList';

    $skin_path = $this->cal->local_skin_path();
    if ($attrib['deleteicon']) {
      $_SESSION[calendar::SESSION_KEY . '_deleteicon'] = $skin_path . $attrib['deleteicon'];
      $this->rc->output->set_env('deleteicon', $skin_path . $attrib['deleteicon']);
    }
    if ($attrib['cancelicon'])
      $this->rc->output->set_env('cancelicon', $skin_path . $attrib['cancelicon']);
    if ($attrib['loadingicon'])
      $this->rc->output->set_env('loadingicon', $skin_path . $attrib['loadingicon']);

    $this->rc->output->add_gui_object('attachmentlist', $attrib['id']);
    $this->attachmentlist_id = $attrib['id'];

    return html::tag('ul', $attrib, '', html::$common_attrib);
  }

  /**
   * Handler for calendar form template.
   * The form content could be overriden by the driver
   */
  function calendar_editform($action, $calendar = array())
  {
    // compose default calendar form fields
    $input_name = new html_inputfield(array('name' => 'name', 'id' => 'calendar-name', 'size' => 20));
    $input_color = new html_inputfield(array('name' => 'color', 'id' => 'calendar-color', 'size' => 6));

    $formfields = array(
      'name' => array(
        'label' => $this->cal->gettext('name'),
        'value' => $input_name->show($calendar['name']),
        'id' => 'calendar-name',
      ),
      'color' => array(
        'label' => $this->cal->gettext('color'),
        'value' => $input_color->show($calendar['color']),
        'id' => 'calendar-color',
      ),
    );

    if ($this->cal->driver->alarms) {
      $checkbox = new html_checkbox(array('name' => 'showalarms', 'id' => 'calendar-showalarms', 'value' => 1));
      $formfields['showalarms'] = array(
        'label' => $this->cal->gettext('showalarms'),
        'value' => $checkbox->show($calendar['showalarms']?1:0),
        'id' => 'calendar-showalarms',
      );
    }

    // allow driver to extend or replace the form content
    return html::tag('form', array('action' => "#", 'method' => "get", 'id' => 'calendarpropform'),
      $this->cal->driver->calendar_form($action, $calendar, $formfields)
    );
  }

  /**
   *
   */
  function attendees_list($attrib = array())
  {
    $table = new html_table(array('cols' => 5, 'border' => 0, 'cellpadding' => 0, 'class' => 'rectable'));
    $table->add_header('role', $this->cal->gettext('role'));
    $table->add_header('name', $this->cal->gettext('attendee'));
    $table->add_header('availability', $this->cal->gettext('availability'));
    $table->add_header('confirmstate', $this->cal->gettext('confirmstate'));
    $table->add_header('options', '');
    
    return $table->show($attrib);
  }

  /**
   *
   */
  function attendees_form($attrib = array())
  {
    $input = new html_inputfield(array('name' => 'participant', 'id' => 'edit-attendee-name', 'size' => 30));
    $checkbox = new html_checkbox(array('name' => 'invite', 'id' => 'edit-attendees-invite', 'value' => 1));
    
    return html::div($attrib,
      html::div(null, $input->show() . " " .
        html::tag('input', array('type' => 'button', 'class' => 'button', 'id' => 'edit-attendee-add', 'value' => $this->cal->gettext('addattendee'))) . " " .
        html::tag('input', array('type' => 'button', 'class' => 'button', 'id' => 'edit-attendee-schedule', 'value' => $this->cal->gettext('scheduletime').'...'))) .
      html::p('attendees-invitebox', html::label(null, $checkbox->show(1) . $this->cal->gettext('sendinvitations')))
      );
  }
  
  /**
   *
   */
  function attendees_freebusy_table($attrib = array())
  {
    $table = new html_table(array('cols' => 2, 'border' => 0, 'cellspacing' => 0));
    $table->add('attendees',
      html::tag('h3', 'boxtitle', $this->cal->gettext('tabattendees')) .
      html::div('timesheader', '&nbsp;') .
      html::div(array('id' => 'schedule-attendees-list', 'class' => 'attendees-list'), '')
    );
    $table->add('times',
      html::div('scroll',
        html::tag('table', array('id' => 'schedule-freebusy-times', 'border' => 0, 'cellspacing' => 0), html::tag('thead') . html::tag('tbody')) .
        html::div(array('id' => 'schedule-event-time', 'style' => 'display:none'), '&nbsp;')
      )
    );
    
    return $table->show($attrib);
  }

  /**
   * Render event details in a table
   */
  function event_details_table($event, $title)
  {
    $table = new html_table(array('cols' => 2, 'border' => 0, 'class' => 'calendar-eventdetails'));
    $table->add('ititle', $title);
    $table->add('title', Q($event['title']));
    $table->add('label', $this->cal->gettext('date'));
    $table->add('location', Q($this->cal->lib->event_date_text($event)));
    if ($event['location']) {
      $table->add('label', $this->cal->gettext('location'));
      $table->add('location', Q($event['location']));
    }
    
    return $table->show();
  }

  /**
   *
   */
  function event_invitebox($attrib = array())
  {
    if ($this->cal->event) {
      return html::div($attrib,
        $this->event_details_table($this->cal->event, $this->cal->gettext('itipinvitation')) .
        $this->cal->invitestatus
      );
    }
    
    return '';
  }

  function event_rsvp_buttons($attrib = array())
  {
    $attrib += array('type' => 'button');
    foreach (array('accepted','tentative','declined') as $method) {
      $buttons .= html::tag('input', array(
        'type' => $attrib['type'],
        'name' => $attrib['iname'],
        'class' => 'button',
        'rel' => $method,
        'value' => $this->cal->gettext('itip' . $method),
      ));
    }
    
    return html::div($attrib,
      html::div('label', $this->cal->gettext('acceptinvitation')) .
      html::div('rsvp-buttons', $buttons));
  }

}
