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

use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfToken;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;

/**
 * Finds placeholders such as {name} in the text of a PDF's first page and takes them out.
 *
 * The template author types them in Word where the text belongs, in white so
 * that they do not show, and saves as PDF. Their place, size and weight then
 * tell where a name or a date goes, without measuring anything.
 *
 * Reading uses the PDF parser that comes with FPDI. On top of it this class
 * follows the text operators of the page: the text and graphics matrices,
 * fonts with their widths and their way back to Unicode, and each glyph's
 * position. A placeholder may be split over several text objects, which Word
 * does when the typed text has mixed formatting history, so glyphs are joined
 * into lines before searching. Text inside form XObjects is not looked at.
 *
 * The page content is also given back without the placeholders: every text
 * operator that shows one is written anew with those glyphs replaced by a
 * move of the same width, so that nothing of them is left to select, copy or
 * find, and text after them in the same text object stays where it was.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_placeholders {
    /** @var string What a placeholder looks like: {name}, or {name:center} for centred text. */
    const PATTERN = '/\{([a-z]+)(?::(left|center|right))?\}/';

    /** @var PdfParser */
    protected $parser;

    /** @var array font resource name => font data, see font() */
    protected $fonts = [];

    /** @var PdfDictionary|null the font resources of the page */
    protected $fontresources;

    /** @var array the glyphs shown, each [text, x, y, size, advance, bold], in user space */
    protected $glyphs = [];

    /** @var array the text showing operators, see show() */
    protected $operators = [];

    /**
     * The placeholders on the first page of a PDF, and that page without them.
     *
     * @param string $path
     * @return array ['placeholders' => list of ['key' => name, 'x' => mm from the
     *      left, 'y' => mm from the top to the baseline, 'size' => pt, 'bold' => bool,
     *      'align' => left|center|right, 'width' => mm the placeholder itself takes],
     *      'content' => the page's content stream without them, or null if it has none]
     */
    public static function find(string $path): array {
        $finder = new self();
        try {
            return $finder->read($path);
        } catch (\Throwable $e) {
            // A PDF this cannot follow simply has no placeholders.
            debugging('Placeholders in the certificate template not readable: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['placeholders' => [], 'content' => null];
        }
    }

    /**
     * Read the page, collect the placeholders and take them out.
     *
     * @param string $path
     * @return array as find() returns it
     */
    protected function read(string $path): array {
        $this->parser = new PdfParser(StreamReader::createByFile($path));
        $reader = new PdfReader($this->parser);
        $page = $reader->getPage(1);

        $resources = PdfType::resolve($page->getAttribute('Resources'), $this->parser);
        $fonts = $resources instanceof PdfDictionary ? PdfDictionary::get($resources, 'Font') : null;
        $fonts = $fonts ? PdfType::resolve($fonts, $this->parser) : null;
        $this->fontresources = $fonts instanceof PdfDictionary ? $fonts : null;

        $content = $page->getContentStream();
        $this->run($content);

        $box = $page->getBoundary();
        [$placeholders, $glyphs] = $this->placeholders($box->getLlx(), $box->getUry());

        return [
            'placeholders' => $placeholders,
            'content' => $placeholders ? $this->strip($content, $glyphs) : null,
        ];
    }

    /**
     * Follow the operators of a content stream and record every glyph.
     *
     * @param string $content
     */
    protected function run(string $content): void {
        $parser = new PdfParser(StreamReader::createByString($content));
        $stream = $parser->getStreamReader();
        $ctm = [1, 0, 0, 1, 0, 0];
        $text = ['font' => null, 'size' => 0, 'charspace' => 0, 'wordspace' => 0, 'scale' => 1, 'leading' => 0, 'rise' => 0];
        $stack = [];
        $tm = $tlm = [1, 0, 0, 1, 0, 0];
        $operands = [];
        // Where the operands of the next operator begin: after the last operator.
        $start = 0;

        while (($value = $parser->readValue()) !== false) {
            if (!$value instanceof PdfToken) {
                $operands[] = $value;
                continue;
            }
            $op = $value->value;
            // The parser looks ahead at most two tokens after a number and
            // stops at the first word, so it never reads past an operator.
            $end = $stream->getPosition() + $stream->getOffset();
            $num = function (int $i) use ($operands): float {
                return isset($operands[$i]) && $operands[$i] instanceof PdfNumeric ? (float) $operands[$i]->value : 0.0;
            };

            switch ($op) {
                case 'q':
                    $stack[] = [$ctm, $text];
                    break;
                case 'Q':
                    if ($stack) {
                        [$ctm, $text] = array_pop($stack);
                    }
                    break;
                case 'cm':
                    $ctm = self::multiply([$num(0), $num(1), $num(2), $num(3), $num(4), $num(5)], $ctm);
                    break;
                case 'BT':
                    $tm = $tlm = [1, 0, 0, 1, 0, 0];
                    break;
                case 'Tf':
                    $text['font'] = isset($operands[0]) && $operands[0] instanceof PdfName ? $operands[0]->value : null;
                    $text['size'] = $num(1);
                    break;
                case 'Tc':
                    $text['charspace'] = $num(0);
                    break;
                case 'Tw':
                    $text['wordspace'] = $num(0);
                    break;
                case 'Tz':
                    $text['scale'] = $num(0) / 100;
                    break;
                case 'TL':
                    $text['leading'] = $num(0);
                    break;
                case 'Ts':
                    $text['rise'] = $num(0);
                    break;
                case 'Td':
                case 'TD':
                    if ($op === 'TD') {
                        $text['leading'] = -$num(1);
                    }
                    $tm = $tlm = self::multiply([1, 0, 0, 1, $num(0), $num(1)], $tlm);
                    break;
                case 'Tm':
                    $tm = $tlm = [$num(0), $num(1), $num(2), $num(3), $num(4), $num(5)];
                    break;
                case 'T*':
                    $tm = $tlm = self::multiply([1, 0, 0, 1, 0, -$text['leading']], $tlm);
                    break;
                case 'Tj':
                case 'TJ':
                case "'":
                case '"':
                    if ($op === '"') {
                        $text['wordspace'] = $num(0);
                        $text['charspace'] = $num(1);
                    }
                    if ($op === "'" || $op === '"') {
                        $tm = $tlm = self::multiply([1, 0, 0, 1, 0, -$text['leading']], $tlm);
                    }
                    $this->operators[] = [
                        'start' => $start,
                        'end' => $end,
                        'op' => $op,
                        'size' => $text['size'],
                        'charspace' => $text['charspace'],
                        'wordspace' => $text['wordspace'],
                        'parts' => [],
                    ];
                    $tm = $this->show(end($operands), $text, $tm, $ctm, count($this->operators) - 1);
                    break;
            }
            $operands = [];
            $start = $end;
        }
    }

    /**
     * Record the glyphs of one text showing operator.
     *
     * Besides the glyphs for the search, the operator keeps its parts: the
     * shifts between glyphs and each glyph's bytes and width, to be written
     * anew by strip().
     *
     * @param PdfType|false $operand a string, or an array of strings and adjustments
     * @param array $text the text state
     * @param array $tm the text matrix
     * @param array $ctm the current transformation matrix
     * @param int $operator index of the operator in $operators
     * @return array the text matrix after the text
     */
    protected function show($operand, array $text, array $tm, array $ctm, int $operator): array {
        $font = $this->font((string) $text['font']);
        $parts = $operand instanceof PdfArray ? $operand->value : [$operand];

        foreach ($parts as $part) {
            if ($part instanceof PdfNumeric) {
                // A shift of the next glyph, in thousandths of the font size.
                $tm = self::multiply([1, 0, 0, 1, -$part->value / 1000 * $text['size'] * $text['scale'], 0], $tm);
                $this->operators[$operator]['parts'][] = ['shift' => (float) $part->value];
                continue;
            }
            $bytes = self::bytes($part);
            if ($bytes === null) {
                continue;
            }
            foreach ($this->decode($bytes, $font) as [$code, $unicode, $raw]) {
                $width = ($font['widths'][$code] ?? $font['default']) / 1000;
                $space = $font['bytes'] === 1 && $code === 32;
                $advance = ($width * $text['size'] + $text['charspace'] + ($space ? $text['wordspace'] : 0)) * $text['scale'];

                $matrix = self::multiply($tm, $ctm);
                $origin = self::apply($matrix, 0, $text['rise']);
                $end = self::apply(self::multiply([1, 0, 0, 1, $advance, 0], $matrix), 0, $text['rise']);
                $size = $text['size'] * sqrt($matrix[2] ** 2 + $matrix[3] ** 2);
                $this->operators[$operator]['parts'][] = [
                    'bytes' => $raw,
                    'width' => $width,
                    // Simple fonts without widths, such as the standard 14, give no width to move by.
                    'known' => $font['bytes'] === 2 || isset($font['widths'][$code]),
                    'space' => $space,
                    'glyph' => count($this->glyphs),
                ];
                $this->glyphs[] = [$unicode, $origin[0], $origin[1], $size, $end[0] - $origin[0], $font['bold']];

                $tm = self::multiply([1, 0, 0, 1, $advance, 0], $tm);
            }
        }

        return $tm;
    }

    /**
     * Join the glyphs into lines and find the placeholders in them.
     *
     * @param float $left left edge of the page in points
     * @param float $top top edge of the page in points
     * @return array [the placeholders as find() returns them, glyph index => true for their glyphs]
     */
    protected function placeholders(float $left, float $top): array {
        $lines = [];
        $line = [];
        foreach ($this->glyphs as $index => $glyph) {
            $last = end($line);
            // A new line where the baseline moves or the text jumps back.
            if ($last && (abs($glyph[2] - $last[2]) > 0.5 || $glyph[1] < $last[1] - 1)) {
                $lines[] = $line;
                $line = [];
            }
            $line[$index] = $glyph;
        }
        if ($line) {
            $lines[] = $line;
        }

        $mm = 25.4 / 72;
        $found = [];
        $used = [];
        foreach ($lines as $glyphs) {
            // Byte offset in the joined text => glyph index.
            $string = '';
            $index = [];
            foreach ($glyphs as $i => $glyph) {
                $index[strlen($string)] = $i;
                $string .= $glyph[0];
            }
            if (!preg_match_all(self::PATTERN, $string, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($matches as $match) {
                $startbyte = $match[0][1];
                $endbyte = $startbyte + strlen($match[0][0]) - 1;
                if (!isset($index[$startbyte], $index[$endbyte])) {
                    continue;
                }
                foreach ($index as $byte => $i) {
                    if ($byte >= $startbyte && $byte <= $endbyte) {
                        $used[$i] = true;
                    }
                }
                $first = $glyphs[$index[$startbyte]];
                $last = $glyphs[$index[$endbyte]];
                $found[] = [
                    'key' => $match[1][0],
                    'x' => ($first[1] - $left) * $mm,
                    'y' => ($top - $first[2]) * $mm,
                    'size' => round($first[3] * 2) / 2,
                    'bold' => $first[5],
                    'align' => ($match[2][0] ?? '') ?: 'left',
                    'width' => ($last[1] + $last[4] - $first[1]) * $mm,
                ];
            }
        }

        return [$found, $used];
    }

    /**
     * The content stream with the given glyphs taken out.
     *
     * Each operator that shows one of them becomes a TJ that shows the other
     * glyphs as before and moves over the taken ones by their width.
     *
     * @param string $content
     * @param array $glyphs glyph index => true
     * @return string
     */
    protected function strip(string $content, array $glyphs): string {
        foreach (array_reverse($this->operators) as $operator) {
            $items = [];
            $run = '';
            $changed = false;
            foreach ($operator['parts'] as $part) {
                if (isset($part['bytes']) && !isset($glyphs[$part['glyph']])) {
                    $run .= $part['bytes'];
                    continue;
                }
                if ($run !== '') {
                    $items[] = '<' . bin2hex($run) . '>';
                    $run = '';
                }
                if (isset($part['shift'])) {
                    $items[] = self::number($part['shift']);
                    continue;
                }
                if (!$part['known']) {
                    // Text after it would move; better leave the operator white than move text.
                    $changed = false;
                    break;
                }
                // The same move the glyph made, as a shift in thousandths of the font size.
                $changed = true;
                $advance = $part['width'] * $operator['size'] + $operator['charspace']
                    + ($part['space'] ? $operator['wordspace'] : 0);
                $items[] = self::number($operator['size'] ? -$advance / $operator['size'] * 1000 : 0);
            }
            if (!$changed) {
                continue;
            }
            if ($run !== '') {
                $items[] = '<' . bin2hex($run) . '>';
            }

            $prefix = '';
            if ($operator['op'] === '"') {
                $prefix = self::number($operator['wordspace']) . ' Tw ' . self::number($operator['charspace']) . ' Tc T* ';
            } else if ($operator['op'] === "'") {
                $prefix = 'T* ';
            }
            $replacement = "\n" . $prefix . '[' . implode(' ', $items) . '] TJ';
            $content = substr($content, 0, $operator['start']) . $replacement . substr($content, $operator['end']);
        }

        return $content;
    }

    /**
     * What the text needs to know about a font.
     *
     * @param string $name font resource name
     * @return array ['bytes' => 1|2, 'widths' => code => width, 'default' => width,
     *      'unicode' => code => text, 'bold' => bool]
     */
    protected function font(string $name): array {
        if (isset($this->fonts[$name])) {
            return $this->fonts[$name];
        }

        $data = ['bytes' => 1, 'widths' => [], 'default' => 0, 'unicode' => [], 'bold' => false];
        $font = null;
        if ($this->fontresources && $name !== '') {
            $font = PdfType::resolve(PdfDictionary::get($this->fontresources, $name), $this->parser);
        }

        if ($font instanceof PdfDictionary) {
            $basefont = PdfType::resolve(PdfDictionary::get($font, 'BaseFont'), $this->parser);
            $data['bold'] = $basefont instanceof PdfName && stripos($basefont->value, 'bold') !== false;

            $subtype = PdfType::resolve(PdfDictionary::get($font, 'Subtype'), $this->parser);
            if ($subtype instanceof PdfName && $subtype->value === 'Type0') {
                // Two byte codes, widths in the descendant font.
                $data['bytes'] = 2;
                $data['default'] = 1000;
                $descendants = PdfType::resolve(PdfDictionary::get($font, 'DescendantFonts'), $this->parser);
                $cidfont = $descendants instanceof PdfArray && $descendants->value
                    ? PdfType::resolve($descendants->value[0], $this->parser) : null;
                if ($cidfont instanceof PdfDictionary) {
                    $dw = PdfType::resolve(PdfDictionary::get($cidfont, 'DW'), $this->parser);
                    if ($dw instanceof PdfNumeric) {
                        $data['default'] = (float) $dw->value;
                    }
                    $data['widths'] = $this->cid_widths(PdfType::resolve(PdfDictionary::get($cidfont, 'W'), $this->parser));
                }
            } else {
                $first = PdfType::resolve(PdfDictionary::get($font, 'FirstChar'), $this->parser);
                $widths = PdfType::resolve(PdfDictionary::get($font, 'Widths'), $this->parser);
                if ($first instanceof PdfNumeric && $widths instanceof PdfArray) {
                    foreach ($widths->value as $i => $width) {
                        $width = PdfType::resolve($width, $this->parser);
                        $data['widths'][(int) $first->value + $i] = $width instanceof PdfNumeric ? (float) $width->value : 0;
                    }
                }
            }

            $tounicode = PdfType::resolve(PdfDictionary::get($font, 'ToUnicode'), $this->parser);
            if ($tounicode instanceof PdfStream) {
                $data['unicode'] = self::cmap($tounicode->getUnfilteredStream());
            }
        }

        return $this->fonts[$name] = $data;
    }

    /**
     * Widths of a CID font from its W array.
     *
     * @param PdfType $w
     * @return array code => width
     */
    protected function cid_widths($w): array {
        $widths = [];
        if (!$w instanceof PdfArray) {
            return $widths;
        }
        $items = array_map(function ($item) {
            return PdfType::resolve($item, $this->parser);
        }, $w->value);

        for ($i = 0; $i < count($items); $i++) {
            if (!$items[$i] instanceof PdfNumeric) {
                continue;
            }
            $start = (int) $items[$i]->value;
            $next = $items[$i + 1] ?? null;
            if ($next instanceof PdfArray) {
                // Form "c [w1 w2 ...]": consecutive codes.
                foreach ($next->value as $j => $width) {
                    $width = PdfType::resolve($width, $this->parser);
                    $widths[$start + $j] = $width instanceof PdfNumeric ? (float) $width->value : 0;
                }
                $i++;
            } else if ($next instanceof PdfNumeric && ($items[$i + 2] ?? null) instanceof PdfNumeric) {
                // Form "first last w": a range of codes with one width.
                for ($code = $start; $code <= (int) $next->value; $code++) {
                    $widths[$code] = (float) $items[$i + 2]->value;
                }
                $i += 2;
            }
        }

        return $widths;
    }

    /**
     * Split the bytes of a string into codes and their text.
     *
     * @param string $bytes
     * @param array $font as font() returns it
     * @return array of [code, text, bytes of the code]
     */
    protected function decode(string $bytes, array $font): array {
        $glyphs = [];
        $step = $font['bytes'];
        for ($i = 0; $i + $step <= strlen($bytes); $i += $step) {
            $raw = substr($bytes, $i, $step);
            $code = $step === 2 ? (ord($raw[0]) << 8) | ord($raw[1]) : ord($raw);
            if (isset($font['unicode'][$code])) {
                $unicode = $font['unicode'][$code];
            } else if ($step === 1) {
                $unicode = \core_text::convert($raw, 'windows-1252', 'utf-8');
            } else {
                $unicode = '';
            }
            $glyphs[] = [$code, $unicode, $raw];
        }

        return $glyphs;
    }

    /**
     * The codes and their text from a ToUnicode CMap.
     *
     * @param string $cmap
     * @return array code => text
     */
    protected static function cmap(string $cmap): array {
        $map = [];
        $utf16 = function (string $hex): string {
            $bytes = hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex);
            return (string) \core_text::convert($bytes, 'utf-16be', 'utf-8');
        };

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $block) {
                preg_match_all('/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]*)>/', $block, $pairs, PREG_SET_ORDER);
                foreach ($pairs as $pair) {
                    $map[hexdec($pair[1])] = $utf16($pair[2]);
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $block) {
                $pattern = '/<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*(<[0-9a-fA-F]+>|\[[^\]]*\])/';
                preg_match_all($pattern, $block, $ranges, PREG_SET_ORDER);
                foreach ($ranges as $range) {
                    $low = hexdec($range[1]);
                    $high = hexdec($range[2]);
                    if ($range[3][0] === '[') {
                        preg_match_all('/<([0-9a-fA-F]+)>/', $range[3], $targets);
                        foreach ($targets[1] as $i => $target) {
                            $map[$low + $i] = $utf16($target);
                        }
                    } else {
                        $start = hexdec(trim($range[3], '<>'));
                        for ($code = $low; $code <= $high && $code - $low < 65536; $code++) {
                            $map[$code] = $utf16(dechex($start + $code - $low));
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * The raw bytes of a string operand.
     *
     * @param mixed $value
     * @return string|null
     */
    protected static function bytes($value): ?string {
        if ($value instanceof PdfString) {
            return PdfString::unescape($value->value);
        }
        if ($value instanceof PdfHexString) {
            $hex = preg_replace('/\s+/', '', $value->value);
            return hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
        }

        return null;
    }

    /**
     * A number as a content stream writes it.
     *
     * @param float $value
     * @return string
     */
    protected static function number(float $value): string {
        $text = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');
        return $text === '' || $text === '-0' ? '0' : $text;
    }

    /**
     * Multiply two matrices given as [a b c d e f].
     *
     * @param array $m
     * @param array $n
     * @return array $m times $n
     */
    protected static function multiply(array $m, array $n): array {
        return [
            $m[0] * $n[0] + $m[1] * $n[2],
            $m[0] * $n[1] + $m[1] * $n[3],
            $m[2] * $n[0] + $m[3] * $n[2],
            $m[2] * $n[1] + $m[3] * $n[3],
            $m[4] * $n[0] + $m[5] * $n[2] + $n[4],
            $m[4] * $n[1] + $m[5] * $n[3] + $n[5],
        ];
    }

    /**
     * Apply a matrix to a point.
     *
     * @param array $m
     * @param float $x
     * @param float $y
     * @return array [x, y]
     */
    protected static function apply(array $m, float $x, float $y): array {
        return [$x * $m[0] + $y * $m[2] + $m[4], $x * $m[1] + $y * $m[3] + $m[5]];
    }
}
