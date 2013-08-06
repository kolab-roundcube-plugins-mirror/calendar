<?php

$labels = array();

// preferences
$labels['default_view'] = 'Ansicht';
$labels['time_format'] = 'Zeitformatierung';
$labels['timeslots'] = 'Zeitraster pro Stunde';
$labels['first_day'] = 'Erster Wochentag';
$labels['first_hour'] = 'Erste angezeigte Stunde';
$labels['workinghours'] = 'Arbeitszeiten';
$labels['add_category'] = 'Katgorie hinzufügen';
$labels['remove_category'] = 'Katgorie entfernen';
$labels['defaultcalendar'] = 'Neue Termine erstellen in';
$labels['eventcoloring'] = 'Färbung der Termine';
$labels['coloringmode0'] = 'Farbe des Kalenders';
$labels['coloringmode1'] = 'Farbe der Kategorie';
$labels['coloringmode2'] = 'Kalenderfarbe außen, Kategoriefarbe innen';
$labels['coloringmode3'] = 'Kategoriefarbe außen, Kalenderfarbe innen';

// calendar
$labels['calendar'] = 'Kalender';
$labels['calendars'] = 'Kalender';
$labels['category'] = 'Kategorie';
$labels['categories'] = 'Kategorien';
$labels['createcalendar'] = 'Neuen Kalender erstellen';
$labels['editcalendar'] = 'Kalendereigenschaften bearbeiten';
$labels['name'] = 'Name';
$labels['color'] = 'Farbe';
$labels['day'] = 'Tag';
$labels['week'] = 'Woche';
$labels['month'] = 'Monat';
$labels['agenda'] = 'Agenda';
$labels['new'] = 'Neu';
$labels['new_event'] = 'Neuer Termin';
$labels['edit_event'] = 'Termin bearbeiten';
$labels['edit'] = 'Bearbeiten';
$labels['save'] = 'Speichern';
$labels['remove'] = 'Entfernen';
$labels['cancel'] = 'Abbrechen';
$labels['select'] = 'Auswählen';
$labels['print'] = 'Drucken';
$labels['printtitle'] = 'Kalender drucken';
$labels['title'] = 'Titel';
$labels['description'] = 'Beschreibung';
$labels['all-day'] = 'ganztägig';
$labels['export'] = 'Exportieren';
$labels['exporttitle'] = 'Kalender als iCalendar exportieren';
$labels['location'] = 'Ort';
$labels['date'] = 'Datum';
$labels['start'] = 'Beginn';
$labels['end'] = 'Ende';
$labels['selectdate'] = 'Datum auswählen';
$labels['freebusy'] = 'Zeige mich als';
$labels['free'] = 'Frei';
$labels['busy'] = 'Gebucht';
$labels['outofoffice'] = 'Abwesend';
$labels['tentative'] = 'Mit Vorbehalt';
$labels['priority'] = 'Priorität';
$labels['sensitivity'] = 'Sichtbarkeit';
$labels['public'] = 'öffentlich';
$labels['private'] = 'privat';
$labels['confidential'] = 'vertraulich';
$labels['alarms'] = 'Erinnerung';
$labels['generated'] = 'erstellt am';
$labels['printdescriptions'] = 'Beschreibung drucken';
$labels['parentcalendar'] = 'Erstellen in';
$labels['searchearlierdates'] = '« Frühere Termine suchen';
$labels['searchlaterdates'] = 'Spätere Termine suchen »';
$labels['andnmore'] = '$nr weitere...';
$labels['togglerole'] = 'Zum Ändern der Rolle klicken';
$labels['createfrommail'] = 'Als Termin speichern';
$labels['importevents'] = 'Termine importieren';
$labels['importrange'] = 'Termine ab';
$labels['onemonthback'] = '1 Monat zurück';
$labels['nmonthsback'] = '$nr Monate zurück';
$labels['showurl'] = 'URL anzeigen';
$labels['showurldescription'] = 'Über die folgende Adresse können Sie mit einem beliebigen Kalenderprogramm Ihren Kalender abrufen (nur lesend), sofern dieses das iCal-Format unterstützt.';

// agenda view
$labels['listrange'] = 'Angezeigter Bereich:';
$labels['listsections'] = 'Unterteilung:';
$labels['smartsections'] = 'Intelligent';
$labels['until'] = 'bis';
$labels['today'] = 'Heute';
$labels['tomorrow'] = 'Morgen';
$labels['thisweek'] = 'Diese Woche';
$labels['nextweek'] = 'Nächste Woche';
$labels['thismonth'] = 'Diesen Monat';
$labels['nextmonth'] = 'Nächsten Monat';
$labels['weekofyear'] = 'Woche';
$labels['pastevents'] = 'Vergangene';
$labels['futureevents'] = 'Zukünftige';

