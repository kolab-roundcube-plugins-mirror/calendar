<?php

$labels = array();

// preferences
$labels['default_view'] = 'Domyślny widok';
$labels['time_format'] = 'Format czasu';
$labels['timeslots'] = 'Okienka na godzinę';
$labels['first_day'] = 'Pierwszy dzień tygodnia';
$labels['first_hour'] = 'Pierwsza godzina do wyświetlenia';
$labels['workinghours'] = 'Godziny pracy';
$labels['add_category'] = 'Dodaj kategorię';
$labels['remove_category'] = 'Usuń kategorię';
$labels['defaultcalendar'] = 'Utwórz nowe zdarzenia w';
$labels['eventcoloring'] = 'Kolorowanie zdarzeń';
$labels['coloringmode0'] = 'zależnie od kalendarza';
$labels['coloringmode1'] = 'zależnie od kategorii';
$labels['coloringmode2'] = 'Kalendarz - obramowanie, kategoria - tło';
$labels['coloringmode3'] = 'Kategoria - obramowanie, kalendarz - tło';

// calendar
$labels['calendar'] = 'Kalendarz';
$labels['calendars'] = 'Kalendarze';
$labels['category'] = 'Kategoria';
$labels['categories'] = 'Kategorie';
$labels['createcalendar'] = 'Utwórz nowy kalendarz';
$labels['editcalendar'] = 'Edytuj właściwości kalendarza';
$labels['name'] = 'Nazwa';
$labels['color'] = 'Kolor';
$labels['day'] = 'Dzień';
$labels['week'] = 'Tydzień';
$labels['month'] = 'Miesiąc';
$labels['agenda'] = 'Agenda';
$labels['new_event'] = 'Nowe zdarzenie';
$labels['edit_event'] = 'Edytuj zdarzenie';
$labels['edit'] = 'Edytuj';
$labels['save'] = 'Zapisz';
$labels['remove'] = 'Usuń';
$labels['cancel'] = 'Anuluj';
$labels['select'] = 'Wybierz';
$labels['print'] = 'Drukuj kalendarze';
$labels['title'] = 'Podsumowanie';
$labels['description'] = 'Opis';
$labels['all-day'] = 'cały dzień';
$labels['export'] = 'Eksport do iCalendarza';
$labels['location'] = 'Położenie';
$labels['date'] = 'Data';
$labels['start'] = 'Początek';
$labels['end'] = 'Koniec';
$labels['selectdate'] = 'Wybierz datę';
$labels['freebusy'] = 'Pokazuj mnie jako';
$labels['free'] = 'Wolny';
$labels['busy'] = 'Zajęty';
$labels['outofoffice'] = 'Poza buirem';
$labels['tentative'] = 'Niepewny';
$labels['priority'] = 'Priorytet';
$labels['sensitivity'] = 'Dostępność';
$labels['public'] = 'publiczny';
$labels['private'] = 'prywatny';
$labels['confidential'] = 'zastrzeżony';
$labels['alarms'] = 'Przypomnienie';
$labels['generated'] = 'wygenerowano';
$labels['printdescriptions'] = 'Drukuj opisy';
$labels['parentcalendar'] = 'Nadrzędny kalendarz';
$labels['searchearlierdates'] = '« Wyszukaj wcześniejsze zdarzenia';
$labels['searchlaterdates'] = 'Wyszukaj późniejsze zdarzenia »';
$labels['andnmore'] = '$nr więcej...';
$labels['showmore'] = 'Pokaż więcej...';
$labels['togglerole'] = 'Kliknij aby zmienić rolę';
$labels['createfrommail'] = 'Zapisz jako zdarzenie';
$labels['importevents'] = 'Import zdarzeń';
$labels['importrange'] = 'Zdarzenia z';
$labels['onemonthback'] = '1 miesiąc wstecz';
$labels['nmonthsback'] = '$nr miesiące wstecz';

// agenda view
$labels['listrange'] = 'Zakres do wyświetlenia:';
$labels['listsections'] = 'Podziel na:';
$labels['smartsections'] = 'Inteligentne sekcje';
$labels['until'] = 'do';
$labels['today'] = 'Dzisiaj';
$labels['tomorrow'] = 'Jutro';
$labels['thisweek'] = 'Ten tydzień';
$labels['nextweek'] = 'Następny tydzień';
$labels['thismonth'] = 'Ten miesiąc';
$labels['nextmonth'] = 'Następny miesiąc';
$labels['weekofyear'] = 'Tydzień';
$labels['pastevents'] = 'Przeszłość';
$labels['futureevents'] = 'Przyszłość';

// alarm/reminder settings
$labels['showalarms'] = 'Wyświetlaj alarmy';
$labels['defaultalarmtype'] = 'Domyślne ustawienia przypomnienia';
$labels['defaultalarmoffset'] = 'Domyślny czas przypomnienia';

