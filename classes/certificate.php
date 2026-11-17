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

use context_course;
use context_system;
use local_coursesoverview\order\module;
use stdClass;
use stored_file;
use ZipArchive;

/**
 * Certificates of attendance, one PDF per person who completed a course.
 *
 * The template is a PDF uploaded in the settings, typically the company's
 * Word certificate exported without name and date: one in general and one
 * per module if wanted. Logo, signature and wording live in that file and
 * never in this plugin. Name and date are written on top of its first page
 * at positions given in the settings, with the PDF libraries Moodle ships.
 * Turning Word into PDF on the server would need LibreOffice, which a rented
 * web server does not have.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate {
    /** @var string File area of the templates in the system context. */
    const FILEAREA = 'certificate';

    /** @var string Where name and date sit in the template this plugin was made for, in mm to the baseline. */
    const DEFAULT_POSITIONS = '18.5 84.8 30.8 142.4';

    /**
     * The file item of a module's template, 0 for the general one.
     *
     * Module prefixes are text and file items numbers, so the prefix is hashed.
     * Renaming a prefix therefore leaves its template behind.
     *
     * @param string $prefix module prefix, empty for the general template
     * @return int
     */
    public static function itemid(string $prefix): int {
        return $prefix === '' ? 0 : (int) (crc32($prefix) % 999999999) + 1;
    }

    /**
     * The template item for a course: its module's own, else the general one.
     *
     * @param int $courseid
     * @return int|null file item, null when there is no template at all
     */
    public static function template(int $courseid): ?int {
        $order = self::order($courseid);
        if ($order) {
            $itemid = self::itemid((string) $order->module);
            if (self::stored($itemid)) {
                return $itemid;
            }
        }

        return self::stored(0) ? 0 : null;
    }

    /**
     * Is there a template under this file item?
     *
     * @param int $itemid
     * @return bool
     */
    public static function exists(int $itemid): bool {
        return self::stored($itemid) !== null;
    }

    /**
     * Read a positions setting.
     *
     * Four numbers in millimetres from the top left corner: name across and
     * down, then date across and down. Spaces, slashes or semicolons between
     * them, a decimal comma is fine.
     *
     * @param string $value
     * @return array|null ['name' => [x, y], 'date' => [x, y]], null when not four numbers
     */
    public static function parse_positions(string $value): ?array {
        $numbers = preg_split('#[\s/;]+#', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        if (count($numbers) !== 4) {
            return null;
        }

        $mm = [];
        foreach ($numbers as $number) {
            $number = str_replace(',', '.', $number);
            if (!is_numeric($number) || $number < 0 || $number > 500) {
                return null;
            }
            $mm[] = (float) $number;
        }

        return ['name' => [$mm[0], $mm[1]], 'date' => [$mm[2], $mm[3]]];
    }

    /**
     * Where name and date go on a template: its own positions, else the general ones.
     *
     * @param int $itemid
     * @return array as parse_positions() returns it
     */
    public static function positions(int $itemid): array {
        foreach ([$itemid, 0] as $item) {
            $positions = self::parse_positions((string) get_config('local_coursesoverview', 'certificatepos_' . $item));
            if ($positions) {
                return $positions;
            }
        }

        return self::parse_positions(self::DEFAULT_POSITIONS);
    }

    /**
     * Everybody who completed the course, with the day they did.
     *
     * The same people the completion status counts: enrolled, active and
     * tracked for completion.
     *
     * @param stdClass $course
     * @return array user records with timecompleted, sorted by last name
     */
    public static function people(stdClass $course): array {
        global $DB;

        $context = context_course::instance($course->id);
        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $tracked = get_enrolled_users(
            $context,
            'moodle/course:isincompletionreports',
            0,
            'u.id, ' . ltrim($namefields, ' ,'),
            null,
            0,
            0,
            true
        );
        if (!$tracked) {
            return [];
        }

        $completed = $DB->get_records_select_menu(
            'course_completions',
            'course = :courseid AND timecompleted IS NOT NULL',
            ['courseid' => $course->id],
            '',
            'userid, timecompleted'
        );

        $people = [];
        foreach ($tracked as $user) {
            if (!empty($completed[$user->id])) {
                $user->timecompleted = (int) $completed[$user->id];
                $people[] = $user;
            }
        }

        usort($people, function ($a, $b) {
            return strcoll($a->lastname . ' ' . $a->firstname, $b->lastname . ' ' . $b->firstname);
        });

        return $people;
    }

    /**
     * A link to the certificates of a course, when there is something to make.
     *
     * @param stdClass $course
     * @param string $class css classes of the link
     * @return string empty without a template or without anybody who completed
     */
    public static function link(stdClass $course, string $class = ''): string {
        if (!self::available($course->id)) {
            return '';
        }
        $count = count(self::people($course));
        if (!$count) {
            return '';
        }

        $url = new \moodle_url('/local/coursesoverview/certificate.php', ['courseid' => $course->id]);

        return \html_writer::link($url, get_string('certificatebutton', 'local_coursesoverview', $count), ['class' => $class]);
    }

    /**
     * Make a PDF for everybody and send them as one ZIP.
     *
     * @param stdClass $course
     * @param int $itemid template item
     * @param array $people as returned by people()
     */
    public static function send(stdClass $course, int $itemid, array $people): void {
        $dir = make_request_directory();
        $make = self::maker($course, $itemid, $dir);

        $zippath = $dir . '/certificates.zip';
        $zip = new ZipArchive();
        if ($zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \moodle_exception('certificatefailed', 'local_coursesoverview');
        }

        $names = [];
        foreach ($people as $person) {
            [$name, $pdf] = $make($person);
            // Two people of the same name each keep their own file.
            $unique = $name;
            for ($n = 2; isset($names[$unique]); $n++) {
                $unique = $name . ' ' . $n;
            }
            $names[$unique] = true;
            $zip->addFromString($unique . '.pdf', $pdf);
        }
        $zip->close();

        $zipname = clean_filename(get_string('certificatezip', 'local_coursesoverview', $course->shortname)) . '.zip';
        send_file($zippath, $zipname, 0, 0, false, true, 'application/zip');
        die();
    }

    /**
     * Make the PDF of one person and send it.
     *
     * @param stdClass $course
     * @param int $itemid template item
     * @param stdClass $person one of people()
     */
    public static function send_single(stdClass $course, int $itemid, stdClass $person): void {
        $make = self::maker($course, $itemid, make_request_directory());
        [$name, $pdf] = $make($person);
        send_file($pdf, $name . '.pdf', 0, 0, true, true, 'application/pdf');
        die();
    }

    /**
     * What makes the certificate of one person of a course.
     *
     * Reads the template once, so that a ZIP for many people does not.
     *
     * @param stdClass $course
     * @param int $itemid template item
     * @param string $dir where the template may be copied to
     * @return callable person => [file name without extension, PDF]
     */
    protected static function maker(stdClass $course, int $itemid, string $dir): callable {
        $template = self::template_path($itemid, $dir);
        $found = pdf_placeholders::find($template);
        $positions = self::positions($itemid);

        $order = self::order($course->id);
        $module = $order ? module::get((string) $order->module) : null;
        $context = context_course::instance($course->id);
        $common = [
            'course' => format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
            'module' => $module ? $module->name : '',
            'title' => $module ? $module->title : '',
            'company' => $order ? $order->company : '',
        ];

        return function (stdClass $person) use ($template, $found, $positions, $common): array {
            $values = $common + [
                'name' => fullname($person),
                'firstname' => $person->firstname,
                'lastname' => $person->lastname,
                'date' => userdate($person->timecompleted, '%d.%m.%Y'),
            ];
            $pdf = self::render($template, self::fields($found['placeholders'], $positions, $values), $found['content']);
            $personname = $person->lastname . ' ' . $person->firstname;
            $name = clean_filename(get_string('certificatefile', 'local_coursesoverview', $personname));

            return [$name, $pdf];
        };
    }

    /**
     * A link to the certificate of one person, for the completion status.
     *
     * @param int $courseid
     * @param int $userid
     * @return string
     */
    public static function single_link(int $courseid, int $userid): string {
        global $OUTPUT;

        $url = new \moodle_url('/local/coursesoverview/certificate.php', ['courseid' => $courseid, 'userid' => $userid]);
        $label = get_string('certificatesingle', 'local_coursesoverview');
        // A font icon takes the colour of the button's text, a file icon would stay grey.
        $icon = $OUTPUT->pix_icon('t/download', '', 'moodle', ['class' => 'me-1']);
        $text = $icon . get_string('certificateshort', 'local_coursesoverview');

        return \html_writer::link($url, $text, [
            'title' => $label,
            'aria-label' => $label,
            'class' => 'btn btn-sm btn-outline-primary ms-2 py-0',
        ]);
    }

    /**
     * Whether the current user may make certificates for a course, and it has a template.
     *
     * @param int $courseid
     * @return bool
     */
    public static function available(int $courseid): bool {
        return has_capability('local/coursesoverview:createorder', context_system::instance())
            && self::template($courseid) !== null;
    }

    /**
     * Send a sample of a template on a millimetre grid, to check where the text lands.
     *
     * @param int $itemid template item
     */
    public static function send_preview(int $itemid): void {
        $dir = make_request_directory();
        $template = self::template_path($itemid, $dir);
        $sample = get_string('certificatesample', 'local_coursesoverview');
        $values = [
            'name' => get_string('certificatesamplename', 'local_coursesoverview'),
            'firstname' => get_string('certificatesamplefirstname', 'local_coursesoverview'),
            'lastname' => get_string('certificatesamplelastname', 'local_coursesoverview'),
            'date' => userdate(time(), '%d.%m.%Y'),
            'course' => $sample,
            'module' => $sample,
            'title' => $sample,
            'company' => $sample,
        ];
        $found = pdf_placeholders::find($template);
        $fields = self::fields($found['placeholders'], self::positions($itemid), $values);
        $pdf = self::render($template, $fields, $found['content'], true);

        $filename = get_string('certificatepreviewfile', 'local_coursesoverview') . '.pdf';
        send_file($pdf, $filename, 0, 0, true, false, 'application/pdf');
        die();
    }

    /**
     * What goes where: at the placeholders of the template, else at the positions.
     *
     * Placeholders win as soon as the template has any this plugin knows. A
     * template without them gets name and date at the positions from the
     * settings, in the font size and weight given there.
     *
     * @param array $placeholders the list pdf_placeholders::find() returns under placeholders
     * @param array $positions as parse_positions() returns them
     * @param array $values placeholder name => text
     * @return array of ['text', 'x', 'y', 'size', 'bold', 'align', 'width'], in mm and pt
     */
    public static function fields(array $placeholders, array $positions, array $values): array {
        $fields = [];
        foreach ($placeholders as $placeholder) {
            if (array_key_exists($placeholder['key'], $values)) {
                $fields[] = ['text' => (string) $values[$placeholder['key']]] + $placeholder;
            }
        }
        if ($fields) {
            return $fields;
        }

        $size = (float) (get_config('local_coursesoverview', 'certificatefontsize') ?: 12);
        $bold = get_config('local_coursesoverview', 'certificatebold');
        foreach (['name', 'date'] as $key) {
            [$x, $y] = $positions[$key];
            $fields[] = [
                'text' => (string) ($values[$key] ?? ''),
                'x' => $x,
                'y' => $y,
                'size' => $size,
                'bold' => $bold === false || (bool) $bold,
                'align' => 'left',
                'width' => 0,
            ];
        }

        return $fields;
    }

    /**
     * Write the fields on the first page of a template.
     *
     * @param string $template path of the PDF template
     * @param array $fields as fields() returns them
     * @param string|null $content the page's content without the placeholders, as pdf_placeholders::find() gives it
     * @param bool $grid draw a millimetre grid, for checking where the text lands
     * @return string the PDF
     */
    public static function render(string $template, array $fields, ?string $content = null, bool $grid = false): string {
        self::libraries();

        $pdf = new certificate_pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->setCellPaddings(0, 0, 0, 0);

        try {
            $pdf->setSourceFile($template);
            $page = $pdf->importPage(1);
            if ($content !== null) {
                // The placeholders are gone, not just white: nothing to select or copy.
                $pdf->replace_content($page, $content);
            }
        } catch (\Throwable $e) {
            throw new \moodle_exception('certificatefailed', 'local_coursesoverview', '', null, $e->getMessage());
        }
        $size = $pdf->getTemplateSize($page);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($page);

        if ($grid) {
            self::grid($pdf, $size['width'], $size['height']);
        }

        $pdf->SetTextColor(0, 0, 0);
        foreach ($fields as $field) {
            // FreeSans looks like the Arial of a Word template and has the
            // letters of names Arial has, ł and ş included.
            $pdf->SetFont($field['bold'] ? 'freesansb' : 'freesans', '', $field['size']);

            // Centred and right aligned text keeps the middle or the end of its
            // placeholder; a cell around that point, inside the page, does the
            // aligning. TCPDF counts a negative x from the right edge.
            $cell = 0;
            $x = $field['x'];
            $align = 'L';
            if ($field['align'] === 'center') {
                $middle = $field['x'] + $field['width'] / 2;
                $cell = 2 * max(0, min($middle, $size['width'] - $middle));
                $x = $middle - $cell / 2;
                $align = 'C';
            } else if ($field['align'] === 'right') {
                $cell = $field['x'] + $field['width'];
                $x = 0;
                $align = 'R';
            }

            // The position is where the letters sit, so a different font does
            // not move the text up or down against the template's own lines.
            $pdf->SetXY($x, $field['y']);
            $pdf->Cell($cell, 0, $field['text'], 0, 0, $align, false, '', 0, false, 'L', 'T');
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Load TCPDF and FPDI as Moodle ships them.
     *
     * FPDI comes with the PDF annotation of assignments, a standard plugin,
     * and extends Moodle's own TCPDF class there.
     */
    protected static function libraries(): void {
        global $CFG;

        // Already loaded when the class is tried out outside Moodle.
        if (class_exists(\setasign\Fpdi\TcpdfFpdi::class)) {
            return;
        }
        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->dirroot . '/mod/assign/feedback/editpdf/fpdi/src/autoload.php');
    }

    /**
     * Draw a light grid every 5 mm, labelled every 10 mm.
     *
     * @param \setasign\Fpdi\TcpdfFpdi $pdf
     * @param float $width
     * @param float $height
     */
    protected static function grid($pdf, float $width, float $height): void {
        $pdf->SetFont('freesans', '', 6);
        for ($mm = 5; $mm < max($width, $height); $mm += 5) {
            $major = ($mm % 10 === 0);
            $pdf->SetDrawColor($major ? 120 : 200, $major ? 160 : 220, 255);
            $pdf->SetLineWidth($major ? 0.15 : 0.05);
            if ($mm < $width) {
                $pdf->Line($mm, 0, $mm, $height);
            }
            if ($mm < $height) {
                $pdf->Line(0, $mm, $width, $mm);
            }
            if ($major) {
                $pdf->SetTextColor(40, 80, 200);
                if ($mm < $width) {
                    $pdf->Text($mm + 0.3, 1, (string) $mm);
                }
                if ($mm < $height) {
                    $pdf->Text(1, $mm + 0.3, (string) $mm);
                }
            }
        }
    }

    /**
     * Copy a stored template into a directory.
     *
     * @param int $itemid
     * @param string $dir
     * @return string path of the copy
     */
    protected static function template_path(int $itemid, string $dir): string {
        $file = self::stored($itemid);
        if (!$file) {
            throw new \moodle_exception('certificatenotemplate', 'local_coursesoverview');
        }
        $path = $dir . '/template-' . $itemid . '.pdf';
        $file->copy_content_to($path);

        return $path;
    }

    /**
     * The template stored under a file item, if any.
     *
     * @param int $itemid
     * @return stored_file|null
     */
    protected static function stored(int $itemid): ?stored_file {
        $files = get_file_storage()->get_area_files(
            context_system::instance()->id,
            'local_coursesoverview',
            self::FILEAREA,
            $itemid,
            'sortorder, id',
            false
        );

        return $files ? reset($files) : null;
    }

    /**
     * The newest order of a course.
     *
     * @param int $courseid
     * @return stdClass|null
     */
    protected static function order(int $courseid): ?stdClass {
        global $DB;

        $orders = $DB->get_records('local_coursesoverview_order', ['courseid' => $courseid], 'id DESC', '*', 0, 1);

        return $orders ? reset($orders) : null;
    }
}
