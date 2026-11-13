{* One computed statistic: the subtitle, the date of the figures and one table per configured table.
   Every value that a user typed is escaped here - the old plugin wrote titles, labels and text
   results into the page as they were. *}

{if $staDateForm !== null}
    {* Not a form of the form presenter: it changes nothing, it only asks for the same page again. *}
    <form id="adm_plg_statistics_date_form" class="row g-2 align-items-center admidio-margin-bottom"
          method="get" action="{$staDateForm.url|escape}">
        <input type="hidden" name="sta_id" value="{$staDateForm.statisticId}">
        <div class="col-auto">
            <label class="col-form-label" for="adm_plg_statistics_date">{$l10n->get('PLG_STATISTICS_REFERENCE_DATE')}</label>
        </div>
        <div class="col-auto">
            <input type="date" class="form-control focus-ring" id="adm_plg_statistics_date" name="date"
                   value="{$staDateForm.date|escape}" required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">{$l10n->get('SYS_APPLY')}</button>
        </div>
    </form>
{/if}

<div class="admidio-content-header">
    {if $staSubtitle neq ''}
        <h2 id="adm_plg_statistics_subtitle">{$staSubtitle|escape}</h2>
    {/if}
    <span id="adm_plg_statistics_as_of">{$l10n->get('PLG_STATISTICS_AS_OF_YX', [$staAsOf])|escape}</span>
    {if $staExcludedRoles neq ''}
        <br><span id="adm_plg_statistics_excluded_roles">{$l10n->get('PLG_STATISTICS_WITHOUT_MEMBERS_OF', [$staExcludedRoles])|escape}</span>
    {/if}
</div>

{foreach $staResultTables as $staTable}
    <div class="admidio-margin-bottom">
        {if $staTable.title neq ''}
            <h3>{$staTable.title|escape}</h3>
        {/if}

        {if $staTable.caption neq ''}
            <p>{$staTable.caption|escape}</p>
        {/if}

        <div class="table-responsive">
            <table id="{$staTable.id}" class="table{if $staPrintMode} table-sm table-striped{else} table-hover{/if}">
                <thead>
                    <tr>
                        {foreach $staTable.headers as $staHeader}
                            <th>{$staHeader|escape}</th>
                        {/foreach}
                    </tr>
                </thead>
                <tbody>
                    {foreach $staTable.rows as $staRow}
                        <tr>
                            {foreach $staRow as $staCell}
                                <td>{$staCell|escape}</td>
                            {/foreach}
                        </tr>
                    {/foreach}
                </tbody>
                {if $staTable.totalRow !== null}
                    {* The total belongs in the footer, so that sorting the table leaves it alone. *}
                    <tfoot>
                        <tr>
                            {foreach $staTable.totalRow as $staCell}
                                <th>{$staCell|escape}</th>
                            {/foreach}
                        </tr>
                    </tfoot>
                {/if}
            </table>
        </div>
    </div>
{/foreach}
