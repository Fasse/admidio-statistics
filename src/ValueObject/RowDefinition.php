<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * One configured row of a statistic table: its label and the condition that selects its members.
 *
 * Version 3 kept the condition in a StatisticCondition object of its own. It is flattened in here,
 * because the database flattens it too - str_field_condition and str_profile_field are columns of
 * this very row - and the double indirection was what made the evaluator hard to read.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class RowDefinition
{
    /**
     * @param string $label The text in the first column of the rendered table.
     * @param int|null $profileFieldId The profile field the condition applies to, or **null** for
     *                                 all members of the role, which the editor offers as SYS_ALL.
     * @param string $condition The condition as the user typed it, with the comparison characters
     *                          as themselves. ConditionCompiler turns it into SQL and encodes it
     *                          for storage.
     */
    public function __construct(
        public readonly string $label = '',
        public readonly ?int $profileFieldId = null,
        public readonly string $condition = ''
    ) {
    }

    /**
     * Build a row from its database record, keyed by column name so that the order of the columns
     * cannot be mistaken.
     *
     * @param array<string,mixed> $record A record of the statistics rows table.
     * @param string $condition The condition, already decoded by ConditionCompiler.
     * @return self
     */
    public static function fromRecord(array $record, string $condition): self
    {
        return new self(
            (string) ($record['str_label'] ?? ''),
            self::toFieldId($record['str_profile_field'] ?? null),
            $condition
        );
    }

    /**
     * A profile field reference as the database holds it. The column is a varchar that version 3
     * also used for the empty selection, so anything that is not a positive number means "all".
     *
     * @param mixed $value
     * @return int|null
     */
    public static function toFieldId(mixed $value): ?int
    {
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
