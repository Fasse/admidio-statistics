{* The widget of the overview page: the statistics that are marked for it, in a compact form. The
   tables are neither sortable nor paged here, and a table that is wider than the widget scrolls
   inside its own container. The heading of each statistic leads to the page that shows it in full. *}

<div id="plugin-{$staWidgetName}" class="admidio-plugin-content">
    {foreach $staWidgetStatistics as $staStatistic}
        <h3><a href="{$staStatistic.url}">{$staStatistic.title|escape}</a></h3>

        {if $staStatistic.subtitle neq ''}
            <p>{$staStatistic.subtitle|escape}</p>
        {/if}

        {foreach $staStatistic.tables as $staTable}
            {if $staTable.title neq ''}
                <h5>{$staTable.title|escape}</h5>
            {/if}

            <div class="table-responsive">
                <table class="table table-sm">
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
        {/foreach}
    {/foreach}
</div>