// attendees
$labels['attendee'] = 'Uczestnik';
$labels['role'] = 'Rola';
$labels['availability'] = 'Dostępn.';
$labels['confirmstate'] = 'Status';
$labels['addattendee'] = 'Dodaj uczestnika';
$labels['roleorganizer'] = 'Organizator';
$labels['rolerequired'] = 'Wymagany';
$labels['roleoptional'] = 'Opcjonalny';
$labels['roleresource'] = 'Żródło';
$labels['availfree'] = 'Wolny';
$labels['availbusy'] = 'Zajęty';
$labels['availunknown'] = 'Nieznany';
$labels['availtentative'] = 'Niepewny';
$labels['availoutofoffice'] = 'Poza buirem';
$labels['scheduletime'] = 'Sprawdź dostępność';
$labels['sendinvitations'] = 'Wyślij zaproszenia';
$labels['sendnotifications'] = 'Informuj uczestników o zmianach';
$labels['sendcancellation'] = 'Informuj uczestników o odwołaniu zdarzenia';
$labels['onlyworkinghours'] = 'Sprawdź dostępność podczas moich godzin pracy';
$labels['reqallattendees'] = 'Wymagani/wszyscy uczestnicy';
$labels['prevslot'] = 'Poprzednie okienko';
$labels['nextslot'] = 'Następne okienko';
$labels['noslotfound'] = 'Nie można znaleźć wolnego okienka';
$labels['invitationsubject'] = 'Zostałeś zaproszony na "$title"';
$labels['invitationmailbody'] = "*\$title*\n\nKiedy: \$date\n\nZaproszeni: \$attendees\n\nW załączeniu plik w formacie iCalendar zawierający szczegóły zdarzenia, który można zaimportować do Pana/Pani kalendarza.";
$labels['invitationattendlinks'] = "W przypadku gdy Pana/Pani program pocztowy nie obsługuje żądań iTip można użyć następującego łącza w celu akceptacji lub odrzucenia tego zdarzenia:\n\$url";
$labels['eventupdatesubject'] = '"$title" zostało zaktualizowane';
$labels['eventupdatesubjectempty'] = 'Zdarzenie, które ciebie dotyczy zostało zaktualizowane';
$labels['eventupdatemailbody'] = "*\$title*\n\nKiedy: \$date\n\nUczestnicy: \$attendees\n\n.W załączeniu plik w formacie iCalendar zawierający zaktualizowane szczegóły zdarzenia, który można zaimportować do Pana/Pani kalendarza.";
$labels['eventcancelsubject'] = '"$title" zostało anulowane';
$labels['eventcancelmailbody'] = "*\$title*\n\nKiedy: \$date\n\nUczestnicy: \$attendees\n\nZdarzenie zostało anulowane przez \$organizer.\n\n.W załączeniu plik w formacie iCalendar zawierający zaktualizowane szczegóły zdarzenia.";

// invitation handling
$labels['itipinvitation'] = 'Zaproszenie dla';
$labels['itipupdate'] = 'Aktualizacja do';
$labels['itipcancellation'] = 'Anulowano:';
$labels['itipreply'] = 'Odpowiedź do';
$labels['itipaccepted'] = 'Akceptuj';
$labels['itiptentative'] = 'Może';
$labels['itipdeclined'] = 'Odrzuć';
$labels['itipsubjectaccepted'] = '"$title" zostało zaakceptowane przez $name';
$labels['itipsubjecttentative'] = '"$title" zostało warunkowo zaakceptowane $name';
$labels['itipsubjectdeclined'] = '"$title" zostało odrzucone przez $name';
$labels['itipmailbodyaccepted'] = "\$sender zaakceptował zaproszenie na następującego zdarzenie:\n\n*\$title*\n\nKiedy: \$date\n\nUczestnicy: \$attendees";
$labels['itipmailbodytentative'] = "\$sender warunkowo zaakceptował zaproszenie na następujące zdarzenie:\n\n*\$title*\n\nKiedy: \$date\n\nUczestnicy: \$attendees";
$labels['itipmailbodydeclined'] = "\$sender odrzucił zaproszenie na następujące zdarzenie:\n\n*\$title*\n\nKiedy: \$date\n\nUczestnicy: \$attendees";
$labels['itipdeclineevent'] = 'Czy chcesz odrzucić zaproszenie na to zdarzenie?';
$labels['importtocalendar'] = 'Zapisz w moim kalendarzu';
$labels['removefromcalendar'] = 'Usuń z mojego kalendarza';
$labels['updateattendeestatus'] = 'Aktualizuj status uczestników';
$labels['acceptinvitation'] = 'Czy akceptujesz to zaproszenie?';
$labels['youhaveaccepted'] = 'Zaakceptowałeś to zaproszenie';
$labels['youhavetentative'] = 'Zaakceptowałeś warunkowo to zaproszenie';
$labels['youhavedeclined'] = 'Odrzuciłeś to zaproszenie';
$labels['notanattendee'] = 'Nie znajdujesz się na liście uczestników tego zdarzenia';
$labels['eventcancelled'] = 'Zdarzenie zostało anulowane';
$labels['saveincalendar'] = 'zapisz w';

