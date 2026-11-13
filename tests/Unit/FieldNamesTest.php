<?php
/**
 * The codec for the editor's field names.
 *
 * The editor registers every field with the form, so the only names parse() ever sees are names this
 * class built. It is anchored and allow-listed all the same, because it is the step between a
 * submitted request and an array index.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use AdmidioPlugin\Statistics\ValueObject\FieldNames;
use PHPUnit\Framework\TestCase;

final class FieldNamesTest extends TestCase
{
    public function testTheNamesAreTheOnesTheOldEditorUsed(): void
    {
        $this->assertSame('table0_title', FieldNames::table(0, 'title'));
        $this->assertSame('table2_column5_func_total', FieldNames::column(2, 5, 'func_total'));
        $this->assertSame('table1_row3_condition', FieldNames::row(1, 3, 'condition'));
    }

    /**
     * The parts of version 4.1: the role and the reference date of a row and a column, and how the
     * date repeats. The form service reads them under exactly these names.
     */
    public function testTheRoleAndTheDayOfARowAndAColumnHaveNamesOfTheirOwn(): void
    {
        foreach (array('role', 'ref_date', 'repeat', 'repeat_max') as $part) {
            $this->assertContains($part, FieldNames::COLUMN_PARTS);
            $this->assertContains($part, FieldNames::ROW_PARTS);
        }

        $this->assertSame('table0_column1_ref_date', FieldNames::column(0, 1, 'ref_date'));
        $this->assertSame('table3_row2_repeat_max', FieldNames::row(3, 2, 'repeat_max'));
        $this->assertSame(
            array('kind' => FieldNames::KIND_ROW, 'table' => 1, 'index' => 4, 'part' => 'role'),
            FieldNames::parse('table1_row4_role')
        );
        $this->assertNull(FieldNames::parse('table0_column0_ref'), 'a prefix of a part is not a part');
    }

    public function testEveryNameItBuildsParsesBackToItself(): void
    {
        foreach (array(0, 1, 12) as $table) {
            foreach (FieldNames::TABLE_PARTS as $part) {
                $parsed = FieldNames::parse(FieldNames::table($table, $part));

                $this->assertIsArray($parsed);
                $this->assertSame(FieldNames::KIND_TABLE, $parsed['kind']);
                $this->assertSame($table, $parsed['table']);
                $this->assertSame($part, $parsed['part']);
            }

            foreach (array(0, 9) as $index) {
                foreach (FieldNames::COLUMN_PARTS as $part) {
                    $parsed = FieldNames::parse(FieldNames::column($table, $index, $part));

                    $this->assertIsArray($parsed);
                    $this->assertSame(FieldNames::KIND_COLUMN, $parsed['kind']);
                    $this->assertSame($table, $parsed['table']);
                    $this->assertSame($index, $parsed['index']);
                    $this->assertSame($part, $parsed['part']);
                }

                foreach (FieldNames::ROW_PARTS as $part) {
                    $parsed = FieldNames::parse(FieldNames::row($table, $index, $part));

                    $this->assertIsArray($parsed);
                    $this->assertSame(FieldNames::KIND_ROW, $parsed['kind']);
                    $this->assertSame($index, $parsed['index']);
                }
            }
        }
    }

    /**
     * @dataProvider namesItDidNotBuild
     */
    public function testItRefusesAnythingItDidNotBuild(string $name): void
    {
        $this->assertNull(FieldNames::parse($name), $name);
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function namesItDidNotBuild(): array
    {
        return array(
            array(''),
            array('table2_column5'),
            array('table-1_column0_label'),
            array('xtable0_column0_label'),
            array('table0_column0_labelx'),
            array('table0_column0_evil'),
            array('table00_column0_label'),
            array('table0_column01_label'),
            array('table0_role_extra'),
            array('statistic_name'),
            array('adm_csrf_token')
        );
    }

    /**
     * A part the class does not know is not a part, whatever it is called.
     */
    public function testAPartThatIsNotDeclaredIsRefused(): void
    {
        $this->assertNull(FieldNames::parse('table0_column0_' . 'usf_id'));
        $this->assertNull(FieldNames::parse('table0_' . 'sta_org_id'));
    }
}
