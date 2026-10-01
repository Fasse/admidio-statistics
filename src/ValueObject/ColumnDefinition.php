<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * One configured column of a statistic table: its label, the condition that selects its members,
 * the function that produces each cell and the function that produces the total row.
 *
 * As in RowDefinition the condition is flattened in, and so is the function: version 3 wrapped the
 * name and the argument in a StatisticFunction object, while the database keeps both as columns of
 * this row. StatisticFunction is now the list of the function names instead.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class ColumnDefinition
{
    /**
     * @param string $label The heading of the column in the rendered table.
     * @param int|null $profileFieldId The profile field the condition applies to, **null** for all.
     * @param string $condition The condition as the user typed it, decoded.
     * @param string $functionName One of StatisticFunction::NAMES.
     * @param int|null $functionArgument The profile field the function aggregates, for the four
     *                                   functions that need one; **null** for count and percent.
     * @param string $functionTotal One of StatisticFunction::TOTAL_NAMES, empty for no total.
     */
    public function __construct(
        public readonly string $label = '',
        public readonly ?int $profileFieldId = null,
        public readonly string $condition = '',
        public readonly string $functionName = StatisticFunction::COUNT,
        public readonly ?int $functionArgument = null,
        public readonly string $functionTotal = ''
    ) {
    }

    /**
     * Build a column from its database record, keyed by column name.
     *
     * @param array<string,mixed> $record A record of the statistics columns table.
     * @param string $condition The condition, already decoded by ConditionCompiler.
     * @return self
     */
    public static function fromRecord(array $record, string $condition): self
    {
        $function = (string) ($record['stc_function_main'] ?? '');

        return new self(
            (string) ($record['stc_label'] ?? ''),
            RowDefinition::toFieldId($record['stc_profile_field'] ?? null),
            $condition,
            StatisticFunction::isValid($function) ? $function : StatisticFunction::COUNT,
            RowDefinition::toFieldId($record['stc_function_arg'] ?? null),
            self::toTotal($record['stc_function_total'] ?? null)
        );
    }

    /**
     * Whether this column declares a total, which is what decides whether the table gets a total
     * row at all. Version 3 asked is_null() of a column that stores the empty string for "no
     * total", so it appended a total row to every table.
     *
     * @return bool
     */
    public function hasTotal(): bool
    {
        return $this->functionTotal !== '';
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function toTotal(mixed $value): string
    {
        $total = (string) $value;

        return StatisticFunction::isValidTotal($total) ? $total : '';
    }
}
