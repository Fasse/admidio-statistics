<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\FormPresenter;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\Service\StatisticFormService;
use AdmidioPlugin\Statistics\Service\StatisticStructureService;
use AdmidioPlugin\Statistics\ValueObject\FieldNames;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;

/**
 * Builds the configuration editor.
 *
 * Every field the user can type in or choose from is registered with the form, which is what puts the
 * list of legal names in the session and lets FormPresenter::validate() police the submitted request.
 * The template then renders the two grids by looking each field up in $elements by the name this
 * class generated, the pattern the category report module uses.
 *
 * The flags that decide which icons a cell shows are computed here, so that the template contains no
 * arithmetic. The old editor worked them out inline with comparisons such as "$cc > 1" and
 * "$rc == $nrOfRows - 2", one of which was off by one.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticEditorPresenter
{
    public const TEMPLATE = 'plugin.statistics.editor.tpl';
    public const FORM_ID = 'adm_plg_statistics_editor_form';

    public const ACTION_PREVIEW = 'preview';
    public const ACTION_SAVE = 'save';
    public const ACTION_SAVE_AS = 'saveas';
    public const ACTION_DELETE = 'delete';

    /**
     * Everything the editor accepts in its action field: the structure operations plus the four that
     * save, copy, delete or compute.
     */
    public const ACTIONS = array(
        self::ACTION_PREVIEW,
        self::ACTION_SAVE,
        self::ACTION_SAVE_AS,
        self::ACTION_DELETE
    );

    /**
     * The chapter of the manual that documents each field. These are the numbers the old editor used
     * in its help links, and help.php still answers them.
     *
     * @var array<string,int>
     */
    private const HELP_IDS = array(
        'standard_role' => 533,
        'table_role' => 542,
        'column_label' => 551,
        'column_profile_field' => 552,
        'column_condition' => 553,
        'column_func_arg' => 554,
        'column_func_main' => 555,
        'column_func_total' => 556,
        'first_column_label' => 561,
        'row_label' => 562,
        'row_profile_field' => 563,
        'row_condition' => 564,
        'preview' => 427
    );

    /**
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @param StatisticDefinition $definition The definition the form is filled from.
     * @param array<int,array{id:int,name:string}> $savedStatistics The statistics of this
     *        organization, offered for loading.
     * @param int $scroll The scroll position to restore, 0 for none.
     * @return FormPresenter
     * @throws Exception
     */
    public static function createForm(
        PagePresenter $page,
        Plugin $plugin,
        StatisticDefinition $definition,
        array $savedStatistics,
        int $scroll
    ): FormPresenter {
        $form = new FormPresenter(
            self::FORM_ID,
            self::TEMPLATE,
            $plugin->getUrl('editor.php'),
            $page
        );

        self::addGeneralFields($form, $definition, $savedStatistics);

        $tables = array();
        $lastTable = count($definition->tables) - 1;

        foreach ($definition->tables as $tableIndex => $table) {
            self::addTableFields($form, $tableIndex, $table->title, $table->roleId, $table->firstColumnLabel);

            $columns = array();
            $lastColumn = count($table->columns) - 1;
            foreach ($table->columns as $columnIndex => $column) {
                self::addColumnFields($form, $tableIndex, $columnIndex, $column);
                $columns[] = array(
                    'nr' => $columnIndex,
                    'number' => $columnIndex + 1,
                    'target' => $tableIndex . ':' . $columnIndex,
                    'canMoveUp' => $columnIndex > 0,
                    'canMoveDown' => $columnIndex < $lastColumn,
                    'canDelete' => $lastColumn > 0,
                    'label' => FieldNames::column($tableIndex, $columnIndex, 'label'),
                    'profile_field' => FieldNames::column($tableIndex, $columnIndex, 'profile_field'),
                    'condition' => FieldNames::column($tableIndex, $columnIndex, 'condition'),
                    'func_arg' => FieldNames::column($tableIndex, $columnIndex, 'func_arg'),
                    'func_main' => FieldNames::column($tableIndex, $columnIndex, 'func_main'),
                    'func_total' => FieldNames::column($tableIndex, $columnIndex, 'func_total')
                );
            }

            $rows = array();
            $lastRow = count($table->rows) - 1;
            foreach ($table->rows as $rowIndex => $row) {
                self::addRowFields($form, $tableIndex, $rowIndex, $row);
                $rows[] = array(
                    'nr' => $rowIndex,
                    'number' => $rowIndex + 1,
                    'target' => $tableIndex . ':' . $rowIndex,
                    'canMoveUp' => $rowIndex > 0,
                    'canMoveDown' => $rowIndex < $lastRow,
                    'canDelete' => $lastRow > 0,
                    'label' => FieldNames::row($tableIndex, $rowIndex, 'label'),
                    'profile_field' => FieldNames::row($tableIndex, $rowIndex, 'profile_field'),
                    'condition' => FieldNames::row($tableIndex, $rowIndex, 'condition')
                );
            }

            $tables[] = array(
                'nr' => $tableIndex,
                'number' => $tableIndex + 1,
                'target' => (string) $tableIndex,
                'canMoveUp' => $tableIndex > 0,
                'canMoveDown' => $tableIndex < $lastTable,
                'canDelete' => $lastTable > 0,
                'title' => FieldNames::table($tableIndex, 'title'),
                'role' => FieldNames::table($tableIndex, 'role'),
                'first_column_label' => FieldNames::table($tableIndex, 'first_column_label'),
                'columns' => $columns,
                'rows' => $rows
            );
        }

        self::addControlFields($form, $scroll);

        /*
         * The buttons are not registered with the form. The core button partial gives a button its
         * own name attribute, so a registered button submits a value of its own and cannot carry the
         * data attributes the click handler reads; every button in this editor has to name the action
         * it stands for, so the template renders them all the same way.
         */

        self::assignVariables($page, $plugin, $definition, $tables);

        return $form;
    }

    /**
     * @param FormPresenter $form
     * @param StatisticDefinition $definition
     * @param array<int,array{id:int,name:string}> $savedStatistics
     * @return void
     * @throws Exception
     */
    private static function addGeneralFields(
        FormPresenter $form,
        StatisticDefinition $definition,
        array $savedStatistics
    ): void {
        global $gL10n;

        $configurations = array(array('', $gL10n->get('SYS_CREATE_NEW_CONFIGURATION')));
        foreach ($savedStatistics as $statistic) {
            $configurations[] = array((string) $statistic['id'], $statistic['name']);
        }

        $form->addSelectBox(
            StatisticFormService::FIELD_ID,
            $gL10n->get('PLG_STATISTICS_STATISTIC'),
            $configurations,
            array(
                'defaultValue' => $definition->id === null ? '' : (string) $definition->id,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addInput(
            StatisticFormService::FIELD_NAME,
            $gL10n->get('SYS_NAME'),
            $definition->name,
            /*
             * Deliberately not marked as required. FormPresenter::validate() enforces a required
             * field on every submit, and most submits here are structure actions - adding a column
             * must not be refused because the statistic has not been named yet. Saving checks the
             * name, which is the moment it has to be there.
             */
            array('maxLength' => 50)
        );
        $form->addInput(
            StatisticFormService::FIELD_TITLE,
            $gL10n->get('PLG_STATISTICS_STATISTICS_TITLE'),
            $definition->title,
            array('maxLength' => 200)
        );
        $form->addInput(
            StatisticFormService::FIELD_SUBTITLE,
            $gL10n->get('PLG_STATISTICS_STATISTICS_SUBTITLE'),
            $definition->subtitle,
            array('maxLength' => 200)
        );
        $form->addSelectBox(
            StatisticFormService::FIELD_STANDARD_ROLE,
            $gL10n->get('PLG_STATISTICS_STATISTICS_STANDARD_ROLE'),
            StatisticFormService::roleOptions('- ' . $gL10n->get('SYS_PLEASE_CHOOSE') . ' -'),
            // Checked when the statistic is saved, for the reason given at the name above.
            array(
                'defaultValue' => (string) $definition->standardRoleId,
                'showContextDependentFirstEntry' => false
            )
        );
    }

    /**
     * @param FormPresenter $form
     * @param int $tableIndex
     * @param string $title
     * @param int|null $roleId
     * @param string $firstColumnLabel
     * @return void
     * @throws Exception
     */
    private static function addTableFields(
        FormPresenter $form,
        int $tableIndex,
        string $title,
        ?int $roleId,
        string $firstColumnLabel
    ): void {
        global $gL10n;

        $form->addInput(
            FieldNames::table($tableIndex, 'title'),
            $gL10n->get('PLG_STATISTICS_TABLE_TITLE'),
            $title,
            array('maxLength' => 200)
        );
        $form->addSelectBox(
            FieldNames::table($tableIndex, 'role'),
            $gL10n->get('PLG_STATISTICS_TABLE_ROLE'),
            StatisticFormService::roleOptions($gL10n->get('PLG_STATISTICS_USE_STANDARD_ROLE')),
            array(
                'defaultValue' => $roleId === null ? '' : (string) $roleId,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addInput(
            FieldNames::table($tableIndex, 'first_column_label'),
            $gL10n->get('PLG_STATISTICS_HEADER'),
            $firstColumnLabel,
            array('maxLength' => 50)
        );
    }

    /**
     * @param FormPresenter $form
     * @param int $tableIndex
     * @param int $columnIndex
     * @param \AdmidioPlugin\Statistics\ValueObject\ColumnDefinition $column
     * @return void
     * @throws Exception
     */
    private static function addColumnFields(
        FormPresenter $form,
        int $tableIndex,
        int $columnIndex,
        \AdmidioPlugin\Statistics\ValueObject\ColumnDefinition $column
    ): void {
        global $gL10n;

        $form->addInput(
            FieldNames::column($tableIndex, $columnIndex, 'label'),
            $gL10n->get('SYS_DESIGNATION'),
            $column->label,
            array('maxLength' => 50)
        );
        $form->addSelectBox(
            FieldNames::column($tableIndex, $columnIndex, 'profile_field'),
            $gL10n->get('PLG_STATISTICS_SELECTION'),
            StatisticFormService::profileFieldOptions($gL10n->get('SYS_ALL')),
            array(
                'defaultValue' => $column->profileFieldId === null ? '' : (string) $column->profileFieldId,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addInput(
            FieldNames::column($tableIndex, $columnIndex, 'condition'),
            $gL10n->get('SYS_CONDITION'),
            $column->condition,
            array('maxLength' => 200)
        );
        $form->addSelectBox(
            FieldNames::column($tableIndex, $columnIndex, 'func_arg'),
            $gL10n->get('PLG_STATISTICS_EVALUATE'),
            StatisticFormService::profileFieldOptions($gL10n->get('PLG_STATISTICS_SELECTION')),
            array(
                'defaultValue' => $column->functionArgument === null ? '' : (string) $column->functionArgument,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addSelectBox(
            FieldNames::column($tableIndex, $columnIndex, 'func_main'),
            $gL10n->get('PLG_STATISTICS_FUNCTION'),
            StatisticFormService::functionOptions(),
            array('defaultValue' => $column->functionName, 'showContextDependentFirstEntry' => false)
        );
        $form->addSelectBox(
            FieldNames::column($tableIndex, $columnIndex, 'func_total'),
            $gL10n->get('PLG_STATISTICS_SUM_FUNCTION'),
            StatisticFormService::totalFunctionOptions($gL10n->get('PLG_STATISTICS_NONE')),
            array('defaultValue' => $column->functionTotal, 'showContextDependentFirstEntry' => false)
        );
    }

    /**
     * @param FormPresenter $form
     * @param int $tableIndex
     * @param int $rowIndex
     * @param \AdmidioPlugin\Statistics\ValueObject\RowDefinition $row
     * @return void
     * @throws Exception
     */
    private static function addRowFields(
        FormPresenter $form,
        int $tableIndex,
        int $rowIndex,
        \AdmidioPlugin\Statistics\ValueObject\RowDefinition $row
    ): void {
        global $gL10n;

        $form->addInput(
            FieldNames::row($tableIndex, $rowIndex, 'label'),
            $gL10n->get('SYS_DESIGNATION'),
            $row->label,
            array('maxLength' => 50)
        );
        $form->addSelectBox(
            FieldNames::row($tableIndex, $rowIndex, 'profile_field'),
            $gL10n->get('PLG_STATISTICS_SELECTION'),
            StatisticFormService::profileFieldOptions($gL10n->get('SYS_ALL')),
            array(
                'defaultValue' => $row->profileFieldId === null ? '' : (string) $row->profileFieldId,
                'showContextDependentFirstEntry' => false
            )
        );
        $form->addInput(
            FieldNames::row($tableIndex, $rowIndex, 'condition'),
            $gL10n->get('SYS_CONDITION'),
            $row->condition,
            array('maxLength' => 200)
        );
    }

    /**
     * The three fields the delegated click handler fills in before it submits the form.
     *
     * @param FormPresenter $form
     * @param int $scroll
     * @return void
     * @throws Exception
     */
    private static function addControlFields(FormPresenter $form, int $scroll): void
    {
        $form->addInput(
            StatisticFormService::FIELD_ACTION,
            '',
            '',
            array('property' => FormPresenter::FIELD_HIDDEN)
        );
        $form->addInput(
            StatisticFormService::FIELD_TARGET,
            '',
            '',
            array('property' => FormPresenter::FIELD_HIDDEN)
        );
        $form->addInput(
            StatisticFormService::FIELD_SCROLL,
            '',
            (string) $scroll,
            array('property' => FormPresenter::FIELD_HIDDEN)
        );
    }

    /**
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @param StatisticDefinition $definition
     * @param array<int,array<string,mixed>> $tables
     * @return void
     * @throws Exception
     */
    private static function assignVariables(
        PagePresenter $page,
        Plugin $plugin,
        StatisticDefinition $definition,
        array $tables
    ): void {
        global $gL10n;

        $page->assignSmartyVariable('staTables', $tables);
        $page->assignSmartyVariable('staIsNew', $definition->isNew());
        $page->assignSmartyVariable('staName', $definition->name);

        $page->assignSmartyVariable('staColumnParts', FieldNames::COLUMN_PARTS);
        $page->assignSmartyVariable('staRowParts', FieldNames::ROW_PARTS);

        /*
         * Which control each part of a column or a row is, so that the template can pick the right
         * partial without asking a PHP function about it.
         */
        $page->assignSmartyVariable('staColumnPartTypes', array(
            'label' => 'input',
            'profile_field' => 'select',
            'condition' => 'input',
            'func_arg' => 'select',
            'func_main' => 'select',
            'func_total' => 'select'
        ));
        $page->assignSmartyVariable('staRowPartTypes', array(
            'label' => 'input',
            'profile_field' => 'select',
            'condition' => 'input'
        ));

        $page->assignSmartyVariable('staColumnPartLabels', array(
            'label' => $gL10n->get('SYS_DESIGNATION'),
            'profile_field' => $gL10n->get('PLG_STATISTICS_SELECTION'),
            'condition' => $gL10n->get('SYS_CONDITION'),
            'func_arg' => $gL10n->get('PLG_STATISTICS_EVALUATE'),
            'func_main' => $gL10n->get('PLG_STATISTICS_FUNCTION'),
            'func_total' => $gL10n->get('PLG_STATISTICS_SUM_FUNCTION')
        ));
        $page->assignSmartyVariable('staRowPartLabels', array(
            'label' => $gL10n->get('SYS_DESIGNATION'),
            'profile_field' => $gL10n->get('PLG_STATISTICS_SELECTION'),
            'condition' => $gL10n->get('SYS_CONDITION')
        ));

        $page->assignSmartyVariable('staColumnHelp', self::helpUrls($plugin, 'column', FieldNames::COLUMN_PARTS));
        $page->assignSmartyVariable('staRowHelp', self::helpUrls($plugin, 'row', FieldNames::ROW_PARTS));
        $page->assignSmartyVariable('staHelpFirstColumnLabel', self::helpUrl($plugin, self::HELP_IDS['first_column_label']));
        $page->assignSmartyVariable('staHelpPreview', self::helpUrl($plugin, self::HELP_IDS['preview']));
        $page->assignSmartyVariable('staHelpTableRole', self::helpUrl($plugin, self::HELP_IDS['table_role']));
        $page->assignSmartyVariable('staHelpStandardRole', self::helpUrl($plugin, self::HELP_IDS['standard_role']));

        $page->assignSmartyVariable('staUrlEditor', $plugin->getUrl('editor.php'));
        $page->assignSmartyVariable('staUrlManual', self::manualUrl($plugin));

        $page->assignSmartyVariable('staActions', array(
            'preview' => self::ACTION_PREVIEW,
            'save' => self::ACTION_SAVE,
            'saveAs' => self::ACTION_SAVE_AS,
            'delete' => self::ACTION_DELETE,
            'addTable' => StatisticStructureService::ADD_TABLE,
            'deleteTable' => StatisticStructureService::DELETE_TABLE,
            'duplicateTable' => StatisticStructureService::DUPLICATE_TABLE,
            'moveTableUp' => StatisticStructureService::MOVE_TABLE_UP,
            'moveTableDown' => StatisticStructureService::MOVE_TABLE_DOWN,
            'addRow' => StatisticStructureService::ADD_ROW,
            'deleteRow' => StatisticStructureService::DELETE_ROW,
            'duplicateRow' => StatisticStructureService::DUPLICATE_ROW,
            'moveRowUp' => StatisticStructureService::MOVE_ROW_UP,
            'moveRowDown' => StatisticStructureService::MOVE_ROW_DOWN,
            'addColumn' => StatisticStructureService::ADD_COLUMN,
            'deleteColumn' => StatisticStructureService::DELETE_COLUMN,
            'duplicateColumn' => StatisticStructureService::DUPLICATE_COLUMN,
            'moveColumnUp' => StatisticStructureService::MOVE_COLUMN_UP,
            'moveColumnDown' => StatisticStructureService::MOVE_COLUMN_DOWN
        ));

        $page->assignSmartyVariable('staFieldAction', StatisticFormService::FIELD_ACTION);
        $page->assignSmartyVariable('staFieldTarget', StatisticFormService::FIELD_TARGET);
        $page->assignSmartyVariable('staFieldScroll', StatisticFormService::FIELD_SCROLL);
        $page->assignSmartyVariable('staFieldId', StatisticFormService::FIELD_ID);
        $page->assignSmartyVariable('staFormId', self::FORM_ID);

        /*
         * The functions that need a profile field, handed to the script so that it can disable the
         * ones that do not apply. The old script hardcoded the positions of the options instead,
         * which welded it to the order the select box happened to be built in: inserting a function
         * would have silently disabled the wrong ones.
         */
        $page->addJavascript(
            'window.admStatisticsArgumentFunctions = '
            . json_encode(StatisticFunction::ARGUMENT_NAMES, JSON_THROW_ON_ERROR) . ';'
            . 'window.admStatisticsEditorUrl = '
            . json_encode($plugin->getUrl('editor.php'), JSON_THROW_ON_ERROR) . ';'
        );
    }

    /**
     * @param Plugin $plugin
     * @param string $kind
     * @param array<int,string> $parts
     * @return array<string,string>
     */
    private static function helpUrls(Plugin $plugin, string $kind, array $parts): array
    {
        $urls = array();
        foreach ($parts as $part) {
            $key = $kind . '_' . $part;
            if (isset(self::HELP_IDS[$key])) {
                $urls[$part] = self::helpUrl($plugin, self::HELP_IDS[$key]);
            }
        }

        return $urls;
    }

    /**
     * @param Plugin $plugin
     * @param int $helpId
     * @return string
     */
    private static function helpUrl(Plugin $plugin, int $helpId): string
    {
        return SecurityUtils::encodeUrl($plugin->getUrl('help.php'), array('help_id' => $helpId));
    }

    /**
     * The manual stays where it has always been, in the resources directory of the plugin, and is
     * addressed through the plugin id rather than through a relative path - a relative path breaks as
     * soon as the pages are served from the modules directory.
     *
     * @param Plugin $plugin
     * @return string
     */
    private static function manualUrl(Plugin $plugin): string
    {
        return ADMIDIO_URL . FOLDER_PLUGINS . '/' . $plugin->id . '/resources/Benutzerhandbuch.pdf';
    }
}
