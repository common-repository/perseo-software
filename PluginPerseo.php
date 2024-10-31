<?php
/*
Plugin Name: Perseo Software
Plugin URI: https://perseo.ec/
Description: Este Plugins integra el Sistema Contable Perseo Web y PC con la tienda Woocommerce
Version: 29.0
Author: Perseo Soft S.A. - Ecuador
Author URI: https://perseo.ec
License: GPL2
License URI:  http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: PluginPerseo
Domain Path: /lenguajes/
*/

$version_Plugin = '29.0';
define('PERSEO_DIR_PATH', plugin_dir_path(__FILE__));
define('PERSEOCONFIGBASE', get_option('pluginperseo_configuracion'));
define('PERSEOCONFIGPARAMETROS', get_option('pluginperseo_parametros'));

function pluginperseo_install()
{
    //Accion ejecutar
    //  require_once 'Activador.php';
}
function pluginperseo_desactivation()
{
    //flush_rewrite_rules();

    //Borrar conffiguraciones
    //elimina el cron
    wp_clear_scheduled_hook('intervalo_perseo', 'perseo_cron');
    wp_unschedule_event(time(), 'intervalo_perseo');

    // Eliminar variables
    $Perseo_option2 = 'pluginperseo_parametros';
    delete_option($Perseo_option2);
}

function pluginperseo_desinstall()
{
    global $wpdb;
    global $table_prefix;
    //Borrar conffiguraciones
    //elimina el cron
    wp_clear_scheduled_hook('intervalo_perseo', 'perseo_cron');
    wp_unschedule_event(time(), 'intervalo_perseo');

    // Eliminar variables
    $wpdb->query("Delete from {$table_prefix}wp_options where option_name ='pluginperseo_configuracion'");

    $wpdb->query(" Delete from {$table_prefix}wp_options where option_name ='pluginperseo_parametros' ");
}

register_activation_hook(__FILE__, 'pluginperseo_install');
register_deactivation_hook(__FILE__, 'pluginperseo_desactivation');
register_uninstall_hook(__FILE__, 'pluginperseo_desinstall.php');

if (!function_exists('fperseo_pluginscargardos')) {
    add_action('plugins_loaded', 'fperseo_pluginscargardos');
    function fperseo_pluginscargardos()
    {
        if (current_user_can('edit_pages')) {
            if (!function_exists('fperseo_addmetadescripction')) {
                add_action('wp_head', 'fperseo_addmetadescripction');
                function fperseo_addmetadescripction()
                {
                    echo " <meta name='description' content='Creacion de plugin perseo ' > ";
                }
            }
        }
    }
}

///////////////////////////////////////////////////////
//////*Creamos el menu Perseo Software*/
if (!function_exists('fperseo_paginaperseo')) {
    add_action('admin_menu', 'fperseo_paginaperseo');
    /*Creamos la funcion de menu  */
    function fperseo_paginaperseo()
    {
        add_menu_page(
            'Perseo Software',
            'Perseo Software',
            'manage_options',
            'mperseo_menupage',
            'mperseo_paginaperseosoftware',
            ''
        );
        /** Creamos el submenu */
        add_submenu_page(
            'mperseo_menupage',
            'Parametrizacion',
            'Parametrizacion',
            'manage_options',
            '',
            'mperseo_paginaparametrizacion'
        );
    }
}

/////////////////////////////////////////////////////////////////////////
/** Saber si existe mi pagina  principal CRON*/
if (!function_exists('mperseo_paginaperseosoftware')) {
    function mperseo_paginaperseosoftware()
    {
        //permisos de usuario administrador
        if (current_user_can('manage_options')) {
            //validacmos si esta guardado correctamente
            if (isset($_GET['settings-updated'])) {
                add_settings_error(
                    'eperseo_verificacion',
                    'eperseo_verificacion',
                    'Guardado correctamente',
                    'updated'
                );
            }
            settings_errors('eperseo_verificacion');
            ///////////////////////////////////////////////////////////////////////
            //llamamos a la pag de secciones 
            //validamos el formulario y enviamos valores para redirigir a la pag perseo
            echo "<form action='options.php' method='post'> ";
            settings_fields('pluginperseo_menu');
            do_settings_sections('pluginperseo_menu');
            submit_button('Guardar Cambios');
            //submit_button('Guardar Cambios','primary','submit', true, array('id'=>'perseoconexion', 'onclick'=>'perseotestconec()')); 
            echo "</form>";
            ///////////////////////////////////////////////////////////////////////
            //TEST de conexion 
            //echo "<p id='perseovalidarconexion'></p>";  
        }
    }
}

