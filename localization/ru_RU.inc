<?php
/**
 * RoundCube Calendar
 *
 * Plugin to add a calendar to RoundCube.
 *
 * @version 0.2 BETA 2
 * @author Lazlo Westerhof
 * @url http://rc-calendar.lazlo.me
 * @licence GNU GPL
 * @copyright (c) 2010 Lazlo Westerhof - Netherlands
 *
 **/

$labels = array();

// config
$labels['time_format'] = 'Формат времени';
$labels['timeslots'] = 'Промежутков в час';
$labels['first_day'] = 'Первый день недели';
$labels['default_view'] = 'Вид по умолчанию';
$labels['workinghours'] = 'Рабочие часы';
$labels['add_category'] = 'Добавить категорию';
$labels['remove_category'] = 'Удалить категорию';
$labels['defaultcalendar'] = 'Создать новое событие в';
$labels['eventcoloring'] = 'Цвет события';
$labels['coloringmode0'] = 'Согласно цвета календаря';
$labels['coloringmode1'] = 'Согласно цвета категории';
$labels['coloringmode2'] = 'Календарь для внешних, категория для внутренних цветов';
$labels['coloringmode3'] = 'Категория для внешних, календарь для внутренних цветов';


// calendar
$labels['calendar'] = 'Календарь';
$labels['day'] = 'День';
$labels['week'] = 'Неделя';
$labels['month'] = 'Месяц';
$labels['new_event'] = 'Новое событие';
$labels['edit_event'] = 'Изменить событие';
$labels['save'] = 'Сохранить';
$labels['remove'] = 'Удалить';
$labels['cancel'] = 'Завершить';
$labels['calendars'] = 'Календари';
$labels['category'] = 'Категория';
$labels['categories'] = 'Категории';
$labels['createcalendar'] = 'Создать новый календарь';
$labels['editcalendar'] = 'Редактировать свойства календаря';
$labels['name'] = 'Имя';
$labels['color'] = 'Цвет';
$labels['agenda'] = 'Список';
$labels['new'] = 'Новый';
$labels['edit'] = 'Редактировать';
$labels['select'] = 'Выбрать';
$labels['print'] = 'Распечатать';
$labels['printtitle'] = 'Распечатать календарь';
$labels['title'] = 'Название мероприятия';
$labels['description'] = 'Описание';
$labels['all-day'] = 'целый день';
$labels['export'] = 'Экспорт';
$labels['exporttitle'] = 'Экспорт в iCalendar';
$labels['location'] = 'Место';
$labels['date'] = 'Дата';
$labels['start'] = 'Начало';
$labels['end'] = 'Конец';
$labels['selectdate'] = 'Выберите дату';
$labels['freebusy'] = 'Показать как';
$labels['free'] = 'Свободен';
$labels['busy'] = 'Занят';
$labels['outofoffice'] = 'Вне офиса';
$labels['tentative'] = 'Предварительно';
$labels['priority'] = 'Приоритет';
$labels['sensitivity'] = 'Секретность';
$labels['public'] = 'Общедоступная';
$labels['private'] = 'Личная';
$labels['confidential'] = 'Конфиденциальная';
$labels['alarms'] = 'Напоминание';
$labels['generated'] = 'generated at';
$labels['printdescriptions'] = 'Распечатать описание';
$labels['parentcalendar'] = 'Вставить внутри';
$labels['searchearlierdates'] = 'В« Искать для ранних событий';
$labels['searchlaterdates'] = 'Искать для поздних событий В»';
$labels['andnmore'] = '$nr больше...';
$labels['showmore'] = 'Показать больше...';
$labels['togglerole'] = 'Кликните для переключения роли';
$labels['createfrommail'] = 'Сохранить как событие';
$labels['importevents'] = 'Импортировать события';
$labels['importrange'] = 'События с';
$labels['onemonthback'] = '1 месяц назад';
$labels['nmonthsback'] = '$nr месяцев назад';
$labels['showurl'] = 'Показать URL календаря';
$labels['showurldescription'] = 'Используйте следующий адрес для доступа к Вашему календарю из других приложений. Вы можете скопировать и вставить это в любое приложение которое поддерживает формат iCal.';

