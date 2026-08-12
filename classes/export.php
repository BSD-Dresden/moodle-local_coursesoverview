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

namespace local_coursesoverview;

use core_text;

/**
 * Spreadsheet export.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export {

    /**
     * Send the table as a download and stop.
     *
     * Excel is written through MoodleExcelWorkbook (PhpSpreadsheet) rather
     * than the core "excel" dataformat: that one is Spout based, writes
     * straight to php://output and cannot colour cells. Any other requested
     * format is handed to the core dataformat plugins, which support neither
     * colours nor the preamble.
     *
     * The export date is appended to the file name, so that repeatedly
     * exported files do not overwrite each other in the download folder.
     *
     * @param string $dataformat requested format, e.g. 'excel'
     * @param string $filename without extension and without date
     * @param string $sheetname worksheet title, sanitised here
     * @param array $columns column headers
     * @param array $rows list of ['values' => array, 'bgcolor' => string|null]
     * @param array $preamble lines written above the table, each an array of
     *      cell values whose first entry is rendered bold
     */
    public static function download(string $dataformat, string $filename, string $sheetname,
            array $columns, array $rows, array $preamble = []): void {
        // ISO order, so that the files sort chronologically by name.
        $filename = clean_filename($filename . '_' . userdate(time(), '%Y-%m-%d'));

        if ($dataformat === 'excel') {
            self::excel($filename, $sheetname, $columns, $rows, $preamble);
        }

        $plain = [];
        foreach ($rows as $row) {
            $plain[] = $row['values'];
        }

        \core\dataformat::download_data($filename, $dataformat, $columns, $plain);
    }

    /**
     * Write the table to an xlsx file and send it to the browser.
     *
     * @param string $filename without extension
     * @param string $sheetname worksheet title
     * @param array $columns column headers
     * @param array $rows list of ['values' => array, 'bgcolor' => string|null]
     * @param array $preamble lines written above the table
     */
    protected static function excel(string $filename, string $sheetname, array $columns,
            array $rows, array $preamble = []): void {
        global $CFG;

        require_once($CFG->libdir . '/excellib.class.php');

        // Excel rejects these characters in sheet titles and caps them at 31
        // characters; either one triggers the "repair" dialog on open.
        $sheetname = preg_replace('#[\\\\/?*\[\]:]#u', ' ', $sheetname);
        $sheetname = trim(core_text::substr($sheetname, 0, 31));

        $workbook = new \MoodleExcelWorkbook('-');
        $workbook->send($filename);

        $worksheet = $workbook->add_worksheet($sheetname !== '' ? $sheetname : 'Export');

        $boldformat = $workbook->add_format(['bold' => 1]);
        $rownum = 0;

        // Context lines above the table, mirroring what the page shows there.
        foreach ($preamble as $line) {
            foreach (array_values($line) as $col => $value) {
                $worksheet->write_string($rownum, $col, (string) $value,
                    $col === 0 ? $boldformat : null);
            }
            $rownum++;
        }
        if ($preamble) {
            $rownum++;
        }

        foreach (array_values($columns) as $col => $label) {
            $worksheet->write_string($rownum, $col, (string) $label, $boldformat);
            $worksheet->set_column($col, $col,
                min(40, max(12, core_text::strlen((string) $label) + 4)));
        }
        $rownum++;

        // One format object per colour, reused across rows.
        $formats = [];

        foreach ($rows as $row) {
            $format = null;
            $bgcolor = $row['bgcolor'] ?? null;
            if (!empty($bgcolor)) {
                if (!isset($formats[$bgcolor])) {
                    $formats[$bgcolor] = $workbook->add_format(['bg_color' => $bgcolor]);
                }
                $format = $formats[$bgcolor];
            }

            foreach (array_values($row['values']) as $col => $value) {
                if (is_int($value) || is_float($value)) {
                    $worksheet->write_number($rownum, $col, $value, $format);
                } else if ($value === null || $value === '') {
                    $worksheet->write_blank($rownum, $col, $format);
                } else {
                    $worksheet->write_string($rownum, $col, (string) $value, $format);
                }
            }

            $rownum++;
        }

        $workbook->close();
        exit;
    }
}
