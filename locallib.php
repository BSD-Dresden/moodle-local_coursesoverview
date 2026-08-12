<?php
/**
 * Shared helpers for local_coursesoverview.
 *
 * @package local_coursesoverview
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/** Courses ending within this window count as "ending soon". */
define('LOCAL_COURSESOVERVIEW_ENDING_SOON', 14 * DAYSECS);

/** Class marking the tables whose row colouring this plugin controls. */
define('LOCAL_COURSESOVERVIEW_TABLE_CLASS', 'coursesoverview-table');

/**
 * Id of the wrapper the tables are rendered into.
 *
 * The row colours are scoped to this id on purpose. Themes and site custom CSS
 * style table rows with selectors such as
 * "table.generaltable tbody tr:nth-child(even) td", which carries more
 * specificity than any class based rule we could write and often !important on
 * top. An id outranks any number of classes, so scoping by id is what makes
 * our colours stick regardless of the theme.
 */
define('LOCAL_COURSESOVERVIEW_WRAPPER_ID', 'coursesoverview-tables');

/**
 * Open the wrapper the row colouring is scoped to.
 *
 * @return string
 */
function local_coursesoverview_start_tables(): string {
    return html_writer::start_div('', ['id' => LOCAL_COURSESOVERVIEW_WRAPPER_ID]);
}

/**
 * Close the wrapper opened by local_coursesoverview_start_tables().
 *
 * @return string
 */
function local_coursesoverview_end_tables(): string {
    return html_writer::end_div();
}

/**
 * Row highlight colours, shared by the on-screen tables and the Excel export
 * so both stay in sync.
 *
 * @return array state key => hex colour, or null for "no highlight"
 */
function local_coursesoverview_colors(): array {
    return [
        // Participants.
        'notstarted' => '#f8d7da',
        'inprogress' => '#fff3cd',
        'completed'  => '#d4edda',
        // Courses.
        'overdue'    => '#f5c6cb',
        'endingsoon' => '#fff3cd',
        'tosettle'   => '#d4edda',
        'hidden'     => '#e0e0e0',
        // Ended, but the completion rate is unknown: stated, not highlighted.
        'past'       => null,
        'active'     => null,
    ];
}

/**
 * Determine the highlight state of a course row.
 *
 * Hidden wins over everything: hiding a course is the manual marker for
 * "settled, may eventually be deleted", so it must never be nagged about.
 * Everybody being done comes next, whether or not the course has ended: that
 * is what makes a course ready to be settled. Courses without completion
 * tracking have no known rate and are never flagged.
 *
 * The counts are compared directly rather than via a rounded percentage, so
 * that 199 of 200 participants does not round up to "everybody done".
 *
 * @param stdClass $course needs visible and enddate
 * @param int|null $completed participants that completed, null if unknown
 * @param int $total tracked participants
 * @param int $now
 * @return string state key
 */
function local_coursesoverview_course_state(stdClass $course, ?int $completed, int $total,
        int $now): string {
    if ($course->visible == 0) {
        return 'hidden';
    }

    if ($completed !== null && $total > 0 && $completed >= $total) {
        return 'tosettle';
    }

    $incomplete = ($completed !== null);
    $ended = (!empty($course->enddate) && $course->enddate < $now);

    if ($ended) {
        return $incomplete ? 'overdue' : 'past';
    }

    if ($incomplete && !empty($course->enddate)
            && $course->enddate <= $now + LOCAL_COURSESOVERVIEW_ENDING_SOON) {
        return 'endingsoon';
    }

    return 'active';
}

/**
 * Render a colour legend for the given states.
 *
 * @param array $states state keys, in the order they should be listed
 * @return string
 */
function local_coursesoverview_legend(array $states): string {
    $colors = local_coursesoverview_colors();
    $items = '';

    foreach ($states as $state) {
        if (empty($colors[$state])) {
            continue;
        }

        $swatch = html_writer::span('', 'coursesoverview-swatch', ['style' =>
            'display: inline-block; width: 1em; height: 1em; margin-right: 0.4em;' .
            ' vertical-align: text-bottom; border: 1px solid #ccc;' .
            ' background-color: ' . $colors[$state] . ';']);

        $items .= html_writer::span(
            $swatch . get_string('status' . $state, 'local_coursesoverview'),
            'd-inline-block mr-3 me-3 mb-1'
        );
    }

    return html_writer::div($items, 'coursesoverview-legend mb-3');
}

