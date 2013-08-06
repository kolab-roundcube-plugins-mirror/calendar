<?php

$labels = array();

// preferences
$labels['default_view'] = 'Vue par défaut';
$labels['time_format'] = 'Format de l\'heure';
$labels['timeslots'] = 'Nombre d\'intervalle par heure';
$labels['first_day'] = 'Premier jour de la semaine';
$labels['first_hour'] = 'Première heure à afficher';
$labels['workinghours'] = 'Heures de travail';
$labels['add_category'] = 'Ajouter une catégorie';
$labels['remove_category'] = 'Supprimer une catégorie';
$labels['defaultcalendar'] = 'Ajouter un nouvel évènement';
$labels['eventcoloring'] = 'Couleurs des évènements';
$labels['coloringmode0'] = 'Selon l\'agenda';
$labels['coloringmode1'] = 'Selon la catégorie';
$labels['coloringmode2'] = 'Calendrier en contour, catégorie en contenu';
$labels['coloringmode3'] = 'Catégorie en contour, calendrier en contenu';

// calendar
$labels['calendar'] = 'Agenda';
$labels['calendars'] = 'Agendas';
$labels['category'] = 'Catégorie';
$labels['categories'] = 'Catégories';
$labels['createcalendar'] = 'Créer un nouvel agenda';
$labels['editcalendar'] = 'Modifier les propriétés de l\'agenda';
$labels['name'] = 'Nom';
$labels['color'] = 'Couleur';
$labels['day'] = 'Jour';
$labels['week'] = 'Semaine';
$labels['month'] = 'Mois';
$labels['agenda'] = 'Ordre du jour';
$labels['new'] = 'Nouveau';
$labels['new_event'] = 'Nouvel évènement';
$labels['edit_event'] = 'Modifier l\'évènement';
$labels['edit'] = 'Modifier';
$labels['save'] = 'Enregistrer';
$labels['remove'] = 'Supprimer';
$labels['cancel'] = 'Annuler';
$labels['select'] = 'Sélectionner';
$labels['print'] = 'Imprimer';
$labels['printtitle'] = 'Imprimer les agendas';
$labels['title'] = 'Résumé';
$labels['description'] = 'Description';
$labels['all-day'] = 'Toute la journée';
$labels['export'] = 'Exporter';
$labels['exporttitle'] = 'Exporter vers iCalendar';
$labels['location'] = 'Lieu';
$labels['date'] = 'Date';
$labels['start'] = 'Début';
$labels['end'] = 'Fin';
$labels['selectdate'] = 'Sélectionner une date';
$labels['freebusy'] = 'Montrez moi comme';
$labels['free'] = 'Libre';
$labels['busy'] = 'Occupé';
$labels['outofoffice'] = 'Absent';
$labels['tentative'] = 'Provisoire';
$labels['priority'] = 'Priorité';
$labels['sensitivity'] = 'Diffusion';
$labels['public'] = 'publique';
$labels['private'] = 'privée';
$labels['confidential'] = 'Confidentiel';
$labels['alarms'] = 'Rappel';
$labels['generated'] = 'généré à';
$labels['printdescriptions'] = 'Imprimer les descriptions';
$labels['parentcalendar'] = 'Ajouter à l\'intérieur';
$labels['searchearlierdates'] = '« Chercher des évènements plus ancien';
$labels['searchlaterdates'] = 'Chercher des évènement plus récent »';
$labels['andnmore'] = '$nr de plus...';
$labels['togglerole'] = 'Cliquez pour changer de rôle';
$labels['createfrommail'] = 'Enregistrer comme un évènement';
$labels['importevents'] = 'Importer des évènements';
$labels['importrange'] = 'Évènements depuis';
$labels['onemonthback'] = '1 mois précédent';
$labels['nmonthsback'] = '$nr mois précédents';
$labels['showurl'] = 'Afficher l\'URL de l\'agenda';
$labels['showurldescription'] = 'Utilisez l\'adresse suivante pour accéder(lecture seule) à votre agenda depuis une autre application. Vous pouvez copier/coller celle-ci dans n\'importe quel agenda électronique gérant le format iCal.';

// agenda view
$labels['listrange'] = 'Intervalle à afficher :';
$labels['listsections'] = 'Diviser en :';
$labels['smartsections'] = 'Section intelligente';
$labels['until'] = 'jusqu\'à';
$labels['today'] = 'Aujourd\'hui';
$labels['tomorrow'] = 'Demain';
$labels['thisweek'] = 'Cette semaine';
$labels['nextweek'] = 'Semaine prochaine';
$labels['thismonth'] = 'Ce mois';
$labels['nextmonth'] = 'Mois prochain';
$labels['weekofyear'] = 'Semaine';
$labels['pastevents'] = 'Passé';
$labels['futureevents'] = 'Futur';