///////////////////////////////////////////////////////////////////////////
/** Saber si existe mi pagina submenu  */
if (!function_exists('mperseo_paginaparametrizacion')) {
    function mperseo_paginaparametrizacion()
    {
        ///////////////////////////////////////
        //permisos de usuario administrador
        if (current_user_can('manage_options')) {
            //validacmos si esta guardado correctamente
            if (isset($_GET['settings-updated'])) {
                add_settings_error(
                    'eperseo_verificacion',
                    'eperseo_verificacion',
                    'Guardado correctamente',
                    'updated'
                );
            }
            settings_errors('eperseo_verificacion');
            ///////////////////////////////////////////////////////////////////////
            //llamamos a la pag de secciones 
            //validamos el formulario y enviamos valores para redirigir a la pag perseo
            echo "<form action='options.php' method='post'   > ";
            settings_fields('pluginperseo_submenu');
            do_settings_sections('pluginperseo_submenu');
            $perseo_config  = PERSEOCONFIGBASE; //VARIABLE DEFINIDA DE CONFIGURACION BASE DE DATOS
            if (!empty($perseo_config['perseotoken'])) {
                submit_button('Guardar Cambios Parametrizacion', 'primary', 'submit', true, array('onclick' => 'perseoprogress()'));
            } else {
                submit_button('Guardar Cambios Parametrizacion', 'primary', 'submit', true, 'disabled');
            }
            echo "</form>";
        }
    }
}

