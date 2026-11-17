# Kursübersicht (local_coursesoverview)

Moodle-Plugin für interne Zwecke. Es beantwortet zwei Fragen, für die man sonst
durch mehrere Moodle-Berichte klicken müsste:

1. **Wie steht es um alle Kurse?** Tabelle über sämtliche Kurse mit Laufzeit,
   Anzahl abgeschlossener Teilnehmer und einem Status, der zeigt, wo etwas zu
   tun ist. Filterbar nach Suchbegriff, Kategorie und Status, sortierbar,
   exportierbar.
2. **Wer in einem Kurs ist fertig?** Abschlussstatus aller Teilnehmer eines
   Kurses, bei Bedarf nach Gruppen getrennt.

## Wer als Teilnehmer zählt

Beide Seiten fragen nach demselben Kriterium: eingeschrieben, Einschreibung
aktiv, und im Besitz von `moodle/course:isincompletionreports`. Das ist
dieselbe Menge, die Moodles Abschlussberichte verwenden — standardmäßig also
die Teilnehmerrolle, nicht Organisatoren, Trainer oder Manager.

Wer eingeschrieben ist, aber nicht dazuzählt, erscheint auf der Detailseite
als **Organisator** über der Tabelle: bei Gruppen über der jeweiligen Gruppe,
sonst über dem Kurs. Organisatoren ohne Gruppenzugehörigkeit stehen oben beim
Kurs, weil sie ihn als Ganzes betreuen.

Im **Excel-Export stehen sie nicht**. Er wird aus den Teilnehmerzeilen gebaut,
und dort kommen Organisatoren gar nicht erst vor.

## Profillinks

Teilnehmernamen führen ins Kursprofil, so wie in Moodles eigenen Tabellen über
`flexible_table::col_fullname()`. Verlinkt wird aber nur, wer
`moodle/user:viewuseractivitiesreport` **im eigenen Nutzerkontext** besitzt —
Administratoren und Manager also, nicht eine Rolle, die nur in einem einzelnen
Kurs vergeben wurde. Dieselbe Prüfung hält die Links aus dem Kern-Abschlussbericht
heraus. Im Export stehen ohnehin nur Namen als Text.

## Installation

Nach `local/coursesoverview` entpacken, dann:

```bash
php admin/cli/upgrade.php --non-interactive
```

Aufruf über **Website-Administration → Schulungsverwaltung → Kursübersicht**. Der
Abschlussstatus eines einzelnen Kurses steht zusätzlich in dessen Kursmenü
unter **Mehr**, für alle, die die Berechtigung in diesem Kurs besitzen.

Voraussetzung: Moodle 4.5+, PHP 8.1+. Getestet gegen Moodle 4.5 mit PHP 8.2 und
gegen 5.2 mit PHP 8.3, jeweils auf PostgreSQL.

## Berechtigung

Beide Seiten hängen an `local/coursesoverview:view`, standardmäßig nur für
Manager. Verbergen und Anzeigen von Kursen erfordert zusätzlich
`moodle/course:visibility` auf dem jeweiligen Kurs.

## Status der Kurse

Der erste zutreffende Fall gewinnt:

| Zustand | Bedingung | Farbe |
|---|---|---|
| Erledigt | von Hand als erledigt markiert | grau |
| Verborgen | Kurs ist verborgen, aber nicht erledigt (etwa eine Vorlage) | keine |
| Beginnt später | Kurs beginnt später, noch niemand eingeschrieben | keine |
| Abgeschlossen, noch nicht erledigt | alle Teilnehmer fertig | grün |
| Beendet, nicht abgeschlossen | Enddatum vorbei, noch offen | rot |
| Endet demnächst, nicht abgeschlossen | Enddatum in ≤ 14 Tagen, noch offen | gelb |
| Beendet | Enddatum vorbei, Quote unbekannt | keine |
| Laufend | alles andere | keine |

Zwei Dinge sind absichtlich so gebaut:

