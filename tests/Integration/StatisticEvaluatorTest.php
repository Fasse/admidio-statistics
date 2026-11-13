<?php
/**
 * Computing a statistic against a real database, a real role and real profile values.
 *
 * The test that matters most here is the one about two conditions. A cell of a cross table counts the
 * members that meet the condition of its row **and** the condition of its column. Version 3 ran one
 * query per condition, appended every member id it got back into one list and then accepted an id that
 * appeared as often as there were conditions - but two of its three queries selected from a join
 * without DISTINCT, so a member with several matching rows in the profile data appeared repeatedly and
 * reached that count while meeting only one of the conditions.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Integration;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Users\Entity\User;
use AdmidioPlugin\Statistics\Service\StatisticEvaluator;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\DateSeries;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

final class StatisticEvaluatorTest extends AdministratorTestCase
{
    private const OPEN_END = '9999-12-31';

    private StatisticEvaluator $evaluator;
    private AdmidioTestFixture $fixture;
    private int $roleId;
    private int $lastNameId;
    private int $firstNameId;

    /** @var array<string,int> The members of the test role, by login name. */
    private array $userIds = array();

    protected function setUp(): void
    {
        parent::setUp();

        global $gCurrentOrgId, $gProfileFields, $gL10n;

        $this->fixture = new AdmidioTestFixture($this->getDatabase());
        $this->evaluator = new StatisticEvaluator($this->getDatabase(), $gProfileFields, $gL10n);

        $this->lastNameId = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');
        $this->firstNameId = (int)$gProfileFields->getProperty('FIRST_NAME', 'usf_id');
        $this->assertGreaterThan(0, $this->lastNameId);
        $this->assertGreaterThan(0, $this->firstNameId);

        $role = $this->fixture->createAndSaveRole('Statistics test role', (int)$gCurrentOrgId);
        $this->roleId = (int)$role['rol_id'];

        /*
         * Four members: two share a last name, two share a first name, one has neither. That is enough
         * to tell an intersection from a union.
         */
        $this->addMember('sta_one', 'Alpha', 'One');
        $this->addMember('sta_two', 'Alpha', 'Two');
        $this->addMember('sta_three', 'Beta', 'One');
        $this->addMember('sta_four', '', '');
    }

    private function addMember(string $login, string $lastName, string $firstName): int
    {
        global $gProfileFields;

        $created = $this->fixture->createAndSaveUser($login, $login . '@example.org');
        $userId = (int)$created['usr_id'];

        if ($lastName !== '' || $firstName !== '') {
            $user = new User($this->getDatabase(), $gProfileFields, $userId);
            if ($lastName !== '') {
                $user->setValue('LAST_NAME', $lastName);
            }
            if ($firstName !== '') {
                $user->setValue('FIRST_NAME', $firstName);
            }
            $user->save();
        }

        $this->fixture->assignUserToRolePeriod($userId, $this->roleId, '1970-01-01', self::OPEN_END);
        $this->userIds[$login] = $userId;

        return $userId;
    }

    /**
     * A second role with a history: one member who has left, one who joined later, one whose
     * membership is going to end and one whose membership has not begun.
     */
    private function createHistoryRole(): int
    {
        global $gCurrentOrgId;

        $roleId = (int)$this->fixture->createAndSaveRole('Statistics history role', (int)$gCurrentOrgId)['rol_id'];

        $nextYear = (new \DateTimeImmutable('today'))->add(new \DateInterval('P1Y'))->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable('today'))->add(new \DateInterval('P1D'))->format('Y-m-d');

        $this->fixture->assignUserToRolePeriod($this->userIds['sta_one'], $roleId, '2019-01-01', '2020-12-31');
        $this->fixture->assignUserToRolePeriod($this->userIds['sta_two'], $roleId, '2021-06-01', self::OPEN_END);
        $this->fixture->assignUserToRolePeriod($this->userIds['sta_three'], $roleId, '2019-01-01', $nextYear);
        $this->fixture->assignUserToRolePeriod($this->userIds['sta_four'], $roleId, $tomorrow, self::OPEN_END);

        return $roleId;
    }

    /**
     * @param array<int,RowDefinition> $rows
     * @param array<int,ColumnDefinition> $columns
     * @param array<int,int> $excludedRoleIds
     */
    private function computeResult(
        array $rows,
        array $columns,
        array $excludedRoleIds = array(),
        ?string $date = null
    ): \AdmidioPlugin\Statistics\ValueObject\StatisticResult {
        global $gCurrentOrgId;

        $definition = new StatisticDefinition(
            null,
            (int)$gCurrentOrgId,
            'Test',
            'Title',
            '',
            $this->roleId,
            array(new TableDefinition('T', null, 'Label', $columns, $rows)),
            $excludedRoleIds
        );

        return $this->evaluator->evaluate($definition, 'as of', $date);
    }

    private function dated(string $date): ColumnDefinition
    {
        return new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', null, $date);
    }

    public function testAColumnWithADayCountsTheMembersOfThatDay(): void
    {
        $history = $this->createHistoryRole();

        $table = $this->computeResult(
            array(new RowDefinition('', null, '', $history)),
            array(
                $this->dated('2018-12-31'),
                $this->dated('2020-01-01'),
                $this->dated('2020-12-31'),
                $this->dated('2021-01-01'),
                $this->dated('2022-01-01'),
                new ColumnDefinition('Today')
            )
        )->tables[0];

        $this->assertSame(
            array('Label', '2018-12-31', '2020-01-01', '2020-12-31', '2021-01-01', '2022-01-01', 'Today'),
            $table->headers,
            'a column without a label is named after its day'
        );
        $this->assertSame(
            array(array('Statistics history role', '0', '2', '2', '1', '2', '2')),
            $table->rows,
            'the last day of a membership counts, a membership that is going to end counts today, '
            . 'and one that has not begun does not'
        );
        $this->assertSame('', $table->caption, 'no cell counts the role of the table');
    }

    public function testARowWithADayWorksTheSameWay(): void
    {
        $history = $this->createHistoryRole();

        $table = $this->computeResult(
            array(new RowDefinition('', null, '', null, '2020-01-01'), new RowDefinition('Now')),
            array(new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', $history))
        )->tables[0];

        $this->assertSame(array('Label', 'Statistics history role'), $table->headers);
        $this->assertSame(array(array('2020-01-01', '2'), array('Now', '2')), $table->rows);
    }

    public function testARowWithoutARoleStillCountsTheRoleOfTheTable(): void
    {
        $history = $this->createHistoryRole();

        $table = $this->computeResult(
            array(new RowDefinition('History', null, '', $history), new RowDefinition('All')),
            array(new ColumnDefinition('Count'))
        )->tables[0];

        $this->assertSame(array(array('History', '2'), array('All', '4')), $table->rows);
        $this->assertStringContainsString('Statistics test role', $table->caption);
    }

    public function testARoleOnTheRowAndOnTheColumnCountsTheMembersOfBoth(): void
    {
        $history = $this->createHistoryRole();

        $table = $this->computeResult(
            array(new RowDefinition('Test role', null, '', $this->roleId)),
            array(
                new ColumnDefinition('History', null, '', StatisticFunction::COUNT, null, '', $history),
                new ColumnDefinition('Alone')
            )
        )->tables[0];

        $this->assertSame(array(array('Test role', '2', '4')), $table->rows);
    }

    public function testAConditionIsAppliedToTheMembersOfTheDay(): void
    {
        $history = $this->createHistoryRole();

        // On the first of January 2020 the role had sta_one (Alpha) and sta_three (Beta).
        $table = $this->computeResult(
            array(new RowDefinition('Alpha', $this->lastNameId, 'Alpha', $history)),
            array($this->dated('2020-01-01'), $this->dated('2022-01-01'))
        )->tables[0];

        $this->assertSame(array(array('Alpha', '1', '1')), $table->rows);
    }

    public function testTheMembersOfAnExcludedRoleAreLeftOutOnTheDaysTheyWereInIt(): void
    {
        global $gCurrentOrgId;

        $excluded = (int)$this->fixture->createAndSaveRole('Statistics excluded role', (int)$gCurrentOrgId)['rol_id'];
        $this->fixture->assignUserToRolePeriod($this->userIds['sta_one'], $excluded, '2021-01-01', self::OPEN_END);

        $result = $this->computeResult(
            array(new RowDefinition('All')),
            array(
                $this->dated('2020-01-01'),
                new ColumnDefinition('Today'),
                new ColumnDefinition('Share', null, '', StatisticFunction::PERCENT)
            ),
            array($excluded)
        );

        $this->assertSame(array('Statistics excluded role'), $result->excludedRoles);
        $this->assertSame(
            array(array('All', '4', '3', '100.0 %')),
            $result->tables[0]->rows,
            'the member is counted before joining the excluded role, and the percentage is taken of '
            . 'the members that are left'
        );
        $this->assertStringContainsString('3', $result->tables[0]->caption);
    }

    public function testAColumnThatRepeatsBecomesAColumnForEachDay(): void
    {
        $history = $this->createHistoryRole();

        $series = new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, StatisticFunction::SUM, null, '2019-01-01', DateSeries::YEARLY);

        $table = $this->computeResult(
            array(new RowDefinition('', null, '', $history)),
            array($series, new ColumnDefinition('Then')),
            array(),
            '2022-06-30'
        )->tables[0];

        $this->assertSame(
            array('Label', '2019-01-01', '2020-01-01', '2021-01-01', '2022-01-01', 'Then'),
            $table->headers,
            'one column for every first of January up to the day the statistic is shown for'
        );
        $this->assertSame(array(array('Statistics history role', '2', '2', '1', '2', '2')), $table->rows);
        $this->assertCount(6, $table->totalRow, 'every day of the series has the total of its column');
        $this->assertNotSame('', $table->totalRow[1]);
        $this->assertSame('', $table->totalRow[5]);
    }

    public function testASeriesShowsItsLatestDaysAndCarriesItsLabel(): void
    {
        $history = $this->createHistoryRole();

        $series = new ColumnDefinition('On', null, '', StatisticFunction::COUNT, null, '', null, '2019-01-01', DateSeries::YEARLY, 2);

        $table = $this->computeResult(
            array(new RowDefinition('', null, '', $history)),
            array($series),
            array(),
            '2022-06-30'
        )->tables[0];

        $this->assertSame(array('Label', 'On 2021-01-01', 'On 2022-01-01'), $table->headers);
        $this->assertSame(array(array('Statistics history role', '1', '2')), $table->rows);
    }

    public function testARowThatRepeatsBecomesARowForEachDay(): void
    {
        $history = $this->createHistoryRole();

        $table = $this->computeResult(
            array(new RowDefinition('', null, '', null, '2020-12-01', DateSeries::MONTHLY)),
            array(new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', $history)),
            array(),
            '2021-02-15'
        )->tables[0];

        $this->assertSame(
            array(array('2020-12-01', '2'), array('2021-01-01', '1'), array('2021-02-01', '1')),
            $table->rows
        );
    }

    public function testASeriesThatHasNotBegunContributesNothing(): void
    {
        $table = $this->computeResult(
            array(new RowDefinition('All')),
            array(
                new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', null, '2030-01-01', DateSeries::WEEKLY),
                new ColumnDefinition('Then')
            ),
            array(),
            '2022-06-30'
        )->tables[0];

        $this->assertSame(array('Label', 'Then'), $table->headers);
    }

    public function testAStatisticCanBeShownForAnotherDay(): void
    {
        $history = $this->createHistoryRole();

        $rows = array(new RowDefinition('', null, '', $history));
        $columns = array(new ColumnDefinition('Count'));

        $this->assertSame('2', $this->computeResult($rows, $columns, array(), '2020-06-01')->tables[0]->rows[0][1]);
        $this->assertSame('1', $this->computeResult($rows, $columns, array(), '2021-01-01')->tables[0]->rows[0][1]);
        $this->assertSame('2', $this->computeResult($rows, $columns)->tables[0]->rows[0][1], 'no day means today');
        $this->assertSame(
            '2',
            $this->computeResult($rows, $columns, array(), 'not a day')->tables[0]->rows[0][1],
            'and so does anything that is not a day'
        );
    }

    /**
     * @param array<int,RowDefinition> $rows
     * @param array<int,ColumnDefinition> $columns
     */
    private function compute(array $rows, array $columns): \AdmidioPlugin\Statistics\ValueObject\ResultTable
    {
        global $gCurrentOrgId;

        $definition = new StatisticDefinition(
            null,
            (int)$gCurrentOrgId,
            'Test',
            'Title',
            '',
            $this->roleId,
            array(new TableDefinition('T', null, 'Label', $columns, $rows))
        );

        $result = $this->evaluator->evaluate($definition, '2026-10-02');

        $this->assertCount(1, $result->tables);

        return $result->tables[0];
    }

    public function testTheRoleHasTheMembersTheTestCreated(): void
    {
        $this->assertSame(4, $this->evaluator->getUserCountFromRoleId($this->roleId));
        $this->assertSame('Statistics test role', $this->evaluator->getRoleNameFromId($this->roleId));
    }

    public function testACellWithoutAnyConditionCountsEveryMemberOfTheRole(): void
    {
        $table = $this->compute(
            array(new RowDefinition('All', null, '')),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertSame(array('Label', 'Count'), $table->headers);
        $this->assertSame(array(array('All', '4')), $table->rows);
        $this->assertNull($table->totalRow, 'no column declares a total');
    }

    public function testOneConditionCountsTheMembersThatMeetIt(): void
    {
        $table = $this->compute(
            array(new RowDefinition('Alpha', $this->lastNameId, 'Alpha')),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertSame(array(array('Alpha', '2')), $table->rows);
    }

    /**
     * The headline test: a member who meets the row condition but not the column condition is not
     * counted.
     */
    public function testTwoConditionsAreIntersectedAndNotUnioned(): void
    {
        $table = $this->compute(
            array(
                new RowDefinition('Alpha', $this->lastNameId, 'Alpha'),
                new RowDefinition('Beta', $this->lastNameId, 'Beta')
            ),
            array(
                new ColumnDefinition('One', $this->firstNameId, 'One', StatisticFunction::COUNT),
                new ColumnDefinition('Two', $this->firstNameId, 'Two', StatisticFunction::COUNT)
            )
        );

        // Alpha/One is one member, Alpha/Two is one, Beta/One is one, Beta/Two is nobody.
        $this->assertSame(
            array(
                array('Alpha', '1', '1'),
                array('Beta', '1', '0')
            ),
            $table->rows
        );
    }

    public function testTheMissingKeywordCountsTheMembersWithoutAValue(): void
    {
        global $gL10n;

        $table = $this->compute(
            array(new RowDefinition('No name', $this->lastNameId, $gL10n->get('PLG_STATISTICS_MISSING'))),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertSame(array(array('No name', '1')), $table->rows);
    }

    public function testTheAvailableKeywordCountsTheMembersWithAValue(): void
    {
        global $gL10n;

        $table = $this->compute(
            array(new RowDefinition('Has a name', $this->lastNameId, $gL10n->get('PLG_STATISTICS_AVAILABLE'))),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertSame(array(array('Has a name', '3')), $table->rows);
    }

    /**
     * The German words the plugin has always accepted still work, whatever the language of the
     * installation is.
     */
    public function testTheGermanKeywordsStillWork(): void
    {
        $missing = $this->compute(
            array(new RowDefinition('No name', $this->lastNameId, 'FEHLT')),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );
        $available = $this->compute(
            array(new RowDefinition('Has a name', $this->lastNameId, 'VORHANDEN')),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertSame(array(array('No name', '1')), $missing->rows);
        $this->assertSame(array(array('Has a name', '3')), $available->rows);
    }

    public function testAPercentageOfTheWholeRoleIsAHundredPercent(): void
    {
        $table = $this->compute(
            array(new RowDefinition('All', null, '')),
            array(new ColumnDefinition('Share', null, '', StatisticFunction::PERCENT))
        );

        $this->assertSame(array(array('All', '100.0 %')), $table->rows);
    }

    /**
     * Version 3 asked is_null() of a column that stores the empty string for "no total", so it put a
     * total row under every table and a value in every column of it.
     */
    public function testATotalRowAppearsOnlyForTheColumnsThatDeclareOne(): void
    {
        $table = $this->compute(
            array(
                new RowDefinition('Alpha', $this->lastNameId, 'Alpha'),
                new RowDefinition('Beta', $this->lastNameId, 'Beta')
            ),
            array(
                new ColumnDefinition('Totalled', null, '', StatisticFunction::COUNT, null, StatisticFunction::SUM),
                new ColumnDefinition('Not totalled', null, '', StatisticFunction::COUNT)
            )
        );

        $this->assertIsArray($table->totalRow);
        $this->assertCount(3, $table->totalRow);
        $this->assertNotSame('', $table->totalRow[1], 'the column that declares a total has one');
        $this->assertSame('', $table->totalRow[2], 'the column that does not stays empty');
    }

    public function testTheCaptionNamesTheRoleAndItsMemberCount(): void
    {
        $table = $this->compute(
            array(new RowDefinition('All', null, '')),
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT))
        );

        $this->assertStringContainsString('Statistics test role', $table->caption);
        $this->assertStringContainsString('4', $table->caption);
    }

    /**
     * A table that names no role of its own counts the standard role of the statistic.
     */
    public function testATableWithoutARoleUsesTheStandardRole(): void
    {
        global $gCurrentOrgId;

        $definition = new StatisticDefinition(
            null,
            (int)$gCurrentOrgId,
            'Test',
            'Title',
            '',
            $this->roleId,
            array(
                new TableDefinition('Own role', $this->roleId, '', array(new ColumnDefinition('C', null, '', '#')), array(new RowDefinition('All'))),
                new TableDefinition('Standard role', null, '', array(new ColumnDefinition('C', null, '', '#')), array(new RowDefinition('All')))
            )
        );

        $result = $this->evaluator->evaluate($definition, '2026-10-02');

        $this->assertCount(2, $result->tables);
        $this->assertSame($result->tables[0]->rows, $result->tables[1]->rows);
    }

    public function testTheResultCarriesTheHeadingsOfTheStatistic(): void
    {
        global $gCurrentOrgId;

        $definition = new StatisticDefinition(
            null,
            (int)$gCurrentOrgId,
            'Test',
            'Our members',
            'by name',
            $this->roleId,
            array(new TableDefinition('T', null, '', array(new ColumnDefinition('C', null, '', '#')), array(new RowDefinition('All'))))
        );

        $result = $this->evaluator->evaluate($definition, '2026-10-02');

        $this->assertSame('Our members', $result->title);
        $this->assertSame('by name', $result->subtitle);
        $this->assertSame('2026-10-02', $result->asOf);
    }
}
