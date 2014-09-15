/**
 * Base Javascript class for the Calendar plugin
 *
 * @version @package_version@
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2013, Kolab Systems AG <contact@kolabsys.com>
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

// Basic setup for Roundcube calendar client class
function rcube_calendar(settings)
{
    // extend base class
    rcube_libcalendaring.call(this, settings);

    // member vars
    this.ui;
    this.ui_loaded = false;

    // private vars
    var me = this;

    // create new event from current mail message
    this.create_from_mail = function(uid)
    {
      if (uid || (uid = rcmail.get_single_uid())) {
        // load calendar UI (scripts and edit dialog template)
        if (!this.ui_loaded) {
          $.when(
            $.getScript('./plugins/calendar/calendar_ui.js'),
            $.getScript('./plugins/calendar/lib/js/fullcalendar.js'),
            $.get(rcmail.url('calendar/inlineui'), function(html){ $(document.body).append(html); }, 'html')
          ).then(function() {
            // disable attendees feature (autocompletion and stuff is not initialized)
            for (var c in rcmail.env.calendars)
              rcmail.env.calendars[c].attendees = false;
            
            me.ui_loaded = true;
            me.ui = new rcube_calendar_ui(me.settings);
            me.create_from_mail(uid);  // start over
          });
          return;
        }
        else {
          // get message contents for event dialog
          var lock = rcmail.set_busy(true, 'loading');
          rcmail.http_post('calendar/mailtoevent', {
              '_mbox': rcmail.env.mailbox,
              '_uid': uid
            }, lock);
        }
      }
    };
    
    // callback function triggered from server with contents for the new event
    this.mail2event_dialog = function(event)
    {
      if (event.title) {
        this.ui.add_event(event);
        rcmail.message_list.blur();
      }
    };
}

// static methods
rcube_calendar.add_event_from_mail = function(mime_id, status)
{
  // ask user to delete the declined event from the local calendar (#1670)
  var del = false;
  if (rcmail.env.rsvp_saved && status == 'declined') {
    del = confirm(rcmail.gettext('calendar.declinedeleteconfirm'));
  }

  var lock = rcmail.set_busy(true, 'calendar.savingdata');
  rcmail.http_post('calendar/mailimportevent', {
      '_uid': rcmail.env.uid,
      '_mbox': rcmail.env.mailbox,
      '_part': mime_id,
      '_calendar': $('#calendar-saveto').val(),
      '_status': status,
      '_del': del?1:0
    }, lock);

  return false;
};

rcube_calendar.remove_event_from_mail = function(uid, title)
{
  if (confirm(rcmail.gettext('calendar.deleteventconfirm'))) {
    var lock = rcmail.set_busy(true, 'calendar.savingdata');
    rcmail.http_post('calendar/event', {
        e:{ uid:uid },
        action: 'remove'
      }, lock);
  }
};

rcube_calendar.fetch_event_rsvp_status = function(event)
{
/*
  var id = event.uid.replace(rcmail.identifier_expr, '');
  $('#import-'+id+', #rsvp-'+id+', div.rsvp-status').hide();
  $('#loading-'+id).show();
*/
  rcmail.http_post('calendar/event', {
    e:event,
    action:'rsvp-status'
  });
};


/* calendar plugin initialization (for non-calendar tasks) */
window.rcmail && rcmail.addEventListener('init', function(evt) {
  if (rcmail.task != 'calendar') {
    var cal = new rcube_calendar($.extend(rcmail.env.calendar_settings, rcmail.env.libcal_settings));

    rcmail.addEventListener('plugin.update_event_rsvp_status', function(p){
      rcmail.env.rsvp_saved = p.saved;

      if (p.html) {
        // append/replace rsvp status display
        $('#loading-'+p.id).next('.rsvp-status').remove();
        $('#loading-'+p.id).hide().after(p.html);
      }
      else {
        $('#loading-'+p.id).hide();
      }

      // enable/disable rsvp buttons
      $('.rsvp-buttons input.button').prop('disabled', false)
        .filter('.'+String(p.status).toLowerCase()).prop('disabled', p.latest);

      // show rsvp/import buttons with or without calendar selector
      if (!p.select)
        $('#rsvp-'+p.id+' .calendar-select').remove();
      $('#'+p.action+'-'+p.id).show().append(p.select);
    });

    rcmail.addEventListener('plugin.fetch_event_rsvp_status', rcube_calendar.fetch_event_rsvp_status);
    
    // register create-from-mail command to message_commands array
    if (rcmail.env.task == 'mail') {
      rcmail.register_command('calendar-create-from-mail', function() { cal.create_from_mail() });
      rcmail.addEventListener('plugin.mail2event_dialog', function(p){ cal.mail2event_dialog(p) });
      rcmail.addEventListener('plugin.unlock_saving', function(p){ cal.ui && cal.ui.unlock_saving(); });
      
      if (rcmail.env.action != 'show') {
        rcmail.env.message_commands.push('calendar-create-from-mail');
        rcmail.add_element($('<a>'));
      }
      else
        rcmail.enable_command('calendar-create-from-mail', true);

      // add contextmenu item
      if (window.rcm_contextmenu_register_command) {
        rcm_contextmenu_register_command(
          'calendar-create-from-mail',
          function(cmd,el){ cal.create_from_mail() },
          'calendar.createfrommail',
          'moveto');
      }
    }
  }

  rcmail.register_command('plugin.calendar', function() { rcmail.switch_task('calendar'); }, true);
  
  rcmail.addEventListener('plugin.ping_url', function(p){
    var action = p.action;
    p.action = p.event = null;
    new Image().src = rcmail.url(action, p);
  });
});