// agenda view
$labels['listrange'] = 'Диапазон:';
$labels['listsections'] = 'Разделитесь на:';
$labels['smartsections'] = 'Умные секции';
$labels['until'] = 'до';
$labels['today'] = 'Сегодня';
$labels['tomorrow'] = 'Завтра';
$labels['thisweek'] = 'Текущая неделя';
$labels['nextweek'] = 'Следующая неделя';
$labels['thismonth'] = 'Этот месяц';
$labels['nextmonth'] = 'Следующий месяц';
$labels['weekofyear'] = 'Неделя';
$labels['pastevents'] = 'Прошлое';
$labels['futureevents'] = 'Будующее';

// alarm/reminder settings
$labels['showalarms'] = 'Показать уведомление';
$labels['defaultalarmtype'] = 'Настройки напоминания по умолчанию';
$labels['defaultalarmoffset'] = 'Время напоминания по умолчанию';

// attendees
$labels['attendee'] = 'Участник';
$labels['role'] = 'Роль';
$labels['availability'] = 'Доступность';
$labels['confirmstate'] = 'Статус';
$labels['addattendee'] = 'Добавить участника';
$labels['roleorganizer'] = 'Организатор';
$labels['rolerequired'] = 'Обязательный';
$labels['roleoptional'] = 'Опциональный';
$labels['rolechair'] = 'Наблюдатель';
$labels['availfree'] = 'Свободен';
$labels['availbusy'] = 'Занят';
$labels['availunknown'] = 'Неизвестно';
$labels['availtentative'] = 'Предварительно';
$labels['availoutofoffice'] = 'Вне офиса';
$labels['scheduletime'] = 'Найти доступность';
$labels['sendinvitations'] = 'Отправить приглашение';
$labels['sendnotifications'] = 'Уведомить участников об изменениях';
$labels['sendcancellation'] = 'Уведомить участников об отмене события';
$labels['onlyworkinghours'] = 'Найти возможность в мои рабочие часы';
$labels['reqallattendees'] = 'Необходимые/все участники';
$labels['prevslot'] = 'Предыдущее время';
$labels['nextslot'] = 'Следующее время';
$labels['noslotfound'] = 'Невозможно найти свободное время';
$labels['invitationsubject'] = 'Вы приглашены на "$title"';
$labels['invitationmailbody'] = "*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees\n\nПожалуйста найдите вложения в iCalendar файл со всеми деталями события которое Вы можете импортировать в вашу программу календаря.";
$labels['invitationattendlinks'] = "В случае, если Ваш почтовый клиент не поддерживает запросы iTip, Вы можете использовать ссылку, чтобы принять или отклонить это приглашение:\n\$url";
$labels['eventupdatesubject'] = '"$title" было обновлено';
$labels['eventupdatesubjectempty'] = 'Событие, которое касается Вас было обновлено';
$labels['eventupdatemailbody'] = "*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees\n\nПожалуйста найдите вложения в iCalendar файл со всеми деталями события которое Вы можете импортировать в вашу программу календаря.";
$labels['eventcancelsubject'] = '"$title" было отменено';
$labels['eventcancelmailbody'] = "*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees\n\nЭто событие отменено \$organizer.\n\nПожалуйста посмотрите вложение в iCalendar файл которое обновит детали события.";

// invitation handling
$labels['itipinvitation'] = 'Приглашение на';
$labels['itipupdate'] = 'Обновление';
$labels['itipcancellation'] = 'Отмененный:';
$labels['itipreply'] = 'Ответить';
$labels['itipaccepted'] = 'Принять';
$labels['itiptentative'] = 'Может быть';
$labels['itipdeclined'] = 'Отклонить';
$labels['itipsubjectaccepted'] = '"$title" принято $name';
$labels['itipsubjecttentative'] = '"$title" предварительно принято $name';
$labels['itipsubjectdeclined'] = '"$title" отклонено $name';
$labels['itipmailbodyaccepted'] = "\$sender принял приглашение на следующее событие:\n\n*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees";
$labels['itipmailbodytentative'] = "\$sender предварительно принял приглашение на следующее событие:\n\n*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees";
$labels['itipmailbodydeclined'] = "\$sender отклонил приглашение на следующее событие:\n\n*\$title*\n\nКогда: \$date\n\nПриглашенные: \$attendees";
$labels['itipdeclineevent'] = 'Вы хотите отклонить приглашение на это событие?';
$labels['importtocalendar'] = 'Сохранить в мой календарь';
$labels['removefromcalendar'] = 'Удалить из моего календаря';
$labels['updateattendeestatus'] = 'Обновить статус участника';
$labels['acceptinvitation'] = 'Вы принимаете это приглашение?';
$labels['youhaveaccepted'] = 'Вы приняли это приглашение';
$labels['youhavetentative'] = 'Вы предварительно приняли это приглашение';
$labels['youhavedeclined'] = 'Вы отклонили это приглашение';
$labels['notanattendee'] = 'Вы не в списке этого события';
$labels['eventcancelled'] = 'Это событие отменено';
$labels['saveincalendar'] = 'сохранить в';

