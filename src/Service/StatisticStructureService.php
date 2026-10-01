<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Exception;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

/**
 * Adding, removing, duplicating and moving the tables, rows and columns of a statistic.
 *
 * Every operation is a function of a definition that returns a new one. That is what makes three of
 * version 3's defects impossible rather than fixed:
 *
 * - **Nothing is left sparse.** Version 3 removed an element with unset(), so the keys of the array
 *   had a hole in them while the editor went on reading them with a counter. Deleting a row in the
 *   middle produced undefined-index warnings and a visibly broken form until the next save.
 * - **Every index is checked.** Version 3 took the table, row and column number straight out of the
 *   query string and indexed its arrays with them, in nine places, with no check at all.
 * - **The last table, row or column cannot be deleted.** Version 3 allowed it, which left a table
 *   with nothing in it and an editor that could not add anything back to it.
 *
 * Moving the first element up, or the last one down, does nothing. Version 3 swapped the element with
 * itself in that case, which also did nothing but read one index past the end of the array to find
 * out.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticStructureService
{
    public const ADD_TABLE = 'addtable';
    public const DELETE_TABLE = 'deltable';
    public const DUPLICATE_TABLE = 'dupltable';
    public const MOVE_TABLE_UP = 'movetableup';
    public const MOVE_TABLE_DOWN = 'movetabledown';

    public const ADD_ROW = 'addrow';
    public const DELETE_ROW = 'delrow';
    public const DUPLICATE_ROW = 'duplrow';
    public const MOVE_ROW_UP = 'moverowup';
    public const MOVE_ROW_DOWN = 'moverowdown';

    public const ADD_COLUMN = 'addcol';
    public const DELETE_COLUMN = 'delcol';
    public const DUPLICATE_COLUMN = 'duplcol';
    public const MOVE_COLUMN_UP = 'movecolup';
    public const MOVE_COLUMN_DOWN = 'movecoldown';

    /**
     * Every operation this service accepts. The editor checks a submitted action against this list
     * before anything else happens.
     */
    public const ACTIONS = array(
        self::ADD_TABLE,
        self::DELETE_TABLE,
        self::DUPLICATE_TABLE,
        self::MOVE_TABLE_UP,
        self::MOVE_TABLE_DOWN,
        self::ADD_ROW,
        self::DELETE_ROW,
        self::DUPLICATE_ROW,
        self::MOVE_ROW_UP,
        self::MOVE_ROW_DOWN,
        self::ADD_COLUMN,
        self::DELETE_COLUMN,
        self::DUPLICATE_COLUMN,
        self::MOVE_COLUMN_UP,
        self::MOVE_COLUMN_DOWN
    );

    /**
     * Apply one structure operation.
     *
     * @param StatisticDefinition $definition
     * @param string $action One of ACTIONS.
     * @param int $tableIndex The table the operation applies to, counted from zero.
     * @param int $elementIndex The row or column the operation applies to, counted from zero, and
     *                          ignored by the operations that work on a whole table.
     * @return StatisticDefinition
     * @throws Exception SYS_INVALID_PAGE_VIEW for an unknown action or an index that is not there.
     */
    public static function apply(
        StatisticDefinition $definition,
        string $action,
        int $tableIndex,
        int $elementIndex = -1
    ): StatisticDefinition {
        if (!in_array($action, self::ACTIONS, true)) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $tables = $definition->tables;

        if ($action === self::ADD_TABLE) {
            $tables[] = TableDefinition::createEmpty();

            return $definition->withTables($tables);
        }

        self::assertIndex($tableIndex, count($tables));

        switch ($action) {
            case self::DELETE_TABLE:
                self::assertNotLast(count($tables));
                unset($tables[$tableIndex]);

                return $definition->withTables(array_values($tables));

            case self::DUPLICATE_TABLE:
                array_splice($tables, $tableIndex + 1, 0, array($tables[$tableIndex]));

                return $definition->withTables($tables);

            case self::MOVE_TABLE_UP:
            case self::MOVE_TABLE_DOWN:
                return $definition->withTables(
                    self::swap($tables, $tableIndex, $action === self::MOVE_TABLE_UP)
                );
        }

        $table = $tables[$tableIndex];

        $tables[$tableIndex] = match ($action) {
            self::ADD_ROW => $table->withRows(self::append($table->rows, new RowDefinition())),
            self::DELETE_ROW => $table->withRows(self::remove($table->rows, $elementIndex)),
            self::DUPLICATE_ROW => $table->withRows(self::duplicate($table->rows, $elementIndex)),
            self::MOVE_ROW_UP => $table->withRows(self::move($table->rows, $elementIndex, true)),
            self::MOVE_ROW_DOWN => $table->withRows(self::move($table->rows, $elementIndex, false)),
            self::ADD_COLUMN => $table->withColumns(self::append($table->columns, new ColumnDefinition())),
            self::DELETE_COLUMN => $table->withColumns(self::remove($table->columns, $elementIndex)),
            self::DUPLICATE_COLUMN => $table->withColumns(self::duplicate($table->columns, $elementIndex)),
            self::MOVE_COLUMN_UP => $table->withColumns(self::move($table->columns, $elementIndex, true)),
            self::MOVE_COLUMN_DOWN => $table->withColumns(self::move($table->columns, $elementIndex, false)),
            default => throw new Exception('SYS_INVALID_PAGE_VIEW')
        };

        return $definition->withTables($tables);
    }

    /**
     * @param array<int,mixed> $elements
     * @param mixed $element
     * @return array<int,mixed>
     */
    private static function append(array $elements, mixed $element): array
    {
        $elements[] = $element;

        return $elements;
    }

    /**
     * @param array<int,mixed> $elements
     * @param int $index
     * @return array<int,mixed>
     * @throws Exception
     */
    private static function remove(array $elements, int $index): array
    {
        self::assertIndex($index, count($elements));
        self::assertNotLast(count($elements));
        unset($elements[$index]);

        return array_values($elements);
    }

    /**
     * The copy is inserted straight after its original. Both are equal, so which of the two the
     * editor then shows first makes no difference.
     *
     * @param array<int,mixed> $elements
     * @param int $index
     * @return array<int,mixed>
     * @throws Exception
     */
    private static function duplicate(array $elements, int $index): array
    {
        self::assertIndex($index, count($elements));
        array_splice($elements, $index + 1, 0, array($elements[$index]));

        return $elements;
    }

    /**
     * @param array<int,mixed> $elements
     * @param int $index
     * @param bool $up
     * @return array<int,mixed>
     * @throws Exception
     */
    private static function move(array $elements, int $index, bool $up): array
    {
        self::assertIndex($index, count($elements));

        return self::swap($elements, $index, $up);
    }

    /**
     * Swap an element with its neighbour, or leave the list alone when it has none in that direction.
     *
     * @param array<int,mixed> $elements
     * @param int $index
     * @param bool $up
     * @return array<int,mixed>
     */
    private static function swap(array $elements, int $index, bool $up): array
    {
        $target = $up ? $index - 1 : $index + 1;

        if ($target < 0 || $target > count($elements) - 1) {
            return $elements;
        }

        $moved = $elements[$index];
        $elements[$index] = $elements[$target];
        $elements[$target] = $moved;

        return $elements;
    }

    /**
     * @param int $index
     * @param int $count
     * @return void
     * @throws Exception
     */
    private static function assertIndex(int $index, int $count): void
    {
        if ($index < 0 || $index >= $count) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }

    /**
     * @param int $count
     * @return void
     * @throws Exception SYS_INVALID_PAGE_VIEW when this is the only element left.
     */
    private static function assertNotLast(int $count): void
    {
        if ($count <= 1) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    }
}
