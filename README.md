# Kursübersicht (local_coursesoverview)

Moodle-Plugin für interne Zwecke. Es beantwortet zwei Fragen, für die man sonst
durch mehrere Moodle-Berichte klicken müsste:

1. **Wie steht es um alle Kurse?** Tabelle über sämtliche Kurse mit Laufzeit,
   Anzahl abgeschlossener Teilnehmer und einem Status, der zeigt, wo etwas zu
   tun ist. Filterbar nach Suchbegriff, Kategorie und Status, sortierbar,
   exportierbar.
2. **Wer in einem Kurs ist fertig?** Abschlussstatus aller Teilnehmer eines
   Kurses, bei Bedarf nach Gruppen getrennt.

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

## Aufbau

```
classes/helper.php    Datumsformat, Kurszustände, Sortierung
classes/output.php    Filterleiste, Legende, Tabellen, Sortier-Links
classes/export.php    Excel-Export
index.php             Kursübersicht
participants.php      Abschlussstatus eines Kurses
lib.php               Eintrag im Kursmenü
settings.php          Eintrag im Admin-Baum
styles.css            Zeilenfarben
```

CI läuft bei jedem Push über `moodle-plugin-ci`, siehe `.github/workflows/ci.yml`.

## Lizenz

GNU GPL v3 oder später.
