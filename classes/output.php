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

use html_table;
use html_table_row;
use html_writer;
use moodle_url;

/**
 * Markup shared by the two pages.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output {
    /** @var string Class marking the tables whose row colouring we control. */
    const TABLE_CLASS = 'coursesoverview-table';

    /**
     * Id of the wrapper the tables are rendered into.
     *
     * The row colours in styles.css are scoped to this id on purpose. Themes
     * and site custom CSS style table rows with selectors such as
     * "table.generaltable tbody tr:nth-child(even) td", which carries more
     * specificity than any class based rule we could write and often
     * !important on top. An id outranks any number of classes, so scoping by
     * id is what makes our colours stick regardless of the theme.
     */
    const WRAPPER_ID = 'coursesoverview-tables';

    /**
     * Open the wrapper the row colouring is scoped to.
     *
     * @return string
     */
    public static function start_tables(): string {
        return html_writer::start_div('', ['id' => self::WRAPPER_ID]);
    }

    /**
     * Close the wrapper opened by start_tables().
     *
     * @return string
     */
    public static function end_tables(): string {
        return html_writer::end_div();
    }

    /**
     * Build a sortable table header cell.
     *
     * Clicking the currently sorted column flips the direction, any other
     * column starts ascending.
     *
     * @param string $label visible column label
     * @param string $column sort key of this column
     * @param string $sort currently active sort column
     * @param bool $descending current sort direction
     * @param moodle_url $baseurl page url without sort parameters
     * @return string
     */
    public static function sort_header(
        string $label,
        string $column,
        string $sort,
        bool $descending,
        moodle_url $baseurl
    ): string {
        global $OUTPUT;

        $isactive = ($sort === $column);
        $newdir = ($isactive && !$descending) ? 'desc' : 'asc';

        $url = new moodle_url($baseurl, ['sort' => $column, 'dir' => $newdir]);
        $header = html_writer::link($url, $label);

        if ($isactive) {
            $icon = $descending ? 't/sort_desc' : 't/sort_asc';
            $alt = get_string($descending ? 'desc' : 'asc');
            $header .= ' ' . $OUTPUT->pix_icon($icon, $alt);
        }

        return $header;
    }

    /**
     * Render the "export to Excel" button for a page.
     *
     * The underlying download handler accepts any installed dataformat, so
     * further formats can be linked here without touching the export code.
     *
     * @param moodle_url $baseurl page url without the download parameter
     * @return string
     */
    public static function export_button(moodle_url $baseurl): string {
        $url = new moodle_url($baseurl, ['download' => 'excel']);
        $label = get_string('exportexcel', 'local_coursesoverview');
        $link = html_writer::link($url, $label, ['class' => 'btn btn-secondary']);

        return html_writer::div($link, 'coursesoverview-export mb-3');
    }

    /**
     * Render the people looking after a course or a single group.
     *
     * Screen only. The Excel export is built from the participant rows, and
     * organisers are deliberately not among them.
     *
     * @param array $names full names, unescaped
     * @return string
     */
    public static function organisers(array $names): string {
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        $key = (count($names) === 1) ? 'organiser' : 'organisers';
        $label = get_string($key, 'local_coursesoverview');
        $list = implode(', ', array_map('s', $names));

        return html_writer::tag(
            'p',
            html_writer::tag('strong', $label . ': ') . $list,
            ['class' => 'coursesoverview-organisers']
        );
    }

    /**
     * Render a colour legend for the given states.
     *
     * @param array $states state keys, in the order they should be listed
     * @return string
     */
    public static function legend(array $states): string {
        $colors = helper::colors();
        $items = '';

        foreach ($states as $state) {
            if (empty($colors[$state])) {
                continue;
            }

            $swatchclass = 'coursesoverview-swatch coursesoverview-swatch-' . $state;
            $swatch = html_writer::span('', $swatchclass);
            $label = get_string('status' . $state, 'local_coursesoverview');

            $items .= html_writer::span($swatch . $label, 'd-inline-block mr-3 me-3 mb-1');
        }

        return html_writer::div($items, 'coursesoverview-legend mb-3');
    }

    /**
     * Render the filter bar of the overview.
     *
     * A plain GET form rather than a moodleform, so that every filter stays in
     * the URL: the sort links, the export button and the visibility actions
     * all carry the current filter, and a filtered view can be bookmarked.
     *
     * @param moodle_url $action page url
     * @param array $current current values, keyed by parameter name
     * @param array $categories category id => name
     * @return string
     */
    public static function filters(moodle_url $action, array $current, array $categories): string {
        $target = $action->out_omit_querystring();

        $formattributes = [
            'method' => 'get',
            'action' => $target,
            'class' => 'coursesoverview-filters form-inline mb-3',
        ];
        $out = html_writer::start_tag('form', $formattributes);

        // Filtering must not throw away the chosen sorting.
        $out .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'sort',
            'value' => $current['sort'],
        ]);
        $out .= html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'dir',
            'value' => $current['dir'],
        ]);

        $out .= html_writer::label(get_string('search'), 'coursesoverview-search', true, [
            'class' => 'mr-2 mb-2',
        ]);
        $out .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'search',
            'id' => 'coursesoverview-search',
            'value' => $current['search'],
            'size' => 25,
            'class' => 'form-control mr-3 mb-2',
        ]);

        $out .= html_writer::label(get_string('category'), 'coursesoverview-category', true, [
            'class' => 'mr-2 mb-2',
        ]);
        $out .= html_writer::select(
            $categories,
            'category',
            $current['category'],
            ['0' => get_string('all')],
            ['id' => 'coursesoverview-category', 'class' => 'custom-select mr-3 mb-2']
        );

        $out .= html_writer::label(get_string('status'), 'coursesoverview-state', true, [
            'class' => 'mr-2 mb-2',
        ]);
        $out .= html_writer::select(
            helper::filter_states(),
            'state',
            $current['state'],
            ['' => get_string('all')],
            ['id' => 'coursesoverview-state', 'class' => 'custom-select mr-3 mb-2']
        );

        $checkbox = html_writer::checkbox(
            'withoutparticipants',
            1,
            (bool) $current['withoutparticipants'],
            get_string('withoutparticipants', 'local_coursesoverview'),
            ['id' => 'coursesoverview-withoutparticipants', 'class' => 'mr-2']
        );
        $out .= html_writer::div($checkbox, 'mr-3 mb-2');

        $out .= html_writer::empty_tag('input', [
            'type' => 'submit',
            'value' => get_string('applyfilters', 'local_coursesoverview'),
            'class' => 'btn btn-primary mr-3 mb-2',
        ]);

        $out .= html_writer::link($target, get_string('reset'), ['class' => 'mb-2']);
        $out .= html_writer::end_tag('form');

        return $out;
    }

    /**
     * Render one participants table.
     *
     * @param array $rows already sorted rows as built by participants.php
     * @param string $sort active sort column
     * @param bool $descending active sort direction
     * @param moodle_url $baseurl page url without sort parameters
     * @param string $caption table caption, read by screen readers
     * @return string
     */
    public static function participants_table(
        array $rows,
        string $sort,
        bool $descending,
        moodle_url $baseurl,
        string $caption
    ): string {
        if (empty($rows)) {
            return html_writer::tag('p', get_string('noparticipants', 'local_coursesoverview'));
        }

        $progresslabel = get_string('progressheader', 'local_coursesoverview');
        $datelabel = get_string('completed_lastseen', 'local_coursesoverview');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable coursesoverview-participants ' . self::TABLE_CLASS;
        $table->caption = $caption;
        $table->captionhide = true;
        $table->head = [
            self::sort_header(get_string('firstname'), 'firstname', $sort, $descending, $baseurl),
            self::sort_header(get_string('lastname'), 'lastname', $sort, $descending, $baseurl),
            self::sort_header(get_string('email'), 'email', $sort, $descending, $baseurl),
            self::sort_header($progresslabel, 'progress', $sort, $descending, $baseurl),
            self::sort_header($datelabel, 'date', $sort, $descending, $baseurl),
        ];

        foreach ($rows as $row) {
            $tablerow = new html_table_row($row['cells']);

            // The table renderer rebuilds the tr attributes, so an inline
            // style set here would be dropped. Colour via a class instead.
            $tablerow->attributes['class'] = $row['state']
                ? 'coursesoverview-' . $row['state']
                : '';

            $table->data[] = $tablerow;
        }

        return html_writer::table($table);
    }
}
