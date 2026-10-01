<?php
/**
 * Reading and writing a statistic, against the real database.
 *
 * Three of version 3's defects are what most of these tests are about: it looked a statistic up by its
 * id alone, so any organization could read and delete another's; it saved by updating the statistic,
 * deleting all of its tables and inserting them again with no transaction around any of it; and it
 * asked for the largest id in the table instead of the one the insert had produced.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Integration;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Tests\Support\AdministratorTestCase;
use AdmidioPlugin\Statistics\Entity\Statistic;
use AdmidioPlugin\Statistics\Entity\StatisticColumn;
use AdmidioPlugin\Statistics\Entity\StatisticRow;
use AdmidioPlugin\Statistics\Entity\StatisticTable;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

final class StatisticRepositoryTest extends AdministratorTestCase
{
    private StatisticRepository $repository;
    private int $orgId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Running both proves that install.sql parses and that it is repeatable.
        self::runScript('uninstall.sql');
        self::runScript('install.sql');
        self::runScript('install.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::runScript('uninstall.sql');

        parent::tearDownAfterClass();
    }

    private static function runScript(string $file): void
    {
        foreach (Database::getSqlStatementsFromSqlFile(dirname(__DIR__, 2) . '/db_scripts/' . $file) as $sql) {
            self::$gDb->queryPrepared($sql);
        }
    }

    /**
     * The statements of install.sql that clear up after version 3, and only those.
     *
     * The rest of the file creates tables, and a statement that changes the schema commits whatever
     * transaction is open - which in a test is the one the base class rolls back afterwards.
     *
     * @return array<int,string>
     */
    private function migrationStatements(): array
    {
        $statements = array();
        foreach (Database::getSqlStatementsFromSqlFile(dirname(__DIR__, 2) . '/db_scripts/install.sql') as $sql) {
            $code = trim((string)preg_replace('#/\*.*?\*/#s', '', $sql));

            if (stripos($code, 'DELETE') === 0) {
                $statements[] = $code;
            }
        }

        $this->assertCount(3, $statements, 'install.sql carries the three statements that clear up');

        return $statements;
    }

    protected function setUp(): void
    {
        parent::setUp();

        global $gCurrentOrgId;

        $this->orgId = (int)$gCurrentOrgId;
        $this->repository = new StatisticRepository($this->getDatabase());
    }

    /**
     * A statistic with two tables, conditions that contain both comparison characters, and a column
     * that declares a total.
     */
    private function definition(?int $id = null, string $name = 'Age groups'): StatisticDefinition
    {
        $first = new TableDefinition(
            'Members by age',
            null,
            'Age group',
            array(
                new ColumnDefinition('Count', null, '', StatisticFunction::COUNT, null, StatisticFunction::SUM),
                new ColumnDefinition('Average', 3, '>=18 AND <=65', StatisticFunction::AVERAGE, 3, '')
            ),
            array(
                new RowDefinition('Young', 3, '<30'),
                new RowDefinition('Old', 3, '>=30')
            )
        );

        $second = new TableDefinition(
            'A second table',
            7,
            '',
            array(new ColumnDefinition('Share', null, '', StatisticFunction::PERCENT)),
            array(new RowDefinition('All', null, ''))
        );

        return new StatisticDefinition($id, $this->orgId, $name, 'Our members', 'by age', 12, array($first, $second));
    }

    /**
     * @param string $table
     * @param string $column
     * @return int
     */
    private function countRows(string $table, string $column, int $value): int
    {
        return (int)$this->getDatabase()
            ->queryPrepared('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?', array($value))
            ->fetchColumn();
    }

    public function testADefinitionSurvivesTheRoundTrip(): void
    {
        $id = $this->repository->save($this->definition());
        $read = $this->repository->find($id, $this->orgId);

        $this->assertInstanceOf(StatisticDefinition::class, $read);
        $this->assertSame($id, $read->id);
        $this->assertSame($this->orgId, $read->orgId);
        $this->assertSame('Age groups', $read->name);
        $this->assertSame('Our members', $read->title);
        $this->assertSame('by age', $read->subtitle);
        $this->assertSame(12, $read->standardRoleId);

        $this->assertCount(2, $read->tables);
        $this->assertSame('Members by age', $read->tables[0]->title);
        $this->assertSame('Age group', $read->tables[0]->firstColumnLabel);
        $this->assertNull($read->tables[0]->roleId, 'no role of its own means the standard role');
        $this->assertSame(7, $read->tables[1]->roleId);
        $this->assertSame('A second table', $read->tables[1]->title);
    }

    /**
     * The comparison characters are stored as braces, so that the submitted value survives the tag
     * stripping, and they come back as the user typed them.
     */
    public function testAConditionComesBackAsItWasTyped(): void
    {
        $id = $this->repository->save($this->definition());
        $read = $this->repository->find($id, $this->orgId);

        $this->assertInstanceOf(StatisticDefinition::class, $read);
        $this->assertSame('>=18 AND <=65', $read->tables[0]->columns[1]->condition);
        $this->assertSame('<30', $read->tables[0]->rows[0]->condition);
        $this->assertSame('>=30', $read->tables[0]->rows[1]->condition);

        // And in the database they are stored in the encoded form the condition parser reads.
        $stored = $this->getDatabase()->queryPrepared(
            'SELECT str_field_condition FROM ' . StatisticRow::table() . ' WHERE str_label = ?',
            array('Young')
        )->fetchColumn();
        $this->assertSame('{30', $stored);
    }

    public function testTheColumnsAndRowsKeepTheirOrderAndTheirSettings(): void
    {
        $id = $this->repository->save($this->definition());
        $read = $this->repository->find($id, $this->orgId);

        $this->assertInstanceOf(StatisticDefinition::class, $read);
        $this->assertSame(array('Count', 'Average'), array_map(
            static fn(ColumnDefinition $column): string => $column->label,
            $read->tables[0]->columns
        ));
        $this->assertSame(array('Young', 'Old'), array_map(
            static fn(RowDefinition $row): string => $row->label,
            $read->tables[0]->rows
        ));

        $average = $read->tables[0]->columns[1];
        $this->assertSame(StatisticFunction::AVERAGE, $average->functionName);
        $this->assertSame(3, $average->functionArgument);
        $this->assertSame(3, $average->profileFieldId);
        $this->assertFalse($average->hasTotal());
        $this->assertTrue($read->tables[0]->columns[0]->hasTotal());
    }

    /**
     * The regression test for asking for the largest id in the table.
     */
    public function testEveryInsertReportsItsOwnId(): void
    {
        $first = $this->repository->save($this->definition(null, 'First'));
        $second = $this->repository->save($this->definition(null, 'Second'));

        $this->assertGreaterThan(0, $first);
        $this->assertNotSame($first, $second);

        $readFirst = $this->repository->find($first, $this->orgId);
        $this->assertInstanceOf(StatisticDefinition::class, $readFirst);
        $this->assertSame('First', $readFirst->name);
    }

    public function testSavingAgainReplacesTheTablesWithoutLeavingOrphans(): void
    {
        $id = $this->repository->save($this->definition());

        $before = $this->countRows(StatisticTable::table(), 'stt_sta_id', $id);
        $this->assertSame(2, $before);

        // The same statistic again, now with one table instead of two.
        $smaller = new StatisticDefinition(
            $id,
            $this->orgId,
            'Age groups',
            'Our members',
            'by age',
            12,
            array(new TableDefinition('Only one', null, '', array(new ColumnDefinition('C')), array(new RowDefinition('R'))))
        );
        $this->repository->save($smaller);

        $this->assertSame(1, $this->countRows(StatisticTable::table(), 'stt_sta_id', $id));

        $tableId = (int)$this->getDatabase()->queryPrepared(
            'SELECT stt_id FROM ' . StatisticTable::table() . ' WHERE stt_sta_id = ?',
            array($id)
        )->fetchColumn();

        $this->assertSame(1, $this->countRows(StatisticColumn::table(), 'stc_stt_id', $tableId));
        $this->assertSame(1, $this->countRows(StatisticRow::table(), 'str_stt_id', $tableId));

        // Nothing is left behind that belongs to a table that is gone.
        $orphanColumns = (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . StatisticColumn::table() . '
              WHERE stc_stt_id NOT IN (SELECT stt_id FROM ' . StatisticTable::table() . ')'
        )->fetchColumn();
        $this->assertSame(0, $orphanColumns);
    }

    public function testDeletingAStatisticTakesItsTablesColumnsAndRowsWithIt(): void
    {
        $id = $this->repository->save($this->definition());
        $tableId = (int)$this->getDatabase()->queryPrepared(
            'SELECT stt_id FROM ' . StatisticTable::table() . ' WHERE stt_sta_id = ? ORDER BY stt_id',
            array($id)
        )->fetchColumn();

        $this->repository->delete($id, $this->orgId);

        $this->assertNull($this->repository->find($id, $this->orgId));
        $this->assertSame(0, $this->countRows(StatisticTable::table(), 'stt_sta_id', $id));
        $this->assertSame(0, $this->countRows(StatisticColumn::table(), 'stc_stt_id', $tableId));
        $this->assertSame(0, $this->countRows(StatisticRow::table(), 'str_stt_id', $tableId));
    }

    /**
     * The regression test for looking a statistic up by its id alone.
     */
    public function testAStatisticOfAnotherOrganizationIsNotFound(): void
    {
        $id = $this->repository->save($this->definition());

        $this->assertNull($this->repository->find($id, $this->orgId + 1000));
    }

    public function testAStatisticOfAnotherOrganizationCannotBeDeleted(): void
    {
        $id = $this->repository->save($this->definition());

        try {
            $this->repository->delete($id, $this->orgId + 1000);
            $this->fail('a statistic of another organization was deleted');
        } catch (Exception $exception) {
            $this->assertSame('SYS_INVALID_PAGE_VIEW', $exception->getTranslationId());
        }

        $this->assertInstanceOf(StatisticDefinition::class, $this->repository->find($id, $this->orgId));
    }

    public function testAStatisticOfAnotherOrganizationCannotBeOverwritten(): void
    {
        $id = $this->repository->save($this->definition());

        $this->expectException(Exception::class);
        $this->repository->save(new StatisticDefinition($id, $this->orgId + 1000, 'Hijacked', '', '', 1));
    }

    public function testTheListingNamesTheOrganizationAndIsSortedByName(): void
    {
        $this->repository->save($this->definition(null, 'Zebra'));
        $this->repository->save($this->definition(null, 'Apple'));

        $names = array_column($this->repository->findAllForOrganization($this->orgId), 'name');

        $this->assertContains('Apple', $names);
        $this->assertContains('Zebra', $names);
        $this->assertSame(array_values(array_unique($names)), $names);
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);

        $this->assertSame(array(), $this->repository->findAllForOrganization($this->orgId + 1000));
    }

    /**
     * The id of the statistic no longer carries a meaning of its own: version 3 reserved the first row
     * as a scratch pad and had to filter it out of every listing.
     */
    public function testNoIdIsReservedForAnythingElse(): void
    {
        $id = $this->repository->save($this->definition(null, 'Whatever its id is'));

        $listed = array_column($this->repository->findAllForOrganization($this->orgId), 'id');

        $this->assertContains($id, $listed);
    }

    /**
     * The regression test for saving without a transaction: the statistic is updated, its tables are
     * deleted and then inserted again, so a failure in the middle left it without any.
     */
    public function testAFailedSaveWritesNothingAtAll(): void
    {
        /*
         * Two tables, the second of which carries a title far longer than the column holds. The first
         * is written before the second fails, so if the save were not one transaction the first would
         * stay behind - which is exactly what version 3 did, having deleted the previous tables first.
         *
         * The rollback unwinds everything that is open, including the transaction the test harness put
         * around this test, so what the database held beforehand cannot be observed afterwards. What
         * can be observed is that the first table is not there either.
         */
        $broken = new StatisticDefinition(
            null,
            $this->orgId,
            'Half written',
            'Our members',
            'by age',
            12,
            array(
                new TableDefinition('Still fine', null, '', array(new ColumnDefinition('C')), array(new RowDefinition('R'))),
                new TableDefinition(str_repeat('x', 500), null, '', array(new ColumnDefinition('C')), array(new RowDefinition('R')))
            )
        );

        /*
         * MySQL only refuses a value that does not fit when it is told to. PostgreSQL always does, so
         * this says nothing there and the statement is allowed to fail.
         */
        $previousMode = null;
        try {
            $previousMode = (string)$this->getDatabase()->queryPrepared('SELECT @@SESSION.sql_mode')->fetchColumn();
            $this->getDatabase()->queryPrepared("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
        } catch (\Throwable) {
            $previousMode = null;
        }

        $failed = false;
        try {
            $this->repository->save($broken);
        } catch (\Throwable) {
            $failed = true;
        } finally {
            if ($previousMode !== null) {
                $this->getDatabase()->queryPrepared('SET SESSION sql_mode = ?', array($previousMode));
            }
        }

        $this->assertTrue($failed, 'the database refused the value that does not fit');

        $survivingTables = (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . StatisticTable::table() . ' WHERE stt_title = ?',
            array('Still fine')
        )->fetchColumn();
        $survivingStatistics = (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . Statistic::table() . ' WHERE sta_name = ?',
            array('Half written')
        )->fetchColumn();

        $this->assertSame(0, $survivingTables, 'the table that was written before the failure is gone');
        $this->assertSame(0, $survivingStatistics, 'and so is the statistic itself');
    }

    /**
     * install.sql removes the rows whose organization was lost to the swapped constructor arguments of
     * version 3, and it has to be able to run on a database that already holds the tables.
     */
    public function testTheInstallScriptClearsTheWreckageOfTheOldVersion(): void
    {
        $statistic = new Statistic($this->getDatabase());
        $statistic->setValue('sta_org_id', 0);
        $statistic->setValue('sta_name', 'Wreckage');
        $statistic->setValue('sta_std_role', 1);
        $statistic->save();

        $wreckageId = (int)$statistic->getValue('sta_id');
        $this->assertGreaterThan(0, $wreckageId);

        foreach ($this->migrationStatements() as $sql) {
            $this->getDatabase()->queryPrepared($sql);
        }

        $remaining = (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . Statistic::table() . ' WHERE sta_id = ?',
            array($wreckageId)
        )->fetchColumn();

        $this->assertSame(0, $remaining, 'a statistic that belongs to no organization is removed');
    }
}