* **Erledigt schlägt alles.** Erledigt heißt: Die Abschlüsse sind
  weitergegeben, es ist nichts mehr zu tun. „Erledigt“ in der Aktionsspalte
  merkt das in einer eigenen Tabelle und verbirgt den Kurs, damit die
  Teilnehmer nicht mehr hineinkommen. „Wieder freigeben“ nimmt beides zurück,
  etwa bei einer Verlängerung. Bis November 2026 stand Verbergen allein für
  erledigt; beim Upgrade wurden verborgene Kurse mit vergangenem Kursende
  übernommen, Vorlagen ohne Kursende nicht.
* **Ohne Abschlussverfolgung wird nie eingefärbt.** Die Quote ist dann nicht
  null, sondern unbekannt.

Die Schwelle für „endet demnächst" steht als Konstante `ENDING_SOON` in
`classes/helper.php`.

## Aufträge in der Übersicht

Wer `local/coursesoverview:createorder` besitzt, sieht zu jedem Kurs aus einem
Auftrag dessen Firma, AB-Nummer, Modul und wie die Zugangsdaten verschickt
wurden; die Suche findet auch Firma und AB-Nummer. Kurse ohne Auftrag lassen
diese Felder leer, der Filter „Herkunft“ trennt beides. Aufträge, deren Kurs
gelöscht ist, stehen unter dem Status „Gelöscht“ mit den Zahlen von kurz vor
dem Löschen. Wer nur `local/coursesoverview:view` hat, sieht die Übersicht ohne
Auftragsdaten. Die frühere Seite `orders.php` leitet auf die Übersicht um.

## Teilnahmebestätigungen

Unter **Einstellungen → Teilnahmebestätigungen** liegt eine allgemeine
PDF-Vorlage und auf Wunsch eine eigene je Modul. Logo, Unterschrift und
Wortlaut stehen nur in diesen Dateien, nicht im Plugin.

In Word stehen an den passenden Stellen Platzhalter wie `{name}` und `{date}`
in weißer Schrift, dann wird als PDF gespeichert. `classes/pdf_placeholders.php`
liest mit dem Parser von FPDI den Text der ersten Seite samt Position, Größe
und Schriftschnitt und findet die Platzhalter dort, auch wenn Word sie auf
mehrere Textstücke verteilt hat. Beim Übernehmen der Seite werden die
Platzhalter aus dem Seiteninhalt entfernt und durch einen Sprung gleicher
Breite ersetzt, im fertigen PDF lassen sie sich also weder markieren noch
kopieren oder finden. Moodle schreibt dann mit TCPDF und FPDI
(beide im Kern) die Werte an diese Stellen; kommt ein Platzhalter mehrfach
vor, wird jede Stelle gefüllt. `{name:center}` und
`{name:right}` richten an Mitte oder Ende des Platzhalters aus. Eine Vorlage
ohne Platzhalter bekommt Name und Datum an Positionen in Millimetern bis zur
Grundlinie, die in den Einstellungen stehen. Die Vorschau zeigt beides auf
einem Raster. Word auf dem Server nach PDF umzuwandeln bräuchte LibreOffice.

Auf der Seite **Abschlussstatus** und in der Meldung nach „Erledigt“ steht
dann ein Link, der für jede Person mit Abschluss ein eigenes PDF mit ihrem
Abschlussdatum erzeugt und alle zusammen als ZIP liefert. Nur mit
`local/coursesoverview:createorder`, Organisatoren der Kunden sehen ihn nicht.
Kurse ohne Auftrag und Module ohne eigene Vorlage nehmen die allgemeine.

## Kurs mit Teilnehmern löschen

In der Aktionsspalte der Übersicht steht bei **erledigten** Kursen ein
`Löschen`. Dadurch kann nichts entfernt werden, das nicht vorher bewusst als
erledigt markiert wurde. Der Link führt
auf eine Bestätigungsseite und löscht nichts.

