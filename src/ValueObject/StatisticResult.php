<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * A computed statistic, ready to render.
 *
 * One template takes this, and it serves both the page that shows a saved statistic and the preview
 * the editor renders above its form - which is why the preview no longer has to save anything in
 * order to be shown.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticResult
{
    /** @var array<int,ResultTable> */
    public readonly array $tables;

    /**
     * @param string $title The headline of the statistic.
     * @param string $subtitle The second line, empty when none was configured.
     * @param string $asOf The date the figures were computed, already formatted for display.
     * @param array<int,ResultTable> $tables
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $asOf,
        array $tables
    ) {
        $this->tables = array_values($tables);
    }
}
