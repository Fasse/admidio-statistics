{* One cell of the editor's grids, for the parts that are a choice.

   The select of sys-template-parts/form.select.tpl without the form group, the label and the help
   text, for the reason given in plugin.statistics.part.input.tpl. The placeholder option of the core
   partial is left out as well: every select in the grid already offers a real first entry that means
   "all" or "none", so there is nothing to hold the place. *}

<select id="{$data.id}" class="form-select form-select-sm focus-ring {$data.class}"
    {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}"
    {/foreach}>
    {assign "group" ""}
    {foreach $data.values as $optionvar}
        {if {array_key_exists key="group" array=$optionvar} && $optionvar["group"] neq $group}
            {if $group neq ""}</optgroup>{/if}
            {if $optionvar["group"] neq ""}<optgroup label="{$optionvar["group"]|escape}">{/if}
            {assign "group" "{$optionvar["group"]}"}
        {/if}
        <option value="{$optionvar["id"]|escape}"
            {if $data.defaultValue eq $optionvar["id"]}selected="selected"{/if}>{$optionvar["value"]|escape}</option>
    {/foreach}
    {if $group neq ""}</optgroup>{/if}
</select>
