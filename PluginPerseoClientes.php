<?php
$version_Plugin = '29.0';
//define('PERSEO_DIR_PATH', plugin_dir_path(__FILE__));
////////////////////////////////////////////////////////////////
//////Anadir campos Cedula al registro mediante Woocommerce////
////// de cedula para guardar  y enviar a perseo       /////
/////////////////////////////////////////////////////////////
/*
<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide" >
        <label for="tipoIdentificacion">Tipo</label>
        <select class="woocommerce-Input " id="tipoIdentificacion" name="tipoIdentificacion">
        <option  value="C">RUC</option>
        <option  value="R">Cedula</option>
        <option  value="P">Pasaporte</option>
        </select >
    </p>
*/

function campos_adicionales_registro_usuario()
{

    $PerseoIdentificacion = (isset($_POST['PerseoIdentificacion'])) ? $_POST['PerseoIdentificacion'] : '';
    $PerseoTelefono = (isset($_POST['billing_phone'])) ? $_POST['billing_phone'] : '';
    $PerseoDireccion = (isset($_POST['billing_address_1'])) ? $_POST['billing_address_1'] : '';
?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label><?php esc_html_e('CI / RUC', 'woocommerce'); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" id="PerseoIdentificacion" name="PerseoIdentificacion" size="25" value="<?php echo (!empty($_POST['PerseoIdentificacion'])) ? esc_attr(wp_unslash($_POST['PerseoIdentificacion'])) : ''; ?>" onblur="var cedula = document.getElementById('PerseoIdentificacion').value; if(cedula.length == 13) { var ruc = cedula.substring(0,10); cedula=ruc; } if(cedula.length == 10){ var digito_region = cedula.substring(0,2); if( digito_region >= 1 && digito_region <=24 ){ var ultimo_digito   = cedula.substring(9,10); var pares = parseInt(cedula.substring(1,2)) + parseInt(cedula.substring(3,4)) + parseInt(cedula.substring(5,6)) + parseInt(cedula.substring(7,8));  var numero1 = cedula.substring(0,1);  var numero1 = (numero1 * 2); if( numero1 > 9 ){ var numero1 = (numero1 - 9); }  var numero3 = cedula.substring(2,3);  var numero3 = (numero3 * 2); if( numero3 > 9 ){ var numero3 = (numero3 - 9); } var numero5 = cedula.substring(4,5);  var numero5 = (numero5 * 2);  if( numero5 > 9 ){ var numero5 = (numero5 - 9); }  var numero7 = cedula.substring(6,7);  var numero7 = (numero7 * 2); if( numero7 > 9 ){ var numero7 = (numero7 - 9); } var numero9 = cedula.substring(8,9);  var numero9 = (numero9 * 2);  if( numero9 > 9 ){ var numero9 = (numero9 - 9); }  var impares = numero1 + numero3 + numero5 + numero7 + numero9;  var suma_total = (pares + impares); var primer_digito_suma = String(suma_total).substring(0,1);  var decena = (parseInt(primer_digito_suma) + 1)  * 10;  var digito_validador = decena - suma_total; if(digito_validador == 10) var digito_validador = 0; if(digito_validador == ultimo_digito){  }else{ alert('CI/RUC:' + cedula + ' es incorrecta');  document.getElementById('PerseoIdentificacion').focus();  } } else{  alert('CI/RUC:' + cedula + ' es incorrecta');  document.getElementById('PerseoIdentificacion').focus(); } };">
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_phone"><?php esc_html_e('Telefono', 'woocommerce'); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" id="billing_phone" name="billing_phone" class="woocommerce-Input woocommerce-Input--text input-text" size="25" value="<?php echo esc_attr($PerseoTelefono); ?>">
    </p>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_address_1"><?php esc_html_e('Direccion', 'woocommerce'); ?>&nbsp;<span class="required">*</span></label>
        <input type="text" id="billing_address_1" name="billing_address_1" class="woocommerce-Input woocommerce-Input--text input-text" size="25" value="<?php echo esc_attr($PerseoDireccion); ?>">
    </p>

<?php

}
//////////////////////////////////////////////////
//woocommerce_register_form//Registro en form de woocomerce
add_action('woocommerce_register_form', 'campos_adicionales_registro_usuario', 10, 3);