// event dialog tabs
$labels['tabsummary'] = 'Сводка';
$labels['tabrecurrence'] = 'Повторение';
$labels['tabattendees'] = 'Участники';
$labels['tabattachments'] = 'Вложения';
$labels['tabsharing'] = 'Совместное использование';

// messages
$labels['deleteventconfirm'] = 'Вы действительно хотите удалить это событие?';
$labels['deletecalendarconfirm'] = 'Вы действительно хотите удалить этот календарь со всеми его событиями?';
$labels['savingdata'] = 'Сохранение данных...';
$labels['errorsaving'] = 'Ошибка сохранения изменений.';
$labels['operationfailed'] = 'Ошибка запрошенной операции.';
$labels['invalideventdates'] = 'Неверная дата! Пожалуйста проверьте данные.';
$labels['invalidcalendarproperties'] = 'Неверные свойства календаря! Пожалуйста введите допустимые данные.';
$labels['searchnoresults'] = 'Событие не найдено в выбранном календаре.';
$labels['successremoval'] = 'Это событие успешно удалено.';
$labels['successrestore'] = 'Это событие успешно восстановлено.';
$labels['errornotifying'] = 'Ошибка в отправке уведомления участникам событий';
$labels['errorimportingevent'] = 'Ошибка при импорте события';
$labels['newerversionexists'] = 'Новая версия этого события уже существует! Отменено.';
$labels['nowritecalendarfound'] = 'Не найден календарь для записи этого события';
$labels['importedsuccessfully'] = 'Это событие успешно добавлено в \'$calendar\'';
$labels['attendeupdateesuccess'] = 'Успешно обновлен статус участников';
$labels['itipresponseerror'] = 'Невозможно послать ответ на это приглашение';
$labels['itipinvalidrequest'] = 'Это приглашение больше не действительно';
$labels['sentresponseto'] = 'Успешно отправлен ответ на приглашение на $mailto';
$labels['localchangeswarning'] = 'Вы собираетесь внести изменения, которые будут только отражены на Вашем личном календаре';
$labels['importsuccess'] = 'Успешно импортированы $nr событий';
$labels['importnone'] = 'Не найдено событий для импорта';
$labels['importerror'] = 'Ошибка при импорте';
$labels['aclnorights'] = 'Вы не имеете прав администратора для этого календаря.';

// recurrence form
$labels['repeat'] = 'Повторить';
$labels['frequency'] = 'Повторить';
$labels['never'] = 'никогда';
$labels['daily'] = 'ежедневно';
$labels['weekly'] = 'еженедельно';
$labels['monthly'] = 'ежемесячно';
$labels['yearly'] = 'ежегодно';
$labels['every'] = 'Каждый';
$labels['days'] = 'дней';
$labels['weeks'] = 'недели';
$labels['months'] = 'месяцы';
$labels['years'] = 'годы в:';
$labels['bydays'] = 'На';
$labels['untildate'] = 'до числа';
$labels['each'] = 'Каждый';
$labels['onevery'] = 'На каждом';
$labels['onsamedate'] = 'В ту же самую дату';
$labels['forever'] = 'всегда';
$labels['recurrencend'] = 'продолжительность';
$labels['forntimes'] = '$nr дней';
$labels['first'] = 'первое';
$labels['second'] = 'второе';
$labels['third'] = 'третье';
$labels['fourth'] = 'четвертое';
$labels['last'] = 'последнее';
$labels['dayofmonth'] = 'День месяца';

$labels['changeeventconfirm'] = 'Изменить событие';
$labels['removeeventconfirm'] = 'Удалить событие';
$labels['changerecurringeventwarning'] = 'Это - повторяющееся событие. Хотели бы Вы редактировать только текущее событие, это и все будущие повторения, все события или сохранять его как новое событие?';
$labels['removerecurringeventwarning'] = 'Это - повторяющееся событие. Хотели бы Вы удалить только текущее событие, это и все будущие события или все эти события?';
$labels['currentevent'] = 'Текущее';
$labels['futurevents'] = 'Будущие';
$labels['allevents'] = 'Все';
$labels['saveasnew'] = 'Сохранить как новое';

?>
