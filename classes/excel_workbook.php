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

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/excellib.class.php');

/**
 * Moodle's workbook plus a real Excel table over the data.
 *
 * MoodleExcelWorksheet keeps its PhpSpreadsheet sheet to itself, the workbook
 * only hands it to subclasses, hence a subclass for this one step.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excel_workbook extends \MoodleExcelWorkbook {
    /**
     * Turn the block below the preamble into an Excel table on the last sheet.
     *
     * A table gives every header a filter and sort button, and Excel keeps it
     * together when sorting. The light style without stripes keeps the row
     * colours readable, they carry the status. The header row stays in view.
     *
     * @param int $headerrow zero based row of the column headers
     * @param int $lastrow zero based row of the last data row
     * @param int $columns number of columns
     */
    public function add_table(int $headerrow, int $lastrow, int $columns): void {
        // Excel refuses a table without a single data row.
        if ($lastrow <= $headerrow || $columns < 1) {
            return;
        }

        $sheet = $this->objspreadsheet->getSheet($this->objspreadsheet->getSheetCount() - 1);
        $range = 'A' . ($headerrow + 1) . ':' . Coordinate::stringFromColumnIndex($columns) . ($lastrow + 1);

        $style = new TableStyle(TableStyle::TABLE_STYLE_LIGHT1);
        $style->setShowRowStripes(false);
        $table = new Table($range, 'Daten');
        $table->setStyle($style);
        $sheet->addTable($table);

        $sheet->freezePane('A' . ($headerrow + 2));
    }
}