// alarm/reminder settings
$labels['showalarms'] = 'Afficher les alarmes';
$labels['defaultalarmtype'] = 'Paramètre de rappel par défaut';
$labels['defaultalarmoffset'] = 'Durée de rappel par défaut';

// attendees
$labels['attendee'] = 'Participant';
$labels['role'] = 'Rôle';
$labels['availability'] = 'Dispo.';
$labels['confirmstate'] = 'Statut';
$labels['addattendee'] = 'Ajouter participant';
$labels['roleorganizer'] = 'Organisateur';
$labels['rolerequired'] = 'Requis';
$labels['roleoptional'] = 'Optionel';
$labels['roleresource'] = 'Ressource';
$labels['availfree'] = 'Disponible';
$labels['availbusy'] = 'Occupé';
$labels['availunknown'] = 'Inconnu';
$labels['availtentative'] = 'Provisoire';
$labels['availoutofoffice'] = 'Absent';
$labels['scheduletime'] = 'Trouver les disponibilités';
$labels['sendinvitations'] = 'Envoyer les invitations';
$labels['sendnotifications'] = 'Informer les participants des modifications';
$labels['sendcancellation'] = 'Informer les participants de l\'annulation';
$labels['onlyworkinghours'] = 'Trouver des disponibilités en fonction de mes heures de travail';
$labels['reqallattendees'] = 'Demandé/tous';
$labels['prevslot'] = 'Créneau précédent';
$labels['nextslot'] = 'Créneau suivant';
$labels['noslotfound'] = 'Impossible de trouver un créneau disponible';
$labels['invitationsubject'] = 'Vous avez invité à "$title"';
$labels['invitationmailbody'] = "*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees\n\nVous trouverez ci-joint un fichier iCalendar avec tous les détails de l'évènement que vous pourrez importer dans votre agenda électronique.";
$labels['invitationattendlinks'] = "Dans le cas où votre application de messagerie ne gère pas les demandes \"iTip\". Vous pouvez utiliser ce lien pour accepter ou refuser l'invitation :\n\$url";
$labels['eventupdatesubject'] = '"$title" a été modifié';
$labels['eventupdatesubjectempty'] = 'Un évènement vous concernant a été modifié';
$labels['eventupdatemailbody'] = "*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees\n\nVous trouverez ci-joint un fichier iCalendar avec tous les modifications de l'évènement que vous pourrez importer dans votre agenda électronique.";
$labels['eventcancelsubject'] = '"$title" a été annulé';
$labels['eventcancelmailbody'] = "*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees\n\nL'évènement a été annulé par \$organizer.\n\nVous trouverez en pièce jointe un fichier iCalendar avec les modifications de l'évènement que vous pourrez importer dans votre agenda électronique.";

// invitation handling
$labels['itipinvitation'] = 'Invitation à';
$labels['itipupdate'] = 'Mise à jour de';
$labels['itipcancellation'] = 'Annulation:';
$labels['itipreply'] = 'Répondre à';
$labels['itipaccepted'] = 'Accepter';
$labels['itiptentative'] = 'Peut-être';
$labels['itipdeclined'] = 'Refuser';
$labels['itipsubjectaccepted'] = '"$title" a été accepté par $name';
$labels['itipsubjecttentative'] = '"$title" a été accepté provisoirement par $name';
$labels['itipsubjectdeclined'] = '"$title" a été refusé par $name';
$labels['itipmailbodyaccepted'] = "\$sender a accepté l'invitation à l'évènement suivant :\n\n*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees";
$labels['itipmailbodytentative'] = "\$sender a accepté provisoirement l'invitation à l'évènement suivant :\n\n*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees";
$labels['itipmailbodydeclined'] = "\$sender a refusé l'invitation à l'évènement suivant :\n\n*\$title*\n\nWhen: \$date\n\nInvitees: \$attendees";
$labels['itipdeclineevent'] = 'Voulez-vous refuser l\'invitation à cet évènement?';
$labels['importtocalendar'] = 'Enregistrer mon agenda';
$labels['removefromcalendar'] = 'Supprimer de mon agenda';
$labels['updateattendeestatus'] = 'Modifier le statut des participants';
$labels['acceptinvitation'] = 'Acceptez-vous cette invitation?';
$labels['youhaveaccepted'] = 'Vous avez accepté cette invitation';
$labels['youhavetentative'] = 'Vous avez accepté provisoirement cette invitation';
$labels['youhavedeclined'] = 'Vous avez refusé cette invitation';
$labels['notanattendee'] = 'Vous n\'êtes pas dans la liste des participants à cet évènement';
$labels['eventcancelled'] = 'L\'évènement a été annulé';
$labels['saveincalendar'] = 'Enregistrer sous';

