<?php

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    include('../../../inc/includes.php');
    require_once(__DIR__ . '/../inc/plugin_state.php');
    pgeservicos_require_plugin_active();
}

?>
<div class="grid-stack-item-content form-group mb-3  required" id="form-group-field-1292"><label for="formcreator_field_1292">Telefone de contato <span class="red">*</span></label><div class="form_field"><input type="text" name="formcreator_field_1292" id="formcreator_field_1292_1156152823" value="tel" class="form-control"><script type="text/javascript">
//<![CDATA[

$(function() {
         pluginFormcreatorInitializeField('formcreator_field_1292', '1156152823');
      });

//]]>
</script></div></div>