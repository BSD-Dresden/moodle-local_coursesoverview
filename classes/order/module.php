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

namespace local_coursesoverview\order;

use core_text;
use stdClass;

/**
 * One module that can be ordered, as configured in the settings.
 *
 * Modules live in a setting rather than in code so that a new one, or the
 * English branch, is a line of text instead of a release.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module {
    /** @var string name shown in the form */
    public $name;

    /** @var string short name of the course the new one is copied from */
    public $template;

    /** @var int category the new course goes into, zero means the one the template sits in */
    public $categoryid;

    /** @var string prefix of the generated course short name, also the key of the module */
    public $prefix;

    /** @var string what the mails call the module, its name unless the settings give a title */
    public $title;

    /** @var stdClass|null|false the template course once it has been looked up */
    protected $templatecourse = false;

    /**
     * Use all() or get().
     *
     * @param string $name
     * @param string $template
     * @param int $categoryid
     * @param string $prefix
     * @param string $title empty for the name
     */
    protected function __construct(string $name, string $template, int $categoryid, string $prefix, string $title = '') {
        $this->name = $name;
        $this->template = $template;
        $this->categoryid = $categoryid;
        $this->prefix = $prefix;
        $this->title = $title !== '' ? $title : $name;
    }

    /**
     * Every configured module, keyed by prefix.
     *
     * One line per module: name, template short name, category id, prefix,
     * and optionally the title the mails use. An empty category id means the
     * category the template course sits in.
     *
     * @return module[]
     */
    public static function all(): array {
        $setting = (string) get_config('local_coursesoverview', 'ordermodules');
        $modules = [];

        foreach (preg_split('/\R/', $setting) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 4) {
                continue;
            }

            [$name, $template, $category, $prefix] = $parts;
            if ($name === '' || $template === '' || $prefix === '') {
                continue;
            }

            $modules[$prefix] = new self($name, $template, (int) $category, $prefix, $parts[4] ?? '');
        }

        return $modules;
    }

    /**
     * The configured modules as a select menu.
     *
     * @return array prefix => name
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::all() as $prefix => $module) {
            $menu[$prefix] = $module->name;
        }

        return $menu;
    }

    /**
     * One module by its prefix.
     *
     * @param string $prefix
     * @return module|null
     */
    public static function get(string $prefix): ?module {
        $modules = self::all();

        return $modules[$prefix] ?? null;
    }

    /**
     * The course this module is copied from.
     *
     * @return stdClass|null
     */
    public function template_course(): ?stdClass {
        global $DB;

        if ($this->templatecourse === false) {
            $course = $DB->get_record('course', ['shortname' => $this->template]);
            $this->templatecourse = $course ?: null;
        }

        return $this->templatecourse;
    }

    /**
     * The category a new course of this module belongs in.
     *
     * @return int zero when neither the setting nor the template answers it
     */
    public function category(): int {
        if ($this->categoryid) {
            return $this->categoryid;
        }

        $template = $this->template_course();

        return $template ? (int) $template->category : 0;
    }

    /**
     * The short name of the course for an order.
     *
     * @param string $company as typed
     * @param string $abnr as typed
     * @return string
     */
    public function shortname(string $company, string $abnr): string {
        return $this->prefix . '_' . self::clean($company) . '-' . self::clean($abnr);
    }

    /**
     * The full name of the course for an order.
     *
     * @param string $company as typed, so that it keeps its spaces
     * @param string $abnr as typed
     * @return string
     */
    public function fullname(string $company, string $abnr): string {
        $pattern = trim((string) get_config('local_coursesoverview', 'orderpattern'));
        $shortname = $this->shortname($company, $abnr);

        if ($pattern === '') {
            return $shortname;
        }

        return strtr($pattern, [
            '{module}' => $this->name,
            '{company}' => trim($company),
            '{abnr}' => trim($abnr),
            '{shortname}' => $shortname,
        ]);
    }

    /**
     * Strip what a course short name should not carry.
     *
     * @param string $value
     * @return string
     */
    public static function clean(string $value): string {
        return preg_replace('/[^A-Za-z0-9_.@-]/', '', self::ascii($value));
    }

    /**
     * Turn a name into plain ascii the German way.
     *
     * core_text::specialtoascii() alone would make "Müller" into "Muller",
     * because its transliteration drops the dots rather than spelling them
     * out. The pairs below run first so that the result reads the way these
     * names are written when an umlaut is not available, and the rest of the
     * alphabet is then left to Moodle.
     *
     * @param string $value
     * @return string
     */
    public static function ascii(string $value): string {
        $pairs = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss',
        ];

        return core_text::specialtoascii(strtr(trim($value), $pairs));
    }
}