// event dialog tabs
$labels['tabsummary'] = 'Résumé';
$labels['tabrecurrence'] = 'Récurrence';
$labels['tabattendees'] = 'Participants';
$labels['tabattachments'] = 'Pièces jointes';
$labels['tabsharing'] = 'Partage';

// messages
$labels['deleteventconfirm'] = 'Voulez-vous vraiment supprimer cet évènement?';
$labels['deletecalendarconfirm'] = 'Voulez-vous vraiment supprimer cet agenda et tous ses évènements?';
$labels['savingdata'] = 'Enregistrer...';
$labels['errorsaving'] = 'Échec lors de l\'enregistrement des changements';
$labels['operationfailed'] = 'L\'opération demandée a échoué';
$labels['invalideventdates'] = 'Dates invalides! Veuillez vérifier votre saisie.';
$labels['invalidcalendarproperties'] = 'Propriétés d\'agenda invalides! Veuillez saisir un nom valide.';
$labels['searchnoresults'] = 'Pas d\'évènement trouvé dans les agendas sélectionnés.';
$labels['successremoval'] = 'L\'évènement a été supprimé.';
$labels['successrestore'] = 'L\'évènement a été restauré.';
$labels['errornotifying'] = 'Échec de l\'envoi de notification aux participants ';
$labels['errorimportingevent'] = 'Échec de l\'import de l\'évènement';
$labels['newerversionexists'] = 'Une nouvelle version de cet évènement existe! Abandon.';
$labels['nowritecalendarfound'] = 'Pas d\'agenda trouvé pour enregistrer l\'évènement';
$labels['importedsuccessfully'] = 'L\'évènement a été ajouté à l\'agenda \'$calendar\'';
$labels['attendeupdateesuccess'] = 'Le statut des participants a été modifié';
$labels['itipsendsuccess'] = 'Invitation envoyé aux participants.';
$labels['itipresponseerror'] = 'Échec de l\'envoi d\'une réponse à cette invitation.';
$labels['itipinvalidrequest'] = 'C\'est invitation n\'est plus valide.';
$labels['sentresponseto'] = 'La réponse à l\'invitation a été envoyé à $mailto';
$labels['localchangeswarning'] = 'Vous êtes sur le point de faire des changements uniquement visible sur votre agenda personnel.';
$labels['importsuccess'] = '$nr évènements importés.';
$labels['importnone'] = 'Pas d\'évènements à importer';
$labels['importerror'] = 'Une erreur est arrivée lors de l\'import';
$labels['aclnorights'] = 'Vous n\'avez pas les droits d\'administration sur cet agenda.';

// recurrence form
$labels['repeat'] = 'Répéter';
$labels['frequency'] = 'Répéter';
$labels['never'] = 'Jamais';
$labels['daily'] = 'Quotidienne';
$labels['weekly'] = 'Hebdomadaire';
$labels['monthly'] = 'Mensuelle';
$labels['yearly'] = 'Annuelle';
$labels['every'] = 'Tous les';
$labels['days'] = 'jour(s)';
$labels['weeks'] = 'semaine(s)';
$labels['months'] = 'mois';
$labels['years'] = 'année(s) en :';
$labels['bydays'] = 'Le';
$labels['untildate'] = 'le';
$labels['each'] = 'Chaque';
$labels['onevery'] = 'Tous les';
$labels['onsamedate'] = 'à la même date';
$labels['forever'] = 'Pour toujours';
$labels['recurrencend'] = 'Jusqu\'à';
$labels['forntimes'] = '$nr fois';
$labels['first'] = 'premier';
$labels['second'] = 'deuxième';
$labels['third'] = 'troisième';
$labels['fourth'] = 'quatrième';
$labels['last'] = 'dernier';
$labels['dayofmonth'] = 'Jour du mois';

$labels['changeeventconfirm'] = 'Modifier l\'évènement';
$labels['removeeventconfirm'] = 'Supprimer l\'évènement';
$labels['changerecurringeventwarning'] = 'Ceci est un évènement récurant. Voulez vous éditer seulement cette occurrence, celle-ci et toutes les suivantes, toutes les occurrences ou l\'enregistrer comme un nouvel évènement? ';
$labels['removerecurringeventwarning'] = 'Ceci est un évènement récurant. Voulez-vous supprimer seulement cette occurrence, celle-ci et toutes les suivantes ou toutes les occurrences de cet évènement?';
$labels['currentevent'] = 'Cette occurrence';
$labels['futurevents'] = 'Cette occurrence et toutes les suivantes';
$labels['allevents'] = 'Toutes les occurrences';
$labels['saveasnew'] = 'Enregistrer comme un nouvel évènement';

?>