//////////////////////////////////////////////////////////////////////
/// Api settings
/// permiso para guardar configuraciones 
function fperseo_plugininicio()
{

    //registrando una configuracion en la pag general
    register_setting('pluginperseo_menu', 'pluginperseo_configuracion');
    register_setting('pluginperseo_submenu', 'pluginperseo_parametros');

    ////////////////////////////////////////////////////////////
    //agrega una seccion de configuracion
    add_settings_section(
        'sperseo_seccionconfiguracion',
        'Perseo Software',
        'sperseo_seccionencabezado',
        'pluginperseo_menu'
    );
    add_settings_section(
        'sperseo_seccionconfiguracion',
        'Parametrizacion ',
        'sperseo_seccionencabezado1',
        'pluginperseo_submenu'
    );

    //////////////////////////////////////////////////////////
    //Agregar campos primera seccion configuracion
    add_settings_field(
        'fperseo_campotiposoftware',
        'Conexion Software',
        'fperseo_tiposoftware',
        'pluginperseo_menu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseotiposoftware',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseotiposoftware'
        ]
    );

    add_settings_field(
        'fperseo_campocertificado',
        'Certificado',
        'fperseo_certificado',
        'pluginperseo_menu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseocertificado',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseocertificado'
        ]
    );


    add_settings_field(
        'fperseo_campoip',
        'IP/Dominio',
        'fperseo_ip',
        'pluginperseo_menu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoip',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoip'
        ]
    );
    add_settings_field(
        'fperseo_campotoken',
        'Token',
        'fperseo_token',
        'pluginperseo_menu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseotoken',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseotoken'
        ]
    );
    add_settings_field(
        'fperseo_camposervidor',
        'Servidor',
        'fperseo_servidor',
        'pluginperseo_menu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoservidor',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoservidor'
        ]
    );

    ////////////////////////////////////////////////////////
    //Campo seccion 2 parametrizacion
    add_settings_field(
        'fperseo_campoimpuestos',
        'Sincronizar Impuestos',
        'fperseo_impuestos',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoimpuestos',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoimpuestos'
        ]
    );
    add_settings_field(
        'fperseo_campoproductos',
        'Sincronizar Productos',
        'fperseo_productos',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoproductos',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoproductos'
        ]
    );
    add_settings_field(
        'fperseo_campoimagenes',
        'Sincronizar Imagenes Productos',
        'fperseo_imagenes',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoimagenes',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoimagenes'
        ]
    );
    add_settings_field(
        'fperseo_campostock',
        'Sincronizar Stock',
        'fperseo_stock',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseostock',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseostock'
        ]
    );
    add_settings_field(
        'fperseo_campoclientes',
        'Sincronizar Clientes',
        'fperseo_clientes',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoclientes',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoclientes'
        ]
    );


    add_settings_field(
        'fperseo_campoenviarclientes',
        'Enviar Clientes a Perseo',
        'fperseo_enviarclientes',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoenviarclientes',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoenviarclientes'
        ]
    );
    add_settings_field(
        'fperseo_campopedidos',
        'Enviar Pedidos a Perseo',
        'fperseo_pedidos',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseopedido',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseopedido'
        ]
    );

    add_settings_field(
        'fperseo_campocategorias',
        'Origen de datos Categoria',
        'fperseo_categorias',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseocategorias',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseocategorias'
        ]
    );

    add_settings_field(
        'fperseo_campoexistencias',
        'Productos Existencias',
        'fperseo_existencias',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseoexistencias',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseoexistencias'
        ]
    );
    add_settings_field(
        'fperseo_camposincronizar',
        'Tiempo en minutos para sincronizar',
        'fperseo_sincronizar',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseosincronizar',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseosincronizar'
        ]
    );
    add_settings_field(
        'fperseo_campotarifaventa',
        'Precio Normal',
        'fperseo_tarifaventa',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseotarifaVenta',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseotarifaVenta'
        ]
    );
    add_settings_field(
        'fperseo_campotarifaAumento',
        'Precio Rebajado',
        'fperseo_tarifaAumento',
        'pluginperseo_submenu',
        'sperseo_seccionconfiguracion',
        [
            'label_for' => 'perseotarifaAumento',
            'class' => 'clase_campo',
            'perseo_datopersonalizado' => 'Valor perseotarifaAumento'
        ]
    );
};

add_action('admin_init', 'fperseo_plugininicio');

function sperseo_seccionencabezado()
{
    echo "<p><b>Sincronizacion de Perseo Software a WordPress :</b><br> Clientes, Productos, Productos Categorias , Productos Imagenes y  Productos Stock. </p>";
    echo "<p><b>Sincronizacion de WordPress a Perseo Software : </b><br> Clientes, Pedidos.</p>";
    echo "<p><b>RECOMENDACIONES GENERALES</b> <br>Instalacion previa de WooCommerce en la tienda WordPress. <br> Creacion de Productos desde Perseo Software. <br>Envio de pedidos a Perseo software en estado del pedido en WooCommerce <b>PROCESANDO</b>. <br>Productos imagenes de Perseo Software deben ser fotos cuadradas. Ejemplo 1080x1080px.<br> Carga de Perseo Software el precio Normal sin IVA. </p>";
    echo "<p><b>Datos Servidor </b> <br> Version PHP " . phpversion() . " " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
    echo "<h3> Conexion  Perseo Software </h3>";
    /////////////////////////////
    /// wp_cron
    /*
    echo '<pre>';
    var_dump(wp_get_schedules());
              
    var_dump(_get_cron_array());
    echo '</pre>';   
   */
}
function sperseo_seccionencabezado1()
{

    echo "<p><b>IMPORTANTE </b>Primera vez guardar los datos en <b>NO</b> y minimo en 10 minutos el <b>tiempo de sincronizacion.</b> Una vez sincronizado se recomienda de 30 a 60 minutos.</p>";
    echo "<p> Parametrizaciones en el siguiente orden (Activar <b>SI</b>):<br></p>";
    echo "<p> 1.-<b>Importante</b> Cargar Impuestos y parametrizar el WooCommerce de acuerdo a como desee mostrar (precio tienda incluido o no impuesto) antes de sincronizar productos.<br></p>";
    echo "<p> 2.-Activar sincronizar Perseo productos y productos imagenes (Carga automaticamente <b>Origen de datos (solo se selecciona 1 origen de datos), Productos Existencias y Precios)</b>.<br></p>";
    echo "<p> 3.-Activar sincronizar Perseo clientes .<br></p>";
    echo "<p> 4.-Activar sincronizar Perseo stock .<br></p>";
    echo "<p> 5.-Activar envio de WooCommerce clientes y Pedidos .<br></p>";
}

