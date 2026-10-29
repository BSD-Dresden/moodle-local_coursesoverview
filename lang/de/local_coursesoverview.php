<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesssince'] = 'Zugang seit';
$string['applyfilters'] = 'Filtern';
$string['backtooverview'] = 'Zurück zur Kursübersicht';
$string['colemail'] = 'E-Mail-Adresse';
$string['colfirstname'] = 'Vorname';
$string['colfullname'] = 'Vorname und Nachname';
$string['colignore'] = 'Wird nicht gebraucht';
$string['collastfirst'] = 'Nachname, Vorname';
$string['collastname'] = 'Nachname';
$string['colnamemail'] = 'Name und Adresse in einer Zelle';
$string['colrole'] = 'Rolle';
$string['completed_lastseen'] = 'Kurs abgeschlossen / Zuletzt im Kurs';
$string['completedparticipants'] = 'Abgeschlossene Teilnehmer';
$string['completiondisabled'] = 'Die Abschlussverfolgung ist in diesem Kurs deaktiviert.';
$string['completionrate'] = 'Abschlussquote (%)';
$string['completionstatus'] = 'Abschlussstatus';
$string['coursehidden'] = 'Kurs „{$a}“ wurde verborgen.';
$string['courseshown'] = 'Kurs „{$a}“ ist wieder sichtbar.';
$string['coursesoverview:createorder'] = 'Auftrag samt zugehörigem Kurs anlegen';
$string['coursesoverview:view'] = 'Kursübersicht sehen';
$string['deletechecked'] = 'Ich habe die Liste geprüft';
$string['deleteconfirm'] = 'Kurs und aufgeführte Konten endgültig löschen';
$string['deletedone'] = 'Kurs „{$a->course}“ gelöscht, {$a->users} Nutzerkonten entfernt.';
$string['deleteheading'] = 'Kurs löschen: {$a}';
$string['deleteirreversible'] = 'Das Löschen lässt sich nicht rückgängig machen. Prüfen Sie beide Listen.';
$string['deletenothidden'] = 'Nur verborgene Kurse können hier gelöscht werden. Verbergen Sie den Kurs zuerst.';
$string['deletenousers'] = 'Es wird kein Nutzerkonto gelöscht, nur der Kurs.';
$string['deleteunchecked'] = 'Bitte bestätigen Sie zuerst, dass Sie die Liste geprüft haben.';
$string['deleteusers'] = 'Diese {$a} Nutzerkonten werden endgültig gelöscht';
$string['delimiterauto'] = 'Selbst erkennen';
$string['delimitercomma'] = 'Komma';
$string['delimitersemicolon'] = 'Semikolon';
$string['delimiterspace'] = 'Leerzeichen';
$string['delimitertab'] = 'Tabulator';
$string['exportexcel'] = 'Nach Excel exportieren';
$string['keepadmin'] = 'Administrator';
$string['keepcourses'] = 'noch in {$a} weiteren Kursen eingeschrieben';
$string['keepguest'] = 'Gastzugang';
$string['keeprole'] = 'Rolle außerhalb dieses Kurses';
$string['keepself'] = 'Ihr eigenes Konto';
$string['keepusers'] = 'Diese {$a} bleiben erhalten';
$string['neworder'] = 'Auftrag anlegen';
$string['nocourses'] = 'Derzeit keine laufenden Kurse.';
$string['nocoursesmatch'] = 'Keine Kurse entsprechen dem Filter.';
$string['nocriteria'] = 'Für diesen Kurs sind keine Abschlusskriterien definiert.';
$string['nogroup'] = 'Ohne Gruppe';
$string['noparticipants'] = 'Keine Teilnehmer vorhanden.';
$string['notifybody'] = 'Im Kurs "{$a->fullname}" ({$a->shortname}) haben alle {$a->total} Teilnehmer/innen abgeschlossen.

Kursende: {$a->enddate}

