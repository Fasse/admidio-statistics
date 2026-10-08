<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * The statistical functions a column can apply, and which of them need a profile field.
 *
 * The names are the ones version 3 stored in stc_function_main and stc_function_total, so a
 * definition written by the old editor keeps working. They are declared once here because the
 * editor offers them, the evaluator dispatches on them and the JavaScript decides from them which
 * options to disable - and in version 3 all three held their own copy, the JavaScript even as
 * hardcoded option positions.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticFunction
{
    public const COUNT = '#';
    public const PERCENT = '%';
    public const MINIMUM = 'min';
    public const MAXIMUM = 'max';
    public const AVERAGE = 'avg';
    public const SUM = 'sum';

    /**
     * Every function a column may use, in the order the editor offers them.
     */
    public const NAMES = array(
        self::COUNT,
        self::PERCENT,
        self::MINIMUM,
        self::MAXIMUM,
        self::AVERAGE,
        self::SUM
    );

    /**
     * The functions that aggregate the values of a profile field and therefore need one named.
     * Count and percent count members instead, so for them the argument stays empty.
     */
    public const ARGUMENT_NAMES = array(
        self::MINIMUM,
        self::MAXIMUM,
        self::AVERAGE,
        self::SUM
    );

    /**
     * The functions a column may apply to its own cells to produce the total row. The empty string
     * is a value of its own - it means the column contributes no total - which is why it is part of
     * the list rather than a missing entry.
     */
    public const TOTAL_NAMES = array(
        '',
        self::MINIMUM,
        self::MAXIMUM,
        self::AVERAGE,
        self::SUM
    );

    /**
     * @param string $name
     * @return bool Whether this function aggregates a profile field, so that the editor has to
     *              offer one and the evaluator has to read one.
     */
    public static function needsArgument(string $name): bool
    {
        return in_array($name, self::ARGUMENT_NAMES, true);
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function isValid(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }

    /**
     * @param string $name
     * @return bool
     */
    public static function isValidTotal(string $name): bool
    {
        return in_array($name, self::TOTAL_NAMES, true);
    }
}
