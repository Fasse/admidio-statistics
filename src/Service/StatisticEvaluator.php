<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\StringUtils;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Roles\ValueObject\ConditionParser;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\ResultTable;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\StatisticResult;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

/**
 * Turns a statistic definition into its figures.
 *
 * A cell counts, or aggregates over, the members of the table's role that meet both the condition of
 * its row and the condition of its column. Free text conditions go through Admidio's own
 * ConditionParser, the same path the list module uses.
 *
 * Seven defects of version 3 are fixed here, and four of them change figures that are shown today:
 *
 * - **Conditions are intersected on sets.** Version 3 ran one query per condition, appended every
 *   returned member id to one list and then accepted an id that appeared as often as there were
 *   conditions. Two of its three queries selected from a join without DISTINCT, so a member with
 *   several matching profile rows appeared repeatedly and reached that count while meeting only one
 *   condition. Each condition now yields a set, and the sets are intersected.
 * - **Only a column that declares a total contributes one.** Version 3 asked is_null() of a column
 *   whose "no total" is the empty string, so every table got a total row and every column a value in
 *   it.
 * - **The minimum is the smallest value, not the smaller of the value and 10000.** Version 3 seeded
 *   its minimum with 10000, so a column whose figures were all larger reported 10000.
 * - **A total is computed from numbers.** Version 3 added up the formatted cell strings, so a
 *   percentage column summed values such as "12.3 %" by string coercion.
 * - The totals are translated instead of carrying hardcoded glyphs.
 * - A condition naming a selection value that the profile field does not offer selects nobody.
 *   Version 3 let array_search() return false, which reached the parser as an empty condition and so
 *   selected everybody.
 * - Every query is prepared. Version 3 concatenated the role id and the profile field id, and both
 *   come out of varchar columns.
 *
 * Two dead methods are gone: one was never called, the other guarded on is_int() a value that always
 * arrives from the database as a string and so always returned nothing.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticEvaluator
{
    /**
     * The date Admidio uses for a membership that has not ended.
     */
    private const DATE_OPEN_END = '9999-12-31';

    /**
     * @var array<int,string> Cache of profile field types, keyed by field id.
     */
    private array $fieldTypes = array();

    /**
     * @param Database $database
     * @param ProfileFields $profileFields
     * @param Language $l10n
     */
    public function __construct(
        private readonly Database $database,
        private readonly ProfileFields $profileFields,
        private readonly Language $l10n
    ) {
    }

    /**
     * Compute every table of a statistic.
     *
     * @param StatisticDefinition $definition
     * @param string $asOf The date of the figures, already formatted for display.
     * @return StatisticResult
     * @throws Exception
     */
    public function evaluate(StatisticDefinition $definition, string $asOf): StatisticResult
    {
        $tables = array();
        foreach ($definition->tables as $table) {
            $tables[] = $this->evaluateTable($table, $definition->standardRoleId);
        }

        return new StatisticResult($definition->title, $definition->subtitle, $asOf, $tables);
    }

    /**
     * The number of members a role currently has.
     *
     * @param int $roleId
     * @return int
     * @throws Exception
     */
    public function getUserCountFromRoleId(int $roleId): int
    {
        $sql = 'SELECT COUNT(*) AS member_count
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_end = ? AND mem_rol_id = ?';

        $record = $this->database->queryPrepared($sql, array(self::DATE_OPEN_END, $roleId))->fetch();

        return $record === false ? 0 : (int) $record['member_count'];
    }

    /**
     * The name of a role, or an empty string when there is no such role.
     *
     * @param int $roleId
     * @return string
     * @throws Exception
     */
    public function getRoleNameFromId(int $roleId): string
    {
        if ($roleId <= 0) {
            return '';
        }

        $sql = 'SELECT rol_name FROM ' . TBL_ROLES . ' WHERE rol_id = ?';
        $record = $this->database->queryPrepared($sql, array($roleId))->fetch();

        return $record === false ? '' : (string) $record['rol_name'];
    }

    /**
     * @param TableDefinition $table
     * @param int $standardRoleId
     * @return ResultTable
     * @throws Exception
     */
    private function evaluateTable(TableDefinition $table, int $standardRoleId): ResultTable
    {
        $roleId = $table->effectiveRoleId($standardRoleId);
        $population = $this->tablePopulation($table, $roleId);

        $headers = array($table->firstColumnLabel);
        foreach ($table->columns as $column) {
            $headers[] = $column->label;
        }

        $rows = array();
        $numbers = array();

        foreach ($table->rows as $rowIndex => $row) {
            $cells = array($row->label);
            foreach ($table->columns as $columnIndex => $column) {
                $cell = $this->evaluateCell($row, $column, $roleId, $population);
                $numbers[$rowIndex][$columnIndex] = $cell['value'];
                $cells[] = $cell['display'];
            }
            $rows[] = $cells;
        }

        $totalRow = null;
        if ($table->hasTotalRow()) {
            $totalRow = array($this->l10n->get('PLG_STATISTICS_TOTAL'));
            foreach ($table->columns as $columnIndex => $column) {
                $totalRow[] = $column->hasTotal()
                    ? $this->formatTotal($column, array_column($numbers, $columnIndex))
                    : '';
            }
        }

        $caption = $this->l10n->get('SYS_ROLE') . ' ' . $this->getRoleNameFromId($roleId) . ', '
            . $this->l10n->get('PLG_STATISTICS_XY_ENTRIES', array($this->getUserCountFromRoleId($roleId)));

        return new ResultTable($table->title, $caption, $headers, $rows, $totalRow);
    }

    /**
     * The number of members a percentage is taken of. When neither the rows nor the columns leave a
     * condition out, it is the number of members that meet at least one of them; otherwise it is the
     * whole role, because a row or column without a condition already covers all of it.
     *
     * @param TableDefinition $table
     * @param int $roleId
     * @return int
     * @throws Exception
     */
    private function tablePopulation(TableDefinition $table, int $roleId): int
    {
        $conditions = array();
        $unconditionalColumn = false;
        $unconditionalRow = false;

        foreach ($table->columns as $column) {
            if ($column->profileFieldId === null) {
                $unconditionalColumn = true;
            } else {
                $conditions[] = array('fieldId' => $column->profileFieldId, 'condition' => $column->condition);
            }
        }

        foreach ($table->rows as $row) {
            if ($row->profileFieldId === null) {
                $unconditionalRow = true;
            } else {
                $conditions[] = array('fieldId' => $row->profileFieldId, 'condition' => $row->condition);
            }
        }

        if (($unconditionalColumn && $unconditionalRow) || $conditions === array()) {
            return $this->getUserCountFromRoleId($roleId);
        }

        return count($this->selectUserIds($roleId, $conditions, false));
    }

    /**
     * One cell: the figure as a number, so that a total can be computed from it, and the figure as
     * the table shows it.
     *
     * @param RowDefinition $row
     * @param ColumnDefinition $column
     * @param int $roleId
     * @param int $population
     * @return array{value:float|null,display:string}
     * @throws Exception
     */
    private function evaluateCell(RowDefinition $row, ColumnDefinition $column, int $roleId, int $population): array
    {
        $conditions = array();
        if ($row->profileFieldId !== null) {
            $conditions[] = array('fieldId' => $row->profileFieldId, 'condition' => $row->condition);
        }
        if ($column->profileFieldId !== null) {
            $conditions[] = array('fieldId' => $column->profileFieldId, 'condition' => $column->condition);
        }

        if ($column->functionName === StatisticFunction::COUNT) {
            $count = count($this->selectUserIds($roleId, $conditions));

            return array('value' => (float) $count, 'display' => (string) $count);
        }

        if ($column->functionName === StatisticFunction::PERCENT) {
            $share = $population > 0
                ? 100 / $population * count($this->selectUserIds($roleId, $conditions))
                : 0.0;

            return array('value' => $share, 'display' => sprintf('%01.1f', $share) . ' %');
        }

        return $this->aggregate($column, $roleId, $conditions);
    }

    /**
     * The four functions that aggregate the values of a profile field.
     *
     * @param ColumnDefinition $column
     * @param int $roleId
     * @param array<int,array{fieldId:int,condition:string}> $conditions
     * @return array{value:float|null,display:string}
     * @throws Exception
     */
    private function aggregate(ColumnDefinition $column, int $roleId, array $conditions): array
    {
        if ($column->functionArgument === null) {
            return array('value' => null, 'display' => '');
        }

        $userIds = $this->selectUserIds($roleId, $conditions);
        if ($userIds === array()) {
            return array('value' => 0.0, 'display' => '0');
        }

        $values = $this->readProfileValues($userIds, $column->functionArgument);
        $type = $this->getProfileFieldType($column->functionArgument);

        if ($type === 'DATE') {
            $values = $this->agesFromDates($values);
        } elseif ($type === 'TEXT') {
            /*
             * The smallest and the largest of a set of texts are texts, so they are shown but they
             * cannot take part in a total. Version 3 offered them for min and max only, and so do we.
             */
            if ($values === array()
                || !in_array($column->functionName, array(StatisticFunction::MINIMUM, StatisticFunction::MAXIMUM), true)) {
                return array('value' => null, 'display' => '');
            }

            $text = $column->functionName === StatisticFunction::MINIMUM ? min($values) : max($values);

            return array('value' => null, 'display' => (string) $text);
        }

        $numbers = array();
        foreach ($values as $value) {
            if (is_numeric($value)) {
                $numbers[] = (float) $value;
            }
        }

        if ($numbers === array()) {
            return array('value' => null, 'display' => '');
        }

        $result = match ($column->functionName) {
            StatisticFunction::MINIMUM => min($numbers),
            StatisticFunction::MAXIMUM => max($numbers),
            StatisticFunction::AVERAGE => array_sum($numbers) / count($numbers),
            StatisticFunction::SUM => array_sum($numbers),
            default => null
        };

        if ($result === null) {
            return array('value' => null, 'display' => '');
        }

        $display = $column->functionName === StatisticFunction::AVERAGE
            ? sprintf('%01.1f', $result)
            : $this->formatNumber($result);

        return array('value' => $result, 'display' => $display);
    }

    /**
     * The total of one column, from the numbers of its cells.
     *
     * @param ColumnDefinition $column
     * @param array<int,float|null> $values
     * @return string
     */
    private function formatTotal(ColumnDefinition $column, array $values): string
    {
        $numbers = array();
        foreach ($values as $value) {
            if ($value !== null) {
                $numbers[] = $value;
            }
        }

        if ($numbers === array()) {
            return '';
        }

        $total = match ($column->functionTotal) {
            StatisticFunction::SUM => array_sum($numbers),
            StatisticFunction::AVERAGE => array_sum($numbers) / count($numbers),
            StatisticFunction::MINIMUM => min($numbers),
            StatisticFunction::MAXIMUM => max($numbers),
            default => null
        };

        if ($total === null) {
            return '';
        }

        $formatted = $column->functionTotal === StatisticFunction::AVERAGE
            ? sprintf('%01.1f', $total)
            : $this->formatNumber($total);

        if ($column->functionName === StatisticFunction::PERCENT) {
            $formatted .= ' %';
        }

        $key = match ($column->functionTotal) {
            StatisticFunction::SUM => 'PLG_STATISTICS_TOTAL_SUM',
            StatisticFunction::AVERAGE => 'PLG_STATISTICS_TOTAL_AVERAGE',
            StatisticFunction::MINIMUM => 'PLG_STATISTICS_TOTAL_MINIMUM',
            StatisticFunction::MAXIMUM => 'PLG_STATISTICS_TOTAL_MAXIMUM',
            default => 'PLG_STATISTICS_TOTAL'
        };

        return $this->l10n->get($key, array($formatted));
    }

    /**
     * The member ids of a role that meet the given conditions.
     *
     * @param int $roleId
     * @param array<int,array{fieldId:int,condition:string}> $conditions
     * @param bool $mustMeetAll **true** for the intersection, which is what a cell of a cross table
     *                          is, **false** for the union, which is what a percentage is taken of.
     * @return array<int,int>
     * @throws Exception
     */
    private function selectUserIds(int $roleId, array $conditions, bool $mustMeetAll = true): array
    {
        if ($conditions === array()) {
            return $this->readRoleMemberIds($roleId);
        }

        $sets = array();
        foreach ($conditions as $condition) {
            $sets[] = $this->readUserIdsForCondition($roleId, $condition['fieldId'], $condition['condition']);
        }

        $result = array_shift($sets);
        foreach ($sets as $set) {
            $result = $mustMeetAll ? array_intersect($result, $set) : array_merge($result, $set);
        }

        return array_values(array_unique($result));
    }

    /**
     * @param int $roleId
     * @return array<int,int>
     * @throws Exception
     */
    private function readRoleMemberIds(int $roleId): array
    {
        $sql = 'SELECT DISTINCT mem_usr_id
                  FROM ' . TBL_MEMBERS . '
                 WHERE mem_end = ? AND mem_rol_id = ?';

        return $this->readIds($sql, array(self::DATE_OPEN_END, $roleId), 'mem_usr_id');
    }

    /**
     * The members of a role that meet one condition. The result is a set, which is what makes the
     * intersection in selectUserIds() correct.
     *
     * @param int $roleId
     * @param int $fieldId
     * @param string $condition
     * @return array<int,int>
     * @throws Exception
     */
    private function readUserIdsForCondition(int $roleId, int $fieldId, string $condition): array
    {
        if ($this->isKeyword($condition, 'PLG_STATISTICS_MISSING', array('FEHLT', 'MISSING'))) {
            $sql = 'SELECT DISTINCT mem_usr_id
                      FROM ' . TBL_MEMBERS . '
                     WHERE mem_end = ? AND mem_rol_id = ?
                       AND mem_usr_id NOT IN (SELECT usd_usr_id FROM ' . TBL_USER_DATA . ' WHERE usd_usf_id = ?)';

            return $this->readIds($sql, array(self::DATE_OPEN_END, $roleId, $fieldId), 'mem_usr_id');
        }

        $sql = 'SELECT DISTINCT mem_usr_id
                  FROM ' . TBL_MEMBERS . '
            INNER JOIN ' . TBL_USER_DATA . ' ON mem_usr_id = usd_usr_id
                 WHERE mem_end = ? AND mem_rol_id = ? AND usd_usf_id = ?';
        $params = array(self::DATE_OPEN_END, $roleId, $fieldId);

        if ($this->isKeyword($condition, 'PLG_STATISTICS_AVAILABLE', array('VORHANDEN', 'AVAILABLE'))
            || $condition === '') {
            return $this->readIds($sql, $params, 'mem_usr_id');
        }

        $fragment = $this->conditionToSql($fieldId, $condition);
        if ($fragment === null) {
            /*
             * The condition names a selection the field does not offer, so nobody can meet it.
             * Version 3 passed the failed lookup on to the parser, where it became an empty
             * condition - and so selected everybody.
             */
            return array();
        }

        return $this->readIds($sql . $fragment, $params, 'mem_usr_id');
    }

    /**
     * Whether a condition is one of the words that mean "the field has no value" or "the field has
     * one", in the translation of the current language or in the two the plugin has always accepted.
     *
     * @param string $condition
     * @param string $translationId
     * @param array<int,string> $literals
     * @return bool
     */
    private function isKeyword(string $condition, string $translationId, array $literals): bool
    {
        $condition = StringUtils::strToUpper(trim($condition));
        $literals[] = StringUtils::strToUpper($this->l10n->get($translationId));

        return in_array($condition, $literals, true);
    }

    /**
     * Turn a condition the user typed into an SQL fragment, through Admidio's own parser.
     *
     * @param int $fieldId
     * @param string $condition
     * @return string|null **null** when the condition can never be met.
     * @throws Exception
     */
    private function conditionToSql(int $fieldId, string $condition): ?string
    {
        $type = $this->getProfileFieldType($fieldId);

        switch ($type) {
            case 'DATE':
                $dataType = 'date';
                break;
            case 'NUMERIC':
                $dataType = 'int';
                break;
            case 'CHECKBOX':
                $dataType = 'checkbox';
                $yesNo = array($this->l10n->get('SYS_YES'), $this->l10n->get('SYS_NO'), 'true', 'false');
                $condition = str_replace(
                    array_map(array(StringUtils::class, 'strToLower'), $yesNo),
                    array('1', '0', '1', '0'),
                    StringUtils::strToLower($condition)
                );
                break;
            case 'DROPDOWN':
            case 'RADIO_BUTTON':
                $dataType = 'string';
                $options = $this->profileFields->getPropertyById($fieldId, 'ufo_usf_options', 'text');
                if (!is_array($options)) {
                    return null;
                }
                $key = array_search(
                    StringUtils::strToLower($condition),
                    array_map(array(StringUtils::class, 'strToLower'), $options),
                    true
                );
                if ($key === false) {
                    return null;
                }
                $condition = (string) $key;
                break;
            default:
                $dataType = 'string';
        }

        $parser = new ConditionParser();

        /*
         * The parser reads the comparison characters in their stored form: it maps "}=" to greater or
         * equal and "{=" to less or equal, and does not know ">" or "<" at all. So the condition goes
         * back through the codec before it is handed over, which is what the old evaluator did too.
         */
        return $parser->makeSqlStatement(
            ConditionCompiler::encode($condition),
            'usd_value',
            $dataType,
            '',
            $this->database
        );
    }

    /**
     * @param array<int,int> $userIds
     * @param int $fieldId
     * @return array<int,string>
     * @throws Exception
     */
    private function readProfileValues(array $userIds, int $fieldId): array
    {
        if ($userIds === array()) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $sql = 'SELECT usd_value
                  FROM ' . TBL_USER_DATA . '
                 WHERE usd_usf_id = ? AND usd_usr_id IN (' . $placeholders . ')';

        $statement = $this->database->queryPrepared($sql, array_merge(array($fieldId), $userIds));

        $values = array();
        while ($record = $statement->fetch()) {
            if ($record['usd_value'] !== null && $record['usd_value'] !== '') {
                $values[] = (string) $record['usd_value'];
            }
        }

        return $values;
    }

    /**
     * The ages, in whole years, of a list of dates of birth. A value that is not a date is left out
     * rather than producing a nonsensical age, which is what version 3 did by handing whatever it
     * found to explode().
     *
     * @param array<int,string> $dates
     * @return array<int,string>
     */
    private function agesFromDates(array $dates): array
    {
        $ages = array();
        $today = new \DateTimeImmutable('today');

        foreach ($dates as $date) {
            /*
             * Only a value that looks like a stored date is one. PHP reads an empty string as the
             * current moment and finds a date in a good deal of other text as well, so without
             * this an empty profile field would count as an age of nought.
             */
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $parts) !== 1) {
                continue;
            }
            if ((int) $parts[1] === 0 || (int) $parts[2] === 0 || (int) $parts[3] === 0) {
                continue;
            }

            try {
                $birthday = new \DateTimeImmutable($date);
            } catch (\Throwable) {
                continue;
            }

            $ages[] = (string) $today->diff($birthday)->y;
        }

        return $ages;
    }

    /**
     * @param int $fieldId
     * @return string
     * @throws Exception
     */
    private function getProfileFieldType(int $fieldId): string
    {
        if (!array_key_exists($fieldId, $this->fieldTypes)) {
            $this->fieldTypes[$fieldId] = (string) $this->profileFields->getPropertyById($fieldId, 'usf_type', 'text');
        }

        return $this->fieldTypes[$fieldId];
    }

    /**
     * @param string $sql
     * @param array<int,mixed> $params
     * @param string $column
     * @return array<int,int>
     * @throws Exception
     */
    private function readIds(string $sql, array $params, string $column): array
    {
        $statement = $this->database->queryPrepared($sql, $params);

        $ids = array();
        while ($record = $statement->fetch()) {
            $ids[] = (int) $record[$column];
        }

        return $ids;
    }

    /**
     * A number without a decimal part is shown as a whole number, which is what the figures of this
     * plugin almost always are.
     *
     * @param float $number
     * @return string
     */
    private function formatNumber(float $number): string
    {
        if ($number === floor($number) && abs($number) < 1.0e+15) {
            return (string) (int) $number;
        }

        return sprintf('%01.1f', $number);
    }
}