Teilnehmerliste: {$a->url}
Excel-Export: {$a->exporturl}';
$string['notifyemails'] = 'Empfänger der Abschlussmeldung';
$string['notifyemails_desc'] = 'Adressen, die eine Meldung erhalten, sobald in einem Kurs alle '
    . 'Teilnehmer/innen abgeschlossen haben. Mehrere durch Komma trennen, leer lassen schaltet die '
    . 'Meldung ab. Ein Moodle-Konto ist nicht nötig, ein Sammelpostfach genügt.';
$string['notifysubject'] = 'Kurs vollständig abgeschlossen: {$a}';
$string['orderabnr'] = 'AB-Nummer';
$string['orderadd_help'] = 'Kurs, Firma, AB-Nummer und Benutzernamen kommen aus dem Auftrag. Wie die Zugangsdaten der '
    . 'Nachgemeldeten rausgehen, lässt sich neu wählen.';
$string['orderaddeddone'] = 'In „{$a->course}“ nachgemeldet: {$a->created} Konten neu, {$a->reused} vorhandene Konten '
    . 'eingeschrieben.';
$string['orderaddheading'] = 'Teilnehmer nachmelden: {$a}';
$string['orderaddpeople'] = 'Teilnehmer nachmelden';
$string['orderalreadyincourse'] = '{$a} ist bereits in diesem Kurs eingeschrieben.';
$string['orderback'] = 'Zurück';
$string['orderbox'] = 'Auftrag';
$string['orderboxinfo'] = '{$a->company}, AB {$a->abnr}, angelegt am {$a->created}.';
$string['orderboxwaiting'] = 'Zugangsdaten für {$a->count} Personen warten auf den {$a->date}.';
$string['ordercheck'] = 'Gegen Moodle prüfen';
$string['ordercheck_help'] = 'Jede Zeile ist jetzt mit den vorhandenen Konten abgeglichen. Rote Zeilen halten den Auftrag auf. '
    . 'Rollen lassen sich hier noch ändern, danach aktualisieren.';
$string['ordercheckcount'] = '{$a->total} Zeilen, davon halten {$a->blocked} den Auftrag auf.';
$string['ordercheckheading'] = 'Prüfung';
$string['ordercolumn'] = 'Spalte {$a}';
$string['ordercolumns'] = 'Was in den Spalten steht';
$string['ordercolumns_help'] = 'Die Zuordnung unten ist aus der eingefügten Liste geraten. Korrigieren Sie, was danebenliegt, und '
    . 'aktualisieren Sie.';
$string['ordercompany'] = 'Firma';
$string['ordercontinue'] = 'Weiter';
$string['ordercourseexists'] = 'Ein Kurs mit dem Kurznamen „{$a}“ existiert bereits.';
$string['ordercoursefailed'] = 'Der Kurs ließ sich nicht aus seiner Vorlage anlegen.';
$string['ordercreate'] = 'Kurs und Konten anlegen';
$string['ordercredentialsfile'] = 'Zugangsdaten';
$string['ordercredentialsgone'] = 'Die Liste der Zugangsdaten ist nicht mehr verfügbar. Sie wird nur in der Sitzung vorgehalten, '
    . 'in der der Auftrag angelegt wurde.';
$string['orderdelimiter'] = 'Trennzeichen';
$string['orderdelivery'] = 'Zugangsdaten';
$string['orderdelivery_help'] = 'Ob die Zugangsdaten an die Adressen aus der Liste gehen oder als Liste zum Versand von Hand '
    . 'zurückkommen. Frei wählbar, auch wenn sich mehrere Personen eine Adresse teilen.';
$string['orderdeliverydirect'] = 'An die Teilnehmer senden';
$string['orderdeliverylist'] = 'Als Liste zurückgeben';
$string['orderdirectqueued'] = 'Die Zugangsdaten gehen mit dem nächsten Lauf der geplanten Aufgabe raus, die neue Passwörter '
    . 'verschickt.';
