<?php
/**
 * Creating the example statistics.
 *
 * The first test is the regression test for the fault this action had when it was first written: it
 * took the standard role out of the editor's form, so asking for the examples on a form that had not
 * been filled in refused with "Field Standard role of statistics is empty". The examples are their own
 * statistics and find a role for themselves.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Integration;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Database;
use Admidio\Tests\Support\AdministratorTestCase;
use AdmidioPlugin\Statistics\Service\StatisticFormService;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\Service\StatisticTemplates;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;

final class StatisticTemplatesTest extends AdministratorTestCase
{
    private StatisticRepository $repository;
    private int $orgId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::runScript('uninstall.sql');
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

    protected function setUp(): void
    {
        parent::setUp();

        global $gCurrentOrgId;

        $this->orgId = (int)$gCurrentOrgId;
        $this->repository = new StatisticRepository($this->getDatabase());
    }

    /**
     * @return array<int,StatisticDefinition>
     */
    private function readAll(): array
    {
        $definitions = array();
        foreach ($this->repository->findAllForOrganization($this->orgId) as $listed) {
            $definition = $this->repository->find($listed['id'], $this->orgId);
            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * The regression test: no role is given, and the examples are created all the same.
     */
    public function testTheExamplesAreCreatedWithoutBeingGivenARole(): void
    {
        $created = StatisticTemplates::create($this->repository, $this->orgId);

        $this->assertSame(2, $created);
        $this->assertCount(2, $this->readAll());
    }

    public function testEveryExampleCountsARoleThatExists(): void
    {
        StatisticTemplates::create($this->repository, $this->orgId);

        foreach ($this->readAll() as $definition) {
            $this->assertGreaterThan(0, $definition->standardRoleId, $definition->name);

            $exists = (int)$this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_ROLES . ' WHERE rol_id = ?',
                array($definition->standardRoleId)
            )->fetchColumn();
            $this->assertSame(1, $exists, $definition->name . ' counts a role that is there');
        }
    }

    public function testTheRoleOfTheEditorIsUsedWhenThereIsOne(): void
    {
        $roleId = StatisticFormService::firstSelectableRoleId();
        $this->assertNotNull($roleId);

        StatisticTemplates::create($this->repository, $this->orgId, $roleId);

        foreach ($this->readAll() as $definition) {
            $this->assertSame($roleId, $definition->standardRoleId);
        }
    }

    /**
     * Version 3 named the profile fields of its examples by the numbers 1 to 7, 10 and 11, which are
     * only the right fields on an installation whose profile fields are still in their original order.
     */
    public function testTheProfileFieldsAreResolvedAndNotHardcoded(): void
    {
        global $gProfileFields;

        StatisticTemplates::create($this->repository, $this->orgId);

        $birthdayId = (int)$gProfileFields->getProperty('BIRTHDAY', 'usf_id');
        $lastNameId = (int)$gProfileFields->getProperty('LAST_NAME', 'usf_id');
        $this->assertGreaterThan(0, $birthdayId);
        $this->assertGreaterThan(0, $lastNameId);

        $fieldIds = array();
        foreach ($this->readAll() as $definition) {
            foreach ($definition->tables as $table) {
                foreach ($table->rows as $row) {
                    if ($row->profileFieldId !== null) {
                        $fieldIds[] = $row->profileFieldId;
                    }
                }
            }
        }

        $this->assertContains($birthdayId, $fieldIds, 'the age statistic names the birthday field');
        $this->assertContains($lastNameId, $fieldIds, 'the completeness statistic names the last name field');

        // Every field named is one this installation actually has.
        foreach (array_unique($fieldIds) as $fieldId) {
            $exists = (int)$this->getDatabase()->queryPrepared(
                'SELECT COUNT(*) FROM ' . TBL_USER_FIELDS . ' WHERE usf_id = ?',
                array($fieldId)
            )->fetchColumn();
            $this->assertSame(1, $exists, 'profile field ' . $fieldId . ' exists');
        }
    }

    /**
     * The labels of version 3 were written in whatever language the installation ran in and stayed that
     * way. These are translated when the example is created, so none of them is a raw key.
     */
    public function testTheLabelsAreTranslatedAndNotLanguageKeys(): void
    {
        StatisticTemplates::create($this->repository, $this->orgId);

        foreach ($this->readAll() as $definition) {
            $this->assertStringNotContainsString('PLG_', $definition->name, 'the name is translated');
            $this->assertStringNotContainsString('SYS_', $definition->name);
            $this->assertNotSame('', $definition->name);

            foreach ($definition->tables as $table) {
                $this->assertStringNotContainsString('PLG_', $table->title);

                foreach ($table->rows as $row) {
                    $this->assertStringNotContainsString('PLG_', $row->label);
                    $this->assertStringNotContainsString('SYS_', $row->label);
                }
            }
        }
    }

    /**
     * The age statistic groups the members into bands, and the condition that does it has to survive
     * the round trip through the database as the parser needs to read it.
     */
    public function testTheAgeBandsComeBackAsConditions(): void
    {
        StatisticTemplates::create($this->repository, $this->orgId);

        $conditions = array();
        foreach ($this->readAll() as $definition) {
            foreach ($definition->tables as $table) {
                foreach ($table->rows as $row) {
                    $conditions[] = $row->condition;
                }
            }
        }

        $this->assertContains('>=0j AND <=6j', $conditions);
        $this->assertContains('>=61j', $conditions);
    }

    /**
     * Asking twice gives two sets, which is the honest outcome: the examples are ordinary statistics and
     * nothing claims a name for itself.
     */
    public function testAskingTwiceCreatesTwoSets(): void
    {
        StatisticTemplates::create($this->repository, $this->orgId);
        StatisticTemplates::create($this->repository, $this->orgId);

        $this->assertCount(4, $this->readAll());
    }

    public function testTheExamplesHaveRowsAndColumnsToShow(): void
    {
        StatisticTemplates::create($this->repository, $this->orgId);

        foreach ($this->readAll() as $definition) {
            $this->assertNotEmpty($definition->tables, $definition->name);

            foreach ($definition->tables as $table) {
                $this->assertNotEmpty($table->rows, $definition->name . ' has rows');
                $this->assertNotEmpty($table->columns, $definition->name . ' has columns');

                $labels = array_map(
                    static fn(RowDefinition $row): string => $row->label,
                    $table->rows
                );
                $this->assertSame($labels, array_values($labels));
            }
        }
    }
}
