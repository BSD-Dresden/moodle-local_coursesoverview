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

Aufruf über **Website-Administration → Kurse → Kursübersicht**. Der
Abschlussstatus eines einzelnen Kurses steht zusätzlich in dessen Kursmenü
unter **Mehr**, für alle, die die Berechtigung in diesem Kurs besitzen.

Voraussetzung: Moodle 4.2+, PHP 8.0+. Getestet gegen Moodle 4.2 mit PHP 8.2 und
PostgreSQL.

## Berechtigung

Beide Seiten hängen an `local/coursesoverview:view`, standardmäßig nur für
Manager. Verbergen und Anzeigen von Kursen erfordert zusätzlich
`moodle/course:visibility` auf dem jeweiligen Kurs.

## Status der Kurse

Der erste zutreffende Fall gewinnt:

| Zustand | Bedingung | Farbe |
|---|---|---|
| Verborgen (abgerechnet) | Kurs ist verborgen | grau |
| Abgeschlossen, noch nicht abgerechnet | alle Teilnehmer fertig | grün |
| Beendet, nicht abgeschlossen | Enddatum vorbei, noch offen | rot |
| Endet demnächst, nicht abgeschlossen | Enddatum in ≤ 14 Tagen, noch offen | gelb |
| Beendet | Enddatum vorbei, Quote unbekannt | keine |
| Laufend | alles andere | keine |

Zwei Dinge sind absichtlich so gebaut:

* **Verborgen schlägt alles.** Ein Kurs wird von Hand verborgen, sobald er
  abgerechnet ist, und darf danach nicht mehr in der Nachfassliste auftauchen.
* **Ohne Abschlussverfolgung wird nie eingefärbt.** Die Quote ist dann nicht
  null, sondern unbekannt.

Die Schwelle für „endet demnächst" steht als Konstante `ENDING_SOON` in
`classes/helper.php`.

## Meldung bei vollständigem Abschluss

Eine geplante Aufgabe meldet per E-Mail, sobald ein Kurs in den grünen Zustand
wechselt — also sobald der letzte Teilnehmer fertig ist. Empfänger eintragen
unter **Website-Administration → Kurse → Kursübersicht: Einstellungen**; ohne
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
classes/helper.php    Datumsformat, Kurszustände, Sortierung
classes/output.php    Filterleiste, Legende, Tabellen, Sortier-Links
classes/export.php    Excel-Export
classes/task/         Geplante Aufgabe für die Abschlussmeldung
db/tasks.php          Zeitplan der Aufgabe
index.php             Kursübersicht
participants.php      Abschlussstatus eines Kurses
lib.php               Eintrag im Kursmenü
settings.php          Admin-Baum und Einstellungen
styles.css            Zeilenfarben
```

CI läuft bei jedem Push über `moodle-plugin-ci`, siehe `.github/workflows/ci.yml`.

## Lizenz

GNU GPL v3 oder später.