/////////////////////////////////////////////////
//Campo seccion token
function fperseo_tiposoftware($args)
{
    $perseo_config = PERSEOCONFIGBASE; //VARIABLE DEFINIDA DE CONFIGURACION BASE DE DATOS
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_config[$args['label_for']]) ? (($perseo_config[$args['label_for']] == 'PC') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_config[$args['label_for']]) ? (($perseo_config[$args['label_for']] == 'WEB') ? $perseo_selec : '') : '';
    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_config[$args['label_for']] = isset($perseo_config[$args['label_for']]) ? esc_attr($perseo_config[$args['label_for']]) : '';
    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_configuracion[{$args['label_for']}]' onchange='perseocomboseleccion()' id='{$args['label_for']}' >
    <option value='PC' " . $perseo_refr1 . " >PERSEO PC</option>
    <option value='WEB' " . $perseo_refr2 . " >PERSEO WEB</option>
    </select >";
    echo $perseo_html;
}
function fperseo_certificado($args)
{
    $perseo_config = PERSEOCONFIGBASE; //VARIABLE DEFINIDA DE CONFIGURACION BASE DE DATOS
    $perseo_selec = 'selected';
    if (isset($perseo_config['perseotiposoftware'])) {
        if ($perseo_config['perseotiposoftware'] == 'WEB') {
            $perseo_activar = 'disabled';
            $perseo_refr1 = '';
            $perseo_refr2 = $perseo_selec;
        } else {
            $perseo_activar = '';
            $perseo_refr1 = isset($perseo_config[$args['label_for']]) ? (($perseo_config[$args['label_for']] == 'http') ? $perseo_selec : '') : '';
            $perseo_refr2 = isset($perseo_config[$args['label_for']]) ? (($perseo_config[$args['label_for']] == 'https') ? $perseo_selec : '') : '';
        };
    } else {
        $perseo_activar = '';
        $perseo_refr1 = '';
        $perseo_refr2 = '';
    };
    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_config[$args['label_for']] = isset($perseo_config[$args['label_for']]) ? esc_attr($perseo_config[$args['label_for']]) : '';
    $perseo_html = "<select {$perseo_activar}  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_configuracion[{$args['label_for']}]'  id='{$args['label_for']}'  >
        <option value='http' " . $perseo_refr1 . " >HTTP</option>
        <option value='https' " . $perseo_refr2 . " >HTTPS</option>
        </select >";
    echo $perseo_html;
}

function fperseo_ip($args)
{
    $perseo_config = PERSEOCONFIGBASE; //VARIABLE DEFINIDA DE CONFIGURACION BASE DE DATOS
    $perseo_activar = '';
    if (isset($perseo_config['perseotiposoftware'])) {
        if ($perseo_config['perseotiposoftware'] == 'WEB') {
            $perseo_activar = 'disabled';
        };
    };
    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_config[$args['label_for']] = isset($perseo_config[$args['label_for']]) ? esc_attr($perseo_config[$args['label_for']]) : '';
    $perseo_html = "<input {$perseo_activar} class='{$args['class']}' data-custom='{$args['perseo_datopersonalizado']}' type='text'  name='pluginperseo_configuracion[{$args['label_for']}]' value='{$perseo_config[$args['label_for']]}' id='{$args['label_for']}' >";
    echo $perseo_html;
}

