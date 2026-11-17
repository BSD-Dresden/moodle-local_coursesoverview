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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * A time of day setting that writes minutes with two digits: 7 : 00, not 7 : 0.
 *
 * Core's time setting lists the minutes as plain numbers, in the select and
 * in the default shown next to it. Values and saving are the same as there.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_time extends \admin_setting_configtime {
    /**
     * The hour and minute selects.
     *
     * @param array $data ['h' => hour, 'm' => minute]
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $default = $this->get_defaultsetting();
        $defaultinfo = is_array($default) ? $default['h'] . ':' . sprintf('%02d', $default['m']) : null;

        $options = function (array $values, $selected, string $format): array {
            return array_map(function ($i) use ($selected, $format) {
                return ['value' => $i, 'name' => sprintf($format, $i), 'selected' => $i == $selected];
            }, $values);
        };
        $context = (object) [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'readonly' => $this->is_readonly(),
            'hours' => $options(range(0, 23), $data['h'], '%d'),
            'minutes' => $options(range(0, 59, 5), $data['m'], '%02d'),
        ];

        $element = $OUTPUT->render_from_template('core_admin/setting_configtime', $context);

        $label = $this->get_id() . 'h';
        return format_admin_setting($this, $this->visiblename, $element, $this->description, $label, '', $defaultinfo, $query);
    }
}
