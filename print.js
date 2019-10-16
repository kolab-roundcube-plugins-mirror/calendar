/**
 * Print view for the Calendar plugin
 *
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2011-2018, Kolab Systems AG <contact@kolabsys.com>
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
 * for the JavaScript code in this file.
 */


/* calendar plugin printing code */
window.rcmail && rcmail.addEventListener('init', function(evt) {

  // quote html entities
  var Q = function(str)
  {
    return String(str).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  var rc_loading;
  var showdesc = true;
  var desc_elements = [];
  var settings = $.extend(rcmail.env.calendar_settings, rcmail.env.libcal_settings);

  // create list of event sources AKA calendars
  var id, src, event_sources = [];
  var add_url = '&mode=print' + (rcmail.env.search ? '&q='+escape(rcmail.env.search) : '');
  for (id in rcmail.env.calendars) {
    if (!rcmail.env.calendars[id].active)
      continue;

    source = $.extend({
      url: "./?_task=calendar&_action=load_events&source=" + escape(id) + add_url,
      className: 'fc-event-cal-'+id,
      id: id
    }, rcmail.env.calendars[id]);

    source.color = '#' + source.color.replace(/^#/, '');

    if (source.color.match(/^#f+$/i))
      source.color = '#ccc';

    event_sources.push(source);
  }

  var viewdate = new Date();
  if (rcmail.env.date)
    viewdate.setTime(rcmail.env.date * 1000);

  // initalize the fullCalendar plugin
  var fc = $('#calendar').fullCalendar({
    header: {
      left: '',
      center: 'title',
      right: 'agendaDay,agendaWeek,month,list'
    },
    theme: false,
    aspectRatio: 0.85,
    selectable: false,
    editable: false,
    timezone: false,  // will treat the given date strings as in local (browser's) timezone
    monthNames: settings.months,
    monthNamesShort: settings.months_short,
    dayNames: settings.days,
    dayNamesShort: settings.days_short,
    weekNumbers: settings.show_weekno > 0,
    weekNumberTitle: rcmail.gettext('weekshort', 'calendar') + ' ',
    firstDay: settings.first_day,
    firstHour: settings.first_hour,
    slotDuration: {minutes: 60/settings.timeslots},
    businessHours: {
      start: settings.work_start + ':00',
      end: settings.work_end + ':00'
    },
    views: {
      list: {
        titleFormat: settings.dates_long,
        listDayFormat: settings.date_long,
        visibleRange: function(currentDate) {
          return {
            start: currentDate.clone(),
            end: currentDate.clone().add(settings.agenda_range, 'days')
          }
        }
      },
      month: {
        columnFormat: 'ddd', // Mon
        titleFormat: 'MMMM YYYY',
        eventLimit: 10
      },
      week: {
        columnFormat: 'ddd ' + settings.date_short, // Mon 9/7
        titleFormat: settings.dates_long
      },
      day: {
        columnFormat: 'dddd ' + settings.date_short,  // Monday 9/7
        titleFormat: 'dddd ' + settings.date_long
      }
    },
    timeFormat: settings.time_format,
    slotLabelFormat: settings.time_format,
    allDayText: rcmail.gettext('all-day', 'calendar'),
    defaultDate: viewdate,
    defaultView: rcmail.env.view,
    eventSources: event_sources,
    buttonText: {
      today: settings['today'],
      day: rcmail.gettext('day', 'calendar'),
      week: rcmail.gettext('week', 'calendar'),
      month: rcmail.gettext('month', 'calendar'),
      list: rcmail.gettext('agenda', 'calendar')
    },
    buttonIcons: {
     prev: 'left-single-arrow',
     next: 'right-single-arrow'
    },
    eventLimitText: function(num) {
      return rcmail.gettext('andnmore', 'calendar').replace('$nr', num);
    },
    loading: function(isLoading) {
      rc_loading = rcmail.set_busy(isLoading, 'loading', rc_loading);
    },
    // event rendering
    eventRender: function(event, element, view) {
      if (view.name == 'list') {
        var loc = $('<td>').attr('class', 'fc-event-location');
        if (event.location)
          loc.text(event.location);
        element.find('.fc-list-item-title').after(loc);

        // we can't add HTML elements after the curent element,
        // so we store it for later.
        if (event.description && showdesc)
          desc_elements.push({element: element[0], description: event.description});
      }
      else if (view.name != 'month') {
        var cont = element.find('div.fc-title');
        if (event.location) {
          cont.after('<div class="fc-event-location">@&nbsp;' + Q(event.location) + '</div>');
          cont = cont.next();
        }
        if (event.description && showdesc) {
          cont.after('<div class="fc-event-description">' + Q(event.description) + '</div>');
        }
      }
    },
    eventAfterAllRender: function(view) {
      if (view.name == 'list') {
        // Fix colspan of headers after we added Location column
        fc.find('tr.fc-list-heading > td').attr('colspan', 4);

        $.each(desc_elements, function() {
          $(this.element).after('<tr class="fc-event-row-secondary fc-list-item"><td colspan="2"></td><td colspan="2" class="fc-event-description">' + Q(this.description) + '</td></tr>');
        });
      }
    },
    viewRender: function(view) {
      desc_elements = [];
    }
  });

  // activate settings form
  $('#propdescription').change(function() {
    showdesc = this.checked;
    desc_elements = [];
    fc.fullCalendar('rerenderEvents');
  });

  var selector = $('#calendar').data('view-selector');
  if (selector) {
    selector = $('#' + selector);

    $('.fc-right button').each(function() {
      var cl = 'btn btn-secondary', btn = $(this);

      if (btn.is('.fc-state-active')) {
        cl += ' active';
      }

      $('<button>').attr({'class': cl, type: 'button'})
        .text(btn.text())
        .appendTo(selector)
          .on('click', function() {
            selector.children('.active').removeClass('active');
            $(this).addClass('active');
            btn.click();
        });
    });
  };

  // Update layout after initialization
  // In devel mode we have to wait until all styles are applied by less
  if (rcmail.env.devel_mode && window.less) {
    less.pageLoadFinished.then(function() { $(window).resize(); });
  }
});