function fperseo_token($args)
{
    $perseo_config = PERSEOCONFIGBASE; //VARIABLE DEFINIDA DE CONFIGURACION BASE DE DATOS
    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_config[$args['label_for']] = isset($perseo_config[$args['label_for']]) ? esc_attr($perseo_config[$args['label_for']]) : '';
    $perseo_html = "<input class='{$args['class']}' data-custom='{$args['perseo_datopersonalizado']}' type='text'  name='pluginperseo_configuracion[{$args['label_for']}]' value='{$perseo_config[$args['label_for']]}'>";
    echo $perseo_html;
}

function fperseo_servidor($args)
{
    $perseo_config = PERSEOCONFIGBASE; //parametros de conexion  
    $perseo_activar = '';
    if (isset($perseo_config['perseotiposoftware'])) {
        if ($perseo_config['perseotiposoftware'] == 'PC') {
            $perseo_activar = 'disabled';
        }
    };
    if (!empty($perseo_config['perseotoken'])) {
        //////////////////////////////////////////////////
        // SOLO SI ES WEB DEBE MOSTRAR LISTADO DE SERVIDORES
        if ($perseo_config['perseotiposoftware'] == 'WEB') {
            ///////////////////////////////////////////
            //Consulta APi
            $perseo_selec       = 'selected';
            /////////////////////////////////////
            //Verificar pc o web
            if ($perseo_config['perseotiposoftware'] == 'WEB') {
                $perseo_urlservidores  = 'https://perseo.app/api/datos/servidores_activos';

                $perseo_responseservidores = wp_remote_post($perseo_urlservidores, array(
                    'method'      => 'POST',
                    'headers'     => array(
                        'Content-Type' => 'application/json',
                        'usuario'     => 'perseo',
                        'clave'       => 'Perseo1232*'
                    )
                ));
            }
            // presentar datos
            //print_r($perseo_responseservidores);
            // echo "<br>-- aqui <br>";
            //print_r($perseo_responseservidores['body']);
            //echo "<br>";
            $perseo_muestra = "";
            if (!empty($perseo_responseservidores)) {
                $perseo_datosServidores = json_decode($perseo_responseservidores['body'], true); //devuelve
                foreach ($perseo_datosServidores as $servidor) {
                    //  echo $registro['sis_servidoresid'];
                    // echo "<br>";                             
                    $perseo_refr1 = ($perseo_config[$args['label_for']] == ($servidor['dominio'])) ? $perseo_selec : '';

                    $perseo_muestra = $perseo_muestra . " <option value='" . $servidor['dominio'] . "' " . $perseo_refr1 . " >" . $servidor['descripcion'] . "</option>'";
                }
            }
            $perseo_config[$args['label_for']] = isset($perseo_config[$args['label_for']]) ? esc_attr($perseo_config[$args['label_for']]) : '';
            $perseo_html = "<select  {$perseo_activar}  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_configuracion[{$args['label_for']}]'  id='{$args['label_for']}'> .$perseo_muestra.</select >";
            echo $perseo_html;
        }
    }
}

/////////////////////////////////////////////////
//Campo seccion 2 PRODUCTOS SINCRONIZACION

function fperseo_impuestos($args)
{
    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion  
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    //$perseo_selec='selected';
    if (isset($perseo_parametros[$args['label_for']])) {
        $perseo_refr1 = ($perseo_parametros[$args['label_for']] == 'SI') ? 'selected' : '';
        $perseo_refr2 = ($perseo_parametros[$args['label_for']] == 'NO') ? 'selected' : '';
    } else {
        $perseo_refr1 = '';
        $perseo_refr2 = '';
    };


    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';
    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_productos($args)
{
    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion  
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_imagenes($args)
{

    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion  
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_stock($args)
{
    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion 
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_clientes($args)
{

    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion 
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_pedidos($args)
{

    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion 
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}
function fperseo_categorias($args)
{

    $perseo_parametros = PERSEOCONFIGPARAMETROS;
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'productos_lineas_consulta') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'productos_categorias_consulta') ? $perseo_selec : '') : '';
    $perseo_refr3 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'productos_subcategorias_consulta') ? $perseo_selec : '') : '';
    $perseo_refr4 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'productos_subgrupos_consulta') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='productos_lineas_consulta' " . $perseo_refr1 . " >Linea</option>
    <option value='productos_categorias_consulta' " . $perseo_refr2 . " >Categoria</option>
    <option value='productos_subcategorias_consulta' " . $perseo_refr3 . " >SubCategoria</option>
    <option value='productos_subgrupos_consulta' " . $perseo_refr4 . " >SubGrupo</option>
    </select >";
    echo $perseo_html;
}



