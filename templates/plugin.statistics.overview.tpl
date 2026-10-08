{if count($statistics) === 0}
    <div class="alert alert-info">{$l10n->get('PLG_STATISTICS_NO_DEFINITIONS_FOUND')}</div>
{else}
    <p>{$l10n->get('PLG_STATISTICS_OVERVIEW_DESC')}</p>

    <div class="table-responsive">
        <table id="adm_plg_statistics_overview_table" class="table table-hover">
            <thead>
                <tr>
                    <th>{$l10n->get('SYS_NAME')}</th>
                </tr>
            </thead>
            <tbody>
                {foreach $statistics as $statistic}
                    <tr>
                        <td><a href="{$statistic.url}">{$statistic.name|escape}</a></td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
{/if}