/**
 * Build the <style> block highlighting table rows for the given states.
 *
 * The rules target td rather than tr so the theme's own row striping does not
 * win over them.
 *
 * @param array $states state keys to emit rules for
 * @param array $extra optional extra declarations per state, e.g. a text colour
 * @return string
 */
function local_coursesoverview_row_styles(array $states, array $extra = []): string {
    $colors = local_coursesoverview_colors();
    $table = '#' . LOCAL_COURSESOVERVIEW_WRAPPER_ID;

    // Row striping and hover reach the cell through several routes depending
    // on the Bootstrap version and the theme: a background colour on the tr
    // that shows through a transparent cell, an inset box shadow on the cell
    // in Bootstrap 4 and 5, a gradient background image in 5.3, and the
    // accent variables feeding both. Chasing them one at a time did not hold
    // up, so every route is closed here for our tables only, on the row and
    // on the cell. Cells get an opaque default background rather than a
    // transparent one, so nothing can show through from behind.
    $css = "{$table} tbody tr,\n{$table} tbody tr:hover {\n" .
           "    background-color: transparent !important;\n" .
           "    background-image: none !important;\n" .
           "    box-shadow: none !important;\n" .
           "}\n" .
           "{$table} tbody tr td,\n{$table} tbody tr:hover td {\n" .
           "    background-color: var(--bs-body-bg, #fff) !important;\n" .
           "    background-image: none !important;\n" .
           "    box-shadow: none !important;\n" .
           "    filter: none !important;\n" .
           "    --bs-table-accent-bg: transparent !important;\n" .
           "    --bs-table-bg-type: transparent !important;\n" .
           "}\n";

    foreach ($states as $state) {
        $declarations = [];
        if (!empty($colors[$state])) {
            $declarations[] = 'background-color: ' . $colors[$state] . ' !important';
        }
        if (!empty($extra[$state])) {
            $declarations[] = $extra[$state];
        }
        if ($declarations) {
            // One class more specific than the reset, and emitted after the
            // hover reset it ties with, so it wins on both counts.
            $rule = ' { ' . implode('; ', $declarations) . "; }\n";
            $css .= "{$table} tbody tr.coursesoverview-{$state} td," .
                "\n{$table} tbody tr.coursesoverview-{$state}:hover td" . $rule;
        }
    }

    return html_writer::tag('style', $css);
}

/**
 * Format a timestamp as a plain date in the user's timezone/locale.
 *
 * Returns a dash for empty timestamps so that "no date set" is not rendered
 * as 01.01.1970.
 *
 * @param int|null $timestamp
 * @return string
 */
function local_coursesoverview_format_date($timestamp): string {
    if (empty($timestamp)) {
        return '—';
    }
    return userdate($timestamp, get_string('strftimedaydate', 'langconfig'));
}

/**
 * Return the course completion criteria, course criteria first, then activity
 * criteria, then everything else.
 *
 * @param completion_info $completion
 * @return array criteria objects, keyed as returned by completion_info
 */
function local_coursesoverview_get_sorted_criteria(completion_info $completion): array {
    $weights = [
        COMPLETION_CRITERIA_TYPE_COURSE   => 0,
        COMPLETION_CRITERIA_TYPE_ACTIVITY => 1,
    ];

    $criteria = $completion->get_criteria();

    uasort($criteria, function($a, $b) use ($weights) {
        $wa = $weights[$a->criteriatype] ?? 2;
        $wb = $weights[$b->criteriatype] ?? 2;
        if ($wa !== $wb) {
            return $wa <=> $wb;
        }
        return $a->id <=> $b->id;
    });

    return $criteria;
}

/**
 * Validate a requested sort column against the columns a page actually offers.
 *
 * @param string $sort requested column
 * @param array $allowed allowed column names
 * @param string $default fallback when the request is not allowed
 * @return string
 */
function local_coursesoverview_validate_sort(string $sort, array $allowed, string $default): string {
    return in_array($sort, $allowed, true) ? $sort : $default;
}

/**
 * Sort table rows by one of the keys in their 'sort' array.
 *
 * Strings are compared locale aware, everything else numerically.
 *
 * @param array $rows rows as built by the pages, each with a 'sort' array
 * @param string $key sort column
 * @param bool $descending
 * @return array re-indexed, sorted rows
 */
