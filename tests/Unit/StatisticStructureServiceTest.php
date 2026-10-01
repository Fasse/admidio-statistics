<?php
/**
 * Adding, removing, duplicating and moving the parts of a statistic.
 *
 * Three of version 3's defects are what these tests pin down: it removed an element with unset() and
 * left the keys sparse, it indexed its arrays with the number out of the query string without checking
 * it, and it allowed the last row or column of a table to be deleted.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Exception;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use AdmidioPlugin\Statistics\Service\StatisticStructureService;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

final class StatisticStructureServiceTest extends PluginTestCase
{
    /**
     * One table with the rows a, b, c and the columns A, B, C.
     */
    private function definition(): StatisticDefinition
    {
        $rows = array(new RowDefinition('a'), new RowDefinition('b'), new RowDefinition('c'));
        $columns = array(new ColumnDefinition('A'), new ColumnDefinition('B'), new ColumnDefinition('C'));

        return new StatisticDefinition(
            1,
            1,
            'n',
            '',
            '',
            5,
            array(new TableDefinition('t0', null, '', $columns, $rows))
        );
    }

    /**
     * @return array<int,string>
     */
    private function rowLabels(StatisticDefinition $definition, int $table = 0): array
    {
        return array_map(
            static fn(RowDefinition $row): string => $row->label,
            $definition->tables[$table]->rows
        );
    }

    /**
     * @return array<int,string>
     */
    private function columnLabels(StatisticDefinition $definition, int $table = 0): array
    {
        return array_map(
            static fn(ColumnDefinition $column): string => $column->label,
            $definition->tables[$table]->columns
        );
    }

    public function testATableIsAddedWithOneColumnAndOneRow(): void
    {
        $result = StatisticStructureService::apply($this->definition(), StatisticStructureService::ADD_TABLE, 0);

        $this->assertCount(2, $result->tables);
        $this->assertCount(1, $result->tables[1]->columns);
        $this->assertCount(1, $result->tables[1]->rows);
    }

    public function testRowsAndColumnsAreAppended(): void
    {
        $withRow = StatisticStructureService::apply($this->definition(), StatisticStructureService::ADD_ROW, 0);
        $withColumn = StatisticStructureService::apply($this->definition(), StatisticStructureService::ADD_COLUMN, 0);

        $this->assertSame(array('a', 'b', 'c', ''), $this->rowLabels($withRow));
        $this->assertSame(array('A', 'B', 'C', ''), $this->columnLabels($withColumn));
    }

    /**
     * The regression test for the sparse keys.
     */
    public function testRemovingAnElementInTheMiddleLeavesTheKeysContiguous(): void
    {
        $result = StatisticStructureService::apply($this->definition(), StatisticStructureService::DELETE_ROW, 0, 1);

        $this->assertSame(array('a', 'c'), $this->rowLabels($result));
        $this->assertSame(array(0, 1), array_keys($result->tables[0]->rows));

        $columns = StatisticStructureService::apply($this->definition(), StatisticStructureService::DELETE_COLUMN, 0, 1);
        $this->assertSame(array(0, 1), array_keys($columns->tables[0]->columns));
    }

    public function testACopyIsPlacedNextToItsOriginal(): void
    {
        $rows = StatisticStructureService::apply($this->definition(), StatisticStructureService::DUPLICATE_ROW, 0, 1);
        $columns = StatisticStructureService::apply($this->definition(), StatisticStructureService::DUPLICATE_COLUMN, 0, 0);

        $this->assertSame(array('a', 'b', 'b', 'c'), $this->rowLabels($rows));
        $this->assertSame(array('A', 'A', 'B', 'C'), $this->columnLabels($columns));
    }

    public function testMovingSwapsWithTheNeighbour(): void
    {
        $up = StatisticStructureService::apply($this->definition(), StatisticStructureService::MOVE_ROW_UP, 0, 2);
        $down = StatisticStructureService::apply($this->definition(), StatisticStructureService::MOVE_ROW_DOWN, 0, 0);

        $this->assertSame(array('a', 'c', 'b'), $this->rowLabels($up));
        $this->assertSame(array('b', 'a', 'c'), $this->rowLabels($down));
    }

    public function testMovingPastTheEndChangesNothing(): void
    {
        $first = StatisticStructureService::apply($this->definition(), StatisticStructureService::MOVE_ROW_UP, 0, 0);
        $last = StatisticStructureService::apply($this->definition(), StatisticStructureService::MOVE_ROW_DOWN, 0, 2);

        $this->assertSame(array('a', 'b', 'c'), $this->rowLabels($first));
        $this->assertSame(array('a', 'b', 'c'), $this->rowLabels($last));
    }

    /**
     * @dataProvider lastElements
     */
    public function testTheLastElementCannotBeDeleted(string $action): void
    {
        $definition = new StatisticDefinition(1, 1, 'n', '', '', 5, array(TableDefinition::createEmpty()));

        $this->expectException(Exception::class);
        StatisticStructureService::apply($definition, $action, 0, 0);
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function lastElements(): array
    {
        return array(
            array(StatisticStructureService::DELETE_TABLE),
            array(StatisticStructureService::DELETE_ROW),
            array(StatisticStructureService::DELETE_COLUMN)
        );
    }

    /**
     * The regression test for the nine places that indexed an array with the number from the query
     * string.
     *
     * @dataProvider indexesThatAreNotThere
     */
    public function testAnIndexThatIsNotThereIsRefused(string $action, int $table, int $element): void
    {
        $this->expectException(Exception::class);
        StatisticStructureService::apply($this->definition(), $action, $table, $element);
    }

    /**
     * @return array<int,array<int,mixed>>
     */
    public static function indexesThatAreNotThere(): array
    {
        return array(
            array(StatisticStructureService::ADD_ROW, 99, -1),
            array(StatisticStructureService::ADD_ROW, -1, -1),
            array(StatisticStructureService::DELETE_ROW, 0, 99),
            array(StatisticStructureService::DELETE_ROW, 0, -1),
            array(StatisticStructureService::MOVE_COLUMN_UP, 0, 99),
            array(StatisticStructureService::DUPLICATE_COLUMN, 0, 99),
            array(StatisticStructureService::DELETE_TABLE, 99, -1)
        );
    }

    public function testAnActionItDoesNotKnowIsRefused(): void
    {
        $this->expectException(Exception::class);
        StatisticStructureService::apply($this->definition(), 'droptable', 0);
    }

    public function testTheDefinitionItIsGivenIsNeverChanged(): void
    {
        $definition = $this->definition();

        StatisticStructureService::apply($definition, StatisticStructureService::DELETE_ROW, 0, 0);
        StatisticStructureService::apply($definition, StatisticStructureService::ADD_TABLE, 0);
        StatisticStructureService::apply($definition, StatisticStructureService::MOVE_ROW_DOWN, 0, 0);

        $this->assertSame(array('a', 'b', 'c'), $this->rowLabels($definition));
        $this->assertCount(1, $definition->tables);
    }

    /**
     * Every action the editor offers is one this service carries out.
     */
    public function testEveryDeclaredActionIsHandled(): void
    {
        $definition = StatisticStructureService::apply($this->definition(), StatisticStructureService::ADD_TABLE, 0);

        $this->assertCount(15, StatisticStructureService::ACTIONS);

        foreach (StatisticStructureService::ACTIONS as $action) {
            $result = StatisticStructureService::apply($definition, $action, 0, 0);

            $this->assertInstanceOf(StatisticDefinition::class, $result, $action);
        }
    }
}
