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
     * @param int|null $roleId The role whose members this row counts instead of the role of the
     *                         table, or **null** for the role of the table.
     * @param string|null $referenceDate The day (Y-m-d) on which the memberships are looked at, or
     *                                   **null** for the day the statistic is shown for.
     * @param string $repeat One of DateSeries::INTERVALS when the reference date is the first day
     *                       of a series, empty when it is one day.
     * @param int|null $repeatMax How many days of the series are shown at most, **null** for
     *                            DateSeries::DEFAULT_MAXIMUM.
     */
    public function __construct(
        public readonly string $label = '',
        public readonly ?int $profileFieldId = null,
        public readonly string $condition = '',
        public readonly ?int $roleId = null,
        public readonly ?string $referenceDate = null,
        public readonly string $repeat = DateSeries::NONE,
        public readonly ?int $repeatMax = null
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
            $condition,
            self::toFieldId($record['str_role'] ?? null),
            self::toDate($record['str_ref_date'] ?? null),
            DateSeries::toInterval($record['str_repeat'] ?? null),
            DateSeries::toMaximum($record['str_repeat_max'] ?? null)
        );
    }

    /**
     * @return bool Whether the row stands for a series of days.
     */
    public function isSeries(): bool
    {
        return $this->referenceDate !== null && $this->repeat !== DateSeries::NONE;
    }

    /**
     * The row for one day of its series.
     *
     * @param string $date
     * @param string $label
     * @return self
     */
    public function onDate(string $date, string $label): self
    {
        return new self($label, $this->profileFieldId, $this->condition, $this->roleId, $date);
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

    /**
     * A reference date as the database or the form holds it. Anything that is not a real day of the
     * calendar in the form Y-m-d means "no date", so that no other text ever reaches a query.
     *
     * @param mixed $value
     * @return string|null
     */
    public static function toDate(mixed $value): ?string
    {
        $date = trim((string) $value);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return $date;
    }
}
