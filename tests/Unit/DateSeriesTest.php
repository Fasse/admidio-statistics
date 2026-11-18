<?php
/**
 * The days of a series.
 *
 * The plain cases are quickly said. What is tested at length are the days the calendar makes awkward:
 * a series that starts on a day its later months or years do not have, and the edges - the day the
 * series is asked up to being a day of the series itself, or the day before one.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use AdmidioPlugin\Statistics\ValueObject\DateSeries;
use PHPUnit\Framework\TestCase;

final class DateSeriesTest extends TestCase
{
    public function testAYearlySeriesHasADayForEveryYear(): void
    {
        $this->assertSame(
            array('2020-01-01', '2021-01-01', '2022-01-01'),
            DateSeries::dates('2020-01-01', DateSeries::YEARLY, null, '2022-06-30')
        );
    }

    public function testTheDayItIsAskedUpToCounts(): void
    {
        $this->assertSame(
            array('2020-01-01', '2021-01-01'),
            DateSeries::dates('2020-01-01', DateSeries::YEARLY, null, '2021-01-01')
        );
        $this->assertSame(
            array('2020-01-01'),
            DateSeries::dates('2020-01-01', DateSeries::YEARLY, null, '2020-12-31')
        );
        $this->assertSame(
            array('2020-01-01'),
            DateSeries::dates('2020-01-01', DateSeries::YEARLY, null, '2020-01-01')
        );
    }

    public function testASeriesThatHasNotBegunIsEmpty(): void
    {
        $this->assertSame(array(), DateSeries::dates('2020-01-01', DateSeries::YEARLY, null, '2019-12-31'));
    }

    public function testOnlyTheLatestDaysAreKept(): void
    {
        $this->assertSame(
            array('2024-03-01', '2025-03-01'),
            DateSeries::dates('2000-03-01', DateSeries::YEARLY, 2, '2026-01-15')
        );
        $this->assertCount(
            DateSeries::DEFAULT_MAXIMUM,
            DateSeries::dates('1990-01-01', DateSeries::YEARLY, null, '2026-01-15')
        );
        $this->assertCount(
            DateSeries::HIGHEST_MAXIMUM,
            DateSeries::dates('1990-01-01', DateSeries::WEEKLY, 100000, '2026-01-15')
        );
    }

    public function testAMonthlySeriesCrossesTheYear(): void
    {
        $this->assertSame(
            array('2020-11-15', '2020-12-15', '2021-01-15', '2021-02-15'),
            DateSeries::dates('2020-11-15', DateSeries::MONTHLY, null, '2021-03-14')
        );
    }

    public function testAMonthlySeriesFromTheThirtyFirstUsesTheLastDayOfAShorterMonth(): void
    {
        $this->assertSame(
            array('2020-01-31', '2020-02-29', '2020-03-31', '2020-04-30', '2020-05-31'),
            DateSeries::dates('2020-01-31', DateSeries::MONTHLY, null, '2020-06-29')
        );
        $this->assertSame(
            array('2021-01-31', '2021-02-28', '2021-03-31'),
            DateSeries::dates('2021-01-31', DateSeries::MONTHLY, null, '2021-03-31')
        );
        $this->assertSame(
            array('2021-01-31', '2021-02-28'),
            DateSeries::dates('2021-01-31', DateSeries::MONTHLY, null, '2021-03-30'),
            'the 30th of March is the day before the March day of the series'
        );
    }

    public function testAYearlySeriesFromTheLeapDayUsesTheTwentyEighthInOtherYears(): void
    {
        $this->assertSame(
            array('2020-02-29', '2021-02-28', '2022-02-28', '2023-02-28', '2024-02-29'),
            DateSeries::dates('2020-02-29', DateSeries::YEARLY, null, '2024-02-29')
        );
    }

    public function testAWeeklySeriesStepsSevenDays(): void
    {
        $this->assertSame(
            array('2024-02-19', '2024-02-26', '2024-03-04'),
            DateSeries::dates('2024-02-19', DateSeries::WEEKLY, null, '2024-03-10')
        );
        $this->assertSame(
            array('2025-12-29', '2026-01-05'),
            DateSeries::dates('1999-01-04', DateSeries::WEEKLY, 2, '2026-01-11'),
            'a long series is counted back from its latest day, and stays on its weekday'
        );
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public static function nothing(): array
    {
        return array(
            array('2020-01-01', '', '2022-01-01'),
            array('2020-01-01', 'daily', '2022-01-01'),
            array('not a day', DateSeries::YEARLY, '2022-01-01'),
            array('2020-02-30', DateSeries::YEARLY, '2022-01-01'),
            array('2020-01-01', DateSeries::YEARLY, 'not a day')
        );
    }

    /**
     * @dataProvider nothing
     */
    public function testWhatIsNotASeriesHasNoDays(string $first, string $interval, string $until): void
    {
        $this->assertSame(array(), DateSeries::dates($first, $interval, null, $until));
    }

    public function testTheValuesOfTheFormAndTheDatabaseAreRead(): void
    {
        $this->assertSame(DateSeries::YEARLY, DateSeries::toInterval('yearly'));
        $this->assertSame(DateSeries::NONE, DateSeries::toInterval('Yearly'));
        $this->assertSame(DateSeries::NONE, DateSeries::toInterval(null));

        $this->assertSame(7, DateSeries::toMaximum('7'));
        $this->assertNull(DateSeries::toMaximum(''));
        $this->assertNull(DateSeries::toMaximum('0'));
        $this->assertNull(DateSeries::toMaximum(null));
        $this->assertSame(DateSeries::HIGHEST_MAXIMUM, DateSeries::toMaximum(1000));
    }
}