$string['orderdone'] = 'Kurs „{$a->course}“ angelegt, {$a->created} Konten neu, {$a->reused} vorhandene Konten eingeschrieben.';
$string['orderdownload'] = 'Zugangsdaten als Excel herunterladen';
$string['orderduplicateemail'] = 'Diese Adresse steht {$a}-mal in der Liste.';
$string['orderduplicateusername'] = 'Benutzername „{$a}“ kommt in dieser Liste mehrfach vor.';
$string['orderduplicateusernamehint'] = 'Bei geteilten Adressen als Benutzernamen vorname.nachname wählen.';
$string['orderenddate'] = 'Kursende';
$string['ordererrorabnr'] = 'Die AB-Nummer fehlt.';
$string['ordererrorcategory'] = 'Für das Modul „{$a}“ ist kein Kursbereich hinterlegt, und der Vorlagenkurs liefert auch keinen.';
$string['ordererrorcompany'] = 'Der Firmenname fehlt oder enthält nichts, was in einen Kurzname passt.';
$string['ordererrordate'] = 'Beginn und Ende müssen beide ein Datum sein.';
$string['ordererrorlist'] = 'Die Teilnehmerliste ist leer.';
$string['ordererrormodule'] = 'Bitte wählen Sie ein Modul.';
$string['ordererrorrange'] = 'Der Kurs kann nicht enden, bevor er beginnt.';
$string['ordererrortemplate'] = 'Es gibt keinen Kurs mit dem Kurznamen „{$a}“, also nichts zum Kopieren.';
$string['orderexists'] = 'Benutzername „{$a->username}“ gehört bereits zu einem Konto: {$a->name}, angelegt am {$a->created}.';
$string['orderexistscourses'] = 'Eingeschrieben in: {$a}.';
$string['orderfullname'] = 'Vollständiger Name';
$string['ordergotocourse'] = 'Zum Kurs';
$string['ordergotoparticipants'] = 'Zur Teilnehmerliste';
$string['orderhasheader'] = 'Erste Zeile benennt die Spalten';
$string['orderheld'] = 'Die Zugangsdaten für {$a->count} Personen gehen am {$a->date} raus. Bis dahin bleibt der Kurs für sie zu, '
    . 'und es geht keine Mail raus.';
$string['orderinvalidemail'] = '„{$a}“ ist keine Adresse.';
$string['orderlist'] = 'Teilnehmerliste';
$string['orderlist_help'] = 'Fügen Sie die Liste so ein, wie der Kunde sie geschickt hat, eine Person je Zeile.';
$string['orderlistready'] = 'Die Passwörter unten werden nur jetzt angezeigt und nirgends gespeichert. Laden Sie die Liste '
    . 'herunter, bevor Sie die Seite verlassen.';
$string['ordermailtaken'] = 'Diese Adresse nutzen bereits {$a} andere Konten.';
$string['ordermailtakenone'] = 'Diese Adresse nutzt bereits ein anderes Konto.';
$string['ordermissingemail'] = 'Adresse fehlt.';
$string['ordermissingemailcolumn'] = 'Keine Spalte enthält die E-Mail-Adresse.';
$string['ordermissingname'] = 'Name unvollständig.';
$string['ordermissingnamecolumn'] = 'Keine Spalte enthält den Nachnamen.';
$string['ordermodule'] = 'Modul';
$string['ordermodules'] = 'Bestellbare Module';
$string['ordermodules_desc'] = 'Ein Modul je Zeile, vier Felder durch einen senkrechten Strich getrennt: Name, Kurzname des '
    . 'Vorlagenkurses, ID des Kursbereichs, Präfix des erzeugten Kurznamens. Ohne Kursbereich landet der neue Kurs dort, wo seine '
    . 'Vorlage liegt.<br />Beispiel: <code>Grundmodul | OJU_Vorlage_GM | 12 | OJU_GM</code>';
$string['ordermonths'] = 'Laufzeit in Monaten';
$string['ordermonths_desc'] = 'Füllt das Kursende beim Anlegen vor und bleibt je Auftrag änderbar.';
$string['ordernolines'] = 'Aus der eingefügten Liste ließ sich nichts lesen.';
$string['ordernomanualenrol'] = 'Der neue Kurs hat keine manuelle Einschreibung, es konnte niemand eingeschrieben werden.';
$string['ordernomodules'] = 'Es ist noch kein Modul eingerichtet. Ein Auftrag braucht mindestens eines.';
$string['ordernopeople'] = 'Die Liste ergibt niemanden. Prüfen Sie das Trennzeichen und die Zuordnung.';
$string['ordernotcreated'] = 'Konto „{$a}“ wurde nicht angelegt.';
$string['ordernotes'] = 'Hinweise';
$string['ordernousername'] = 'Aus dieser Zeile lässt sich kein Benutzername bilden.';
$string['orderpattern'] = 'Muster für den vollständigen Kursnamen';
$string['orderpattern_desc'] = 'Platzhalter: {module}, {company}, {abnr}, {shortname}. Leer lassen, wenn der Kurzname für beides '
    . 'reichen soll.';
