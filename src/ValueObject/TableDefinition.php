<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * One configured table of a statistic: its heading, the role whose members it counts, and its
 * columns and rows.
 *
 * The arrays are always indexed from zero without gaps. Version 3 removed an element with unset(),
 * which left the keys sparse while the editor went on iterating with a counter - so deleting a row
 * in the middle produced undefined-index warnings and a visibly broken form. Nothing here mutates:
 * StatisticStructureService builds a new table through withColumns() and withRows() and reindexes
 * as it goes, which makes that whole class of bug unreachable.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class TableDefinition
{
    /** @var array<int,ColumnDefinition> */
    public readonly array $columns;

    /** @var array<int,RowDefinition> */
    public readonly array $rows;

    /**
     * @param string $title The heading above the table.
     * @param int|null $roleId The role whose members this table counts, or **null** to use the
     *                         standard role of the statistic. Version 3 wrote 0 or an empty string
     *                         for that and asked empty() everywhere.
     * @param string $firstColumnLabel The heading of the label column, above the row labels.
     * @param array<int,ColumnDefinition> $columns
     * @param array<int,RowDefinition> $rows
     */
    public function __construct(
        public readonly string $title = '',
        public readonly ?int $roleId = null,
        public readonly string $firstColumnLabel = '',
        array $columns = array(),
        array $rows = array()
    ) {
        $this->columns = array_values($columns);
        $this->rows = array_values($rows);
    }

    /**
     * Build a table from its database record, keyed by column name.
     *
     * @param array<string,mixed> $record A record of the statistics tables table.
     * @param array<int,ColumnDefinition> $columns
     * @param array<int,RowDefinition> $rows
     * @return self
     */
    public static function fromRecord(array $record, array $columns, array $rows): self
    {
        $role = (int) ($record['stt_role'] ?? 0);

        return new self(
            (string) ($record['stt_title'] ?? ''),
            $role > 0 ? $role : null,
            (string) ($record['stt_first_column_label'] ?? ''),
            $columns,
            $rows
        );
    }

    /**
     * The smallest table the editor can show: one column and one row, as version 3 built it in
     * createEmptyTable().
     *
     * @return self
     */
    public static function createEmpty(): self
    {
        return new self('', null, '', array(new ColumnDefinition()), array(new RowDefinition()));
    }

    /**
     * The role this table actually counts, which is its own if it has one and the standard role of
     * the statistic otherwise.
     *
     * @param int $standardRoleId
     * @return int
     */
    public function effectiveRoleId(int $standardRoleId): int
    {
        return $this->roleId ?? $standardRoleId;
    }

    /**
     * @return bool Whether any column declares a total, which is what decides whether the rendered
     *              table gets a total row.
     */
    public function hasTotalRow(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->hasTotal()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,ColumnDefinition> $columns
     * @return self
     */
    public function withColumns(array $columns): self
    {
        return new self($this->title, $this->roleId, $this->firstColumnLabel, $columns, $this->rows);
    }

    /**
     * @param array<int,RowDefinition> $rows
     * @return self
     */
    public function withRows(array $rows): self
    {
        return new self($this->title, $this->roleId, $this->firstColumnLabel, $this->columns, $rows);
    }
}
