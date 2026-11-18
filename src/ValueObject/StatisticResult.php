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

    /** @var array<int,string> */
    public readonly array $excludedRoles;

    /**
     * @param string $title The headline of the statistic.
     * @param string $subtitle The second line, empty when none was configured.
     * @param string $asOf The date the figures were computed, already formatted for display.
     * @param array<int,ResultTable> $tables
     * @param array<int,string> $excludedRoles The names of the roles whose members are left out.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $subtitle,
        public readonly string $asOf,
        array $tables,
        array $excludedRoles = array()
    ) {
        $this->tables = array_values($tables);
        $this->excludedRoles = array_values($excludedRoles);
    }

    /**
     * The result as plain values, for the cache.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return array(
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'asOf' => $this->asOf,
            'tables' => array_map(static fn (ResultTable $table): array => $table->toArray(), $this->tables),
            'excludedRoles' => $this->excludedRoles
        );
    }

    /**
     * The result back from its plain values.
     *
     * @param mixed $data
     * @return self
     * @throws \InvalidArgumentException when the values are not of the shape toArray() writes.
     */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !is_array($data['tables'] ?? null)) {
            throw new \InvalidArgumentException('not a stored result');
        }

        return new self(
            (string) ($data['title'] ?? ''),
            (string) ($data['subtitle'] ?? ''),
            (string) ($data['asOf'] ?? ''),
            array_map(array(ResultTable::class, 'fromArray'), $data['tables']),
            array_map('strval', (array) ($data['excludedRoles'] ?? array()))
        );
    }
}
