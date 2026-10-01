{* The configuration editor.

   The two grids are built by looking each field up in $elements by the name the presenter generated,
   which is the pattern the category report module uses. Every icon is a nameless submit button: the
   delegated handler in assets/statistics.editor.js writes the action and its target into the three
   hidden fields and lets the form submit itself, so there is no inline JavaScript and no generated
   script per icon as the old editor emitted. A button without a name submits nothing of its own,
   which matters because the form refuses any key it does not know. *}

{if $staHasPreview}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PREVIEW')}</div>
        <div class="card-body">
            {include file='plugin.statistics.result.tpl'}
        </div>
    </div>
{/if}

<form {foreach $attributes as $attribute}{$attribute@key}="{$attribute}" {/foreach}>
    {include file='sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include file='sys-template-parts/form.input.tpl' data=$elements[$staFieldAction]}
    {include file='sys-template-parts/form.input.tpl' data=$elements[$staFieldTarget]}
    {include file='sys-template-parts/form.input.tpl' data=$elements[$staFieldScroll]}

    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('PLG_STATISTICS_GENERAL_INFORMATIONS')}</div>
        <div class="card-body">
            <p>{$l10n->get('PLG_STATISTICS_EDITOR_CONFIG_LOAD_OR_CHANGE')}</p>

            {include file='sys-template-parts/form.select.tpl' data=$elements[$staFieldId]}
            {include file='sys-template-parts/form.input.tpl' data=$elements['statistic_name']}
            {include file='sys-template-parts/form.input.tpl' data=$elements['statistic_title']}
            {include file='sys-template-parts/form.input.tpl' data=$elements['statistic_subtitle']}
            {include file='sys-template-parts/form.select.tpl' data=$elements['statistic_std_role']}

            <div class="offset-sm-3">
                <a class="icon-text-link openPopup" href="javascript:void(0);" data-class="modal-lg"
                   data-href="{$staHelpStandardRole}">
                    <i class="bi bi-info-circle-fill admidio-info-icon"></i>
                    {$l10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC')}
                </a>
            </div>
        </div>
    </div>

    {foreach $staTables as $staTable}
        <div class="card admidio-field-group" id="adm_plg_statistics_table_card_{$staTable.nr}">
            <div class="card-header">
                {$l10n->get('PLG_STATISTICS_XY_TABLE', [$staTable.number])}

                <span class="float-end">
                    {if $staTable.canMoveUp}
                        <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                data-adm-action="{$staActions.moveTableUp}" data-adm-target="{$staTable.target}"
                                title="{$l10n->get('PLG_STATISTICS_MOVE_TABLE_UP')}">
                            <i class="bi bi-arrow-up-circle"></i>
                        </button>
                    {/if}
                    {if $staTable.canMoveDown}
                        <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                data-adm-action="{$staActions.moveTableDown}" data-adm-target="{$staTable.target}"
                                title="{$l10n->get('PLG_STATISTICS_MOVE_TABLE_DOWN')}">
                            <i class="bi bi-arrow-down-circle"></i>
                        </button>
                    {/if}
                    <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                            data-adm-action="{$staActions.duplicateTable}" data-adm-target="{$staTable.target}"
                            title="{$l10n->get('PLG_STATISTICS_DUPLICATE_TABLE')}">
                        <i class="bi bi-copy"></i>
                    </button>
                    {if $staTable.canDelete}
                        <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                data-adm-action="{$staActions.deleteTable}" data-adm-target="{$staTable.target}"
                                title="{$l10n->get('PLG_STATISTICS_DELETE_TABLE')}">
                            <i class="bi bi-trash"></i>
                        </button>
                    {/if}
                </span>
            </div>
            <div class="card-body">
                {include file='sys-template-parts/form.input.tpl' data=$elements[$staTable.title]}
                {include file='sys-template-parts/form.select.tpl' data=$elements[$staTable.role]}
                {include file='sys-template-parts/form.input.tpl' data=$elements[$staTable.first_column_label]}

                <div class="table-responsive">
                    <table class="table table-sm adm-statistics-grid adm-statistics-grid-columns">
                        <thead>
                            <tr>
                                <th class="adm-statistics-grid-head">{$l10n->get('PLG_STATISTICS_COLUMNS')}</th>
                                {foreach $staTable.columns as $staColumn}
                                    <th>{$l10n->get('PLG_STATISTICS_XY_COLUMN', [$staColumn.number])}</th>
                                {/foreach}
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $staColumnParts as $staPart}
                                <tr class="adm-statistics-cell-{$staPart}">
                                    <th class="adm-statistics-grid-head">
                                        {$staColumnPartLabels[$staPart]}
                                        {if isset($staColumnHelp[$staPart])}
                                            <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                               data-class="modal-lg" data-href="{$staColumnHelp[$staPart]}">
                                                <i class="bi bi-info-circle-fill admidio-info-icon"></i>
                                            </a>
                                        {/if}
                                    </th>
                                    {foreach $staTable.columns as $staColumn}
                                        <td>
                                            {if $staColumnPartTypes[$staPart] eq 'input'}
                                                {include file='plugin.statistics.part.input.tpl' data=$elements[$staColumn[$staPart]]}
                                            {else}
                                                {include file='plugin.statistics.part.select.tpl' data=$elements[$staColumn[$staPart]]}
                                            {/if}
                                        </td>
                                    {/foreach}
                                    <td></td>
                                </tr>
                            {/foreach}
                            <tr class="adm-statistics-cell-actions">
                                <th class="adm-statistics-grid-head"></th>
                                {foreach $staTable.columns as $staColumn}
                                    <td>
                                        {if $staColumn.canMoveUp}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.moveColumnUp}" data-adm-target="{$staColumn.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_MOVE_COLUMN_BACKWARDS')}">
                                                <i class="bi bi-arrow-left-circle"></i>
                                            </button>
                                        {/if}
                                        {if $staColumn.canMoveDown}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.moveColumnDown}" data-adm-target="{$staColumn.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_MOVE_COLUMN_FORWARD')}">
                                                <i class="bi bi-arrow-right-circle"></i>
                                            </button>
                                        {/if}
                                        <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                data-adm-action="{$staActions.duplicateColumn}" data-adm-target="{$staColumn.target}"
                                                title="{$l10n->get('PLG_STATISTICS_DUPLICATE_COLUMN')}">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                        {if $staColumn.canDelete}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.deleteColumn}" data-adm-target="{$staColumn.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_DELETE_COLUMN')}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        {/if}
                                    </td>
                                {/foreach}
                                <td>
                                    <button type="submit" class="btn btn-link p-0 border-0 icon-text-link adm-statistics-action"
                                            data-adm-action="{$staActions.addColumn}" data-adm-target="{$staTable.target}">
                                        <i class="bi bi-plus-circle-fill"></i> {$l10n->get('PLG_STATISTICS_ADD_COLUMN')}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm adm-statistics-grid adm-statistics-grid-rows">
                        <thead>
                            <tr>
                                <th class="adm-statistics-grid-head">{$l10n->get('PLG_STATISTICS_ROWS')}</th>
                                {foreach $staRowParts as $staPart}
                                    <th>
                                        {$staRowPartLabels[$staPart]}
                                        {if isset($staRowHelp[$staPart])}
                                            <a class="admidio-icon-link openPopup" href="javascript:void(0);"
                                               data-class="modal-lg" data-href="{$staRowHelp[$staPart]}">
                                                <i class="bi bi-info-circle-fill admidio-info-icon"></i>
                                            </a>
                                        {/if}
                                    </th>
                                {/foreach}
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $staTable.rows as $staRow}
                                <tr>
                                    <th class="adm-statistics-grid-head">{$l10n->get('PLG_STATISTICS_XY_ROW', [$staRow.number])}</th>
                                    {foreach $staRowParts as $staPart}
                                        <td>
                                            {if $staRowPartTypes[$staPart] eq 'input'}
                                                {include file='plugin.statistics.part.input.tpl' data=$elements[$staRow[$staPart]]}
                                            {else}
                                                {include file='plugin.statistics.part.select.tpl' data=$elements[$staRow[$staPart]]}
                                            {/if}
                                        </td>
                                    {/foreach}
                                    <td>
                                        {if $staRow.canMoveUp}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.moveRowUp}" data-adm-target="{$staRow.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_MOVE_ROW_UP')}">
                                                <i class="bi bi-arrow-up-circle"></i>
                                            </button>
                                        {/if}
                                        {if $staRow.canMoveDown}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.moveRowDown}" data-adm-target="{$staRow.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_MOVE_ROW_DOWN')}">
                                                <i class="bi bi-arrow-down-circle"></i>
                                            </button>
                                        {/if}
                                        <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                data-adm-action="{$staActions.duplicateRow}" data-adm-target="{$staRow.target}"
                                                title="{$l10n->get('PLG_STATISTICS_DUPLICATE_ROW')}">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                        {if $staRow.canDelete}
                                            <button type="submit" class="btn btn-link p-0 border-0 admidio-icon-link adm-statistics-action"
                                                    data-adm-action="{$staActions.deleteRow}" data-adm-target="{$staRow.target}"
                                                    title="{$l10n->get('PLG_STATISTICS_DELETE_ROW')}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        {/if}
                                    </td>
                                </tr>
                            {/foreach}
                            <tr>
                                <th class="adm-statistics-grid-head"></th>
                                <td colspan="{$staRowParts|@count + 1}">
                                    <button type="submit" class="btn btn-link p-0 border-0 icon-text-link adm-statistics-action"
                                            data-adm-action="{$staActions.addRow}" data-adm-target="{$staTable.target}">
                                        <i class="bi bi-plus-circle-fill"></i> {$l10n->get('PLG_STATISTICS_ADD_ROW')}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    {/foreach}

    <div class="mb-3">
        <button type="submit" class="btn btn-link p-0 border-0 icon-text-link adm-statistics-action"
                data-adm-action="{$staActions.addTable}" data-adm-target="0">
            <i class="bi bi-plus-circle-fill"></i> {$l10n->get('PLG_STATISTICS_ADD_TABLE')}
        </button>
    </div>

    <div class="adm-statistics-buttons">
        <button type="submit" class="btn btn-primary adm-statistics-action"
                data-adm-action="{$staActions.save}">
            <i class="bi bi-check-lg"></i> {$l10n->get('SYS_SAVE')}
        </button>

        <button type="submit" class="btn btn-secondary adm-statistics-action"
                data-adm-action="{$staActions.saveAs}">
            <i class="bi bi-copy"></i> {$l10n->get('PLG_STATISTICS_SAVE_AS')}
        </button>

        <button type="submit" class="btn btn-secondary adm-statistics-action"
                data-adm-action="{$staActions.preview}">
            <i class="bi bi-bar-chart-line-fill"></i> {$l10n->get('SYS_PREVIEW')}
        </button>

        {if !$staIsNew}
            <button type="submit" class="btn btn-secondary adm-statistics-action"
                    data-adm-action="{$staActions.delete}"
                    data-adm-confirm="{$l10n->get('PLG_STATISTICS_CONFIRM_DELETE_CONFIGURATION', [$staName])|escape}">
                <i class="bi bi-trash"></i> {$l10n->get('SYS_DELETE')}
            </button>
        {/if}

        <a class="btn btn-secondary" href="{$staUrlEditor}">
            <i class="bi bi-file-earmark-plus"></i> {$l10n->get('SYS_CREATE_NEW_CONFIGURATION')}
        </a>

        <a class="btn btn-secondary" href="{$staUrlManual}" target="_blank" rel="noopener">
            <i class="bi bi-book-fill"></i> {$l10n->get('PLG_STATISTICS_OPEN_MANUAL')}
        </a>
    </div>
</form>
