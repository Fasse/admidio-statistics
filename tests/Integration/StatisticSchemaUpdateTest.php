<?php
/**
 * The update from version 4.0 to 4.1, against a database that holds the tables of 4.0.
 *
 * install.sql creates the tables in their newest shape, so on its own it says nothing about an
 * installation that already has them. This test builds the 4.0 tables - install.sql without what 4.1
 * added - and then runs the steps of update_4_1.xml the way Admidio does: a step marked error="ignore"
 * may fail, every other step may not. It runs them twice, because an update that was interrupted is
 * started again from a step that may already have been done.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Integration;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\AdministratorTestCase;
use AdmidioPlugin\Statistics\Entity\Statistic;
use AdmidioPlugin\Statistics\Entity\StatisticColumn;
use AdmidioPlugin\Statistics\Entity\StatisticRow;
use AdmidioPlugin\Statistics\Service\StatisticCache;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

final class StatisticSchemaUpdateTest extends AdministratorTestCase
{
    private const ADDED_COLUMNS = array(
        'stc_role',
        'stc_ref_date',
        'stc_repeat',
        'stc_repeat_max',
        'str_role',
        'str_ref_date',
        'str_repeat',
        'str_repeat_max',
        'sta_overview'
    );

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::runScript('uninstall.sql');

        foreach (self::statementsOfVersion40() as $sql) {
            self::$gDb->queryPrepared($sql);
        }

        self::runUpdate();
        self::runUpdate();
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
     * install.sql as it was in version 4.0: without the nine columns and without the tables of the
     * excluded roles and of the cache.
     *
     * @return array<int,string>
     */
    private static function statementsOfVersion40(): array
    {
        $statements = array();

        foreach (Database::getSqlStatementsFromSqlFile(dirname(__DIR__, 2) . '/db_scripts/install.sql') as $sql) {
            if (str_contains($sql, '_statistics_excluded_roles') || str_contains($sql, '_statistics_cache')) {
                continue;
            }

            $lines = array_filter(
                explode("\n", $sql),
                static function (string $line): bool {
                    foreach (self::ADDED_COLUMNS as $column) {
                        if (preg_match('/^\s*' . $column . '\s/', $line) === 1) {
                            return false;
                        }
                    }

                    return true;
                }
            );

            $statements[] = implode("\n", $lines);
        }

        return $statements;
    }

    private static function runUpdate(): void
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2) . '/db_scripts/update_4_1.xml');

        foreach ($xml->step as $step) {
            $sql = trim((string)$step);
            if ($sql === 'stop') {
                break;
            }

            $sql = str_replace('%PREFIX%', TABLE_PREFIX, $sql);

            if ((string)$step['error'] === 'ignore') {
                // Admidio runs such a step without letting its failure stop the update.
                self::$gDb->queryPrepared($sql, array(), false);
            } else {
                self::$gDb->queryPrepared($sql);
            }
        }
    }

    public function testTheTablesOfVersion40WereBuiltWithoutTheNewColumns(): void
    {
        foreach (self::statementsOfVersion40() as $sql) {
            foreach (self::ADDED_COLUMNS as $column) {
                $this->assertStringNotContainsString($column, $sql);
            }
        }
    }

    public function testTheUpdateAddsTheColumns(): void
    {
        $columns = $this->getDatabase()->getTableColumns(StatisticColumn::table());
        $this->assertContains('stc_role', $columns);
        $this->assertContains('stc_ref_date', $columns);
        $this->assertContains('stc_repeat', $columns);
        $this->assertContains('stc_repeat_max', $columns);

        $rows = $this->getDatabase()->getTableColumns(StatisticRow::table());
        $this->assertContains('str_role', $rows);
        $this->assertContains('str_ref_date', $rows);
        $this->assertContains('str_repeat', $rows);
        $this->assertContains('str_repeat_max', $rows);

        $this->assertContains('sta_overview', $this->getDatabase()->getTableColumns(Statistic::table()));
        $this->assertContains('sca_key', $this->getDatabase()->getTableColumns(StatisticCache::table()));
    }

    /**
     * The proof that the updated schema is the one the code expects: a statistic that uses everything
     * 4.1 added is saved and read back.
     */
    public function testAStatisticOfVersion41IsStoredInTheUpdatedTables(): void
    {
        global $gCurrentOrgId;

        $roleId = (int)$this->getDatabase()
            ->queryPrepared('SELECT MIN(rol_id) FROM ' . TBL_ROLES)
            ->fetchColumn();
        $this->assertGreaterThan(0, $roleId);

        $table = new TableDefinition(
            '',
            null,
            '',
            array(new ColumnDefinition('', null, '', StatisticFunction::COUNT, null, '', null, '2020-01-01')),
            array(new RowDefinition('', null, '', $roleId))
        );

        $repository = new StatisticRepository($this->getDatabase());
        $id = $repository->save(
            new StatisticDefinition(null, (int)$gCurrentOrgId, 'Updated', '', '', $roleId, array($table), array($roleId))
        );
        $read = $repository->find($id, (int)$gCurrentOrgId);

        $this->assertSame(array($roleId), $read->excludedRoleIds);
        $this->assertSame('2020-01-01', $read->tables[0]->columns[0]->referenceDate);
        $this->assertSame($roleId, $read->tables[0]->rows[0]->roleId);
    }
}
