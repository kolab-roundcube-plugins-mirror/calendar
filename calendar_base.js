/**
 * Base Javascript class for the Calendar plugin
 *
 * @author Lazlo Westerhof <hello@lazlo.me>
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this page.
 *
 * Copyright (C) 2010, Lazlo Westerhof <hello@lazlo.me>
 * Copyright (C) 2013-2015, Kolab Systems AG <contact@kolabsys.com>
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
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this page.
 */

// Basic setup for Roundcube calendar client class
function rcube_calendar(settings)
{
    // extend base class
    rcube_libcalendaring.call(this, settings);

    // create new event from current mail message
    this.create_from_mail = function(uid)
    {
      if (!uid && !(uid = rcmail.get_single_uid())) {
        return;
      }

      var url = {_mbox: rcmail.env.mailbox, _uid: uid, _framed: 1},
        buttons = {},
        button_classes = ['mainaction save', 'cancel'],
        title = rcmail.gettext('calendar.createfrommail'),
        dialog = $('<iframe>').attr({
            id: 'kolabcalendarinlinegui',
            name: 'kolabcalendardialog',
            src: rcmail.url('calendar/dialog-ui', url)
          });

      // dialog buttons
      buttons[rcmail.gettext('save')] = function() {
        var frame = rcmail.get_frame_window('kolabcalendarinlinegui');
        frame.rcmail.command('event-save');
      };

      buttons[rcmail.gettext('cancel')] = function() {
        dialog.dialog('destroy');
      };

      // open jquery UI dialog
      window.kolab_event_dialog_element = dialog = rcmail.show_popup_dialog(dialog, title, buttons, {
          button_classes: button_classes,
          minWidth: 500,
          width: 600,
          height: 600
      });
    };

    // handler for attachment-save-calendar commands
    this.save_to_calendar = function(p)
    {
      // TODO: show dialog to select the calendar for importing
      if (this.selected_attachment && window.rcube_libcalendaring) {
        rcmail.http_post('calendar/mailimportattach', {
            _uid: rcmail.env.uid,
            _mbox: rcmail.env.mailbox,
            _part: this.selected_attachment
            // _calendar: $('#calendar-attachment-saveto').val(),
          }, rcmail.set_busy(true, 'itip.savingdata'));
      }
    };
}


/* calendar plugin initialization (for non-calendar tasks) */
window.rcmail && rcmail.addEventListener('init', function(evt) {
  if (rcmail.task != 'calendar') {
    var cal = new rcube_calendar($.extend(rcmail.env.calendar_settings, rcmail.env.libcal_settings));

    // register create-from-mail command to message_commands array
    if (rcmail.env.task == 'mail') {
      rcmail.register_command('calendar-create-from-mail', function() { cal.create_from_mail(); });
      rcmail.register_command('attachment-save-calendar', function() { cal.save_to_calendar(); });

      if (rcmail.env.action != 'show') {
        rcmail.env.message_commands.push('calendar-create-from-mail');
      }
      else {
        rcmail.enable_command('calendar-create-from-mail', true);
      }

      rcmail.addEventListener('beforemenu-open', function(p) {
        if (p.menu == 'attachmentmenu') {
          cal.selected_attachment = p.id;
          var mimetype = rcmail.env.attachments[p.id];
          rcmail.enable_command('attachment-save-calendar', mimetype == 'text/calendar' || mimetype == 'text/x-vcalendar' || mimetype == 'application/ics');
        }
      });
    }
  }

  rcmail.register_command('plugin.calendar', function() { rcmail.switch_task('calendar'); }, true);

  rcmail.addEventListener('plugin.ping_url', function(p) {
    var action = p.action;
    p.action = p.event = null;
    new Image().src = rcmail.url(action, p);
  });
});