// alarm/reminder settings
$labels['showalarms'] = 'Erinnerungen anzeigen';
$labels['defaultalarmtype'] = 'Standard-Erinnerungseinstellung';
$labels['defaultalarmoffset'] = 'Standard-Erinnerungszeit';

// attendees
$labels['attendee'] = 'Teilnehmer';
$labels['role'] = 'Rolle';
$labels['availability'] = 'Verfüg.';
$labels['confirmstate'] = 'Status';
$labels['addattendee'] = 'Hinzufügen';
$labels['roleorganizer'] = 'Organisator';
$labels['rolerequired'] = 'Erforderlich';
$labels['roleoptional'] = 'Optional';
$labels['rolechair'] = 'Vorsitz';
$labels['availfree'] = 'Frei';
$labels['availbusy'] = 'Gebucht';
$labels['availunknown'] = 'Unbekannt';
$labels['availtentative'] = 'Mit Vorbehalt';
$labels['availoutofoffice'] = 'Abwesend';
$labels['scheduletime'] = 'Verfügbarkeit anzeigen';
$labels['sendinvitations'] = 'Einladungen versenden';
$labels['sendnotifications'] = 'Teilnehmer über die Änderungen informieren';
$labels['sendcancellation'] = 'Teilnehmer über die Terminabsage informieren';
$labels['onlyworkinghours'] = 'Verfügbarkeit innerhalb meiner Arbeitszeiten suchen';
$labels['reqallattendees'] = 'Erforderliche/alle Teilnehmer';
$labels['prevslot'] = 'Vorheriger Vorschlag';
$labels['nextslot'] = 'Nächster Vorschlag';
$labels['noslotfound'] = 'Es konnten keine freien Zeiten gefunden werden';
$labels['invitationsubject'] = 'Sie wurden zu "$title" eingeladen';
$labels['invitationmailbody'] = "*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees\n\nIm Anhang finden Sie eine iCalendar-Datei mit allen Details des Termins. Diese können Sie in Ihre Kalenderanwendung importieren.";
$labels['invitationattendlinks'] = "Falls Ihr E-Mail-Programm keine iTip-Anfragen unterstützt, können Sie den folgenden Link verwenden, um den Termin zu bestätigen oder abzulehnen:\n\$url";
$labels['eventupdatesubject'] = '"$title" wurde aktualisiert';
$labels['eventupdatesubjectempty'] = 'Termin wurde aktualisiert';
$labels['eventupdatemailbody'] = "*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees\n\nIm Anhang finden Sie eine iCalendar-Datei mit den aktualisiereten Termindaten. Diese können Sie in Ihre Kalenderanwendung importieren.";
$labels['eventcancelsubject'] = '"$title" wurde abgesagt';
$labels['eventcancelmailbody'] = "*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees\n\nDer Termin wurde von \$organizer abgesagt.\n\nIm Anhang finden Sie eine iCalendar-Datei mit den Termindaten.";

// invitation handling
$labels['itipinvitation'] = 'Einladung zu';
$labels['itipreply'] = 'Antwort zu';
$labels['itipupdate'] = 'Aktialisiert:';
$labels['itipcancellation'] = 'Abgesagt:';
$labels['itipaccepted'] = 'Akzeptieren';
$labels['itiptentative'] = 'Mit Vorbehalt';
$labels['itipdeclined'] = 'Ablehnen';
$labels['itipsubjectaccepted'] = 'Einladung zu "$title" wurde von $name angenommen';
$labels['itipsubjecttentative'] = 'Einladung zu "$title" wurde von $name mit Vorbehalt angenommen';
$labels['itipsubjectdeclined'] = 'Einladung zu "$title" wurde von $name abgelehnt';
$labels['itipmailbodyaccepted'] = "\$sender hat die Einladung zum folgenden Termin angenommen:\n\n*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees";
$labels['itipmailbodytentative'] = "\$sender hat die Einladung mit Vorbehalt zum folgenden Termin angenommen:\n\n*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees";
$labels['itipmailbodydeclined'] = "\$sender hat die Einladung zum folgenden Termin abgelehnt:\n\n*\$title*\n\nWann: \$date\n\nTeilnehmer: \$attendees";
$labels['itipdeclineevent'] = 'Möchten Sie die Einladung zu diesem Termin ablehnen?';
$labels['declinedeleteconfirm'] = 'Möchten Sie den abgelehnten Termin ebenfalls aus Ihrem Kalender löschen?';
$labels['importtocalendar'] = 'In Kalender übernehmen';
$labels['removefromcalendar'] = 'Aus meinem Kalender löschen';
$labels['updateattendeestatus'] = 'Teilnehmerstatus aktualisieren';
$labels['acceptinvitation'] = 'Möchten Sie die Einladung zu diesem Termin annehmen?';
$labels['youhaveaccepted'] = 'Sie haben die Einladung angenommen';
$labels['youhavetentative'] = 'Sie haben die Einladung mit Vorbehalt angenommen';
$labels['youhavedeclined'] = 'Sie haben die Einladung abgelehnt';
$labels['notanattendee'] = 'Sie sind nicht in der Liste der Teilnehmer aufgeführt';
$labels['eventcancelled'] = 'Der Termin wurde vom Organisator abgesagt';
$labels['saveincalendar'] = 'speichern in';