function fperseo_enviarclientes($args)
{
    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion 
    //condcion si existe valiable los dos puntos caso de sino es valor2=10 ? true : false
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'SI') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == 'NO') ? $perseo_selec : '') : '';

    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='NO' " . $perseo_refr2 . " >NO</option>
    <option value='SI' " . $perseo_refr1 . " >SI</option>
    
    </select >";
    echo $perseo_html;
}

function fperseo_existencias($args)
{

    $perseo_parametros = PERSEOCONFIGPARAMETROS;
    $perseo_selec = 'selected';
    $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == '0') ? $perseo_selec : '') : '';
    $perseo_refr2 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == '1') ? $perseo_selec : '') : '';

    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<select  class='{$args['class']}'  data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >
    <option value='0' " . $perseo_refr1 . " >Todos</option>
    <option value='1' " . $perseo_refr2 . " >Con Existencias</option>
    </select >";
    echo $perseo_html;
}
function fperseo_sincronizar($args)
{

    $perseo_parametros = PERSEOCONFIGPARAMETROS;
    ////////////////////////////////////////
    //verificar si todo es NO enviar vacio

    //condcion si existe valiable los dos puntos caso de sino es
    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

    $perseo_html = "<input class='{$args['class']}' data-custom='{$args['perseo_datopersonalizado']}' type='text'  name='pluginperseo_parametros[{$args['label_for']}]' value='{$perseo_parametros[$args['label_for']]}'>";

    echo $perseo_html;
}

function fperseo_tarifaventa($args)
{
    $perseo_config = PERSEOCONFIGBASE; //parametros de conexion
    $perseo_parametros = PERSEOCONFIGPARAMETROS; // parametrizacion 

    if (!empty($perseo_config['perseotoken'])) {
        ///////////////////////////////////////////
        //Consulta APi
        $perseo_selec       = 'selected';
        /////////////////////////////////////
        //Verificar pc o web
        if ($perseo_config['perseotiposoftware'] == 'WEB') {
            // $perseo_urltarifas  =$perseo_config['perseoservidor'].''.'/api/tarifas_consulta';
            $perseo_urltarifas  = $perseo_config['perseoservidor'] . '/api/tarifas_consulta';
        } else {
            $perseo_urltarifas  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/tarifas_consulta';
        }
        $datoKEY = [
            'api_key' => $perseo_config['perseotoken']
        ];
        $perseo_responsetarifas = wp_remote_post($perseo_urltarifas, array(
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json'),
            'body'        => wp_json_encode($datoKEY)
        ));
        // presentar datos
        //print_r($perseo_responsetarifas);       
        //print_r($perseo_responsetarifas['body']);

        $perseo_muestra = "";
        if (!empty($perseo_responsetarifas)) {
            if (is_wp_error($perseo_responsetarifas)) {
                echo "<p>Existe problemas en conexion de Api Perseo<p>";
            } else {
                if (!empty($perseo_responsetarifas['body'])) {
                    $perseo_datosTarifas = json_decode($perseo_responsetarifas['body'], true); //devuelve
                    // si es diferente de vacio
                    if (isset($perseo_datosTarifas)) {
                        foreach ($perseo_datosTarifas as $registro) {
                            foreach ($registro as $tarifa) {
                                $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == ($tarifa['tarifasid'])) ? $perseo_selec : '') : '';
                                $perseo_muestra =  $perseo_muestra . " <option value='" . $tarifa['tarifasid'] . "' " . $perseo_refr1 . " >" . $tarifa['descripcion'] . "</option>'";
                            }
                        }
                    };

                    $perseo_parametros = get_option('pluginperseo_parametros');
                    //condcion si existe valiable los dos puntos caso de sino es
                    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

                    $perseo_html = "<select  class='{$args['class']}' data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >  " . $perseo_muestra . "  </select >";

                    echo $perseo_html;
                }
            };
        }
    }
}
// Parametro Servidor

