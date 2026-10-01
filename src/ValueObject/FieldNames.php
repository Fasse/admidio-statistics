<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * The names of the editor's form fields, built and read in one place.
 *
 * The names are the ones version 3 used - table0_title, table0_column2_func_total,
 * table0_row3_condition - so the form is unchanged for anyone looking at the HTML. What is new is
 * that one class both writes and reads them, and that parse() is anchored and accepts only the
 * parts listed here. The editor registers every field with the form, so the only names parse() ever
 * sees are names this class produced; a forged one is rejected by the form before it gets here.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class FieldNames
{
    public const KIND_TABLE = 'table';
    public const KIND_COLUMN = 'column';
    public const KIND_ROW = 'row';

    /**
     * The parts of a table that are form fields of their own.
     */
    public const TABLE_PARTS = array('title', 'role', 'first_column_label');

    /**
     * The parts of a column, in the order the editor stacks them as the rows of its column grid.
     */
    public const COLUMN_PARTS = array(
        'label',
        'profile_field',
        'condition',
        'func_arg',
        'func_main',
        'func_total'
    );

    /**
     * The parts of a row, in the order the editor lays them out as the columns of its row grid.
     */
    public const ROW_PARTS = array('label', 'profile_field', 'condition');

    /**
     * @param int $table Index of the table, counted from zero.
     * @param string $part One of TABLE_PARTS.
     * @return string
     */
    public static function table(int $table, string $part): string
    {
        return 'table' . $table . '_' . $part;
    }

    /**
     * @param int $table Index of the table, counted from zero.
     * @param int $column Index of the column within that table, counted from zero.
     * @param string $part One of COLUMN_PARTS.
     * @return string
     */
    public static function column(int $table, int $column, string $part): string
    {
        return 'table' . $table . '_column' . $column . '_' . $part;
    }

    /**
     * @param int $table Index of the table, counted from zero.
     * @param int $row Index of the row within that table, counted from zero.
     * @param string $part One of ROW_PARTS.
     * @return string
     */
    public static function row(int $table, int $row, string $part): string
    {
        return 'table' . $table . '_row' . $row . '_' . $part;
    }

    /**
     * Read a field name back into what it addresses.
     *
     * @param string $name
     * @return array{kind:string,table:int,index:int,part:string}|null **null** when the name is not
     *         one this class builds, which includes a part that is not listed above.
     */
    public static function parse(string $name): ?array
    {
        $number = '(0|[1-9][0-9]*)';

        $pattern = '/^table' . $number . '_column' . $number . '_(' . self::alternatives(self::COLUMN_PARTS) . ')$/';
        if (preg_match($pattern, $name, $matches) === 1) {
            return array(
                'kind' => self::KIND_COLUMN,
                'table' => (int) $matches[1],
                'index' => (int) $matches[2],
                'part' => $matches[3]
            );
        }

        $pattern = '/^table' . $number . '_row' . $number . '_(' . self::alternatives(self::ROW_PARTS) . ')$/';
        if (preg_match($pattern, $name, $matches) === 1) {
            return array(
                'kind' => self::KIND_ROW,
                'table' => (int) $matches[1],
                'index' => (int) $matches[2],
                'part' => $matches[3]
            );
        }

        $pattern = '/^table' . $number . '_(' . self::alternatives(self::TABLE_PARTS) . ')$/';
        if (preg_match($pattern, $name, $matches) === 1) {
            return array(
                'kind' => self::KIND_TABLE,
                'table' => (int) $matches[1],
                'index' => -1,
                'part' => $matches[2]
            );
        }

        return null;
    }

    /**
     * @param array<int,string> $parts
     * @return string
     */
    private static function alternatives(array $parts): string
    {
        return implode('|', array_map('preg_quote', $parts));
    }
}