Dort stehen zwei Listen: die Konten, die mitgelöscht werden, und die, die
bleiben, jeweils mit Grund. Erst nach dem Setzen des Hakens ist der Löschknopf
wirksam; der Haken wird auch serverseitig geprüft.

Ein Konto bleibt erhalten, wenn es

* einem Administrator gehört, dem Gastzugang oder Ihnen selbst,
* eine Rolle außerhalb dieses Kurses besitzt — System- oder Kursbereichsebene,
* oder in **irgendeinem** anderen Kurs eingeschrieben ist, auch verborgen,
  abgelaufen oder ausgesetzt.

Die Rolle im zu löschenden Kurs spielt keine Rolle: Organisatoren werden
mitgelöscht, wenn sie sonst nirgends gebraucht werden.

Nötig sind `moodle/course:delete` im Kurs sowie `moodle/user:delete` und
`local/coursesoverview:view` auf Systemebene.

**Was Löschen bedeutet.** Für Konten wird `delete_user()` verwendet, dieselbe
Funktion wie in Moodles Bulk-Löschung. Sie entfernt Bewertungen, Einschreibungen,
Rollen, Gruppen- und Kalendereinträge, markiert den Datensatz dann als gelöscht
und ersetzt Anmeldename und E-Mail — **Vor- und Nachname bleiben in der
`user`-Tabelle stehen**. Der Kurs selbst wird über `delete_course()` wirklich
entfernt. Beides ist nicht rückgängig zu machen.

## Meldung bei vollständigem Abschluss

Eine geplante Aufgabe meldet per E-Mail, sobald ein Kurs in den grünen Zustand
wechselt — also sobald der letzte Teilnehmer fertig ist. Empfänger eintragen
unter **Website-Administration → Schulungsverwaltung → Einstellungen → Aufträge und Module**; ohne
Eintrag passiert nichts. Die Adressen brauchen kein Moodle-Konto.

Zwei Eigenheiten:

* **Der erste Lauf meldet nichts.** Er merkt sich nur, welche Kurse bereits
  abgeschlossen sind. Sonst käme für jeden vor der Einführung fertigen Kurs
  eine Mail.
* **Erneute Meldung ist gewollt.** Gespeichert wird der aktuelle Stand, nicht
  die Summe aller je gemeldeten Kurse. Wird jemand nachträglich eingeschrieben,
  fällt der Kurs aus dem Zustand heraus und wird erneut gemeldet, sobald auch
  diese Person fertig ist — genau der Nachzügler, auf den man wartet.

Die Aufgabe läuft stündlich, einstellbar unter **Server → Geplante Aufgaben**.

## Aufbau

```
classes/certificate.php  Teilnahmebestätigungen auf der PDF-Vorlage
classes/certificate_pdf.php  FPDI mit ausgetauschtem Seiteninhalt
classes/done.php      Als erledigt markierte Kurse
classes/pdf_placeholders.php  Platzhalter in der PDF-Vorlage finden und entfernen
classes/helper.php    Datumsformat, Kurszustände, Sortierung
classes/output.php    Filterleiste, Legende, Tabellen, Sortier-Links
classes/export.php    Excel-Export, als Excel-Tabelle mit Filter
classes/order/        Aufträge: Anlegen, Versand, Erinnerungen, Liste
classes/purge.php     Wer beim Löschen eines Kurses mitgeht
classes/task/         Geplante Aufgabe für die Abschlussmeldung
db/tasks.php          Zeitplan der Aufgabe
index.php             Kursübersicht
participants.php      Abschlussstatus eines Kurses
delete.php            Bestätigung und Löschung von Kurs samt Konten
lib.php               Eintrag im Kursmenü, Löschen auf der Profilseite
orders.php            Eintrag eines gelöschten Auftrags entfernen
certificate.php       Teilnahmebestätigungen eines Kurses als ZIP
settings.php          Admin-Baum und Einstellungen
styles.css            Zeilenfarben
```

CI läuft bei jedem Push über `moodle-plugin-ci`, siehe `.github/workflows/ci.yml`.

## Lizenz

GNU GPL v3 oder später.
