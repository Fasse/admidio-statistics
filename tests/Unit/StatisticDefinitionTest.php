<?php
/**
 * The value objects a statistic is made of.
 *
 * The first test here is the regression test for the defect that made version 3 write a statistic's
 * name into an integer column: its constructor took six positional values and one call site passed the
 * name where the organization belonged.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\DateSeries;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;
use PHPUnit\Framework\TestCase;
use TypeError;

final class StatisticDefinitionTest extends TestCase
{
    public function testARecordIsReadByColumnNameIntoTheRightProperty(): void
    {
        $definition = StatisticDefinition::fromRecord(array(
            'sta_id' => '7',
            'sta_org_id' => '3',
            'sta_name' => 'Age groups',
            'sta_title' => 'Our members',
            'sta_subtitle' => 'by age',
            'sta_std_role' => '12'
        ), array());

        $this->assertSame(7, $definition->id);
        $this->assertSame(3, $definition->orgId, 'sta_org_id is the organization');
        $this->assertSame('Age groups', $definition->name, 'sta_name is the name');
        $this->assertSame('Our members', $definition->title);
        $this->assertSame('by age', $definition->subtitle);
        $this->assertSame(12, $definition->standardRoleId);
        $this->assertFalse($definition->isNew());
    }

    /**
     * The organization is an int and the name is a string, so the mix-up version 3 made is not a silent
     * coercion any more.
     */
    public function testTheOrganizationCannotBeGivenAName(): void
    {
        $this->expectException(TypeError::class);

        /** @phpstan-ignore-next-line the wrong type is the point of this test */
        new StatisticDefinition(null, 'Age groups', 'Age groups');
    }

    public function testTheExcludedRolesAndTheOverviewMarkAreRead(): void
    {
        $record = array('sta_id' => '7', 'sta_org_id' => '3', 'sta_std_role' => '12');

        $definition = StatisticDefinition::fromRecord($record + array('sta_overview' => '1'), array(), array(5, 9));
        $this->assertSame(array(5, 9), $definition->excludedRoleIds);
        $this->assertTrue($definition->showOnOverview);

        $plain = StatisticDefinition::fromRecord($record, array());
        $this->assertSame(array(), $plain->excludedRoleIds);
        $this->assertFalse($plain->showOnOverview, 'a record of version 4.0 has no mark');
    }

    /**
     * The excluded roles come from a multi-select, so the list may repeat a role or carry an empty
     * entry. Each role is kept once, in the order it first appeared, and nothing else.
     */
    public function testEachExcludedRoleIsKeptOnceAndNothingThatIsNotARole(): void
    {
        $definition = new StatisticDefinition(null, 3, 'S', '', '', 12, array(), array(9, '5', 0, 9, -1, '', 5));

        $this->assertSame(array(9, 5), $definition->excludedRoleIds);
    }

    /**
     * A copy keeps the roles it leaves out, because they are part of what its figures mean, but it is
     * not shown on the overview page until somebody asks for that.
     */
    public function testACopyKeepsTheExcludedRolesButNotTheOverviewMark(): void
    {
        $original = new StatisticDefinition(7, 3, 'History', 'T', 'S', 12, array(TableDefinition::createEmpty()), array(5, 9), true);
        $copy = $original->asCopyNamed('History (copy)');

        $this->assertSame(array(5, 9), $copy->excludedRoleIds);
        $this->assertFalse($copy->showOnOverview);
        $this->assertTrue($original->showOnOverview, 'the original is untouched');
    }

    public function testTheRoleTheDayAndTheRepetitionOfARowAndAColumnAreRead(): void
    {
        $row = RowDefinition::fromRecord(array(
            'str_label' => '',
            'str_role' => '15',
            'str_ref_date' => '2020-01-01',
            'str_repeat' => DateSeries::MONTHLY,
            'str_repeat_max' => '24'
        ), '');

        $this->assertSame(15, $row->roleId);
        $this->assertSame('2020-01-01', $row->referenceDate);
        $this->assertSame(DateSeries::MONTHLY, $row->repeat);
        $this->assertSame(24, $row->repeatMax);
        $this->assertTrue($row->isSeries());

        $column = ColumnDefinition::fromRecord(array(
            'stc_role' => '16',
            'stc_ref_date' => '2021-02-28',
            'stc_repeat' => DateSeries::YEARLY,
            'stc_repeat_max' => null
        ), '');

        $this->assertSame(16, $column->roleId);
        $this->assertSame('2021-02-28', $column->referenceDate);
        $this->assertSame(DateSeries::YEARLY, $column->repeat);
        $this->assertNull($column->repeatMax);
        $this->assertTrue($column->isSeries());

        // A record of version 4.0 has none of the four columns.
        $old = ColumnDefinition::fromRecord(array('stc_label' => 'Count'), '');
        $this->assertNull($old->roleId);
        $this->assertNull($old->referenceDate);
        $this->assertSame(DateSeries::NONE, $old->repeat);
        $this->assertFalse($old->isSeries());
    }

    /**
     * Only a real day of the calendar is a reference date; anything else means "the day the statistic
     * is shown for", so no other text reaches a query.
     */
    public function testOnlyARealDayIsAReferenceDate(): void
    {
        $this->assertSame('2024-02-29', RowDefinition::toDate('2024-02-29'));
        $this->assertSame('2024-02-29', RowDefinition::toDate(' 2024-02-29 '));

        foreach (array(null, '', '0000-00-00', '2023-02-29', '2024-13-01', '1.1.2024', '2024-01-01 00:00:00', "2024-01-01' OR 1=1") as $value) {
            $this->assertNull(RowDefinition::toDate($value), var_export($value, true));
        }
    }

    /**
     * A repetition without a day is no series; the editor refuses to save one, and the evaluator
     * treats it as a plain row.
     */
    public function testARepetitionWithoutADayIsNoSeries(): void
    {
        $this->assertFalse((new RowDefinition('', null, '', null, null, DateSeries::YEARLY))->isSeries());
        $this->assertFalse((new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', null, '2020-01-01'))->isSeries());
    }

    public function testAStatisticWithADayOnAnyRowOrColumnIsAHistory(): void
    {
        $plain = new TableDefinition('', null, '', array(new ColumnDefinition('Count')), array(new RowDefinition('All')));
        $datedRow = new TableDefinition('', null, '', array(new ColumnDefinition('Count')), array(new RowDefinition('', null, '', null, '2020-01-01')));
        $datedColumn = new TableDefinition('', null, '', array(new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', null, '2020-01-01')), array());

        $this->assertFalse((new StatisticDefinition(null, 3, 'S', '', '', 12, array($plain)))->usesReferenceDates());
        $this->assertTrue((new StatisticDefinition(null, 3, 'S', '', '', 12, array($plain, $datedRow)))->usesReferenceDates());
        $this->assertTrue((new StatisticDefinition(null, 3, 'S', '', '', 12, array($datedColumn)))->usesReferenceDates());

        $this->assertTrue($datedRow->rowsUseReferenceDates());
        $this->assertFalse($datedRow->columnsUseReferenceDates());
        $this->assertTrue($datedColumn->columnsUseReferenceDates());
        $this->assertFalse($datedColumn->rowsUseReferenceDates());
    }

    public function testAnEmptyStatisticHasOneTableWithOneColumnAndOneRow(): void
    {
        $definition = StatisticDefinition::createEmpty(4);

        $this->assertCount(1, $definition->tables);
        $this->assertCount(1, $definition->tables[0]->columns);
        $this->assertCount(1, $definition->tables[0]->rows);
        $this->assertSame(4, $definition->orgId);
        $this->assertTrue($definition->isNew(), 'it carries no id, because there is no scratch row');
    }

    public function testACopyIsANewRecordUnderANewName(): void
    {
        $original = new StatisticDefinition(7, 3, 'Age groups', 'T', 'S', 12, array(TableDefinition::createEmpty()));
        $copy = $original->asCopyNamed('Age groups (copy)');

        $this->assertNull($copy->id);
        $this->assertSame('Age groups (copy)', $copy->name);
        $this->assertSame($original->tables, $copy->tables);
        $this->assertSame('Age groups', $original->name, 'the original is untouched');
        $this->assertSame(7, $original->id);
    }

    /**
     * Version 3 removed an element with unset(), which left a hole in the keys while the editor read
     * them with a counter.
     */
    public function testTheArraysAreAlwaysIndexedFromZeroWithoutGaps(): void
    {
        $sparse = array(0 => new RowDefinition('a'), 2 => new RowDefinition('c'), 7 => new RowDefinition('h'));
        $table = new TableDefinition('t', null, '', array(), $sparse);

        $this->assertSame(array(0, 1, 2), array_keys($table->rows));
        $this->assertSame('c', $table->rows[1]->label, 'the order is kept');

        $definition = new StatisticDefinition(null, 1, '', '', '', 0, array(3 => $table));
        $this->assertSame(array(0), array_keys($definition->tables));
    }

    public function testARoleOfZeroMeansTheStandardRoleOfTheStatistic(): void
    {
        $withoutRole = TableDefinition::fromRecord(array('stt_role' => '0'), array(), array());
        $withRole = TableDefinition::fromRecord(array('stt_role' => '5'), array(), array());

        $this->assertNull($withoutRole->roleId);
        $this->assertSame(9, $withoutRole->effectiveRoleId(9));
        $this->assertSame(5, $withRole->roleId);
        $this->assertSame(5, $withRole->effectiveRoleId(9));
    }

    public function testAnEmptyProfileFieldMeansAllMembers(): void
    {
        $column = ColumnDefinition::fromRecord(array('stc_profile_field' => '', 'stc_function_arg' => '0'), '');

        $this->assertNull($column->profileFieldId);
        $this->assertNull($column->functionArgument);
    }

    /**
     * The column stores the empty string for "no total", so asking whether it is null - as version 3
     * did - answered yes for every column and put a total row under every table.
     */
    public function testOnlyAColumnThatNamesATotalHasOne(): void
    {
        $this->assertFalse(ColumnDefinition::fromRecord(array('stc_function_total' => ''), '')->hasTotal());
        $this->assertTrue(ColumnDefinition::fromRecord(array('stc_function_total' => 'sum'), '')->hasTotal());

        $withTotal = new TableDefinition('t', null, '', array(new ColumnDefinition('', null, '', '#', null, 'sum')), array());
        $without = new TableDefinition('t', null, '', array(new ColumnDefinition()), array());

        $this->assertTrue($withTotal->hasTotalRow());
        $this->assertFalse($without->hasTotalRow());
    }

    public function testAFunctionTheCodeDoesNotKnowBecomesACount(): void
    {
        $this->assertSame(
            StatisticFunction::COUNT,
            ColumnDefinition::fromRecord(array('stc_function_main' => 'nonsense'), '')->functionName
        );
        $this->assertSame(
            '',
            ColumnDefinition::fromRecord(array('stc_function_total' => 'nonsense'), '')->functionTotal
        );
    }

    public function testOnlyTheFourAggregatingFunctionsNeedAProfileField(): void
    {
        foreach (array('min', 'max', 'avg', 'sum') as $name) {
            $this->assertTrue(StatisticFunction::needsArgument($name), $name);
        }

        foreach (array('#', '%') as $name) {
            $this->assertFalse(StatisticFunction::needsArgument($name), $name);
        }
    }

    public function testNoTotalIsAValueOfTheTotalFunctionAndNotAMissingOne(): void
    {
        $this->assertTrue(StatisticFunction::isValidTotal(''));
        $this->assertFalse(StatisticFunction::isValidTotal('#'), 'counting is not a total');
        $this->assertFalse(StatisticFunction::isValid(''), 'a column always computes something');
    }
}
