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
use AdmidioPlugin\Statistics\ValueObject\DateSeries;
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
 * Who is a member is decided for one day, in one place: membershipCondition(). A row or a column may
 * name a role of its own, which then stands in for the role of the table, and a day of its own, which
 * stands in for the day the statistic is shown for. The members of the excluded roles of the statistic
 * are left out on that same day. Only the memberships have a history - a profile value is the one the
 * member has now, whatever day the cell looks at.
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
     * @var array<int,string> Cache of profile field types, keyed by field id.
     */
    private array $fieldTypes = array();

    /**
     * @var array<string,int> Cache of the percentage bases, keyed by the roles and the day.
     */
    private array $populations = array();

    /**
     * @var string The day (Y-m-d) a cell looks at unless its row or its column names another.
     */
    private string $date;

    /**
     * @var array<int,int> The roles whose members are left out.
     */
    private array $excludedRoleIds = array();

    /**
     * @param Database $database
     * @param ProfileFields $profileFields
     * @param Language $l10n
     * @param string $dateFormat The format a reference date is shown in when it serves as a label.
     */
    public function __construct(
        private readonly Database $database,
        private readonly ProfileFields $profileFields,
        private readonly Language $l10n,
        private readonly string $dateFormat = 'Y-m-d'
    ) {
        $this->date = date('Y-m-d');
    }

    /**
     * Compute every table of a statistic.
     *
     * @param StatisticDefinition $definition
     * @param string $asOf The date of the figures, already formatted for display.
     * @param string|null $date The day (Y-m-d) the memberships are looked at wherever a row or a
     *                          column does not name one of its own, **null** for today.
     * @return StatisticResult
     * @throws Exception
     */
    public function evaluate(StatisticDefinition $definition, string $asOf, ?string $date = null): StatisticResult
    {
        $this->date = RowDefinition::toDate($date) ?? date('Y-m-d');
        $this->excludedRoleIds = $definition->excludedRoleIds;
        $this->populations = array();

        $tables = array();
        foreach ($definition->tables as $table) {
            $tables[] = $this->evaluateTable($table, $definition->standardRoleId);
        }

        $excludedRoles = array();
        foreach ($this->excludedRoleIds as $roleId) {
            $name = $this->getRoleNameFromId($roleId);
            if ($name !== '') {
                $excludedRoles[] = $name;
            }
        }

        return new StatisticResult($definition->title, $definition->subtitle, $asOf, $tables, $excludedRoles);
    }

    /**
     * The number of members a role has on the day of the figures, without the members of the
     * excluded roles.
     *
     * @param int $roleId
     * @return int
     * @throws Exception
     */
    public function getUserCountFromRoleId(int $roleId): int
    {
        return count($this->readRoleMemberIds(array($roleId), $this->date));
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

        return $record === false ? '' : Language::translateIfTranslationStrId((string) $record['rol_name']);
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
        $table = $this->expandSeries($table);

        $headers = array($table->firstColumnLabel);
        foreach ($table->columns as $column) {
            $headers[] = $this->label($column->label, $column->roleId, $column->referenceDate);
        }

        $rows = array();
        $numbers = array();

        foreach ($table->rows as $rowIndex => $row) {
            $cells = array($this->label($row->label, $row->roleId, $row->referenceDate));
            foreach ($table->columns as $columnIndex => $column) {
                $cell = $this->evaluateCell($table, $row, $column, $roleId);
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

        /*
         * The line that names the role says something only while a cell counts that role. A table
         * whose rows, or whose columns, each name a role of their own never does.
         */
        $caption = '';
        if ($table->usesTableRole()) {
            $caption = $this->l10n->get('SYS_ROLE') . ' ' . $this->getRoleNameFromId($roleId) . ', '
                . $this->l10n->get('PLG_STATISTICS_XY_ENTRIES', array($this->getUserCountFromRoleId($roleId)));
        }

        return new ResultTable($table->title, $caption, $headers, $rows, $totalRow);
    }

    /**
     * Replace every row and every column that stands for a series of days by one for each day, up
     * to the day the statistic is shown for. Everything after this sees ordinary rows and columns.
     *
     * A series that was given a label carries it in front of each day; one without is named after
     * the day alone, like any other dated row or column. A series that has not begun yet contributes
     * nothing.
     *
     * @param TableDefinition $table
     * @return TableDefinition
     */
    private function expandSeries(TableDefinition $table): TableDefinition
    {
        $expand = function (array $elements): array {
            $expanded = array();
            foreach ($elements as $element) {
                if (!$element->isSeries()) {
                    $expanded[] = $element;
                    continue;
                }

                $dates = DateSeries::dates($element->referenceDate, $element->repeat, $element->repeatMax, $this->date);
                foreach ($dates as $date) {
                    $label = $element->label === ''
                        ? ''
                        : $element->label . ' ' . (new \DateTimeImmutable($date))->format($this->dateFormat);
                    $expanded[] = $element->onDate($date, $label);
                }
            }

            return $expanded;
        };

        return $table->withColumns($expand($table->columns))->withRows($expand($table->rows));
    }

    /**
     * The text of a row or a column. One that was given no label is named after what it selects: its
     * role, its day, or both.
     *
     * @param string $label
     * @param int|null $roleId
     * @param string|null $referenceDate
     * @return string
     * @throws Exception
     */
    private function label(string $label, ?int $roleId, ?string $referenceDate): string
    {
        if ($label !== '') {
            return $label;
        }

        $parts = array();
        if ($roleId !== null) {
            $parts[] = $this->getRoleNameFromId($roleId);
        }
        if ($referenceDate !== null) {
            $parts[] = (new \DateTimeImmutable($referenceDate))->format($this->dateFormat);
        }

        return implode(', ', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    /**
     * The roles a cell counts: the ones its row and its column name, and the role of the table when
     * neither names one. A member has to be in all of them.
     *
     * @param RowDefinition $row
     * @param ColumnDefinition $column
     * @param int $tableRoleId
     * @return array<int,int>
     */
    private function cellRoleIds(RowDefinition $row, ColumnDefinition $column, int $tableRoleId): array
    {
        $roleIds = array();
        if ($row->roleId !== null) {
            $roleIds[] = $row->roleId;
        }
        if ($column->roleId !== null && $column->roleId !== $row->roleId) {
            $roleIds[] = $column->roleId;
        }

        return $roleIds === array() ? array($tableRoleId) : $roleIds;
    }

    /**
     * The number of members a percentage is taken of, for the roles and the day of one cell. When
     * neither the rows nor the columns leave a condition out, it is the number of members that meet
     * at least one of them; otherwise it is all members of those roles, because a row or column
     * without a condition already covers all of them.
     *
     * @param TableDefinition $table
     * @param array<int,int> $roleIds
     * @param string $date
     * @return int
     * @throws Exception
     */
    private function tablePopulation(TableDefinition $table, array $roleIds, string $date): int
    {
        $key = implode(',', $roleIds) . '@' . $date;
        if (array_key_exists($key, $this->populations)) {
            return $this->populations[$key];
        }

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
            $population = count($this->readRoleMemberIds($roleIds, $date));
        } else {
            $population = count($this->selectUserIds($roleIds, $date, $conditions, false));
        }

        return $this->populations[$key] = $population;
    }

    /**
     * One cell: the figure as a number, so that a total can be computed from it, and the figure as
     * the table shows it.
     *
     * @param TableDefinition $table
     * @param RowDefinition $row
     * @param ColumnDefinition $column
     * @param int $tableRoleId
     * @return array{value:float|null,display:string}
     * @throws Exception
     */
    private function evaluateCell(TableDefinition $table, RowDefinition $row, ColumnDefinition $column, int $tableRoleId): array
    {
        $roleIds = $this->cellRoleIds($row, $column, $tableRoleId);
        $date = $row->referenceDate ?? $column->referenceDate ?? $this->date;

        $conditions = array();
        if ($row->profileFieldId !== null) {
            $conditions[] = array('fieldId' => $row->profileFieldId, 'condition' => $row->condition);
        }
        if ($column->profileFieldId !== null) {
            $conditions[] = array('fieldId' => $column->profileFieldId, 'condition' => $column->condition);
        }

        if ($column->functionName === StatisticFunction::COUNT) {
            $count = count($this->selectUserIds($roleIds, $date, $conditions));

            return array('value' => (float) $count, 'display' => (string) $count);
        }

        if ($column->functionName === StatisticFunction::PERCENT) {
            $population = $this->tablePopulation($table, $roleIds, $date);
            $share = $population > 0
                ? 100 / $population * count($this->selectUserIds($roleIds, $date, $conditions))
                : 0.0;

            return array('value' => $share, 'display' => sprintf('%01.1f', $share) . ' %');
        }

        return $this->aggregate($column, $roleIds, $date, $conditions);
    }

    /**
     * The four functions that aggregate the values of a profile field.
     *
     * @param ColumnDefinition $column
     * @param array<int,int> $roleIds
     * @param string $date
     * @param array<int,array{fieldId:int,condition:string}> $conditions
     * @return array{value:float|null,display:string}
     * @throws Exception
     */
    private function aggregate(ColumnDefinition $column, array $roleIds, string $date, array $conditions): array
    {
        if ($column->functionArgument === null) {
            return array('value' => null, 'display' => '');
        }

        $userIds = $this->selectUserIds($roleIds, $date, $conditions);
        if ($userIds === array()) {
            return array('value' => 0.0, 'display' => '0');
        }

        $values = $this->readProfileValues($userIds, $column->functionArgument);
        $type = $this->getProfileFieldType($column->functionArgument);

        if ($type === 'DATE') {
            $values = $this->agesFromDates($values, $date);
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
     * The ids of the members of some roles, on one day, that meet the given conditions.
     *
     * @param array<int,int> $roleIds
     * @param string $date
     * @param array<int,array{fieldId:int,condition:string}> $conditions
     * @param bool $mustMeetAll **true** for the intersection, which is what a cell of a cross table
     *                          is, **false** for the union, which is what a percentage is taken of.
     * @return array<int,int>
     * @throws Exception
     */
    private function selectUserIds(array $roleIds, string $date, array $conditions, bool $mustMeetAll = true): array
    {
        if ($conditions === array()) {
            return $this->readRoleMemberIds($roleIds, $date);
        }

        $sets = array();
        foreach ($conditions as $condition) {
            $sets[] = $this->readUserIdsForCondition($roleIds, $date, $condition['fieldId'], $condition['condition']);
        }

        $result = array_shift($sets);
        foreach ($sets as $set) {
            $result = $mustMeetAll ? array_intersect($result, $set) : array_merge($result, $set);
        }

        return array_values(array_unique($result));
    }

    /**
     * Who counts as a member: the one place that decides it, for every query of this class.
     *
     * A membership counts on a day from its first day to its last, both included, which is how the
     * profile of a member shows it. Version 4.0 asked for a membership without an end instead, so it
     * left out a member whose membership was going to end and counted one whose membership had not
     * begun.
     *
     * A member has to be in every one of the roles on that day, and in none of the excluded roles
     * of the statistic on that day.
     *
     * @param array<int,int> $roleIds At least one role.
     * @param string $date The day, as Y-m-d.
     * @return array{0:string,1:array<int,mixed>} The condition on the members table under its alias
     *         **m**, and the values it binds.
     */
    private function membershipCondition(array $roleIds, string $date): array
    {
        $roleIds = array_values($roleIds);

        $sql = 'm.mem_rol_id = ? AND m.mem_begin <= ? AND m.mem_end >= ?';
        $params = array($roleIds[0] ?? 0, $date, $date);

        foreach (array_slice($roleIds, 1) as $roleId) {
            $sql .= ' AND EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ' o
                                   WHERE o.mem_usr_id = m.mem_usr_id AND o.mem_rol_id = ?
                                     AND o.mem_begin <= ? AND o.mem_end >= ?)';
            array_push($params, $roleId, $date, $date);
        }

        if ($this->excludedRoleIds !== array()) {
            $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . TBL_MEMBERS . ' x
                                       WHERE x.mem_usr_id = m.mem_usr_id
                                         AND x.mem_rol_id IN (' . Database::getQmForValues($this->excludedRoleIds) . ')
                                         AND x.mem_begin <= ? AND x.mem_end >= ?)';
            array_push($params, ...$this->excludedRoleIds);
            array_push($params, $date, $date);
        }

        return array($sql, $params);
    }

    /**
     * @param array<int,int> $roleIds
     * @param string $date
     * @return array<int,int>
     * @throws Exception
     */
    private function readRoleMemberIds(array $roleIds, string $date): array
    {
        list($membership, $params) = $this->membershipCondition($roleIds, $date);

        $sql = 'SELECT DISTINCT m.mem_usr_id
                  FROM ' . TBL_MEMBERS . ' m
                 WHERE ' . $membership;

        return $this->readIds($sql, $params, 'mem_usr_id');
    }

    /**
     * The members that meet one condition. The result is a set, which is what makes the
     * intersection in selectUserIds() correct.
     *
     * @param array<int,int> $roleIds
     * @param string $date
     * @param int $fieldId
     * @param string $condition
     * @return array<int,int>
     * @throws Exception
     */
    private function readUserIdsForCondition(array $roleIds, string $date, int $fieldId, string $condition): array
    {
        list($membership, $params) = $this->membershipCondition($roleIds, $date);
        $params[] = $fieldId;

        if ($this->isKeyword($condition, 'PLG_STATISTICS_MISSING', array('FEHLT', 'MISSING'))) {
            $sql = 'SELECT DISTINCT m.mem_usr_id
                      FROM ' . TBL_MEMBERS . ' m
                     WHERE ' . $membership . '
                       AND m.mem_usr_id NOT IN (SELECT usd_usr_id FROM ' . TBL_USER_DATA . ' WHERE usd_usf_id = ?)';

            return $this->readIds($sql, $params, 'mem_usr_id');
        }

        $sql = 'SELECT DISTINCT m.mem_usr_id
                  FROM ' . TBL_MEMBERS . ' m
            INNER JOIN ' . TBL_USER_DATA . ' ON m.mem_usr_id = usd_usr_id
                 WHERE ' . $membership . ' AND usd_usf_id = ?';

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
     * The ages, in whole years, of a list of dates of birth on the day a cell looks at. A value that is not a date is left out
     * rather than producing a nonsensical age, which is what version 3 did by handing whatever it
     * found to explode().
     *
     * @param array<int,string> $dates
     * @param string $onDate The day (Y-m-d) the ages are taken on.
     * @return array<int,string>
     */
    private function agesFromDates(array $dates, string $onDate): array
    {
        $ages = array();
        $today = new \DateTimeImmutable($onDate);

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
