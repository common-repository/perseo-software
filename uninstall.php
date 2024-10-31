<?php
if (!defined('WP_UNINSTALL_PLUGIN')){
    exit();
}
$Perseo_option1 = 'pluginperseo_configuracion';
$Perseo_option2 = 'pluginperseo_parametros';
delete_option($Perseo_option1);
delete_option($Perseo_option2);
