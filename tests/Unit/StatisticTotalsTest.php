<?php
/**
 * The arithmetic of the evaluator that needs no database: the totals of a column and the ages it
 * computes from dates of birth.
 *
 * Three of version 3's defects are pinned down here. It seeded its minimum with 10000, so a column
 * whose figures were all larger reported 10000. It added up the formatted strings of the cells, so a
 * percentage column summed values such as "12.3 %" by string coercion. And it handed whatever it found
 * in a date field to explode(), so a value that is not a date became a nonsensical age.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Language;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use AdmidioPlugin\Statistics\Service\StatisticEvaluator;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * A language that gives the key and its parameters back, so that a test can see both.
 */
final class TotalsTestLanguage extends Language
{
    public function __construct()
    {
    }

    public function get(string $textId, array $params = array()): string
    {
        return $params === array() ? $textId : $textId . '(' . implode('|', $params) . ')';
    }
}

final class StatisticTotalsTest extends TestCase
{
    private StatisticEvaluator $evaluator;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        // Neither method under test reaches the database or the profile fields.
        $database = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        $profileFields = (new ReflectionClass(ProfileFields::class))->newInstanceWithoutConstructor();

        $this->evaluator = new StatisticEvaluator($database, $profileFields, new TotalsTestLanguage());
        $this->reflection = new ReflectionClass(StatisticEvaluator::class);
    }

    /**
     * @param array<int,float|null> $values
     */
    private function total(string $totalFunction, array $values, string $function = '#'): string
    {
        $method = $this->reflection->getMethod('formatTotal');
        $method->setAccessible(true);

        $column = new ColumnDefinition('', null, '', $function, null, $totalFunction);

        return (string)$method->invoke($this->evaluator, $column, $values);
    }

    /**
     * @param array<int,string> $dates
     * @return array<int,string>
     */
    private function ages(array $dates): array
    {
        $method = $this->reflection->getMethod('agesFromDates');
        $method->setAccessible(true);

        return (array)$method->invoke($this->evaluator, $dates);
    }

    /**
     * The regression test for the minimum that started at 10000.
     */
    public function testTheMinimumIsTheSmallestValueEvenWhenEveryValueIsLarge(): void
    {
        $values = array(20000.0, 30000.0, 40000.0);

        $this->assertSame('PLG_STATISTICS_TOTAL_MINIMUM(20000)', $this->total(StatisticFunction::MINIMUM, $values));
        $this->assertSame('PLG_STATISTICS_TOTAL_MAXIMUM(40000)', $this->total(StatisticFunction::MAXIMUM, $values));
    }

    public function testTheTotalsAreTranslatedAndNotHardcodedGlyphs(): void
    {
        $this->assertSame('PLG_STATISTICS_TOTAL_SUM(7)', $this->total(StatisticFunction::SUM, array(1.0, 2.0, 4.0)));
        $this->assertSame(
            'PLG_STATISTICS_TOTAL_AVERAGE(1.5)',
            $this->total(StatisticFunction::AVERAGE, array(1.0, 2.0))
        );
    }

    /**
     * The regression test for adding up the formatted strings of the cells.
     */
    public function testAPercentageTotalIsComputedFromTheNumbersAndKeepsItsUnit(): void
    {
        $this->assertSame(
            'PLG_STATISTICS_TOTAL_SUM(100 %)',
            $this->total(StatisticFunction::SUM, array(33.3, 33.3, 33.4), StatisticFunction::PERCENT)
        );
    }

    public function testAColumnWithNothingToTotalShowsNothing(): void
    {
        $this->assertSame('', $this->total(StatisticFunction::SUM, array()));
        $this->assertSame('', $this->total(StatisticFunction::SUM, array(null, null)));
    }

    /**
     * A cell that has no number - a column that reports the smallest of a set of texts, for instance -
     * does not count as a zero.
     */
    public function testCellsWithoutANumberAreLeftOutRatherThanCountedAsZero(): void
    {
        $this->assertSame(
            'PLG_STATISTICS_TOTAL_AVERAGE(4.0)',
            $this->total(StatisticFunction::AVERAGE, array(null, 4.0, null))
        );
    }

    public function testAnAgeIsCountedInWholeYears(): void
    {
        $today = new DateTimeImmutable('today');
        $twentyYearsAgo = $today->sub(new DateInterval('P20Y'));

        $this->assertSame(array('20'), $this->ages(array($twentyYearsAgo->format('Y-m-d'))));
        $this->assertSame(
            array('19'),
            $this->ages(array($twentyYearsAgo->add(new DateInterval('P1D'))->format('Y-m-d'))),
            'a birthday one day away is still the year before'
        );
    }

    /**
     * The regression test for handing anything at all to explode().
     */
    public function testAValueThatIsNotADateIsLeftOut(): void
    {
        $twenty = (new DateTimeImmutable('today'))->sub(new DateInterval('P20Y'))->format('Y-m-d');

        $this->assertSame(array('20'), $this->ages(array('not a date', $twenty)));
        $this->assertSame(array(), $this->ages(array()));
        $this->assertSame(array(), $this->ages(array('', '0000-00-00x')));
    }
}