//
function fperseo_tarifaAumento($args)
{
    $perseo_config      = PERSEOCONFIGBASE; //parametros de conexion    
    $perseo_parametros  = PERSEOCONFIGPARAMETROS; // parametrizacion  
    if (!empty($perseo_config['perseotoken'])) {
        ///////////////////////////////////////////
        //Consulta APi
        $perseo_selec = 'selected';
        /////////////////////////////////////
        //Verificar pc o web
        if ($perseo_config['perseotiposoftware'] == 'WEB') {
            $perseo_urltarifas  = $perseo_config['perseoservidor'] . '/api/tarifas_consulta';
        } else {
            $perseo_urltarifas  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/tarifas_consulta';
        }
        $datoKEY = ['api_key' => $perseo_config['perseotoken']];

        $perseo_responsetarifas = wp_remote_post($perseo_urltarifas, array(
            'method'      => 'POST',
            'headers'     => array('Content-Type' => 'application/json'),
            'body'        => wp_json_encode($datoKEY)
        ));
        // print_r($perseo_responsetarifas);
        $perseo_muestra = "";
        if (!empty($perseo_responsetarifas)) {
            if (is_wp_error($perseo_responsetarifas)) {
                echo "<p>Existe problemas en conexion de Api Perseo<p>";
            } else {
                if (!empty($perseo_responsetarifas['body'])) {
                    //if (isset($perseo_responsetarifas['body']['tarifas'])){
                    $perseo_datosTarifas = json_decode($perseo_responsetarifas['body'], true); //devuelve
                    if (isset($perseo_datosTarifas)) {
                        foreach ($perseo_datosTarifas as $registro) {
                            foreach ($registro as $tarifa) {
                                $perseo_refr1 = isset($perseo_parametros[$args['label_for']]) ? (($perseo_parametros[$args['label_for']] == ($tarifa['tarifasid'])) ? $perseo_selec : '') : '';
                                $perseo_muestra =  $perseo_muestra . " <option value='" . $tarifa['tarifasid'] . "' " . $perseo_refr1 . " >" . $tarifa['descripcion'] . "</option>'";
                            }
                        }
                    };
                    //echo $muestra;
                    //condcion si existe valiable los dos puntos caso de sino es
                    $perseo_parametros[$args['label_for']] = isset($perseo_parametros[$args['label_for']]) ? esc_attr($perseo_parametros[$args['label_for']]) : '';

                    $perseo_html = "<select  class='{$args['class']}' data-custom='{$args['perseo_datopersonalizado']}' name='pluginperseo_parametros[{$args['label_for']}]' >" . $perseo_muestra . "  </select >";

                    echo $perseo_html;
                    //} 
                } else {
                    echo "<p>No existe conexion con Api Perseo<p>";
                }
            }
        }
    }
}



/////////////////////////////////////
//libreria js

function fperseo_cargandolibrerias()
{
    $version_Plugin = "";
    wp_enqueue_script(
        'perseo_script',
        plugins_url('js/PluginPerseo_combo.js', __FILE__),
        [],
        $version_Plugin,
        true
    );
}
add_action('admin_enqueue_scripts', 'fperseo_cargandolibrerias');

require_once PERSEO_DIR_PATH . 'PluginPerseoClientes.php';
require_once PERSEO_DIR_PATH . 'includes/PluginPerseo_master.php';


function fperseo_runmaster()
{
    $perseo_master = new CPerseo_Master; //classe instanciada
    $perseo_master->fperseo_run();
}
fperseo_runmaster();