function local_coursesoverview_sort_rows(array $rows, string $key, bool $descending): array {
    $rows = array_values($rows);

    usort($rows, function($a, $b) use ($key, $descending) {
        $va = $a['sort'][$key] ?? null;
        $vb = $b['sort'][$key] ?? null;

        if (is_string($va) || is_string($vb)) {
            $cmp = strcoll((string) $va, (string) $vb);
        } else {
            $cmp = $va <=> $vb;
        }

        return $descending ? -$cmp : $cmp;
    });

    return $rows;
}

/**
 * Build a sortable table header cell.
 *
 * Clicking the currently sorted column flips the direction, any other column
 * starts ascending.
 *
 * @param string $label visible column label
 * @param string $column sort key of this column
 * @param string $sort currently active sort column
 * @param bool $descending current sort direction
 * @param moodle_url $baseurl page url without sort parameters
 * @return string
 */
function local_coursesoverview_sort_header(string $label, string $column, string $sort,
        bool $descending, moodle_url $baseurl): string {
    global $OUTPUT;

    $isactive = ($sort === $column);
    $newdir = ($isactive && !$descending) ? 'desc' : 'asc';

    $url = new moodle_url($baseurl, ['sort' => $column, 'dir' => $newdir]);
    $header = html_writer::link($url, $label);

    if ($isactive) {
        $header .= ' ' . $OUTPUT->pix_icon(
            $descending ? 't/sort_desc' : 't/sort_asc',
            get_string($descending ? 'desc' : 'asc')
        );
    }

    return $header;
}

/**
 * Send the table as a download and stop.
 *
 * Excel is written through MoodleExcelWorkbook (PhpSpreadsheet) rather than
 * the core "excel" dataformat: that one is Spout based, writes straight to
 * php://output and cannot colour cells. Any other requested format is handed
 * to the core dataformat plugins, which do not support colours either.
 *
 * The export date is appended to the file name, so that repeatedly exported
 * files do not overwrite each other in the download folder.
 *
 * @param string $dataformat requested format, e.g. 'excel'
 * @param string $filename without extension and without date
 * @param string $sheetname worksheet title, sanitised here
 * @param array $columns column headers
 * @param array $rows list of ['values' => array, 'bgcolor' => string|null]
 */
function local_coursesoverview_download(string $dataformat, string $filename, string $sheetname,
        array $columns, array $rows): void {
    // ISO order, so that the files sort chronologically by name.
    $filename = clean_filename($filename . '_' . userdate(time(), '%Y-%m-%d'));

    if ($dataformat === 'excel') {
        local_coursesoverview_export_excel($filename, $sheetname, $columns, $rows);
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
 */
function local_coursesoverview_export_excel(string $filename, string $sheetname,
        array $columns, array $rows): void {
    global $CFG;

    require_once($CFG->libdir . '/excellib.class.php');

    // Excel rejects these characters in sheet titles and caps them at 31
    // characters; either one triggers the "repair" dialog on open.
    $sheetname = preg_replace('#[\\\\/?*\[\]:]#u', ' ', $sheetname);
    $sheetname = trim(core_text::substr($sheetname, 0, 31));

    $workbook = new MoodleExcelWorkbook('-');
    $workbook->send($filename);

    $worksheet = $workbook->add_worksheet($sheetname !== '' ? $sheetname : 'Export');

    $headerformat = $workbook->add_format(['bold' => 1]);
    foreach (array_values($columns) as $col => $label) {
        $worksheet->write_string(0, $col, (string) $label, $headerformat);
        $worksheet->set_column($col, $col, min(40, max(12, core_text::strlen((string) $label) + 4)));
    }

    // One format object per colour, reused across rows.
    $formats = [];
    $rownum = 1;

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

/**
 * Render the "export to Excel" button for a page.
 *
 * The underlying download handler accepts any installed dataformat, so further
 * formats can be linked here without touching the export code itself.
 *
 * @param moodle_url $baseurl page url without the download parameter
 * @return string
 */
function local_coursesoverview_export_button(moodle_url $baseurl): string {
    $url = new moodle_url($baseurl, ['download' => 'excel']);

    return html_writer::div(
        html_writer::link($url, get_string('exportexcel', 'local_coursesoverview'),
            ['class' => 'btn btn-secondary']),
        'coursesoverview-export mb-3'
    );
}