$string['orderpeople'] = 'Was gelesen wurde';
$string['orderpeoplecount'] = '{$a->total} Personen, davon {$a->flawed} mit Hinweisen.';
$string['orderrelease'] = 'Jetzt freischalten und senden';
$string['orderreleaseconfirm'] = 'Die Einschreibungen öffnen sofort, und die Zugangsdaten für {$a->count} Personen gehen jetzt '
    . 'raus statt am {$a->date}. Fortfahren?';
$string['orderreleased'] = 'Kurs freigeschaltet, {$a} Zugangsdaten verschickt.';
$string['orderreuse'] = 'Vorhandenes Konto verwenden und nur einschreiben';
$string['orderreused'] = 'Das vorhandene Konto wird weiterverwendet und bekommt keine neuen Zugangsdaten.';
$string['orderreusehint'] = 'Ein vorhandenes Konto behalten darf nur ein Organisator.';
$string['orderrow'] = 'Zeile';
$string['orderruntime'] = 'Laufzeit';
$string['orderscheme'] = 'Benutzernamen';
$string['orderscheme_help'] = 'Die Adresse ist der einfachere Weg, geht aber nur einmal je Person. Ein geteiltes Postfach braucht '
    . 'deshalb vorname.nachname.';
$string['orderschemeemail'] = 'E-Mail-Adresse';
$string['orderschemename'] = 'vorname.nachname';
$string['ordershortname'] = 'Kurzname des Kurses';
$string['orderstartdate'] = 'Kursbeginn';
$string['orderstartdate_help'] = 'Ein Beginn in der Zukunft hält die Einschreibung zurück und die Zugangsdaten mit ihr.';
$string['orderstartpassword'] = 'Start-Passwort';
$string['orderupdate'] = 'Aktualisieren';
$string['orderusername'] = 'Benutzername';
$string['orderwithoutcourse'] = 'Der Kurs dieses Auftrags existiert nicht mehr, nachmelden ist nicht möglich.';
$string['organiser'] = 'Organisator';
$string['organisers'] = 'Organisatoren';
$string['overview'] = 'Kursübersicht';
$string['pluginname'] = 'Online-Unterweisungen';
$string['progressheader'] = 'Abschnitte abgeschlossen / Gesamt';
$string['roleboth'] = 'Organisator und Teilnehmer';
$string['roleorganiser'] = 'Organisator';
$string['roleparticipant'] = 'Teilnehmer';
$string['settings'] = 'Online-Unterweisungen: Einstellungen';
$string['statusactive'] = 'Laufend';
$string['statuscompleted'] = 'Abgeschlossen';
$string['statusendingsoon'] = 'Endet demnächst, nicht abgeschlossen';
$string['statushidden'] = 'Verborgen (abgerechnet)';
$string['statusinprogress'] = 'Begonnen, nicht abgeschlossen';
$string['statusnotstarted'] = 'Nicht begonnen';
$string['statusoverdue'] = 'Beendet, nicht abgeschlossen';
$string['statuspast'] = 'Beendet';
$string['statustodo'] = 'Handlungsbedarf';
$string['statustosettle'] = 'Abgeschlossen, noch nicht abgerechnet';
$string['tasknotifycompleted'] = 'Meldung über vollständig abgeschlossene Kurse';
$string['tasksendcredentials'] = 'Zurückgehaltene Zugangsdaten zum Kursbeginn verschicken';
$string['totalparticipants'] = 'Gesamtteilnehmer';
$string['withoutparticipants'] = 'Kurse ohne Teilnehmer anzeigen';