// event dialog tabs
$labels['tabsummary'] = 'Podsumowanie';
$labels['tabrecurrence'] = 'Powtarzalność';
$labels['tabattendees'] = 'Uczestnicy';
$labels['tabattachments'] = 'Załączniki';
$labels['tabsharing'] = 'Udostępnianie';

// messages
$labels['deleteventconfirm'] = 'Czy na pewno chcesz usunąć to zdarzenie?';
$labels['deletecalendarconfirm'] = 'Czy na pewno chcesz usunąć ten kalendarz wraz z wszystkimi zdarzeniami?';
$labels['savingdata'] = 'Zapisywanie danych...';
$labels['errorsaving'] = 'Nie udało się zapisanie zmian.';
$labels['operationfailed'] = 'Żądana operacja nie powiodła się.';
$labels['invalideventdates'] = 'Wprowadzono błędne daty! Sprawdź dane w formularzu.';
$labels['invalidcalendarproperties'] = 'Błędne właściwości kalendarza! Proszę podać poprawną datę.';
$labels['searchnoresults'] = 'Nie znaleziono zdarzeń w wybranym kalendarzu.';
$labels['successremoval'] = 'Zdarzenie zostało usunięte pomyślnie.';
$labels['successrestore'] = 'Zdarzenie zostało odtworzone pomyślnie.';
$labels['errornotifying'] = 'Nie udało się wysłać powiadomień do uczestników';
$labels['errorimportingevent'] = 'Nie powódł się import zdarzenia';
$labels['newerversionexists'] = 'Nowsza wersja tego zdarzenia już istnieje! Przerwano.';
$labels['nowritecalendarfound'] = 'Nie znaleziono kalendarza do zapisu zdarzenia';
$labels['importedsuccessfully'] = 'Zdarzenie zostało pomyślnie zapisane w \'$calendar\'';
$labels['attendeupdateesuccess'] = 'Pomyślnie zaktualizowano status uczestnika';
$labels['itipresponseerror'] = 'Nie udało się wysłać odpowiedzi na to zaproszenie';
$labels['itipinvalidrequest'] = 'To zaproszenie nie jest już aktualne';
$labels['sentresponseto'] = 'Pomyślnie wysłano odpowiedź na zaproszenie do $mailto';
$labels['localchangeswarning'] = 'Próbujesz dokonać zmian, które będą widoczne tylko w twoim prywatnym kalendarzu';
$labels['importsuccess'] = 'Pomyślnie zaimportowano $nr zdarzeń';
$labels['importnone'] = 'Nie znaleziono zdarzeń do importu';
$labels['importerror'] = 'Wystąpił błąd podczas operacji importu.';

// recurrence form
$labels['repeat'] = 'Powtórz';
$labels['frequency'] = 'Częstotliwość';
$labels['never'] = 'nigdy';
$labels['daily'] = 'codziennie';
$labels['weekly'] = 'co tydzień';
$labels['monthly'] = 'co miesiąc';
$labels['yearly'] = 'co rok';
$labels['every'] = 'Każdego';
$labels['days'] = 'dni(a)';
$labels['weeks'] = 'tygodni(a)';
$labels['months'] = 'miesięcy';
$labels['years'] = 'lat w:';
$labels['bydays'] = 'W';
$labels['untildate'] = 'Do';
$labels['each'] = 'Każdy';
$labels['onevery'] = 'W każdy';
$labels['onsamedate'] = 'W tej samej dacie';
$labels['forever'] = 'zawsze';
$labels['recurrencend'] = 'aż do';
$labels['forntimes'] = '$nr raz(y)';
$labels['first'] = 'pierwszy';
$labels['second'] = 'drugi';
$labels['third'] = 'trzeci';
$labels['fourth'] = 'czwarty';
$labels['last'] = 'ostatni';
$labels['dayofmonth'] = 'Dzień miesiąca';

$labels['changeeventconfirm'] = 'Zmień zdarzenie';
$labels['removeeventconfirm'] = 'Usuń zdarzenie';
$labels['changerecurringeventwarning'] = 'To jest zdarzenie powtarzające się. Czy chesz zmienić tylko bieżące wystąpienie, to i wszystkie przyszłe wystąpienia, czy wszystkie wystąpienia i zapisać je jako nowe zdarzenie?';
$labels['removerecurringeventwarning'] = 'To jest zdarzenie powtarzające się. Czy chesz usunąć tylko bieżące wystąpienie, to i wszystkie przyszłe wystąpienia, czy wszystkie wystąpienia tego zdarzenie?';
$labels['currentevent'] = 'Bieżące';
$labels['futurevents'] = 'Przyszłe';
$labels['allevents'] = 'Wszystkie';
$labels['saveasnew'] = 'Zapisz jako nowy';

?>
