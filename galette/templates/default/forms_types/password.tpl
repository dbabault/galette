{include
    file="forms_types/input.tpl"
    type="password"
    name=$entry->field_id
    id=$entry->field_id
    required=$entry->required
    disabled=$entry->disabled
    label=$entry->label
    autocomplete="off"
    value=null
}
{include
    file="forms_types/input.tpl"
    type="password"
    name="mdp_adh2"
    id="mdp_adh2"
    required=$entry->required
    disabled=$entry->disabled
    label={_T string="Password confirmation:"}
    autocomplete="off"
    elt_class="labelalign"
    value=null
}
<script type="text/javascript">
    $(function() {
        {include file="js_pwdcheck.tpl" selector="{'#'}{$entry->field_id}"}
    });
</script>
