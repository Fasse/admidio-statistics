<?php
/**
 * Reading the editor's form back into a definition.
 *
 * This is where version 3 was weakest: it read three hidden counters out of the request and looped
 * over them, with no ceiling at all. The names come from the form object here, so the request decides
 * neither how many tables there are nor what the fields are called.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Exception;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use AdmidioPlugin\Statistics\Service\StatisticFormService;
use AdmidioPlugin\Statistics\ValueObject\FieldNames;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;

final class StatisticFormServiceTest extends PluginTestCase
{
    /**
     * The names an editor registers for a statistic of the given shape.
     *
     * @param array<int,array{columns:int,rows:int}> $shape
     * @return array<int,string>
     */
    private function fieldNames(array $shape): array
    {
        $names = array(
            StatisticFormService::FIELD_ID,
            StatisticFormService::FIELD_NAME,
            StatisticFormService::FIELD_TITLE,
            StatisticFormService::FIELD_SUBTITLE,
            StatisticFormService::FIELD_STANDARD_ROLE
        );

        foreach ($shape as $table => $counts) {
            foreach (FieldNames::TABLE_PARTS as $part) {
                $names[] = FieldNames::table($table, $part);
            }
            for ($column = 0; $column < $counts['columns']; $column++) {
                foreach (FieldNames::COLUMN_PARTS as $part) {
                    $names[] = FieldNames::column($table, $column, $part);
                }
            }
            for ($row = 0; $row < $counts['rows']; $row++) {
                foreach (FieldNames::ROW_PARTS as $part) {
                    $names[] = FieldNames::row($table, $row, $part);
                }
            }
        }

        return $names;
    }

    /**
     * @return array<string,string>
     */
    private function values(): array
    {
        return array(
            StatisticFormService::FIELD_ID => '7',
            StatisticFormService::FIELD_NAME => '  Age groups  ',
            StatisticFormService::FIELD_TITLE => 'Our members',
            StatisticFormService::FIELD_SUBTITLE => 'by age',
            StatisticFormService::FIELD_STANDARD_ROLE => '12',
            'table0_title' => 'First table',
            'table0_role' => '0',
            'table0_first_column_label' => 'Group',
            'table0_column0_label' => 'Count',
            'table0_column0_profile_field' => '',
            'table0_column0_condition' => '',
            'table0_column0_func_arg' => '',
            'table0_column0_func_main' => '#',
            'table0_column0_func_total' => 'sum',
            'table0_column1_label' => 'Average age',
            'table0_column1_profile_field' => '3',
            'table0_column1_condition' => '>=18',
            'table0_column1_func_arg' => '3',
            'table0_column1_func_main' => 'avg',
            'table0_column1_func_total' => '',
            'table0_row0_label' => 'All',
            'table0_row0_profile_field' => '',
            'table0_row0_condition' => '',
            'table1_title' => 'Second table',
            'table1_role' => '5',
            'table1_first_column_label' => '',
            'table1_column0_label' => 'Percent',
            'table1_column0_profile_field' => '4',
            'table1_column0_condition' => 'VORHANDEN',
            'table1_column0_func_arg' => '',
            'table1_column0_func_main' => '%',
            'table1_column0_func_total' => '',
            'table1_row0_label' => 'Row one',
            'table1_row0_profile_field' => '9',
            'table1_row0_condition' => '<30',
            'table1_row1_label' => 'Row two',
            'table1_row1_profile_field' => '',
            'table1_row1_condition' => ''
        );
    }

    private function parse(): StatisticDefinition
    {
        $shape = array(0 => array('columns' => 2, 'rows' => 1), 1 => array('columns' => 1, 'rows' => 2));

        return StatisticFormService::fromFieldNames($this->fieldNames($shape), $this->values(), 42);
    }

    public function testTheMetadataIsRead(): void
    {
        $definition = $this->parse();

        $this->assertSame(7, $definition->id);
        $this->assertSame(42, $definition->orgId, 'the organization comes from the caller, not the request');
        $this->assertSame('Age groups', $definition->name, 'the name is trimmed');
        $this->assertSame('Our members', $definition->title);
        $this->assertSame('by age', $definition->subtitle);
        $this->assertSame(12, $definition->standardRoleId);
    }

    public function testTheShapeIsTheOneTheFormRegistered(): void
    {
        $definition = $this->parse();

        $this->assertCount(2, $definition->tables);
        $this->assertSame('First table', $definition->tables[0]->title);
        $this->assertSame('Second table', $definition->tables[1]->title);
        $this->assertCount(2, $definition->tables[0]->columns);
        $this->assertCount(1, $definition->tables[0]->rows);
        $this->assertCount(1, $definition->tables[1]->columns);
        $this->assertCount(2, $definition->tables[1]->rows);
    }

    public function testTheColumnsAndRowsAreRead(): void
    {
        $column = $this->parse()->tables[0]->columns[1];

        $this->assertSame('Average age', $column->label);
        $this->assertSame(3, $column->profileFieldId);
        $this->assertSame('>=18', $column->condition, 'the condition is kept as the user typed it');
        $this->assertSame('avg', $column->functionName);
        $this->assertSame(3, $column->functionArgument);
        $this->assertFalse($column->hasTotal());

        $rows = $this->parse()->tables[1]->rows;
        $this->assertSame('Row one', $rows[0]->label);
        $this->assertSame(9, $rows[0]->profileFieldId);
        $this->assertSame('<30', $rows[0]->condition);
        $this->assertNull($rows[1]->profileFieldId, 'an empty selection means all members');
    }

    public function testATableWithoutItsOwnRoleUsesTheStandardRole(): void
    {
        $definition = $this->parse();

        $this->assertNull($definition->tables[0]->roleId);
        $this->assertSame(5, $definition->tables[1]->roleId);
    }

    /**
     * The second line of defence. FormPresenter::validate() refuses a key the form does not know before
     * this is reached, but a name that is not in the list is ignored here as well.
     */
    public function testAFieldTheFormNeverRegisteredIsIgnored(): void
    {
        $shape = array(0 => array('columns' => 2, 'rows' => 1), 1 => array('columns' => 1, 'rows' => 2));
        $values = $this->values();
        $values['table99_column99_label'] = 'injected';
        $values['table0_column9_label'] = 'injected';
        $values['table0_column0_evil'] = 'injected';

        $definition = StatisticFormService::fromFieldNames($this->fieldNames($shape), $values, 42);

        $this->assertCount(2, $definition->tables);
        $this->assertCount(2, $definition->tables[0]->columns);
    }

    public function testAnEmptyIdMeansANewStatistic(): void
    {
        $shape = array(0 => array('columns' => 1, 'rows' => 1));
        $values = $this->values();
        $values[StatisticFormService::FIELD_ID] = '';

        $this->assertTrue(StatisticFormService::fromFieldNames($this->fieldNames($shape), $values, 42)->isNew());
    }

    /**
     * @dataProvider targets
     */
    public function testTheStructureTargetIsRead(string $target, int $table, int $element): void
    {
        $this->assertSame(array($table, $element), StatisticFormService::parseTarget($target));
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public static function targets(): array
    {
        return array(
            array('3', 3, -1),
            array('3:7', 3, 7),
            array('0:0', 0, 0),
            array('12:34', 12, 34)
        );
    }

    /**
     * @dataProvider badTargets
     */
    public function testATargetOfAnotherShapeIsRefused(string $target): void
    {
        $this->expectException(Exception::class);
        StatisticFormService::parseTarget($target);
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function badTargets(): array
    {
        return array(
            array(''),
            array('-1'),
            array('1:'),
            array(':2'),
            array('01'),
            array('1:2:3'),
            array('a'),
            array('1;2'),
            array('1 OR 1=1')
        );
    }

    public function testAStatisticCannotBeSavedWithoutANameOrARole(): void
    {
        $withoutName = new StatisticDefinition(null, 1, '', '', '', 5);
        $withoutRole = new StatisticDefinition(null, 1, 'Name', '', '', 0);

        foreach (array($withoutName, $withoutRole) as $definition) {
            try {
                StatisticFormService::assertSaveable($definition);
                $this->fail('a statistic without a name or a role was accepted');
            } catch (Exception $exception) {
                $this->assertSame('SYS_FIELD_EMPTY', $exception->getTranslationId());
            }
        }
    }

    public function testACompleteStatisticIsSaveable(): void
    {
        StatisticFormService::assertSaveable(new StatisticDefinition(null, 1, 'Name', '', '', 5));

        $this->expectNotToPerformAssertions();
    }
}