// event dialog tabs
$labels['tabsummary'] = 'Übersicht';
$labels['tabrecurrence'] = 'Wiederholung';
$labels['tabattendees'] = 'Teilnehmer';
$labels['tabattachments'] = 'Anhänge';
$labels['tabsharing'] = 'Freigabe';

// messages
$labels['deleteventconfirm'] = 'Möchten Sie diesen Termin wirklich löschen?';
$labels['deletecalendarconfirm'] = 'Möchten Sie diesen Kalender mit allen Terminen wirklich löschen?';
$labels['savingdata'] = 'Speichere Daten...';
$labels['errorsaving'] = 'Fehler beim Speichern.';
$labels['operationfailed'] = 'Die Aktion ist fehlgeschlagen.';
$labels['invalideventdates'] = 'Ungültige Daten eingegeben! Bitte überprüfen Sie die Eingaben.';
$labels['invalidcalendarproperties'] = 'Ungültige Kalenderinformationen! Bitte geben Sie einen Namen ein.';
$labels['searchnoresults'] = 'Keine Termine in den gewählten Kalendern gefunden.';
$labels['successremoval'] = 'Der Termin wurde erfolgreich gelöscht.';
$labels['successrestore'] = 'Der Termin wurde erfolgreich wieder hergestellt.';
$labels['errornotifying'] = 'Benachrichtigung an die Teilnehmer konnten nicht gesendet werden';
$labels['errorimportingevent'] = 'Fehler beim Importieren';
$labels['newerversionexists'] = 'Eine neuere Version dieses Termins exisitert bereits! Import abgebrochen.';
$labels['nowritecalendarfound'] = 'Kein Kalender zum Speichern gefunden';
$labels['importedsuccessfully'] = 'Der Termin wurde erfolgreich in \'$calendar\' gespeichert';
$labels['attendeupdateesuccess'] = 'Teilnehmerstatus erfolgreich aktualisiert';
$labels['itipsendsuccess'] = 'Einladung an Teilnehmer versendet.';
$labels['itipresponseerror'] = 'Die Antwort auf diese Einladung konnte nicht versendet werden';
$labels['sentresponseto'] = 'Antwort auf diese Einladung erfolgreich an $mailto gesendet';
$labels['localchangeswarning'] = 'Änderungen an diesem Termin werden nur in Ihrem Kalender gespeichert und nicht an den Organisator des Termins gesendet.';
$labels['importsuccess'] = 'Es wurden $nr Termine erfolgreich importiert';
$labels['importnone'] = 'Keine Termine zum Importieren gefunden';
$labels['importerror'] = 'Fehler beim Importieren';

// recurrence form
$labels['repeat'] = 'Wiederholung';
$labels['frequency'] = 'Wiederholung';
$labels['never'] = 'nie';
$labels['daily'] = 'täglich';
$labels['weekly'] = 'wöchentlich';
$labels['monthly'] = 'monatlich';
$labels['yearly'] = 'jährlich';
$labels['every'] = 'Alle';
$labels['days'] = 'Tag(e)';
$labels['weeks'] = 'Woche(n)';
$labels['months'] = 'Monat(e)';
$labels['years'] = 'Jahre(e) im:';
$labels['bydays'] = 'Am';
$labels['untildate'] = 'am';
$labels['each'] = 'Jeden';
$labels['onevery'] = 'An jedem';
$labels['onsamedate'] = 'Am gleichen Tag';
$labels['forever'] = 'unendlich';
$labels['recurrencend'] = 'bis';
$labels['forntimes'] = '$nr Wiederholungen';
$labels['first'] = 'erster';
$labels['second'] = 'zweiter';
$labels['third'] = 'dritter';
$labels['fourth'] = 'vierter';
$labels['last'] = 'letzter';
$labels['dayofmonth'] = 'Tag des Montats';

$labels['changeeventconfirm'] = 'Termin ändern';
$labels['removeeventconfirm'] = 'Termin löschen';
$labels['changerecurringeventwarning'] = 'Dies ist eine Terminreihe. Möchten Sie nur den aktuellen, diesen und alle zukünftigen oder alle Termine bearbeiten oder die Änderungen als neuen Termin speichern?';
$labels['removerecurringeventwarning'] = 'Dies ist eine Terminreihe. Möchten Sie nur den aktuellen, diesen und alle zukünftigen oder alle Termine löschen?';
$labels['currentevent'] = 'Aktuellen';
$labels['futurevents'] = 'Zukünftige';
$labels['allevents'] = 'Alle';
$labels['saveasnew'] = 'Als neu speichern';

?>
