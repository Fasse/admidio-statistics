<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * The days of a series: a first day that repeats every year, every month or every week.
 *
 * A row or a column with a reference date can ask for it to repeat. It then stands for a run of
 * days instead of one, and the run grows by itself: a column for the first of January of every year
 * gets its next day when the next year begins, without anybody editing the statistic.
 *
 * This class only does the arithmetic of the calendar. It knows nothing of rows and columns, which
 * is what lets the awkward days be tested on their own: a yearly series that starts on the 29th of
 * February, and a monthly one that starts on the 31st.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class DateSeries
{
    public const NONE = '';
    public const YEARLY = 'yearly';
    public const MONTHLY = 'monthly';
    public const WEEKLY = 'weekly';

    /**
     * The intervals a series can have, in the order the editor offers them.
     */
    public const INTERVALS = array(self::YEARLY, self::MONTHLY, self::WEEKLY);

    /**
     * How many days a series shows when it does not say.
     */
    public const DEFAULT_MAXIMUM = 10;

    /**
     * The most days a series can show. Every day is a column or a row, and every cell at least one
     * query.
     */
    public const HIGHEST_MAXIMUM = 366;

    /**
     * An interval as the database or the form holds it. Anything that is not one of INTERVALS means
     * that the day does not repeat.
     *
     * @param mixed $value
     * @return string
     */
    public static function toInterval(mixed $value): string
    {
        $interval = (string) $value;

        return in_array($interval, self::INTERVALS, true) ? $interval : self::NONE;
    }

    /**
     * A number of days as the database or the form holds it, **null** when none was given.
     *
     * @param mixed $value
     * @return int|null
     */
    public static function toMaximum(mixed $value): ?int
    {
        $maximum = (int) $value;

        return $maximum > 0 ? min($maximum, self::HIGHEST_MAXIMUM) : null;
    }

    /**
     * The days of a series up to a given day, oldest first.
     *
     * @param string $first The first day of the series, as Y-m-d.
     * @param string $interval One of INTERVALS.
     * @param int|null $maximum How many days to return at most, counted back from the latest;
     *                          **null** for DEFAULT_MAXIMUM.
     * @param string $until The last day a day of the series may be, as Y-m-d.
     * @return array<int,string> Empty when the series has not begun by then.
     */
    public static function dates(string $first, string $interval, ?int $maximum, string $until): array
    {
        if (RowDefinition::toDate($first) === null || RowDefinition::toDate($until) === null
            || self::toInterval($interval) === self::NONE) {
            return array();
        }

        $maximum = min($maximum ?? self::DEFAULT_MAXIMUM, self::HIGHEST_MAXIMUM);
        if ($maximum < 1) {
            $maximum = self::DEFAULT_MAXIMUM;
        }

        /*
         * Counted back from the latest day rather than forward from the first, so that a weekly
         * series that began decades ago costs as many steps as it shows days. The dates are Y-m-d,
         * so comparing them as text compares them as days.
         */
        $step = self::stepsUntil($first, $interval, $until);

        $dates = array();
        while ($step >= 0 && count($dates) < $maximum) {
            $dates[] = self::dateAt($first, $interval, $step);
            $step--;
        }

        return array_reverse($dates);
    }

    /**
     * The number of the latest step of the series that is not after the given day, -1 when even the
     * first day is.
     *
     * @param string $first
     * @param string $interval
     * @param string $until
     * @return int
     */
    private static function stepsUntil(string $first, string $interval, string $until): int
    {
        if ($first > $until) {
            return -1;
        }

        $from = new \DateTimeImmutable($first);
        $to = new \DateTimeImmutable($until);

        // An estimate that is never too low, corrected downwards by at most a step or two.
        $step = match ($interval) {
            self::WEEKLY => intdiv((int) $from->diff($to)->days, 7),
            self::MONTHLY => ((int) $to->format('Y') - (int) $from->format('Y')) * 12
                + (int) $to->format('n') - (int) $from->format('n'),
            default => (int) $to->format('Y') - (int) $from->format('Y')
        };

        while ($step > 0 && self::dateAt($first, $interval, $step) > $until) {
            $step--;
        }

        return $step;
    }

    /**
     * The day of one step of the series. The day of the month is the one of the first day wherever
     * the month has it, and the last day of the month where it has not: a series that starts on the
     * 31st of January continues on the 28th or 29th of February and on the 31st of March.
     *
     * @param string $first
     * @param string $interval
     * @param int $step 0 for the first day itself.
     * @return string
     */
    private static function dateAt(string $first, string $interval, int $step): string
    {
        $from = new \DateTimeImmutable($first);

        if ($interval === self::WEEKLY) {
            return $from->modify('+' . ($step * 7) . ' days')->format('Y-m-d');
        }

        $months = ((int) $from->format('Y')) * 12 + ((int) $from->format('n')) - 1
            + ($interval === self::YEARLY ? $step * 12 : $step);

        $year = intdiv($months, 12);
        $month = $months % 12 + 1;
        $day = min((int) $from->format('j'), (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t'));

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
