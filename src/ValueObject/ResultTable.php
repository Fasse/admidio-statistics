<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * One computed table of a statistic, in the shape a template renders.
 *
 * It is deliberately flat: a list of headings and a list of rows, each row already a list of
 * strings beginning with its label. Version 3 had no result object and reused the definition graph
 * as the carrier, addressing computed cells by coordinates through setCell(), getCell() and
 * deleteCell() - all three of which were broken, because deleteCell() unset the foreach copy rather
 * than the array element, so setCell() appended duplicates and getCell() returned the stale first
 * match. With no cell lookup at all there is nothing left to get out of order.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class ResultTable
{
    /** @var array<int,string> */
    public readonly array $headers;

    /** @var array<int,array<int,string>> */
    public readonly array $rows;

    /**
     * @param string $title The heading above the table.
     * @param string $caption The line under the heading naming the role and its member count.
     * @param array<int,string> $headers The label column heading followed by the column headings.
     * @param array<int,array<int,string>> $rows Each row as its label followed by its cells.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $caption,
        array $headers,
        array $rows
    ) {
        $this->headers = array_values($headers);
        $this->rows = array_map('array_values', array_values($rows));
    }
}
