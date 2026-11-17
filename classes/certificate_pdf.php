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

use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;

/**
 * FPDI on Moodle's TCPDF, with a way to give an imported page other content.
 *
 * Only to be created after certificate::libraries() has loaded both.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_pdf extends \setasign\Fpdi\TcpdfFpdi {
    /**
     * Replace the content stream of an imported page.
     *
     * Everything else of the page stays: its resources, so fonts and images
     * the new content uses, and its box.
     *
     * @param string $pageid as importPage() returned it
     * @param string $content the new, unfiltered content stream
     */
    public function replace_content(string $pageid, string $content): void {
        $dictionary = $this->importedPages[$pageid]['stream']->value;
        unset($dictionary->value['DecodeParms']);
        $data = gzcompress($content);
        $dictionary->value['Filter'] = PdfName::create('FlateDecode');
        $dictionary->value['Length'] = PdfNumeric::create(strlen($data));
        $this->importedPages[$pageid]['stream'] = PdfStream::create($dictionary, $data);
    }
}
