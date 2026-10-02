<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Language;
use Admidio\Roles\Service\RolesService;
use Admidio\UI\Presenter\FormPresenter;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\FieldNames;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

/**
 * Translates between the editor's form and a statistic definition.
 *
 * Reading the form back is where version 3 was at its weakest, and the direction of the fix matters
 * more than the detail: **the submitted request does not get to say what the form contained.** The
 * page that rendered the form registered every field with it and put the form in the session, so the
 * list of legal field names is the server's. fromFormValues() walks that list and reads the value of
 * each name it finds, and FormPresenter::validate() has already thrown out any submitted key that is
 * not in it.
 *
 * Version 3 instead read three hidden counters - nr_of_tables and, per table, the number of rows and
 * the number of columns - and looped over them. They had no upper bound, so nr_of_tables=100000 made
 * the server build a hundred thousand tables and insert them. Those counters do not exist any more.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticFormService
{
    public const FIELD_ID = 'statistic_id';
    public const FIELD_NAME = 'statistic_name';
    public const FIELD_TITLE = 'statistic_title';
    public const FIELD_SUBTITLE = 'statistic_subtitle';
    public const FIELD_STANDARD_ROLE = 'statistic_std_role';

    public const FIELD_ACTION = 'adm_statistics_action';
    public const FIELD_TARGET = 'adm_statistics_target';
    public const FIELD_SCROLL = 'adm_statistics_scroll';

    /**
     * Rebuild a definition from what was submitted.
     *
     * @param FormPresenter $form The form as the session kept it, which is the list of legal names.
     * @param array<string,mixed> $values The values FormPresenter::validate() returned.
     * @param int $orgId
     * @return StatisticDefinition
     */
    public static function fromFormValues(FormPresenter $form, array $values, int $orgId): StatisticDefinition
    {
        return self::fromFieldNames(array_keys($form->getElements()), $values, $orgId);
    }

    /**
     * The same, from the list of field names on its own.
     *
     * Only the caller above produces that list, and it takes it from the form object, never from the
     * request. Keeping the two apart is what lets this be tested without a bootstrapped form.
     *
     * @param array<int,string> $fieldNames Every name the form registered.
     * @param array<string,mixed> $values
     * @param int $orgId
     * @return StatisticDefinition
     */
    public static function fromFieldNames(array $fieldNames, array $values, int $orgId): StatisticDefinition
    {
        $raw = array();

        foreach ($fieldNames as $elementId) {
            $part = FieldNames::parse((string) $elementId);
            if ($part === null) {
                continue;
            }

            $value = (string) ($values[$elementId] ?? '');

            if ($part['kind'] === FieldNames::KIND_TABLE) {
                $raw[$part['table']]['table'][$part['part']] = $value;
            } elseif ($part['kind'] === FieldNames::KIND_COLUMN) {
                $raw[$part['table']]['columns'][$part['index']][$part['part']] = $value;
            } else {
                $raw[$part['table']]['rows'][$part['index']][$part['part']] = $value;
            }
        }

        ksort($raw, SORT_NUMERIC);

        $tables = array();
        foreach ($raw as $table) {
            $tables[] = self::buildTable($table);
        }

        $id = (int) ($values[self::FIELD_ID] ?? 0);

        return new StatisticDefinition(
            $id > 0 ? $id : null,
            $orgId,
            trim((string) ($values[self::FIELD_NAME] ?? '')),
            (string) ($values[self::FIELD_TITLE] ?? ''),
            (string) ($values[self::FIELD_SUBTITLE] ?? ''),
            (int) ($values[self::FIELD_STANDARD_ROLE] ?? 0),
            $tables
        );
    }

    /**
     * The two values a statistic cannot be stored without.
     *
     * They are checked here rather than marked as required fields, because the form is submitted for
     * every structure action too and adding a column must not be refused because the statistic has
     * not been named yet.
     *
     * @param StatisticDefinition $definition
     * @return void
     * @throws Exception SYS_FIELD_EMPTY naming the field that is missing.
     */
    public static function assertSaveable(StatisticDefinition $definition): void
    {
        global $gL10n;

        if ($definition->name === '') {
            throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('SYS_NAME')));
        }

        if ($definition->standardRoleId <= 0) {
            throw new Exception('SYS_FIELD_EMPTY', array($gL10n->get('PLG_STATISTICS_STATISTICS_STANDARD_ROLE')));
        }
    }

    /**
     * The target of a structure action, as the editor's hidden field carries it: the table on its
     * own, or the table and the row or column separated by a colon.
     *
     * @param string $target
     * @return array{0:int,1:int} The table index and the element index, the latter -1 when the
     *         action works on a whole table.
     * @throws Exception SYS_INVALID_PAGE_VIEW when the field does not have that shape.
     */
    public static function parseTarget(string $target): array
    {
        if (preg_match('/^(0|[1-9][0-9]*)(?::(0|[1-9][0-9]*))?$/', $target, $matches) !== 1) {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }

        return array((int) $matches[1], isset($matches[2]) ? (int) $matches[2] : -1);
    }

    /**
     * The roles the current user may pick, grouped by their category.
     *
     * RolesService::findAll() already restricts the list to the active roles of the current
     * organization that the user is allowed to view, and leaves the event categories out - the same
     * three conditions the old plugin wrote into a query of its own.
     *
     * @param string $firstEntry The label of the entry that selects no role at all.
     * @return array<int,array<int,string>> Triples of value, label and group, which is the shape
     *         FormPresenter turns into option groups.
     * @throws Exception
     */
    public static function roleOptions(string $firstEntry): array
    {
        global $gDb;

        $options = array(array('', $firstEntry, ''));

        foreach ((new RolesService($gDb))->findAll() as $role) {
            $options[] = array(
                (string) $role['rol_id'],
                Language::translateIfTranslationStrId((string) $role['rol_name']),
                Language::translateIfTranslationStrId((string) $role['cat_name'])
            );
        }

        return $options;
    }

    /**
     * The first role the current user may pick, for the example statistics: they have to count
     * some role, and asking the editor for the one in its form would tie them to whatever statistic
     * happened to be open.
     *
     * @return int|null **null** when this user may see no role at all.
     * @throws Exception
     */
    public static function firstSelectableRoleId(): ?int
    {
        global $gDb;

        foreach ((new RolesService($gDb))->findAll() as $role) {
            $id = (int) $role['rol_id'];
            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /**
     * The profile fields the current user may pick, grouped by their category.
     *
     * A hidden field is offered only to a user who may see hidden profile data, which is the rule
     * the old editor applied as well.
     *
     * @param string $firstEntry The label of the entry that selects no field at all.
     * @return array<int,array<int,string>>
     * @throws Exception
     */
    public static function profileFieldOptions(string $firstEntry): array
    {
        global $gProfileFields, $gCurrentUser;

        $options = array(array('', $firstEntry, ''));

        foreach ($gProfileFields->getProfileFields() as $field) {
            if ((int) $field->getValue('usf_hidden') !== 0 && !$gCurrentUser->isAdministratorUsers()) {
                continue;
            }

            $options[] = array(
                (string) $field->getValue('usf_id'),
                Language::translateIfTranslationStrId((string) $field->getValue('usf_name')),
                Language::translateIfTranslationStrId((string) $field->getValue('cat_name'))
            );
        }

        return $options;
    }

    /**
     * The functions a column can apply, as a select box offers them.
     *
     * @return array<int,array<int,string>>
     */
    public static function functionOptions(): array
    {
        $options = array();
        foreach (StatisticFunction::NAMES as $name) {
            $options[] = array($name, $name);
        }

        return $options;
    }

    /**
     * The functions a column can apply to its own cells, with the entry that means no total.
     *
     * @param string $noneLabel
     * @return array<int,array<int,string>>
     */
    public static function totalFunctionOptions(string $noneLabel): array
    {
        $options = array();
        foreach (StatisticFunction::TOTAL_NAMES as $name) {
            $options[] = array($name, $name === '' ? $noneLabel : $name);
        }

        return $options;
    }

    /**
     * @param array<string,array<mixed>> $table
     * @return TableDefinition
     */
    private static function buildTable(array $table): TableDefinition
    {
        $meta = $table['table'] ?? array();

        $columns = array();
        $rawColumns = $table['columns'] ?? array();
        ksort($rawColumns, SORT_NUMERIC);
        foreach ($rawColumns as $column) {
            $function = (string) ($column['func_main'] ?? '');

            $columns[] = new ColumnDefinition(
                (string) ($column['label'] ?? ''),
                RowDefinition::toFieldId($column['profile_field'] ?? null),
                (string) ($column['condition'] ?? ''),
                StatisticFunction::isValid($function) ? $function : StatisticFunction::COUNT,
                RowDefinition::toFieldId($column['func_arg'] ?? null),
                StatisticFunction::isValidTotal((string) ($column['func_total'] ?? ''))
                    ? (string) ($column['func_total'] ?? '')
                    : ''
            );
        }

        $rows = array();
        $rawRows = $table['rows'] ?? array();
        ksort($rawRows, SORT_NUMERIC);
        foreach ($rawRows as $row) {
            $rows[] = new RowDefinition(
                (string) ($row['label'] ?? ''),
                RowDefinition::toFieldId($row['profile_field'] ?? null),
                (string) ($row['condition'] ?? '')
            );
        }

        $role = (int) ($meta['role'] ?? 0);

        return new TableDefinition(
            (string) ($meta['title'] ?? ''),
            $role > 0 ? $role : null,
            (string) ($meta['first_column_label'] ?? ''),
            $columns,
            $rows
        );
    }
}
