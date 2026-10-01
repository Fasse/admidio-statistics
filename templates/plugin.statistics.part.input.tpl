{* One cell of the editor's grids.

   This is the input of sys-template-parts/form.input.tpl with the Bootstrap form group, the label
   and the help text left out. A grid of six parts by N columns is around two hundred cells, and a
   form group per cell does not fit; the vertical form type of the core partial drops the row wrapper
   but still emits a label in every cell, which is why this partial exists instead. Everything that
   matters - the name, the value, the attributes - still comes from the form element, so the form
   object remains the single authority over what may be submitted. *}

<input id="{$data.id}" name="{$data.id}" class="form-control form-control-sm focus-ring {$data.class}"
    type="{$data.type}" value="{$data.value}"
    {foreach $data.attributes as $itemvar}
        {$itemvar@key}="{$itemvar}"
    {/foreach}
>
