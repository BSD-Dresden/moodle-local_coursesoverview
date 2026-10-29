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
 * How a customer builds its mail addresses, to spot the ones that are off.
 *
 * Most customers give everybody an address like vorname.nachname@firma.de,
 * and a list typed by hand then has the odd address with a typo in it or the
 * wrong domain. The pattern is written the way one would say it, with the
 * words vorname and nachname (or firstname and lastname), v and n (or f and
 * l) for the initials and * for anything. Names are compared generously:
 * an umlaut may be written as ae or as a, a double name may be joined in any
 * of the usual ways or shortened to one of its parts. An address that still
 * does not fit is worth a look, but it may be right all the same, so it never
 * stops an order.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class emailpattern {
    /** @var string the whole first name */
    const FIRST = 'first';

    /** @var string the whole surname */
    const LAST = 'last';

    /** @var string the first letter of the first name */
    const FIRSTINITIAL = 'firstinitial';

    /** @var string the first letter of the surname */
    const LASTINITIAL = 'lastinitial';

    /** @var string anything at all */
    const ANY = 'any';

    /** @var string the patterns tried when the list is asked for its own */
    const CANDIDATES = [
        'vorname.nachname',
        'v.nachname',
        'vnachname',
        'nachname.vorname',
        'vorname_nachname',
        'vorname-nachname',
        'vornamenachname',
        'nachname',
    ];

    /** @var string the pattern as it was entered, tidied up */
    protected $text;

    /** @var array[] the local part as pieces, each [type, literal text] */
    protected $pieces = [];

    /** @var string the domain, * for any */
    protected $domain;

    /**
     * Read a pattern.
     *
     * Without an @ the whole pattern is taken as the domain, so that typing
     * just the customer's domain checks only that.
     *
     * @param string $text
     */
    protected function __construct(string $text) {
        $text = core_text::strtolower(trim($text));

        $at = strrpos($text, '@');
        if ($at === false) {
            $local = '*';
            $domain = $text;
        } else {
            $local = substr($text, 0, $at);
            $domain = substr($text, $at + 1);
        }
        $local = $local === '' ? '*' : $local;
        $this->domain = $domain === '' ? '*' : $domain;
        $this->text = $local . '@' . $this->domain;

        foreach (preg_split('/([._-])/', $local, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $token) {
            foreach (self::read_token($token) as $piece) {
                $this->pieces[] = $piece;
            }
        }
    }

    /**
     * A pattern, or nothing when none was entered.
     *
     * @param string|null $text
     * @return self|null
     */
    public static function parse(?string $text): ?self {
        $text = trim((string) $text);

        return $text === '' ? null : new self($text);
    }

    /**
     * The pattern as it is understood, for showing.
     *
     * @return string
     */
    public function text(): string {
        return $this->text;
    }

    /**
     * Whether a person's address fits.
     *
     * @param participant $person
     * @return bool
     */
    public function matches(participant $person): bool {
        $email = core_text::strtolower($person->email);
        $at = strrpos($email, '@');
        if ($at === false || !$this->domain_matches(substr($email, $at + 1))) {
            return false;
        }

        return (bool) preg_match($this->local_regex($person), substr($email, 0, $at));
    }

    /**
     * Whether a domain fits, which is all that can be said about a shared mailbox.
     *
     * @param string $domain
     * @return bool
     */
    public function domain_matches(string $domain): bool {
        return (bool) preg_match(self::wildcard($this->domain), core_text::strtolower($domain));
    }

    /**
     * The domain of the pattern, for showing.
     *
     * @return string
     */
    public function domain(): string {
        return $this->domain;
    }

    /**
     * The address a person would be expected to have.
     *
     * @param participant $person
     * @return string
     */
    public function example(participant $person): string {
        $local = '';
        foreach ($this->pieces as [$type, $literal]) {
            $variants = $this->variants($type, $literal, $person);
            $local .= $variants ? reset($variants) : '';
        }

        return $local . '@' . $this->domain;
    }

    /**
     * The pattern most addresses of a list follow, if there is one.
     *
     * @param participant[] $people
     * @return stdClass|null pattern, hits and total, or null when no pattern fits at least half of them
     */
    public static function guess(array $people): ?stdClass {
        $judged = [];
        $domains = [];
        foreach ($people as $person) {
            if ($person->problems()) {
                continue;
            }
            $judged[] = $person;
            $domain = substr($person->email, strrpos($person->email, '@') + 1);
            $domains[$domain] = ($domains[$domain] ?? 0) + 1;
        }

        // Too few addresses to call anything a rule.
        if (count($judged) < 3) {
            return null;
        }

        arsort($domains);
        $domain = (string) array_key_first($domains);

        $best = null;
        foreach (self::CANDIDATES as $candidate) {
            $pattern = new self($candidate . '@' . $domain);
            $hits = 0;
            foreach ($judged as $person) {
                if ($pattern->matches($person)) {
                    $hits++;
                }
            }
            if (!$best || $hits > $best->hits) {
                $best = (object) ['pattern' => $pattern->text(), 'hits' => $hits, 'total' => count($judged)];
            }
        }

        return $best->hits * 2 >= $best->total ? $best : null;
    }

    /**
     * Split a token between separators into pieces.
     *
     * A token made only of the words and initials is read as such, so that
     * "vnachname" means the initial followed by the surname. Anything else is
     * taken literally, which keeps a word like "info" from turning into
     * initials.
     *
     * @param string $token
     * @return array[] pieces, each [type, literal text]
     */
    protected static function read_token(string $token): array {
        if ($token === '*') {
            return [[self::ANY, '']];
        }

        $words = [
            'vorname' => self::FIRST,
            'firstname' => self::FIRST,
            'nachname' => self::LAST,
            'lastname' => self::LAST,
            'v' => self::FIRSTINITIAL,
            'f' => self::FIRSTINITIAL,
            'n' => self::LASTINITIAL,
            'l' => self::LASTINITIAL,
        ];
        $alternatives = '(vorname|firstname|nachname|lastname|[vfnl])';

        if (!preg_match('/^' . $alternatives . '+$/', $token)) {
            return [['', $token]];
        }

        preg_match_all('/' . $alternatives . '/', $token, $matches);
        $pieces = [];
        foreach ($matches[1] as $word) {
            $pieces[] = [$words[$word], ''];
        }

        return $pieces;
    }

    /**
     * The regular expression a person's local part has to match.
     *
     * @param participant $person
     * @return string
     */
    protected function local_regex(participant $person): string {
        $regex = '';
        foreach ($this->pieces as [$type, $literal]) {
            if ($type === self::ANY) {
                $regex .= '.*';
                continue;
            }

            $variants = $this->variants($type, $literal, $person);
            if (!$variants) {
                // Nothing to build that part from, so nothing can fit it.
                return '/(?!)/';
            }
            $regex .= '(' . implode('|', array_map(function ($variant) {
                return preg_quote($variant, '/');
            }, $variants)) . ')';
        }

        return '/^' . $regex . '$/';
    }

    /**
     * The ways a piece of the pattern may be written for a person.
     *
     * @param string $type
     * @param string $literal
     * @param participant $person
     * @return string[] the expected spelling first
     */
    protected function variants(string $type, string $literal, participant $person): array {
        switch ($type) {
            case self::FIRST:
                return self::spellings($person->firstname);
            case self::LAST:
                return self::spellings($person->lastname);
            case self::FIRSTINITIAL:
                return self::initials($person->firstname);
            case self::LASTINITIAL:
                return self::initials($person->lastname);
            case self::ANY:
                return ['*'];
            default:
                return [$literal];
        }
    }

    /**
     * The ways a name turns up in addresses.
     *
     * "Hans-Peter Müller-Lüdenscheidt" may be written in full with an umlaut
     * spelt out or dropped, its parts joined by a hyphen, a dot, an underscore
     * or nothing, or shortened to its first or last part.
     *
     * @param string $name
     * @return string[] the German spelling, hyphenated, first
     */
    protected static function spellings(string $name): array {
        $words = preg_split('/[\s-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $spellings = [];

        foreach ([[module::class, 'ascii'], [core_text::class, 'specialtoascii']] as $ascii) {
            $parts = [];
            foreach ($words as $word) {
                $part = preg_replace('/[^a-z0-9]/', '', core_text::strtolower($ascii($word)));
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
            if (!$parts) {
                continue;
            }

            foreach (['-', '', '.', '_'] as $glue) {
                $spellings[] = implode($glue, $parts);
            }
            $spellings[] = reset($parts);
            $spellings[] = end($parts);
        }

        return array_values(array_unique($spellings));
    }

    /**
     * The first letters a name may start with in an address.
     *
     * @param string $name
     * @return string[]
     */
    protected static function initials(string $name): array {
        $initials = [];
        foreach (self::spellings($name) as $spelling) {
            $initials[] = $spelling[0];
        }

        return array_values(array_unique($initials));
    }

    /**
     * A domain with wildcards as a regular expression.
     *
     * @param string $pattern
     * @return string
     */
    protected static function wildcard(string $pattern): string {
        return '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';
    }
}
