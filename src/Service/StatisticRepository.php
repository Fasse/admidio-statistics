<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use AdmidioPlugin\Statistics\Entity\Statistic;
use AdmidioPlugin\Statistics\Entity\StatisticColumn;
use AdmidioPlugin\Statistics\Entity\StatisticRow;
use AdmidioPlugin\Statistics\Entity\StatisticTable;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

/**
 * Reads and writes the saved statistics.
 *
 * Three things are different from the persistence layer of version 3, and each of them was a bug:
 *
 * - **Every query is prepared and every value is bound.** Version 3 concatenated ids into its SQL,
 *   and the id columns are varchars, so anything that reached them by another route than the editor
 *   went into a statement unchecked.
 * - **Every read and every write names the organization.** Version 3 looked a statistic up by id
 *   alone, so any organization could read and delete any other organization's statistic, and its
 *   listing defaulted to organization 1 when the caller passed none.
 * - **Saving is one transaction.** Version 3 updated the statistic, deleted all of its tables and
 *   then inserted them again with no transaction at all, so a failure in the middle left the
 *   statistic without its tables.
 *
 * The id of a newly inserted statistic is the one the database generated. Version 3 asked for the
 * largest id in the table instead, which is a race and which failed outright when the organization
 * had no statistics yet.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticRepository
{
    /**
     * @param Database $database
     */
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * The saved statistics of one organization, in the order they are offered for selection.
     *
     * @param int $orgId
     * @return array<int,array{id:int,name:string}>
     * @throws Exception
     */
    public function findAllForOrganization(int $orgId): array
    {
        $sql = 'SELECT sta_id, sta_name
                  FROM ' . Statistic::table() . '
                 WHERE sta_org_id = ?
              ORDER BY sta_name, sta_id';

        $statement = $this->database->queryPrepared($sql, array($orgId));

        $statistics = array();
        while ($record = $statement->fetch()) {
            $statistics[] = array('id' => (int) $record['sta_id'], 'name' => (string) $record['sta_name']);
        }

        return $statistics;
    }

    /**
     * One saved statistic with all of its tables, columns and rows.
     *
     * @param int $id
     * @param int $orgId The organization the statistic has to belong to.
     * @return StatisticDefinition|null **null** when this organization has no such statistic, which
     *         is what a caller turns into SYS_INVALID_PAGE_VIEW.
     * @throws Exception
     */
    public function find(int $id, int $orgId): ?StatisticDefinition
    {
        $sql = 'SELECT * FROM ' . Statistic::table() . ' WHERE sta_id = ? AND sta_org_id = ?';
        $record = $this->database->queryPrepared($sql, array($id, $orgId))->fetch();

        if ($record === false) {
            return null;
        }

        return StatisticDefinition::fromRecord($record, $this->findTables($id));
    }

    /**
     * Insert or update a statistic together with everything below it, in one transaction.
     *
     * @param StatisticDefinition $definition
     * @return int The id of the saved statistic, which for a new one is the generated one.
     * @throws Exception
     */
    public function save(StatisticDefinition $definition): int
    {
        $this->database->startTransaction();

        try {
            $statistic = new Statistic($this->database);

            if ($definition->isNew()) {
                $statistic->setValue('sta_org_id', $definition->orgId);
            } else {
                $statistic->readDataById((int) $definition->id);

                if ($statistic->isNewRecord() || (int) $statistic->getValue('sta_org_id') !== $definition->orgId) {
                    throw new Exception('SYS_INVALID_PAGE_VIEW');
                }
            }

            $statistic->setValue('sta_name', $definition->name);
            $statistic->setValue('sta_title', $definition->title);
            $statistic->setValue('sta_subtitle', $definition->subtitle);
            $statistic->setValue('sta_std_role', $definition->standardRoleId);
            $statistic->save();

            $statisticId = (int) $statistic->getValue('sta_id');

            /*
             * The tables are replaced as a whole, because that is what the editor submits: one
             * complete definition. The foreign keys take the columns and rows of each table with it,
             * which is why this one statement is enough. These rows are internals of the statistic
             * and carry no changelog of their own, so replacing them in one statement loses nothing
             * that an entity write would have recorded - the statistic itself is written above.
             */
            $sql = 'DELETE FROM ' . StatisticTable::table() . ' WHERE stt_sta_id = ?';
            $this->database->queryPrepared($sql, array($statisticId));

            foreach ($definition->tables as $table) {
                $this->insertTable($statisticId, $table);
            }

            $this->database->endTransaction();
        } catch (\Throwable $exception) {
            $this->database->rollback();

            throw $exception;
        }

        return $statisticId;
    }

    /**
     * Delete a statistic. The foreign keys remove its tables, columns and rows.
     *
     * @param int $id
     * @param int $orgId The organization the statistic has to belong to.
     * @return void
     * @throws Exception
     */
    public function delete(int $id, int $orgId): void
    {
        $statistic = new Statistic($this->database);
        $statistic->readDataById($id);

        if ($statistic->isNewRecord() || (int) $statistic->getValue('sta_org_id') !== $orgId) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        $statistic->delete();
    }

    /**
     * @param int $statisticId
     * @return array<int,TableDefinition>
     * @throws Exception
     */
    private function findTables(int $statisticId): array
    {
        $sql = 'SELECT * FROM ' . StatisticTable::table() . ' WHERE stt_sta_id = ? ORDER BY stt_id';
        $statement = $this->database->queryPrepared($sql, array($statisticId));

        $tables = array();
        while ($record = $statement->fetch()) {
            $tableId = (int) $record['stt_id'];
            $tables[] = TableDefinition::fromRecord(
                $record,
                $this->findColumns($tableId),
                $this->findRows($tableId)
            );
        }

        return $tables;
    }

    /**
     * @param int $tableId
     * @return array<int,ColumnDefinition>
     * @throws Exception
     */
    private function findColumns(int $tableId): array
    {
        $sql = 'SELECT * FROM ' . StatisticColumn::table() . ' WHERE stc_stt_id = ? ORDER BY stc_id';
        $statement = $this->database->queryPrepared($sql, array($tableId));

        $columns = array();
        while ($record = $statement->fetch()) {
            $columns[] = ColumnDefinition::fromRecord(
                $record,
                ConditionCompiler::decode($record['stc_field_condition'] ?? null)
            );
        }

        return $columns;
    }

    /**
     * @param int $tableId
     * @return array<int,RowDefinition>
     * @throws Exception
     */
    private function findRows(int $tableId): array
    {
        $sql = 'SELECT * FROM ' . StatisticRow::table() . ' WHERE str_stt_id = ? ORDER BY str_id';
        $statement = $this->database->queryPrepared($sql, array($tableId));

        $rows = array();
        while ($record = $statement->fetch()) {
            $rows[] = RowDefinition::fromRecord(
                $record,
                ConditionCompiler::decode($record['str_field_condition'] ?? null)
            );
        }

        return $rows;
    }

    /**
     * @param int $statisticId
     * @param TableDefinition $table
     * @return void
     * @throws Exception
     */
    private function insertTable(int $statisticId, TableDefinition $table): void
    {
        $entity = new StatisticTable($this->database);
        $entity->setValue('stt_sta_id', $statisticId);
        $entity->setValue('stt_title', $table->title);
        $entity->setValue('stt_role', $table->roleId ?? 0);
        $entity->setValue('stt_first_column_label', $table->firstColumnLabel);
        $entity->save();

        $tableId = (int) $entity->getValue('stt_id');

        foreach ($table->columns as $column) {
            $record = new StatisticColumn($this->database);
            $record->setValue('stc_stt_id', $tableId);
            $record->setValue('stc_label', $column->label);
            $record->setValue('stc_profile_field', (string) ($column->profileFieldId ?? ''));
            $record->setValue('stc_field_condition', ConditionCompiler::encode($column->condition));
            $record->setValue('stc_function_main', $column->functionName);
            $record->setValue('stc_function_arg', (string) ($column->functionArgument ?? ''));
            $record->setValue('stc_function_total', $column->functionTotal);
            $record->save();
        }

        foreach ($table->rows as $row) {
            $record = new StatisticRow($this->database);
            $record->setValue('str_stt_id', $tableId);
            $record->setValue('str_label', $row->label);
            $record->setValue('str_profile_field', (string) ($row->profileFieldId ?? ''));
            $record->setValue('str_field_condition', ConditionCompiler::encode($row->condition));
            $record->save();
        }
    }
}