///////////////////////////////////////////////////////////
///////////////Validar Campos antes de guardarlos/////////
/////////////////////////////////////////////////////////
function validar_datos_usuario($errors, $sanitized_user_login, $user_email)
{
    global $wpdb;
    global $table_prefix;
    if (empty($_POST['PerseoIdentificacion'])) {
        $errors->add('PerseoIdentificacion_error', __(' Identificacion no puede estar vacia', 'textdomain'));
    } else {
        //verificar si ya existe esta cedula 
        $perseo_IngresoIdent = $wpdb->get_var("SELECT user.user_id as id FROM {$table_prefix}usermeta as user where user.meta_key='PerseoIdentificacion' and user.meta_value like '%" . $_POST['PerseoIdentificacion'] . "%'");
        //$errors->add('PerseoIdentificacion_error', __('<strong>Error</strong>: Identificacion ya existe-'.$perseo_IngresoIdent, 'textdomain'));
        if ($perseo_IngresoIdent <> "") {
            $errors->add('PerseoIdentificacion_error', __(' Identificacion ya existe', 'textdomain'));
        }
    }
    if (empty($_POST['billing_phone'])) {
        $errors->add('billing_phone_error', __(' Telefono no puede estar vacia', 'textdomain'));
    }
    if (empty($_POST['billing_address_1'])) {
        $errors->add('billing_address_1_error', __(' Direccion no puede estar vacia', 'textdomain'));
    }
    return $errors;
}


add_filter('woocommerce_registration_errors', 'validar_datos_usuario', 10, 3);


///////////////////////////////////////////////////////////
////////////////guardar datos ////////////////////////////
/////////////////////////////////////////////////////////

function guardar_campos_adicionales_usuario($user_id)
{
    if (isset($_POST['PerseoIdentificacion'])) {
        update_user_meta($user_id, 'PerseoIdentificacion', sanitize_text_field($_POST['PerseoIdentificacion']));
    }
    if (isset($_POST['billing_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
    }
    if (isset($_POST['billing_address_1'])) {
        update_user_meta($user_id, 'billing_address_1', sanitize_text_field($_POST['billing_address_1']));
    }
}
add_action('user_register', 'guardar_campos_adicionales_usuario');


/////////////////////////////////////////////////////////////
//Añadimos los campos en una nueva sección de person/////////
add_action('show_user_profile', 'fperseo_agregarcamposseccion');
add_action('edit_user_profile', 'fperseo_agregarcamposseccion');

function fperseo_agregarcamposseccion($user)
{
?>
    <h3><?php _e('Datos Perseo Software'); ?></h3>

    <table class="form-table">
        <tr>
            <th>
                <label for="PerseoCodigo"><?php _e('PerseoCodigo'); ?></label>
            </th>
            <td>
                <input type="text" name="PerseoCodigo" id="PerseoCodigo" class="regular-text" value="<?php echo esc_attr(get_the_author_meta('PerseoCodigo', $user->ID)); ?>" />
                <p class="description"><?php _e('ID de Perseo Software'); ?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="PerseoID"><?php _e('PerseoID'); ?></label>
            </th>
            <td>
                <input type="text" name="PerseoID" id="PerseoID" class="regular-text" value="<?php echo esc_attr(get_the_author_meta('PerseoID', $user->ID)); ?>" />
                <p class="description"><?php _e('Codigo de Perseo Software'); ?></p>
            </td>
        </tr>
        <tr>
            <th>
                <label for="PerseoIdentificacion">CI / RUC</label>
            </th>
            <td>
                <input type="text" name="PerseoIdentificacion" id="PerseoIdentificacion" class="regular-text" value="<?php echo esc_attr(get_the_author_meta('PerseoIdentificacion', $user->ID)); ?>" />
                <p class="description"><?php _e('Cedula o RUC de Perseo Software'); ?></p>
            </td>
        </tr>
    </table>
<?php }

//Guardamos los nuevos campos
add_action('personal_options_update', 'fperseo_grabarcamposseccion');
add_action('edit_user_profile_update', 'fperseo_grabarcamposseccion');

function fperseo_grabarcamposseccion($perseo_userid)
{

    if (!current_user_can('edit_user', $perseo_userid)) {
        return false;
    }
    if (isset($_POST['PerseoCodigo'])) {
        $perseo_codigo = sanitize_text_field($_POST['PerseoCodigo']);
        update_user_meta($perseo_userid, 'PerseoCodigo', $perseo_codigo);
    }
    if (isset($_POST['PerseoID'])) {
        $perseo_id = sanitize_text_field($_POST['PerseoID']);
        update_user_meta($perseo_userid, 'PerseoID', $perseo_id);
    }
    if (isset($_POST['PerseoIdentificacion'])) {
        $perseo_identificacion = sanitize_text_field($_POST['PerseoIdentificacion']);
        update_user_meta($perseo_userid, 'PerseoIdentificacion', $perseo_identificacion);
    }
}


?>