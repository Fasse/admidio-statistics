{* One computed statistic: the subtitle, the date of the figures and one table per configured table.
   Every value that a user typed is escaped here - the old plugin wrote titles, labels and text
   results into the page as they were. *}

<div class="admidio-content-header">
    {if $staSubtitle neq ''}
        <h2 id="adm_plg_statistics_subtitle">{$staSubtitle|escape}</h2>
    {/if}
    <span id="adm_plg_statistics_as_of">{$l10n->get('PLG_STATISTICS_AS_OF_YX', [$staAsOf])|escape}</span>
</div>

{foreach $staResultTables as $staTable}
    <div class="admidio-margin-bottom">
        {if $staTable.title neq ''}
            <h3>{$staTable.title|escape}</h3>
        {/if}

        <p>{$staTable.caption|escape}</p>

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
