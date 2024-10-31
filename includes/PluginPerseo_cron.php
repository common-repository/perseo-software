<?php
class CPerseo_Cron
{
    private function ejecutar_proceso_con_bloqueo($proceso, $callback)
    {
        // Crear un nombre único para el transient basado en el proceso
        $transient_name = "perseo_cron_bloqueo_{$proceso}";

        // Verifica si el transient de bloqueo existe.
        if (get_transient($transient_name)) {
            //error_log("El proceso '{$proceso}' ya se está ejecutando. Deteniendo nuevo intento.");
            return;
        }

        // Establece el transient de bloqueo con una expiración.
        set_transient($transient_name, true);

        try {
            // Ejecutar el callback del proceso
            call_user_func($callback);
        } catch (Exception $e) {
            //error_log("Error en el proceso '{$proceso}': " . $e->getMessage());
        } finally {
            // Eliminar el transient de bloqueo
            delete_transient($transient_name);
            //error_log("Proceso '{$proceso}' completado y bloqueo eliminado.");
        }
    }

    public function fperseo_inicializador()
    {
        //elimina el cron
        $perseo_parametros = get_option('pluginperseo_parametros');

        // echo "-".$perseo_parametros['perseosincronizar'];
        //echo "<br>";
        if (isset($perseo_parametros['perseosincronizar'])) {

            if ($perseo_parametros['perseosincronizar'] == "") {

                $perseo_timestamp = wp_next_scheduled('intervalo_perseo');
                wp_unschedule_event($perseo_timestamp, 'intervalo_perseo', 'perseo_cron');
                wp_clear_scheduled_hook('intervalo_perseo', 'perseo_cron');
                //wp_unschedule_event(time(), 'intervalo_perseo');
            };
            //crea el cron
            if (!wp_next_scheduled('perseo_cron')) {
                wp_schedule_event(time() + (intval($perseo_parametros['perseosincronizar']) * 60), 'intervalo_perseo', 'perseo_cron');
            };
        };
    }

    public function fperseo_intervalos($perseo_intervalos)
    {
        //sincronizar 
        $perseo_parametros = get_option('pluginperseo_parametros');
        if (isset($perseo_parametros['perseosincronizar'])) {
            if (empty($perseo_parametros['perseosincronizar'])) {
                //$perseo_valorCron=intval(1)*60;
            } else {
                $perseo_valorCron = intval($perseo_parametros['perseosincronizar']) * 60;
            };

            $perseo_intervalos['intervalo_perseo'] = [
                'interval'  => $perseo_valorCron,
                'display'   => 'Cada tiempo ' . $perseo_parametros['perseosincronizar'] . ' Perseo'
            ];

            return $perseo_intervalos;
        };
    }

    public function fperseo_enviarclientes()
    {
        $this->ejecutar_proceso_con_bloqueo('enviar_clientes', function () {
            global $wpdb;
            global $table_prefix;
            $perseo_config          = get_option('pluginperseo_configuracion');
            $perseo_parametros      = get_option('pluginperseo_parametros');

            if ($perseo_parametros['perseoenviarclientes'] == 'SI') {


                //////////////////////////////////
                //consultamos 
                //SELECT DISTINCT(usuario.ID), usuario.*, user.meta_value  FROM {$table_prefix}users as usuario ,  {$table_prefix}usermeta as user  where  usuario.ID=user.user_id and user.meta_key='PerseoIdentificacion'
                $ConsultaclientesWordpress = $wpdb->get_results("SELECT DISTINCT(usuario.ID) as UserID, usuario.*, user.meta_value as identificacion FROM {$table_prefix}users as usuario ,  {$table_prefix}usermeta as user  where  usuario.ID=user.user_id and user.meta_key='PerseoIdentificacion'");
                //print_r($ConsultaclientesWordpress);
                //echo "<br>";
                $Perseo_TipoIdentificacion = "";
                if (!empty($ConsultaclientesWordpress)) {
                    foreach ($ConsultaclientesWordpress as $Clientes) {
                        //echo $Clientes -> identificacion;
                        //echo "<br>";                    
                        if ($perseo_config['perseotiposoftware'] == 'WEB') {
                            $perseo_urlcliente = $perseo_config['perseoservidor'] . '/api/clientes_consulta';
                        } else {
                            $perseo_urlcliente  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/clientes_consulta';
                        };
                        $perseo_bodycliente = [
                            'api_key' => $perseo_config['perseotoken'],
                            'clienteid'      => '',
                            'clientescodigo' => '',
                            'identificacion' => $Clientes->identificacion,
                            'contenido'      => ''
                        ];
                        ///////////////////////////////////
                        //ejecutamos y enviamos  el id del prodclienteucto 
                        $perseo_responseclient = wp_remote_post(
                            $perseo_urlcliente,
                            array(
                                'method'      => 'POST',
                                'timeout'     => 10000,
                                'redirection' => 5,
                                'httpversion' => '1.0',
                                'blocking'    => true,
                                'headers'     => array('Content-Type' => 'application/json'),
                                'body'        => wp_json_encode($perseo_bodycliente)
                            )
                        );
                        ////////////////////////////////////////////
                        //Verificar si hay conexion con el api
                        if (is_wp_error($perseo_responseclient)) {
                            //no existe
                        } else {
                            if (isset($perseo_responseclient['body'])) {
                                $datoclient = json_decode($perseo_responseclient['body'], true);


                                if (isset($datoclient['clientes'])) {
                                    //no hacer nada si existe 
                                } else {
                                    //echo "NO EXISTE"; 
                                    //echo "<br>";                       
                                    //contar caracteres de identificacion                        
                                    switch (strlen($Clientes->identificacion)) {
                                        case 10:
                                            $Perseo_TipoIdentificacion = "C";
                                            break;
                                        case 13:
                                            $Perseo_TipoIdentificacion = "R";
                                            break;
                                        default:
                                            $Perseo_TipoIdentificacion = "P";
                                    };
                                    ///consultar direccion 
                                    $Perseo_clientesWordpressdireccion = $wpdb->get_results("SELECT DISTINCT(usuario.ID) as UserI, user.meta_value as direccion FROM {$table_prefix}users as usuario , {$table_prefix}usermeta as user where usuario.ID=user.user_id and usuario.ID=" . $Clientes->UserID . " and user.meta_key='billing_address_1'");

                                    $Perseo_direccion = $Perseo_clientesWordpressdireccion[0];
                                    //var_dump( $Perseo_direccion );
                                    //echo "<br>";
                                    //echo "<br>";

                                    //si es vacio realiza el registro en perseo
                                    $Perseo_EnviarClie = [
                                        'api_key' => $perseo_config['perseotoken'],
                                        'registros' => [array(
                                            'clientes' => array(
                                                'clientesid'        => '',
                                                'clientescodigo'    => '',
                                                'codigocontable'    => '1.1.02.05.01',
                                                'clientes_gruposid' => 1,
                                                'provinciasid'      => '17',
                                                'ciudadesid'        => '1701',
                                                'razonsocial'       => $Clientes->display_name,
                                                'parroquiasid'      => '170150',
                                                'clientes_zonasid'  => 1,
                                                'nombrecomercial'   => $Clientes->user_login,
                                                'direccion'         => $Perseo_direccion->direccion,
                                                'identificacion'    => $Clientes->identificacion,
                                                'tipoidentificacion' => $Perseo_TipoIdentificacion,
                                                'email'             => $Clientes->user_email,
                                                'telefono1'         => '',
                                                'telefono2'         => '',
                                                'telefono3'         => '',
                                                'tipodestino'       => '1',
                                                'vendedoresid'      => 3,
                                                'cobradoresid'      => 3,
                                                'creditocupo'       => 0,
                                                'creditodias'       => 0,
                                                'estado'            => true,
                                                'tarifasid'         => 1,
                                                'forma_pago_empresaid' => 1,
                                                'ordenvisita'       => 0,
                                                'latitud'           => '',
                                                'longitud'          => '',
                                                'clientes_rutasid'  => 1,
                                                'usuariocreacion'   => 'WORDPRESS'
                                            )
                                        )]
                                    ];
                                    $Perseo_EnviarCli = wp_json_encode($Perseo_EnviarClie);


                                    if ($perseo_config['perseotiposoftware'] == 'WEB') {
                                        $perseo_urlclientecrear = $perseo_config['perseoservidor'] . '/api/clientes_crear';
                                    } else {
                                        $perseo_urlclientecrear  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/clientes_crear';
                                    };
                                    ///////////////////////////////////
                                    //ejecutamos y enviamos  
                                    $perseo_responseclient = wp_remote_post(
                                        $perseo_urlclientecrear,
                                        array(
                                            'method'      => 'POST',
                                            'headers'     => array('Content-Type' => 'application/json'),
                                            'body'        => $Perseo_EnviarCli
                                        )
                                    );
                                    //print_r($perseo_responseclient);

                                    ////////////////////////////////////
                                    //Actualizamos datos de perseo en cliente de worpress 

                                    $perseo_bodyccliente = [
                                        'api_key'       => $perseo_config['perseotoken'],
                                        'clienteid'     => '',
                                        'clientescodigo' => '',
                                        'identificacion' => $Clientes->identificacion,
                                        'contenido' => ''
                                    ];
                                    ///////////////////////////////////
                                    //ejecutamos y enviamos  el id del prodclienteucto 
                                    $perseo_dresponseclient = wp_remote_post(
                                        $perseo_urlcliente,
                                        array(
                                            'method'      => 'POST',
                                            'headers'     => array('Content-Type' => 'application/json'),
                                            'body'        => wp_json_encode($perseo_bodyccliente)
                                        )
                                    );
                                    // print_r($perseo_dresponseclient);
                                    //echo $Clientes -> identificacion;
                                    //echo "<br>";  echo "<br>";
                                    if (!empty($perseo_dresponseclient)) {
                                        $perseo_datosCliente = json_decode($perseo_dresponseclient['body'], true); //devuelve
                                        // print_r($perseo_datosCliente);
                                        // echo "<br>";  echo "<br>";
                                        //Actualizamos datos
                                        $Perseo_IDUSU = $Clientes->UserID;
                                        $Perseo_COUSU = $perseo_datosCliente['clientes'][0]['clientescodigo'];
                                        $Perseo_USU = $perseo_datosCliente['clientes'][0]['clientesid'];

                                        $wpdb->insert($table_prefix . 'usermeta', array('user_id' => $Perseo_IDUSU, 'meta_key' => 'PerseoCodigo', 'meta_value' => $Perseo_COUSU));
                                        $wpdb->insert($table_prefix . 'usermeta', array('user_id' => $Perseo_IDUSU, 'meta_key' => 'PerseoID', 'meta_value' => $Perseo_USU));
                                    }
                                }
                            }
                        };
                    }
                }
            }
        });
    }

    public function fperseo_pedidos()
    {
        $this->ejecutar_proceso_con_bloqueo('pedidos', function () {
            global $wpdb;
            global $table_prefix;
            $perseo_config      = get_option('pluginperseo_configuracion');
            $perseo_parametros  = get_option('pluginperseo_parametros');

            ///////////////////////////////////////////
            //Enviar pedidos
            if ($perseo_parametros['perseopedido'] == 'SI') {

                //////////////////////////////////////////
                //Consultamos pedidos
                $perseo_arraypedidos = $wpdb->get_results("SELECT post.ID as codigoPedido ,post.post_date, post.post_title, post.post_status, users.*, cabecera.* FROM {$table_prefix}posts post , {$table_prefix}wc_order_stats cabecera, {$table_prefix}wc_customer_lookup lokkup, {$table_prefix}users users WHERE post.ID=cabecera.order_id and cabecera.customer_id=lokkup.customer_id and lokkup.user_id=users.ID and cabecera.customer_id=lokkup.customer_id and post.post_type='shop_order' and cabecera.status='wc-processing' and post.post_content='' ");
                $perseo_registroPedido = array();
                //var_dump($perseo_arraypedidos);


                if (!empty($perseo_arraypedidos)) {
                    foreach ($perseo_arraypedidos as $DatPedido) {
                        $perseo_DetallePedido = array();
                        /////////////////////////////////////////////
                        //detalle pedido
                        $perseosubtotalsiniva = 0;
                        $perseosubtotalconiva = 0;

                        $perseo_arraydetalles = $wpdb->get_results("SELECT producto.product_id as varprod ,ord.* , producto.* FROM {$table_prefix}wc_order_product_lookup ord , {$table_prefix}wc_product_meta_lookup producto where ord.product_id=producto.product_id and  order_id ='" . $DatPedido->codigoPedido . "'");
                        // var_dump( $perseo_arraydetalles);
                        // echo "<br>";
                        //echo "<br>";
                        foreach ($perseo_arraydetalles as $detalPedido) {

                            //////////////////////////////////////
                            //codigo del producto de perseo
                            $perseo_DatoCodPro = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key='_product_attributes' and post_id='" . $detalPedido->varprod . "'");

                            $perseo_CodProdP = unserialize($perseo_DatoCodPro);
                            $perseo_DatoIva = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key='PERSEOPORCIVA' and post_id='" . $detalPedido->varprod . "'");
                            $perseo_precio = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key='_price' and post_id='" . $detalPedido->varprod . "'");

                            if (isset($perseo_CodProdP['ID_Perseo']['value'])) {
                                $perseo_valor = $perseo_CodProdP['ID_Perseo']['value'];
                                //}else{
                                //    $perseo_valor=$perseo_CodProdP['id_perseo']['value'];

                                // var_dump($perseo_DatoIva);
                                // print_r($detalPedido->varprod);
                                //echo '<br>';
                                // echo  $perseo_precio." ".$perseo_DatoIva;
                                // echo '<br>';
                                // echo '<br>';
                                $perseo_pedidoiva = round(($perseo_DatoIva / 100) + 1, 2);
                                $perseo_tarifaventapedido = round($perseo_precio * $perseo_pedidoiva, 3);

                                $perseo_DetalleP  =   array(
                                    'pedidosid' => '',
                                    'centros_costosid' => 1,
                                    'productosid' => str_replace('"', '', $perseo_valor),
                                    'medidasid' => 1,
                                    'almacenesid' => 1,
                                    'cantidaddigitada' => intval($detalPedido->product_qty),
                                    'cantidad' => intval($detalPedido->product_qty),
                                    'cantidadfactor' => 1,
                                    'precio' => number_format($perseo_precio, 3),
                                    'preciovisible' => number_format($detalPedido->product_gross_revenue, 3),
                                    'iva' => number_format($perseo_DatoIva, 2),
                                    'precioiva' => number_format($perseo_tarifaventapedido, 2),
                                    'descuento' => 0
                                );
                                array_push($perseo_DetallePedido, $perseo_DetalleP);

                                /////////////////////////////////////////
                                //sumar los subtotales con iva o sin iva 
                                /////////////////////////////////////////
                                if ($perseo_DatoIva == 0) {
                                    $perseosubtotalsiniva += number_format($detalPedido->product_net_revenue, 3);
                                } else {
                                    $perseosubtotalconiva += number_format($detalPedido->product_net_revenue, 3);
                                }
                            };
                        }
                        //////////////////////////////////
                        //tipo de metodo de pago
                        $perseo_TipoMetodoPago = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key='_payment_method_title' and post_id='" . $DatPedido->codigoPedido . "'");
                        //echo $perseototaliva;
                        // echo '<br>';
                        ///////////////////////////////////////////////////
                        //cabecera de pedido 
                        $consult = "SELECT meta_value FROM {$table_prefix}usermeta WHERE meta_key='PerseoID' and user_id='" . $DatPedido->ID . "'";
                        $perseo_IDCliente = $wpdb->get_var($consult);
                        //echo $consult;
                        $perseo_CabeceraPedidos    =  array(
                            'pedidos' => array(
                                'pedidosid' => '',
                                'emision' => date('Ymd', strtotime($DatPedido->post_date)),
                                'pedidos_codigo' => '',
                                'forma_pago_empresaid' => 1,
                                'facturadoresid' => 1,
                                'clientesid' => intval($perseo_IDCliente),
                                'razonsocial' => $DatPedido->user_login,
                                'almacenesid' => 1,
                                'centros_costosid' => 1,
                                'vendedoresid' => 1,
                                'tarifasid' => 1,
                                'concepto' => 'PEDIDO #' . $detalPedido->order_id . ' WOOCOMMERCE, IMPORTE TOTAL ' . $DatPedido->total_sales,
                                'origen' => '0',
                                'documentosid' => 0,
                                'observacion' => 'Pedido Woocomerce #' . $detalPedido->order_id . ', METODO DE PAGO  ' . $perseo_TipoMetodoPago . ' , IMPORTE TOTAL ' . $DatPedido->total_sales,
                                'subtotalsiniva' => number_format($perseosubtotalsiniva, 3),
                                'subtotalconiva' => number_format($perseosubtotalconiva, 3),
                                'total_descuento' => 0,
                                'subtotalneto' => number_format($DatPedido->net_total, 3),
                                'total_iva' => number_format(($perseosubtotalconiva * 12) / 100, 3),
                                'total' => number_format($DatPedido->total_sales, 3),
                                'usuariocreacion' => 'Woocommerce',
                                'fechacreacion' => date('Ymd', strtotime($DatPedido->post_date)),
                                'uui' => wp_generate_uuid4(),
                                'detalles' => $perseo_DetallePedido
                            )

                        );
                        array_push($perseo_registroPedido, $perseo_CabeceraPedidos);

                        ////////////////////////////////////////////////
                        //actualizamos variable post_content
                        $perseo_IDsql = "SELECT MAX(ID) FROM {$table_prefix}posts where post_type='shop_order' ";
                        $perseo_IDActualizar_post = $wpdb->get_var($perseo_IDsql);
                        $perseo_ActPedido = $wpdb->update(
                            $table_prefix . 'posts',
                            array('post_content' => 'EnviadoPerseo'),
                            array('ID' => $perseo_IDActualizar_post)
                        );
                    };

                    $perseo_InsertarPedido = [
                        'api_key' => $perseo_config['perseotoken'],
                        'registro' => $perseo_registroPedido
                    ];

                    $perseo_bodypedido = wp_json_encode($perseo_InsertarPedido);

                    // print_r($perseo_bodypedido);
                    //echo "<br>";
                    //  echo "<br>";

                    ///////////////////////////////////////////
                    //Enviamos api perseo
                    /////////////////////////////////////
                    //Verificar pc o web
                    if ($perseo_config['perseotiposoftware'] == 'WEB') {
                        $perseo_urlpedido = $perseo_config['perseoservidor'] . '/api/pedidos_crear';
                    } else {
                        $perseo_urlpedido  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/pedidos_crear';
                    }

                    $perseo_response = wp_remote_post(
                        $perseo_urlpedido,
                        array(
                            'method'      => 'POST',
                            'headers'     => array('Content-Type' => 'application/json'),
                            'body'        => $perseo_bodypedido
                        )
                    );
                    // print_r( $perseo_response);
                }
            };
        });
    }

    public function fperseo_cliente()
    {
        $this->ejecutar_proceso_con_bloqueo('clientes', function () {
            global $wpdb;
            global $table_prefix;
            $perseo_config      = get_option('pluginperseo_configuracion');
            $perseo_parametros  = get_option('pluginperseo_parametros');

            if ($perseo_parametros['perseoclientes'] == 'SI') {
                /////////////////////////////////////
                //Verificar pc o web

                if ($perseo_config['perseotiposoftware'] == 'WEB') {
                    $perseo_urlcliente = $perseo_config['perseoservidor'] . '/api/clientes_consulta';
                } else {
                    $perseo_urlcliente  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/clientes_consulta';
                }
                //echo$perseo_urlcliente ;
                $perseo_bodycliente = [
                    'api_key' => $perseo_config['perseotoken'],
                    'clienteid'         => '',
                    'clientescodigo'    => '',
                    'identificacion'    => '',
                    'contenido'         => ''
                ];
                $perseo_responsecliente = wp_remote_post(
                    $perseo_urlcliente,
                    array(
                        'method'      => 'POST',
                        'timeout'     => 1800,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array('Content-Type' => 'application/json'),
                        'body'        => wp_json_encode($perseo_bodycliente)
                    )
                );
                //var_dump($perseo_responsecliente);
                //echo "<br>";

                if (!empty($perseo_responsecliente)) {
                    ////////////////////////////////////////////
                    //Verificar si hay conexion con el api
                    if (is_wp_error($perseo_responsecliente)) {
                        //no existe
                    } else {
                        if (isset($perseo_responsecliente['body'])) {
                            $perseo_datosCliente = json_decode($perseo_responsecliente['body'], true); //devuelve
                            // print_r($perseo_datosCliente);
                            foreach ($perseo_datosCliente['clientes'] as $cliente) {
                                if ($cliente['email'] <> "") {

                                    $perseo_ConsultaClientes = $wpdb->get_results("SELECT * FROM {$table_prefix}users usua, {$table_prefix}usermeta descri where usua.ID= descri.user_id and meta_key ='wp_user_level' and meta_value =0 and usua.ID = (SELECT meta.user_id FROM {$table_prefix}usermeta as meta where meta.meta_key ='PerseoIdentificacion' and meta.meta_value='" . $cliente['identificacion'] . "')");
                                    //print_r($perseo_ConsultaClientes);
                                    //echo "<br>";
                                    if (empty($perseo_ConsultaClientes)) {
                                        if ($cliente['clientesid'] <> '1') {

                                            $perseo_nombreCliente = explode(" ", $cliente['razonsocial']);
                                            $primermail = preg_split("/[\s,]+/", $cliente['email']);
                                            $perseo_userdata  = [
                                                'user_login'    =>  sanitize_text_field($cliente['razonsocial']),
                                                'user_pass'     =>  sanitize_text_field($cliente['identificacion']),
                                                'user_email'     => $primermail[0],
                                                'first_name'    =>  sanitize_text_field($perseo_nombreCliente[0]),
                                                'last_name'     =>  sanitize_text_field($perseo_nombreCliente[1]),
                                                'user_registered' => date_format(date_create($cliente['fechamodificacion']), 'Y-m-d H:i:s'),
                                                'wp_capabilities'  =>  'a:1:{s:8:"customer";b:1;}'

                                            ];
                                            $perseo_userid = username_exists($perseo_userdata['user_login']);

                                            if (!$perseo_userid && email_exists($perseo_userdata['user_login']) === false) {

                                                $perseo_userid = wp_insert_user($perseo_userdata);

                                                if (!is_wp_error($perseo_userid)) {
                                                    // echo $perseo_userid;
                                                    //echo "<br>";
                                                    $wpdb->insert($table_prefix . 'usermeta', array('user_id' => $perseo_userid, 'meta_key'  => 'PerseoCodigo', 'meta_value' => $cliente['clientescodigo']));
                                                    $wpdb->insert($table_prefix . 'usermeta', array('user_id' => $perseo_userid, 'meta_key'  => 'PerseoIdentificacion', 'meta_value' => $cliente['identificacion']));
                                                    $wpdb->insert($table_prefix . 'usermeta', array('user_id' => $perseo_userid, 'meta_key'  => 'PerseoID', 'meta_value' => $cliente['clientesid']));

                                                    $perseo_link = home_url();
                                                    $perseo_link_host = $_SERVER['HTTP_HOST'];
                                                    $perseo_destinatario = $perseo_userdata['user_email'];
                                                    $perseo_asunto       = "Bienvenido a la plataforma Ecommerce";
                                                    $perseo_cuerpo       = '<div style="font-family:Montserrat,Arial,sans-serif;font-size:18px;font-weight:500;font-style:normal;line-height:1.57;letter-spacing:normal;color:#313131"><div border="0" cellpadding="0" cellspacing="0" style="width:100%;max-width:648px;border-collapse:collapse;margin:0 auto;padding:0" bgcolor="#ffffff" ><h1 align="center">Bienvenido ' . $perseo_userdata['user_login'] . ' </h1><br>A la plataforma E-Commerce de &nbsp;<span style="color:#16a085"><a href="https:' . $perseo_link . '">' . $perseo_link_host . '</a> </span>visita nuestra pagina con los siguientes accesos: <br> <br> <hr style="height:1px;width:80%;background-color:#f1f1f1;border:0px"></td>
                                                <div align="center" style="font-family:Montserrat;font-size:20px;font-weight:700;line-height:1.1;text-transform:uppercase;padding:15px;border-radius:9px;border-collapse:collapse;margin:0 auto;padding:0"  bgcolor="#f4f4f4"><p style="color:#00a082;"> Usuario:</p> ' . $perseo_userdata['user_login'] . '<p style="color:#00a082;"> Contraseña:</p>' . $perseo_userdata['user_pass'] . '</div><hr style="height:1px;width:80%;background-color:#f1f1f1;border:0px"></td>
                                                <div style="font-family:Montserrat,Arial,sans-serif;font-size:10px;font-weight:500;line-height:2.2;color:#aaaaaa"><br>Generado por plugin Perseo Software <a href="https://perseo.ec">Perseo.ec</a></div></div></div>';
                                                    $perseo_headers = array('Content-Type: text/html; charset=UTF-8');

                                                    wp_mail($perseo_destinatario, $perseo_asunto, $perseo_cuerpo, $perseo_headers);
                                                    //wp_mail($perseo_userdata['user_email'], 'Bienvenido a la plataforma Ecommerce', "Visita nuestra pagina Ecommerce {$perseo_link}  Se ha creado el usuario : {$perseo_userdata['user_login']} Su contraseña es : {$perseo_userdata['user_pass']}");

                                                };
                                            }
                                        };
                                    };
                                };
                            }
                            //////////////////////////////
                            //limpio variables json
                            $perseo_responsecliente    = "";
                            $perseo_datosCliente       = "";
                        }
                    }
                }
            }
        });
    }

    public function fperseo_categoria()
    {
        $this->ejecutar_proceso_con_bloqueo('categoria', function () {
            // Código para enviar clientes
            global $wpdb;
            global $table_prefix;
            $perseo_config      = get_option('pluginperseo_configuracion');
            $perseo_parametros  = get_option('pluginperseo_parametros');

            if ($perseo_parametros['perseoproductos'] == 'SI') {
                /////////////////////////////////////
                //Verificar pc o web
                if ($perseo_config['perseotiposoftware'] == 'WEB') {
                    $perseo_urlcategoria = $perseo_config['perseoservidor'] . '/api/' . $perseo_parametros['perseocategorias'];
                } else {
                    $perseo_urlcategoria  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api' . '/' . $perseo_parametros['perseocategorias'];
                }
                //echo $perseo_urlcategoria ;
                //echo "<br>";
                $datoKEY = ['api_key' => $perseo_config['perseotoken']];

                $perseo_responsecategoria = wp_remote_post(
                    $perseo_urlcategoria,
                    array(
                        'method'      => 'POST',
                        'timeout'     => 1800,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array('Content-Type' => 'application/json'),
                        'body'        => wp_json_encode($datoKEY)
                    )
                );


                if (!empty($perseo_responsecategoria)) {
                    ////////////////////////////////////////////
                    //Verificar si hay conexion con el api
                    if (is_wp_error($perseo_responsecategoria)) {
                        //no existe
                    } else {
                        if (isset($perseo_responsecategoria['body'])) {
                            $perseo_datosCategoria = json_decode($perseo_responsecategoria['body'], true); //devuelve
                            //print_r($perseo_datosCategoria);
                            //echo "<br>";

                            if (isset($perseo_datosCategoria['categorias'])) {
                                $perseo_ConsultaCat = $perseo_datosCategoria['categorias'];
                            };
                            if (isset($perseo_datosCategoria['lineas'])) {
                                $perseo_ConsultaCat = $perseo_datosCategoria['lineas'];
                            };
                            if (isset($perseo_datosCategoria['subcategorias'])) {
                                $perseo_ConsultaCat = $perseo_datosCategoria['subcategorias'];
                            };
                            if (isset($perseo_datosCategoria['subgrupo'])) {
                                $perseo_ConsultaCat = $perseo_datosCategoria['subgrupo'];
                            };


                            ///consulta sin categorizar
                            $perseo_Consultaidsincate = $wpdb->get_var("SELECT term.term_id as id FROM {$table_prefix}terms as term where term.name='Sin categorizar'");
                            // var_dump($perseo_Consultaidsincate);

                            foreach ($perseo_ConsultaCat as $categoria) {
                                $perseo_ConsultaCategoria = $wpdb->get_var("SELECT term.term_id as id FROM {$table_prefix}terms as term where term.name='" . $categoria['descripcion'] . "'");
                                //echo  $perseo_ConsultaCategoria;
                                //echo '<br';

                                if (empty($perseo_ConsultaCategoria)) {
                                    //echo $categoria['descripcion'];                                            
                                    $wpdb->insert(
                                        $table_prefix . 'terms',
                                        array(
                                            'name'      => $categoria['descripcion'],
                                            'slug'      => $categoria['descripcion'],
                                            'term_group' => '0'
                                        )
                                    );
                                    //////////////////////////////////////////
                                    //Consultamos id ultimo
                                    $perseo_rescate = $wpdb->get_var("SELECT MAX(term_id) FROM {$table_prefix}terms ");
                                    //echo  $perseo_rescate;
                                    //echo '<br';

                                    if (isset($categoria['productos_lineasid'])) {
                                        $perseo_idC = $categoria['productos_lineasid'];
                                    };
                                    if (isset($categoria['productos_categoriasid'])) {
                                        $perseo_idC = $categoria['productos_categoriasid'];
                                    };
                                    if (isset($categoria['productos_subcategoriasid'])) {
                                        $perseo_idC = $categoria['productos_subcategoriasid'];
                                    };
                                    if (isset($categoria['productos_subgruposid'])) {
                                        $perseo_idC = $categoria['productos_subgruposid'];
                                    };


                                    $wpdb->insert(
                                        $table_prefix . 'term_taxonomy',
                                        array(
                                            'term_id' => $perseo_rescate,
                                            'taxonomy'  => 'product_cat',
                                            'description' =>  $perseo_idC . '-Perseo',
                                            'parent' =>  '0',
                                            'count' => '0'
                                        )
                                    );
                                };
                            }
                            //////////////////////////////
                            //limpio variables json
                            $perseo_responsecategoria    = "";
                            $perseo_datosCategoria       = "";
                        }
                    };
                }
            }
        });
    }

    public function fperseo_impuestos()
    {
        $this->ejecutar_proceso_con_bloqueo('impuestos', function () {
            global $wpdb;
            global $table_prefix;
            $perseo_config      = get_option('pluginperseo_configuracion');
            $perseo_parametros  = get_option('pluginperseo_parametros');

            if ($perseo_parametros['perseoimpuestos'] == 'SI') {
                /////////////////////////////////////
                //subir tipos de ivas 
                if ($perseo_config['perseotiposoftware'] == 'WEB') {
                    $perseo_urliva = $perseo_config['perseoservidor'] . '/api/tipoiva_consulta';
                } else {
                    $perseo_urliva  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/tipoiva_consulta';
                    //  echo $perseo_urliva ;
                };
                //echo $perseo_urliva ;
                //echo
                $datoKEY = ['api_key' => $perseo_config['perseotoken']];
                $perseo_responseiva = wp_remote_post(
                    $perseo_urliva,
                    array(
                        'method'      => 'POST',
                        'timeout'     => 1800,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array('Content-Type' => 'application/json'),
                        'body'        => wp_json_encode($datoKEY)
                    )
                );

                if (!empty($perseo_responseiva)) {
                    ////////////////////////////////////////////
                    //Verificar si hay conexion con el api
                    if (is_wp_error($perseo_responseiva)) {
                        //no existe
                    } else {
                        if (isset($perseo_responseiva['body'])) {
                            $perseo_datosivas = json_decode($perseo_responseiva['body'], true);
                            //print_r($perseo_datosivas);
                            //verificar si existe el producto iva 
                            foreach ($perseo_datosivas['iva'] as $datoiva) {
                                ///si ya existe el iva
                                $Consultaiva = "";
                                $Consultaiva = $wpdb->get_var("SELECT iva.tax_rate_id FROM {$table_prefix}woocommerce_tax_rates iva where  iva.tax_rate =" . $datoiva['valor'] . " and iva.tax_rate_name ='" . $datoiva['porcentaje'] . "'");

                                if (empty($Consultaiva)) {
                                    //  print_r($Consultaiva);
                                    //  echo '<br>';

                                    switch ($datoiva['valor']) {
                                        case 0:
                                            $wpdb->insert($table_prefix . 'woocommerce_tax_rates', array(
                                                'tax_rate_country'  => 'EC',
                                                'tax_rate_state'    => '',
                                                'tax_rate'          => $datoiva['valor'],
                                                'tax_rate_name'     => $datoiva['porcentaje'],
                                                'tax_rate_priority'  => 1,
                                                'tax_rate_compound'  => 0,
                                                'tax_rate_shipping'  => 0,
                                                'tax_rate_order'  => 0,
                                                'tax_rate_class' => 'tasa-cero'
                                            ));
                                            break;
                                        default:
                                            $wpdb->insert($table_prefix . 'woocommerce_tax_rates', array(
                                                'tax_rate_country'  => 'EC',
                                                'tax_rate_state'    => '',
                                                'tax_rate'          => $datoiva['valor'],
                                                'tax_rate_name'     => $datoiva['porcentaje'],
                                                'tax_rate_priority'  => 1,
                                                'tax_rate_compound'  => 0,
                                                'tax_rate_shipping'  => 0,
                                                'tax_rate_order'  => 0,
                                                'tax_rate_class' => ''
                                            ));
                                            break;
                                    }
                                };
                            }
                            //////////////////////////////
                            //limpio variables json
                            $perseo_responseiva    = "";
                            $perseo_datosivas      = "";
                        }
                    };
                };
            };
        });
    }

    public function fperseo_producto()
    {
        $this->ejecutar_proceso_con_bloqueo('productos', function () {
            //echo 'Memoria en uso producto antes:  ('. round(((memory_get_usage() / 1024) / 1024),2) .'M) <br>';
            global $wpdb;
            global $table_prefix;
            $perseo_config      = get_option('pluginperseo_configuracion');
            $perseo_parametros  = get_option('pluginperseo_parametros');

            if ($perseo_parametros['perseoproductos'] == 'SI') {
                /////////////////////////////////////
                //Verificar pc o web
                if ($perseo_config['perseotiposoftware'] == 'WEB') {
                    $perseo_urlproducto = $perseo_config['perseoservidor'] . '/api/productos_consulta';
                    $perseo_urlimagen   = $perseo_config['perseoservidor'] . '/api/productos_imagenes_consulta';
                } else {
                    $perseo_urlproducto  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/productos_consulta';
                    $perseo_urlimagen    = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/productos_imagenes_consulta';
                };
                // echo "<br>- Producto---<br> ";
                //echo  $perseo_urlproducto;
                $perseo_bodyproducto = [
                    'api_key'       => $perseo_config['perseotoken'],
                    'productosid'   => '',
                    'productocodigo' => '',
                    'barras'        => '',
                    'contenido'     => ''
                ];
                //print_r(wp_json_encode($perseo_bodyproducto));
                //echo "<br>";
                $perseo_responseproducto = wp_remote_post(
                    $perseo_urlproducto,
                    array(
                        'method'      => 'POST',
                        'timeout'     => 55000,
                        'redirection' => 5,
                        'httpversion' => '1.0',
                        'blocking'    => true,
                        'headers'     => array('Content-Type' => 'application/json'),
                        'body'        => wp_json_encode($perseo_bodyproducto)
                    )
                );


                // print_r($perseo_responseproducto['body']);
                if (!empty($perseo_responseproducto)) {
                    ////////////////////////////////////////////
                    //Verificar si hay conexion con el api
                    if (is_wp_error($perseo_responseproducto)) {
                        //no existe
                    } else {
                        if (isset($perseo_responseproducto['body'])) {
                            $perseo_datosProductos = json_decode($perseo_responseproducto['body'], true);
                            // print_r($perseo_datosProductos['productos']);
                            //echo "<br>";   echo "<br>";
                            foreach ($perseo_datosProductos['productos'] as $producto) {
                                ////////////////////////////////////////////////////
                                //SI es producto esta activo 
                                // echo "<br>";
                                // echo $producto['descripcion']."-". $producto['venta']."-".$producto['estado']."-". $producto['servicio'];
                                // echo "<br>";echo "<br>";
                                if ($producto['venta'] == 1 && $producto['estado'] == 1  && $producto['servicio'] == 0 && $producto['ecommerce_estado'] == 1) {
                                    if ($producto['existenciastotales'] >= $perseo_parametros['perseoexistencias']) {
                                        ///variables actualizacion
                                        $ConsultaProductoUpd = $wpdb->get_var("SELECT post_id FROM {$table_prefix}postmeta where meta_key = 'PERSEOID' and meta_value =" . $producto['productosid']);

                                        //API imagen
                                        $perseo_body_imagenproducto = [
                                            'api_key'       => $perseo_config['perseotoken'],
                                            'productosid'   => $producto['productosid'],
                                        ];
                                        //print_r(wp_json_encode($perseo_bodyproducto));
                                        //echo "<br>";
                                        $perseo_response_imagenproducto = wp_remote_post(
                                            $perseo_urlimagen,
                                            array(
                                                'method'      => 'POST',
                                                'timeout'     => 55000,
                                                'redirection' => 5,
                                                'httpversion' => '1.0',
                                                'blocking'    => true,
                                                'headers'     => array('Content-Type' => 'application/json'),
                                                'body'        => wp_json_encode($perseo_body_imagenproducto)
                                            )
                                        );

                                        if (empty($ConsultaProductoUpd)) {
                                            // echo "PRODUCTO NUEVO";   
                                            //  echo $producto['productocodigo'];
                                            // echo "<br>"; 
                                            //  echo "<br>";                  

                                            $Remplazamos = preg_replace('([^A-Za-z0-9])', '', $producto['descripcion']);

                                            //////////////////////////////////////////////////
                                            //insertamos Nuevo producto PRIMERA TABLA plugin_posts
                                            $wpdb->insert(
                                                $table_prefix . 'posts',
                                                array(
                                                    'post_author' => '1',
                                                    'post_date' =>  $producto['fecha_sync'],
                                                    'post_date_gmt' => '0000-00-00 00:00:00',
                                                    'post_content' => $producto['fichatecnica'],
                                                    'post_title' =>  $producto['descripcion'],
                                                    'post_excerpt' =>  $producto['descripcion'],
                                                    'post_status' => 'publish',
                                                    'comment_status' => 'open',
                                                    'ping_status' => 'closed',
                                                    'post_password' => '',
                                                    'post_name' => $Remplazamos,
                                                    'to_ping' => '',
                                                    'pinged' => '',
                                                    'post_modified' => $producto['fecha_sync'],
                                                    'post_modified_gmt' => $producto['fecha_sync'],
                                                    'post_content_filtered' => '',
                                                    'post_parent' => '0',
                                                    'guid' =>  home_url() . '/?post_type=product&#038;p=',
                                                    'menu_order' => '0',
                                                    'post_type' => 'product',
                                                    'post_mime_type' => '',
                                                    'comment_count' => '0'
                                                )
                                            );
                                            ///////////////////////////////////////////////////////
                                            //Consultamos id ultimo
                                            $sqlProdID = "SELECT MAX(ID) FROM {$table_prefix}posts  ";
                                            $resProdPerseo = $wpdb->get_var($sqlProdID);
                                            //echo $resProdPerseo;
                                            //echo "<br>";
                                            $idPost = $resProdPerseo;
                                            ///////////////////////////////////////////////////////
                                            ///actualizamos 
                                            $wpdb->update($table_prefix . 'posts', array('guid' => home_url() . '/?post_type=product&#038;p=' . $idPost . ''), array('ID' => $idPost));
                                            ///////////////////////////////////////////////////
                                            //insertamos Nuevo producto SEGUNDA TABLA plugin_postmeta
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_edit_lock', 'meta_value' => '1589'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_edit_last', 'meta_value' => '1'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => 'total_sales', 'meta_value' => '0'));
                                            /////////////////////////////////////////
                                            ///saber si tiene IVA 12 % o 0%
                                            if ($producto['porcentajeiva'] == '0') {
                                                $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_tax_status', 'meta_value' => 'none'));
                                                $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_tax_class', 'meta_value' => 'tasa-cero'));
                                            } else {
                                                $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_tax_status', 'meta_value' => 'taxable'));
                                                $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_tax_class', 'meta_value' => ''));
                                            };

                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_manage_stock', 'meta_value' => 'yes'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_backorders', 'meta_value' => 'no'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_sold_individually', 'meta_value' => 'no'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_virtual', 'meta_value' => 'no'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_downloadable', 'meta_value' => 'no'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_download_limit', 'meta_value' => '-1'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_download_expiry', 'meta_value' => '-1'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_stock', 'meta_value' => $producto['existenciastotales']));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_stock_status', 'meta_value' => 'instock'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_wc_average_rating', 'meta_value' => '0'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_wc_review_count', 'meta_value' => '0'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => 'PERSEOID', 'meta_value' => $producto['productosid']));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => 'PERSEOCODPROD', 'meta_value' => $producto['productocodigo']));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => 'PERSEOPORCIVA', 'meta_value' => $producto['porcentajeiva']));
                                            ///////////////////////////
                                            ///Descripcion de producto

                                            $descProductoPS = array(
                                                'ID_Perseo' => array(
                                                    'name' => 'ID_Perseo',
                                                    'value' => intval($producto['productosid']),
                                                    'position' => '0',
                                                    'is_visible' => '0',
                                                    'is_variation' => '0',
                                                    'is_taxonomy' => '0'
                                                ),
                                                'COD_Perseo' => array(
                                                    'name' => 'COD_Perseo',
                                                    'value' => $producto['productocodigo'],
                                                    'position' => '0',
                                                    'is_visible' => '1',
                                                    'is_variation' => '0',
                                                    'is_taxonomy' => '0'
                                                )
                                            );
                                            $datosProd = serialize($descProductoPS);
                                            // print_r ($datosProd);
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_product_attributes', 'meta_value' =>  $datosProd));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_product_version', 'meta_value' => '4.1.0'));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_sku', 'meta_value' => '')); //nose
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_weight', 'meta_value' => ''));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_lenght', 'meta_value' => ''));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_width', 'meta_value' => ''));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_height', 'meta_value' => ''));
                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_purchase_note', 'meta_value' => '')); //nose

                                            //////////////////////////////////////////////////////////
                                            //insertamos Nuevo producto TERCERA TABLA plugin_term_relationships 
                                            //verificamos q cargo si categoria o linea wp_term_relationships categoriasproductos_consulta
                                            if ($perseo_parametros['perseocategorias'] == 'productos_lineas_consulta') {
                                                //consultamos categoria x codigo wp_term_taxonomy
                                                $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_lineasid'] . "-Perseo' ";
                                                $resProdCat = $wpdb->get_var($sql);
                                            };

                                            if ($perseo_parametros['perseocategorias'] == 'productos_categorias_consulta') {
                                                $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_categoriasid'] . "-Perseo' ";
                                                $resProdCat = $wpdb->get_var($sql);
                                            };

                                            if ($perseo_parametros['perseocategorias'] == 'productos_subcategorias_consulta') {
                                                $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_subcategoriasid'] . "-Perseo' ";
                                                $resProdCat = $wpdb->get_var($sql);
                                            };
                                            if ($perseo_parametros['perseocategorias'] == 'productos_subgrupos_consulta') {
                                                $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_subgruposid'] . "-Perseo' ";
                                                $resProdCat = $wpdb->get_var($sql);
                                            };


                                            $wpdb->insert($table_prefix . 'term_relationships', array('object_id'  => $idPost, 'term_taxonomy_id'  => $resProdCat, 'term_order' => '0'));

                                            /////////////////////////////////////////////////////
                                            //Saber el precio seleccionado

                                            foreach ($producto['tarifas'] as $tarifa) {
                                                //echo 'Aqui entro a tarifa <br>';
                                                //tarifa venta
                                                $perseo_tarifaventa = 0;
                                                //tarifa aumento   
                                                $perseo_tarifaaumento = 0;
                                                if (isset($tarifa)) {
                                                    //$perseo_iva= ($producto['porcentajeiva']/100)+1;
                                                    ////// si la tarifa es la misma solo ingrese la primera 
                                                    if ($perseo_parametros['perseotarifaVenta'] == $perseo_parametros['perseotarifaAumento']) {
                                                        if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                            $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_price', 'meta_value' => $perseo_tarifaventa)); //nose
                                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_regular_price', 'meta_value' => $perseo_tarifaventa));
                                                            ///////////////////////////////////////////////////
                                                            //insertamos registro/////////////////////////////
                                                            $wpdb->insert($table_prefix . 'wc_product_meta_lookup', array(
                                                                'product_id'    => $idPost,
                                                                'sku'           => '0',
                                                                'virtual'       => '0',
                                                                'downloadable'  => '0',
                                                                'min_price'     =>  0,
                                                                'max_price'     =>  $perseo_tarifaventa,
                                                                'onsale'        => '0',
                                                                'stock_quantity' => '',
                                                                'stock_status'  => 'instock',
                                                                'rating_count'  => '0',
                                                                'average_rating' => '0.00',
                                                                'total_sales'   => '0',
                                                                'tax_status'    => 'taxable',
                                                                'tax_class'     => ''
                                                            ));
                                                        }
                                                    } else {
                                                        if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                            $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_price', 'meta_value' => $perseo_tarifaventa)); //nose
                                                            $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $idPost, 'meta_key'  => '_regular_price', 'meta_value' => $perseo_tarifaventa));
                                                            ///////////////////////////////////////////////////
                                                            //insertamos registro/////////////////////////////
                                                            $wpdb->insert(
                                                                $table_prefix . 'wc_product_meta_lookup',
                                                                array(
                                                                    'product_id'    => $idPost,
                                                                    'sku'           => '0',
                                                                    'virtual'       => '0',
                                                                    'downloadable'  => '0',
                                                                    'min_price'     =>  0,
                                                                    'max_price'     =>  $perseo_tarifaventa,
                                                                    'onsale'        => '0',
                                                                    'stock_quantity' => '',
                                                                    'stock_status'  => 'instock',
                                                                    'rating_count'  => '0',
                                                                    'average_rating' => '0.00',
                                                                    'total_sales'   => '0',
                                                                    'tax_status'    => 'taxable',
                                                                    'tax_class'     => ''
                                                                )
                                                            );
                                                        };
                                                        //precio 2
                                                        if ($perseo_parametros['perseotarifaAumento'] == $tarifa['tarifasid']) {
                                                            $perseo_tarifaaumento = round($tarifa['precio'], 2);
                                                            $wpdb->insert($table_prefix . 'postmeta', array(
                                                                'post_id' => $idPost,
                                                                'meta_key'  => '_sale_price',
                                                                'meta_value' => $perseo_tarifaaumento
                                                            ));
                                                            ///////////////////////////////////////////////////
                                                            //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                            $wpdb->update(
                                                                $table_prefix . 'wc_product_meta_lookup',
                                                                array(
                                                                    'product_id'    => $idPost,
                                                                    'sku'           => '0',
                                                                    'virtual'       => '0',
                                                                    'downloadable'  => '0',
                                                                    'min_price'     =>  $perseo_tarifaaumento,
                                                                    'max_price'     =>  0,
                                                                    'onsale'        => '0',
                                                                    'stock_quantity' => '',
                                                                    'stock_status'  => 'instock',
                                                                    'rating_count'  => '0',
                                                                    'average_rating' => '0.00',
                                                                    'total_sales'   => '0',
                                                                    'tax_status'    => 'taxable',
                                                                    'tax_class'     => ''
                                                                ),
                                                                array('product_id' => $idPost)
                                                            );
                                                        }
                                                    }
                                                };
                                            }



                                            ///////////////////////////////////////////////////
                                            ///Ingresar imagenes si esta activado
                                            if ($perseo_parametros['perseoimagenes'] == 'SI') {

                                                // print_r($perseo_responseproducto['body']);
                                                if (!empty($perseo_response_imagenproducto)) {
                                                    ////////////////////////////////////////////
                                                    //Verificar si hay conexion con el api
                                                    if (is_wp_error($perseo_response_imagenproducto)) {
                                                        //no existe
                                                    } else {
                                                        if (isset($perseo_response_imagenproducto['body'])) {
                                                            $perseo_datos_ImagenProductos = json_decode($perseo_response_imagenproducto['body'], true);
                                                            $perseo_num     = 1;
                                                            $perseo_sumar   = '';
                                                            foreach ($perseo_datos_ImagenProductos['productos_imagenes'] as $imagen) {
                                                                //////////////////////////////////////////
                                                                //verificamos si esta activo el ecommerce
                                                                //var_dump($producto['imagenes']);
                                                                //echo "<br>";
                                                                if ($imagen["ecommerce"] == 1) {
                                                                    //echo "si es <br>";
                                                                    $perseo_nombreimagen = $producto['productosid'] . '' . substr($Remplazamos, 0, 15);
                                                                    //echo $perseo_nombreimagen;
                                                                    //echo '<br>';
                                                                    //echo '<br>';
                                                                    $Perseo_baseFromJavascript = "data:image/jpeg;base64,{$imagen['imagen']}";
                                                                    // Remover la parte de la cadena de texto que no necesitamos (data:image/png;base64,)
                                                                    // y usar base64_decode para obtener la información binaria de la imagen
                                                                    $Perseo_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $Perseo_baseFromJavascript));
                                                                    $Perseo_upload_dir  = wp_upload_dir();
                                                                    $upload_dir_perseo  = $Perseo_upload_dir['basedir'] . "/" . Date('Y') . "/" . Date('m');
                                                                    $Perseo_filepath    = $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png"; // or image.jpg
                                                                    $Perseo_filepath1   = $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-100x100.png"; // or image.jpg
                                                                    $Perseo_filepath2   = $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-150x150.png"; // or image.jpg
                                                                    // Finalmente guarda la imágen en el directorio especificado y con la informacion dada
                                                                    file_put_contents($Perseo_filepath, $Perseo_data);
                                                                    $perseoimage = wp_get_image_editor($Perseo_filepath);
                                                                    if (!is_wp_error($perseoimage)) {
                                                                        $perseoimage->resize(100, 100, true);
                                                                        $perseoimage->save($Perseo_filepath1);
                                                                    };
                                                                    $perseoimage1 = wp_get_image_editor($Perseo_filepath);
                                                                    if (!is_wp_error($perseoimage1)) {
                                                                        $perseoimage1->resize(150, 110, true);
                                                                        $perseoimage1->save($Perseo_filepath2);
                                                                    };

                                                                    $datima = Date('Y') . "/" . Date('m') . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png";
                                                                    //$upload_dir=['baseurl'];
                                                                    $upload_dir = get_site_url();
                                                                    $ValorGuid = "";
                                                                    //$ValorGuid =$upload_dir['baseurl']."/".$datima;
                                                                    $ValorGuid = $upload_dir . "/" . $datima;
                                                                    $wpdb->insert(
                                                                        $table_prefix . 'posts',
                                                                        array(
                                                                            'post_author' => '1',
                                                                            'post_date' =>  $producto['fecha_sync'],
                                                                            'post_date_gmt' => '0000-00-00 00:00:00',
                                                                            'post_content' => '',
                                                                            'post_title' => $perseo_num . '' . $perseo_nombreimagen,
                                                                            'post_excerpt' => '',
                                                                            'post_status' => 'inherit',
                                                                            'comment_status' => 'open',
                                                                            'ping_status' => 'closed',
                                                                            'post_password' => '',
                                                                            'post_name' => $perseo_num . '' . $perseo_nombreimagen,
                                                                            'to_ping' => '',
                                                                            'pinged' => '',
                                                                            'post_modified' => $producto['fecha_sync'],
                                                                            'post_modified_gmt' => $producto['fecha_sync'],
                                                                            'post_content_filtered' => '',
                                                                            'post_parent' => $idPost, //dato del registro padre 
                                                                            'guid' =>   $ValorGuid,
                                                                            'menu_order' => '0',
                                                                            'post_type' => 'attachment',
                                                                            'post_mime_type' => 'image/png',
                                                                            'comment_count' => '0'
                                                                        )
                                                                    );

                                                                    /////////////////////////// 
                                                                    /// atachement
                                                                    $perseo_info = getimagesize($Perseo_filepath);
                                                                    $perseo_info1 = getimagesize($Perseo_filepath1);
                                                                    $perseo_info2 = getimagesize($Perseo_filepath2);
                                                                    $meta = array(
                                                                        'width'     => $perseo_info[0],
                                                                        'height'    => $perseo_info[1],
                                                                        'file'      => Date('Y') . "/" . Date('m') . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png",
                                                                        'sizes'     => array(
                                                                            'thumbnail' => array(
                                                                                'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . ".png"),
                                                                                'width' => $perseo_info[0],
                                                                                'height' => $perseo_info[1],
                                                                                'mime-type' => 'image/png'
                                                                            ),
                                                                            'woocommerce_gallery_thumbnail' => array(
                                                                                'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . "-100x100.png"),
                                                                                'width' => $perseo_info1[0],
                                                                                'height' => $perseo_info1[1],
                                                                                'mime-type' => 'image/png'
                                                                            ),
                                                                            'shop_thumbnail' => array(
                                                                                'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . "-150x150.png"),
                                                                                'width' => $perseo_info2[0],
                                                                                'height' => $perseo_info2[1],
                                                                                'mime-type' => 'image/png'

                                                                            )
                                                                        ),
                                                                        'image_meta' => array(
                                                                            'aperture' => '0',
                                                                            'credit' => '',
                                                                            'camera' => '',
                                                                            'caption' => '',
                                                                            'created_timestamp' => '0',
                                                                            'copyright' => '',
                                                                            'focal_length' => '0',
                                                                            'iso' => '0',
                                                                            'shutter_speed' => '0',
                                                                            'title' => '',
                                                                            'orientation' => '0',
                                                                            'keywords' => array()
                                                                        )
                                                                    );
                                                                    ////////////////////////////////////////////////////////////
                                                                    //producto padre
                                                                    $perseo_sqlima = "SELECT MAX(ID) FROM {$table_prefix}posts ";

                                                                    ///////////////////////////////////////////
                                                                    //selecciona la primera imagen q sera visible en el producto
                                                                    if ($imagen["primera"] == 1) {
                                                                        $perseo_resima = $wpdb->get_var($perseo_sqlima);
                                                                        $wpdb->insert(
                                                                            $table_prefix . 'postmeta',
                                                                            array(
                                                                                'post_id' => $idPost,
                                                                                'meta_key'  => '_thumbnail_id',
                                                                                'meta_value' => $perseo_resima
                                                                            )
                                                                        );
                                                                    }
                                                                    ///////////////////////////////////////////////////
                                                                    //// imagen padre
                                                                    $perseo_nuevaima = $wpdb->get_var($perseo_sqlima);

                                                                    if ($imagen["primera"] == 0) {
                                                                        $perseo_sumar = $perseo_sumar . $perseo_nuevaima . ',';
                                                                    }

                                                                    $wpdb->insert(
                                                                        $table_prefix . 'postmeta',
                                                                        array(
                                                                            'post_id' => $perseo_nuevaima,
                                                                            'meta_key'  => '_wp_attached_file',
                                                                            'meta_value' => $datima
                                                                        )
                                                                    );
                                                                    $wpdb->insert(
                                                                        $table_prefix . 'postmeta',
                                                                        array(
                                                                            'post_id' => $perseo_nuevaima,
                                                                            'meta_key'  => '_wp_attachment_metadata',
                                                                            'meta_value' => serialize($meta)
                                                                        )
                                                                    );

                                                                    if ($imagen["ecommerce"] == 1) {
                                                                        //$perseo_concat=rtrim($perseo_sumar,',');
                                                                        $wpdb->insert(
                                                                            $table_prefix . 'postmeta',
                                                                            array(
                                                                                'post_id' => $idPost,
                                                                                'meta_key'  => '_product_image_gallery',
                                                                                'meta_value' => rtrim($perseo_sumar, ',')
                                                                            )
                                                                        );
                                                                    }

                                                                    $perseo_num++;
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            };
                                        } else {
                                            ///////////////////////////////////////////////////////
                                            ///Fecha de modificacion es igual a la fecha de ingreso
                                            $ConsultaProductofecha = $wpdb->get_var("SELECT posts.post_modified FROM {$table_prefix}posts posts where  posts.post_type='product' and posts.ID='" . $ConsultaProductoUpd . "'");
                                            $perseo_fechaprod =  date_format(date_create($producto['fecha_sync']), 'Y-m-d H:i:s');
                                            //echo $perseo_fechaprod ." > ".$ConsultaProductofecha;
                                            //echo "<br>";                   
                                            if ($perseo_fechaprod  > $ConsultaProductofecha) {
                                                //echo "PRODUCTO MODIFICADO";
                                                //echo "<br>"; 
                                                $perseo_actualizar = array(
                                                    'post_content'      =>  $producto['fichatecnica'],
                                                    'post_title'        =>  $producto['descripcion'],
                                                    'post_excerpt'      =>  $producto['descripcion'],
                                                    'post_modified'     =>  $producto['fecha_sync'],
                                                    'post_modified_gmt' =>  $producto['fecha_sync']
                                                );


                                                $wpdb->update($table_prefix . 'posts', $perseo_actualizar, array('ID' => $ConsultaProductoUpd));

                                                update_post_meta($ConsultaProductoUpd, '_stock', $producto['existenciastotales']);

                                                ////////////////////////////////////////////////////
                                                //Eliminamos impuestos 
                                                $wpdb->query("Delete from {$table_prefix}postmeta where meta_key  = '_tax_status'  and  post_id=" . $ConsultaProductoUpd);
                                                $wpdb->query("Delete from {$table_prefix}postmeta where meta_key  = '_tax_class'  and  post_id=" . $ConsultaProductoUpd);
                                                /////////////////////////////////////////
                                                ///saber si tiene IVA 12 % o 0%
                                                if ($producto['porcentajeiva'] == '0') {
                                                    $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $ConsultaProductoUpd, 'meta_key'  => '_tax_status', 'meta_value' => 'none'));
                                                    $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $ConsultaProductoUpd, 'meta_key'  => '_tax_class', 'meta_value' => 'tasa-cero'));
                                                } else {
                                                    $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $ConsultaProductoUpd, 'meta_key'  => '_tax_status', 'meta_value' => 'taxable'));
                                                    $wpdb->insert($table_prefix . 'postmeta', array('post_id' => $ConsultaProductoUpd, 'meta_key'  => '_tax_class', 'meta_value' => ''));
                                                };

                                                ///////////////////////////////////////////////////
                                                //insertamos Nuevo producto TERCERA TABLA plugin_term_relationships 
                                                //verificamos q cargo si categoria o linea wp_term_relationships categoriasproductos_consulta
                                                if ($perseo_parametros['perseocategorias'] == 'productos_lineas_consulta') {
                                                    //consultamos categoria x codigo wp_term_taxonomy
                                                    $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_lineasid'] . "-Perseo' ";
                                                    $resProdCat = $wpdb->get_var($sql);
                                                };

                                                if ($perseo_parametros['perseocategorias'] == 'productos_categorias_consulta') {
                                                    $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_categoriasid'] . "-Perseo' ";
                                                    $resProdCat = $wpdb->get_var($sql);
                                                };

                                                if ($perseo_parametros['perseocategorias'] == 'productos_subcategorias_consulta') {
                                                    $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_subcategoriasid'] . "-Perseo' ";
                                                    $resProdCat = $wpdb->get_var($sql);
                                                };

                                                if ($perseo_parametros['perseocategorias'] == 'productos_subgrupos_consulta') {
                                                    $sql = "SELECT term_taxonomy_id  FROM {$table_prefix}term_taxonomy where description = '" . $producto['productos_subgruposid'] . "-Perseo' ";
                                                    $resProdCat = $wpdb->get_var($sql);
                                                };

                                                $wpdb->query("Delete from {$table_prefix}term_relationships where object_id=" . $ConsultaProductoUpd);
                                                $wpdb->insert($table_prefix . 'term_relationships', array('object_id'  => $ConsultaProductoUpd, 'term_taxonomy_id'  => $resProdCat, 'term_order' => '0'));

                                                //$wpdb->update($table_prefix.'term_relationships',array('term_taxonomy_id'=> $resProdCat),array('object_id' => $ConsultaProductoUpd)); 

                                                /////////////////////////////////////////////////////
                                                //Saber el precio seleccionado                                

                                                foreach ($producto['tarifas'] as $tarifa) {
                                                    //tarifa venta
                                                    $perseo_tarifaventa = 0;
                                                    //tarifa aumento   
                                                    $perseo_tarifaaumento = 0;

                                                    if (isset($tarifa)) {
                                                        //$perseo_iva= ($producto['porcentajeiva']/100)+1;
                                                        ////// si la tarifa es la misma solo ingrese la primera 
                                                        if ($perseo_parametros['perseotarifaVenta'] == $perseo_parametros['perseotarifaAumento']) {
                                                            if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                                $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                                //echo $perseo_tarifaventa;
                                                                //echo "<br>";
                                                                update_post_meta($ConsultaProductoUpd, '_price', $perseo_tarifaventa);
                                                                update_post_meta($ConsultaProductoUpd, '_regular_price', $perseo_tarifaventa);

                                                                ///////////////////////////////////////////////////
                                                                //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                                $wpdb->update(
                                                                    $table_prefix . 'wc_product_meta_lookup',
                                                                    array(
                                                                        'max_price'     =>  $perseo_tarifaventa
                                                                    ),
                                                                    array('product_id' => $ConsultaProductoUpd)
                                                                );
                                                            }
                                                        } else {
                                                            if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                                $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                                //echo $perseo_tarifaventa;
                                                                //echo "<br>";
                                                                update_post_meta($ConsultaProductoUpd, '_price', $perseo_tarifaventa);
                                                                update_post_meta($ConsultaProductoUpd, '_regular_price', $perseo_tarifaventa);

                                                                ///////////////////////////////////////////////////
                                                                //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                                $wpdb->update(
                                                                    $table_prefix . 'wc_product_meta_lookup',
                                                                    array(
                                                                        'max_price'     =>  $perseo_tarifaventa
                                                                    ),
                                                                    array('product_id' => $ConsultaProductoUpd)
                                                                );
                                                            };
                                                            //precio 2
                                                            if ($perseo_parametros['perseotarifaAumento'] == $tarifa['tarifasid']) {
                                                                $perseo_tarifaaumento = round($tarifa['precio'], 2);
                                                                update_post_meta($ConsultaProductoUpd, '_sale_price', $perseo_tarifaaumento);

                                                                ///////////////////////////////////////////////////
                                                                //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                                $wpdb->update(
                                                                    $table_prefix . 'wc_product_meta_lookup',
                                                                    array(
                                                                        'min_price'     =>  $perseo_tarifaaumento
                                                                    ),
                                                                    array('product_id' => $ConsultaProductoUpd)
                                                                );
                                                            }
                                                        }
                                                    };
                                                }

                                                ///////////////////////////////////////////////////
                                                ///Ingresar imagenes si esta activado
                                                if ($perseo_parametros['perseoimagenes'] == 'SI') {
                                                    $Remplazamos = preg_replace('([^A-Za-z0-9])', '', $producto['descripcion']);
                                                    $perseo_nombreimagen = $producto['productosid'] . '' . substr($Remplazamos, 0, 15);

                                                    $upload_dir = wp_upload_dir();
                                                    $upload_dir_perseo = $upload_dir['basedir'] . "/" . Date('Y') . "/" . Date('m');

                                                    /////////////////////////////////////////////////
                                                    //Eliminar imagenes del producto y volver a guardar
                                                    $perseo_eliminarimagenes = $wpdb->get_results("SELECT posts.post_modified FROM {$table_prefix}posts posts where  posts.post_mime_type='image/png' and posts.post_parent=" . $ConsultaProductoUpd);
                                                    if (!empty($perseo_eliminarimagenes)) {
                                                        ///////////////////////////////////
                                                        //si tiene imagenes 
                                                        $perseoimagen = $wpdb->get_results("SELECT  meta.post_id as postid FROM {$table_prefix}posts posts , {$table_prefix}postmeta meta where posts.ID = meta.post_id and meta.meta_key='_wp_attached_file' and posts.post_parent =" . $ConsultaProductoUpd);
                                                        //var_dump($perseoimagen);
                                                        $perseo_num = 1;
                                                        foreach ($perseoimagen as $direcion) {
                                                            $perseoEliminarImg = unlink($upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png");
                                                            $perseoEliminarImg = unlink($upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-100x100.png");
                                                            $perseoEliminarImg = unlink($upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-150x150.png");
                                                            $perseo_num++;

                                                            $wpdb->query("Delete from {$table_prefix}postmeta where post_id=" . $direcion->postid . " and meta_key='_wp_attached_file'");
                                                            $wpdb->query(" Delete from {$table_prefix}postmeta where post_id=" . $direcion->postid . " and meta_key='_wp_attachment_metadata'");

                                                            $wpdb->query(" Delete from {$table_prefix}postmeta where post_id=" . $ConsultaProductoUpd . " and meta_key='_thumbnail_id'");
                                                            $wpdb->query(" Delete from {$table_prefix}postmeta where post_id=" . $ConsultaProductoUpd . " and meta_key='_product_image_gallery'");

                                                            $wpdb->query(" Delete from {$table_prefix}posts where ID=" . $direcion->postid . " and post_mime_type='image/png'");
                                                        }
                                                    } //else{
                                                    //////////////////////////////////
                                                    //no tiene imagenes 
                                                    $Perseo_baseFromJavascript = '';
                                                    $Perseo_data = '';
                                                    $perseo_num = 1;
                                                    $perseo_sumar = '';

                                                    if (isset($perseo_response_imagenproducto['body'])) {
                                                        $perseo_datos_ImagenProductos = json_decode($perseo_response_imagenproducto['body'], true);

                                                        foreach ($perseo_datos_ImagenProductos['productos_imagenes'] as $imagen) {
                                                            if ($imagen["ecommerce"] == 1) {
                                                                $perseo_nombreimagen = $producto['productosid'] . '' . substr($Remplazamos, 0, 15);
                                                                $Perseo_baseFromJavascript = "data:image/jpeg;base64,{$imagen['imagen']}";
                                                                // Remover la parte de la cadena de texto que no necesitamos (data:image/png;base64,)
                                                                // y usar base64_decode para obtener la información binaria de la imagen
                                                                $Perseo_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $Perseo_baseFromJavascript));

                                                                $Perseo_filepath =    $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png"; // or image.jpg
                                                                $Perseo_filepath1 =    $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-100x100.png"; // or image.jpg
                                                                $Perseo_filepath2 =    $upload_dir_perseo . "/" . $perseo_num . '' . $perseo_nombreimagen . "-150x150.png"; // or image.jpg
                                                                // Finalmente guarda la imágen en el directorio especificado y con la informacion dada
                                                                file_put_contents($Perseo_filepath, $Perseo_data);
                                                                $perseo_image = wp_get_image_editor($Perseo_filepath);
                                                                if (!is_wp_error($perseo_image)) {
                                                                    $perseo_image->resize(100, 100, true);
                                                                    $perseo_image->save($Perseo_filepath1);
                                                                };
                                                                $perseo_image1 = wp_get_image_editor($Perseo_filepath);
                                                                if (!is_wp_error($perseo_image1)) {
                                                                    $perseo_image1->resize(150, 110, true);
                                                                    $perseo_image1->save($Perseo_filepath2);
                                                                };

                                                                $datima = Date('Y') . "/" . Date('m') . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png";

                                                                $wpdb->insert(
                                                                    $table_prefix . 'posts',
                                                                    array(
                                                                        'post_author' => '1',
                                                                        'post_date' =>  $producto['fecha_sync'],
                                                                        'post_date_gmt' => '0000-00-00 00:00:00',
                                                                        'post_content' => '',
                                                                        'post_title' => $perseo_num . '' . $perseo_nombreimagen,
                                                                        'post_excerpt' => '',
                                                                        'post_status' => 'inherit',
                                                                        'comment_status' => 'open',
                                                                        'ping_status' => 'closed',
                                                                        'post_password' => '',
                                                                        'post_name' => $perseo_num . '' . $perseo_nombreimagen,
                                                                        'to_ping' => '',
                                                                        'pinged' => '',
                                                                        'post_modified' => $producto['fecha_sync'],
                                                                        'post_modified_gmt' => '0000-00-00 00:00:00',
                                                                        'post_content_filtered' => '',
                                                                        'post_parent' => $ConsultaProductoUpd, //dato del registro padre 
                                                                        'guid' =>   $upload_dir['baseurl'] . "/" . $datima,
                                                                        'menu_order' => '0',
                                                                        'post_type' => 'attachment',
                                                                        'post_mime_type' => 'image/png',
                                                                        'comment_count' => '0'
                                                                    )
                                                                );

                                                                /////////////////////////// 
                                                                /// atachement
                                                                $perseo_info = getimagesize($Perseo_filepath);
                                                                $perseo_info1 = getimagesize($Perseo_filepath1);
                                                                $perseo_info2 = getimagesize($Perseo_filepath2);
                                                                $meta = array(
                                                                    'width'     => $perseo_info[0],
                                                                    'height'    => $perseo_info[1],
                                                                    'file'      => Date('Y') . "/" . Date('m') . "/" . $perseo_num . '' . $perseo_nombreimagen . ".png",
                                                                    'sizes'     => array(
                                                                        'thumbnail' => array(
                                                                            'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . ".png"),
                                                                            'width' => $perseo_info[0],
                                                                            'height' => $perseo_info[1],
                                                                            'mime-type' => 'image/png'
                                                                        ),
                                                                        'woocommerce_gallery_thumbnail' => array(
                                                                            'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . "-100x100.png"),
                                                                            'width' => $perseo_info1[0],
                                                                            'height' => $perseo_info1[1],
                                                                            'mime-type' => 'image/png'
                                                                        ),
                                                                        'shop_thumbnail' => array(
                                                                            'file' => basename("/" . $perseo_num . '' . $perseo_nombreimagen . "-150x150.png"),
                                                                            'width' => $perseo_info2[0],
                                                                            'height' => $perseo_info2[1],
                                                                            'mime-type' => 'image/png'

                                                                        )
                                                                    ),
                                                                    'image_meta' => array(
                                                                        'aperture' => '0',
                                                                        'credit' => '',
                                                                        'camera' => '',
                                                                        'caption' => '',
                                                                        'created_timestamp' => '0',
                                                                        'copyright' => '',
                                                                        'focal_length' => '0',
                                                                        'iso' => '0',
                                                                        'shutter_speed' => '0',
                                                                        'title' => '',
                                                                        'orientation' => '0',
                                                                        'keywords' => array()
                                                                    )
                                                                );
                                                                ////////////////////////////////////////////////////////////
                                                                //producto padre
                                                                $perseo_sqlima = "SELECT MAX(ID) FROM {$table_prefix}posts ";

                                                                ///////////////////////////////////////////
                                                                //selecciona la imagen del producto
                                                                if ($imagen["primera"] == 1) {
                                                                    $perseo_resima = $wpdb->get_var($perseo_sqlima);
                                                                    $wpdb->insert(
                                                                        $table_prefix . 'postmeta',
                                                                        array(
                                                                            'post_id' => $ConsultaProductoUpd,
                                                                            'meta_key'  => '_thumbnail_id',
                                                                            'meta_value' => $perseo_resima
                                                                        )
                                                                    );
                                                                }
                                                                ///////////////////////////////////////////////////
                                                                //// imagen padre
                                                                $perseo_nuevaima = $wpdb->get_var($perseo_sqlima);
                                                                if ($imagen["primera"] == 0) {
                                                                    $perseo_sumar = $perseo_sumar . $perseo_nuevaima . ',';
                                                                }

                                                                $wpdb->insert(
                                                                    $table_prefix . 'postmeta',
                                                                    array(
                                                                        'post_id' => $perseo_nuevaima,
                                                                        'meta_key'  => '_wp_attached_file',
                                                                        'meta_value' => $datima
                                                                    )
                                                                );
                                                                $wpdb->insert(
                                                                    $table_prefix . 'postmeta',
                                                                    array(
                                                                        'post_id' => $perseo_nuevaima,
                                                                        'meta_key'  => '_wp_attachment_metadata',
                                                                        'meta_value' => serialize($meta)
                                                                    )
                                                                );

                                                                if ($imagen["ecommerce"] == 1) {
                                                                    //$perseo_concat=rtrim($perseo_sumar,',');
                                                                    $wpdb->insert(
                                                                        $table_prefix . 'postmeta',
                                                                        array(
                                                                            'post_id' => $ConsultaProductoUpd,
                                                                            'meta_key'  => '_product_image_gallery',
                                                                            'meta_value' => rtrim($perseo_sumar, ',')
                                                                        )
                                                                    );

                                                                    $perseo_num++;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    //}    

                                                };
                                            };
                                        }
                                    }
                                };
                            }
                            //////////////////////////////
                            //limpio variables json
                            //$perseo_responseproducto    = "";
                            //$perseo_datosProductos      = "";
                            //imagenes base 64 y wordpressimg
                            $Perseo_baseFromJavascript  = "";
                            $Perseo_data                = "";
                            $perseoimage                = null;
                            $perseoimage1               = null;
                        }
                    };
                    //limite de memoria
                    //echo 'Memoria usada: ' . round(memory_get_usage() / 1024,1) . ' KB de ' . round(memory_get_usage(1) / 1024,1) . ' KB';
                    // echo 'Memoria en uso:  ('. round(((memory_get_usage() / 1024) / 1024),2) .'M) <br>';
                    //echo 'Memory limit: ' . ini_get('memory_limit') . '<br>';
                };
            };
            // });
        });
    }

    public function fperseo_stockproducto()
    {
        $this->ejecutar_proceso_con_bloqueo('stock_producto', function () {
            // echo '<br>- consulta stock del producto desde aqui- <br>';
            global $wpdb;
            global $table_prefix;
            $perseo_config          = get_option('pluginperseo_configuracion');
            $perseo_parametros      = get_option('pluginperseo_parametros');


            //////////////////////////////////
            //mostramos el codigo del producto para traer stock mediante consultas 
            $ConsultaProducstock = $wpdb->get_results("SELECT product_id FROM {$table_prefix}wc_product_meta_lookup ");
            //print_r($ConsultaProducstock);
            //echo '<br>- consulta  <br>';
            if (!empty($ConsultaProducstock)) {

                foreach ($ConsultaProducstock as $DatProd) {
                    $perseo_totalstock = 0;
                    //consulta stock
                    //echo $DatProd -> product_id;
                    //echo '<br>- consulta stock- <br>';

                    $ConsultaProducstock = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key ='_product_attributes' and post_id=" . $DatProd->product_id);
                    $perseo_codigoprodstock = unserialize($ConsultaProducstock);
                    // print_r($perseo_codigoprodstock);
                    // echo '<br>';  
                    // echo '<br>'; 
                    if (isset($perseo_codigoprodstock['ID_Perseo']['value'])) {
                        $perseo_codigostock = $perseo_codigoprodstock['ID_Perseo']['value'];
                        //}else{
                        //          $perseo_codigostock=$perseo_codigoprodstock['id_perseo']['value'];


                        if ($perseo_parametros['perseostock'] == 'SI') {
                            if ($perseo_config['perseotiposoftware'] == 'WEB') {
                                $perseo_urlstock = $perseo_config['perseoservidor'] . '/api/existencia_producto';
                            } else {
                                $perseo_urlstock  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/existencia_producto';
                            };
                            //  echo $perseo_codigostock;
                            // echo '<br>- codigo stock - <br>';
                            $perseo_bodystock = [
                                'api_key'       => $perseo_config['perseotoken'],
                                'productosid'   => $perseo_codigostock,
                                'productocodigo' => '',
                                'barras'        => '',
                                'contenido'     => ''
                            ];
                            //echo $perseo_bodystock;
                            // echo '<br>';
                            // echo '<br>';
                            ///////////////////////////////////
                            //ejecutamos y enviamos  el id del producto 
                            $perseo_responsestsock = wp_remote_post(
                                $perseo_urlstock,
                                array(
                                    'method'      => 'POST',
                                    'timeout'     => 5500,
                                    'redirection' => 5,
                                    'httpversion' => '1.0',
                                    'blocking'    => true,
                                    'headers'     => array('Content-Type' => 'application/json'),
                                    'body'        => wp_json_encode($perseo_bodystock)
                                )
                            );
                            if (!empty($perseo_responsestsock)) {
                                ////////////////////////////////////////////
                                //Verificar si hay conexion con el api
                                if (is_wp_error($perseo_responsestsock)) {
                                    //no existe
                                } else {
                                    if (isset($perseo_responsestsock['body'])) {
                                        $perseo_datosPrdstock = json_decode($perseo_responsestsock['body'], true);

                                        if (!isset($perseo_datosPrdstock['fault'])) {

                                            // var_dump($perseo_datosPrdstock);
                                            //echo '<br>';
                                            //  echo '<br>';

                                            foreach ($perseo_datosPrdstock['existencias'] as $stock) {
                                                $perseo_totalstock += $stock['existencias'];
                                            }
                                            //echo $perseo_totalstock;
                                            //echo '<br>';
                                            ////////////////////////////////////
                                            //actualizamos stock
                                            //$wpdb->insert($table_prefix.'usermeta', array('user_id' => $Perseo_IDUSU,'meta_key'=>'PerseoID','meta_value'=>$Perseo_USU));

                                            update_post_meta($DatProd->product_id, '_stock', $perseo_totalstock);
                                        } else {
                                            // echo "no hay importe";
                                            //echo '<br>';
                                            //echo '<br>';
                                        }
                                        //////////////////////////////
                                        //limpio variables json
                                        $perseo_datosPrdstock       = "";
                                        $perseo_responsestsock      = "";
                                    }
                                }
                            }
                        };
                    }
                }
            }
        });
    }

    public function fperseo_ActualizarTarifas()
    {
        $this->ejecutar_proceso_con_bloqueo('actualizar_tarifas', function () {
            // echo '<br>- consulta stock del producto desde aqui- <br>';
            global $wpdb;
            global $table_prefix;
            $perseo_config          = get_option('pluginperseo_configuracion');
            $perseo_parametros      = get_option('pluginperseo_parametros');


            //////////////////////////////////
            //mostramos el codigo del producto para traer tarifa mediante consultas 
            $ConsultaProducstock = $wpdb->get_results("SELECT product_id FROM {$table_prefix}wc_product_meta_lookup ");
            //print_r($ConsultaProducstock);
            //echo '<br>- consulta  <br>';
            if (!empty($ConsultaProducstock)) {

                foreach ($ConsultaProducstock as $DatProd) {
                    $perseo_totalstock = 0;
                    //consulta id del producto 
                    //echo 'ID woocommerce ';
                    //echo $DatProd -> product_id;
                    // echo '<br><br>';

                    $ConsultaProductoUpd = $DatProd->product_id;
                    //echo  $ConsultaProductoUpd;
                    $ConsultaProducstock = $wpdb->get_var("SELECT meta_value FROM {$table_prefix}postmeta where meta_key ='_product_attributes' and post_id=" . $DatProd->product_id);
                    $perseo_codigoprodstock = unserialize($ConsultaProducstock);
                    //print_r($perseo_codigoprodstock);
                    //echo '<br>'; 
                    if (isset($perseo_codigoprodstock['ID_Perseo']['value'])) {
                        $perseo_codigostock = $perseo_codigoprodstock['ID_Perseo']['value'];
                        //}else{
                        //            $perseo_codigostock=$perseo_codigoprodstock['id_perseo']['value'];


                        //echo $perseo_codigoprodstock['ID_Perseo']['value'];
                        //echo ' - ID perseo<br>';  
                        //echo '<br>'; 
                        /////////////////////////////////////
                        //Verificar pc o web
                        if ($perseo_config['perseotiposoftware'] == 'WEB') {
                            $perseo_urlproducto = $perseo_config['perseoservidor'] . '/api/productos_consulta';
                        } else {
                            $perseo_urlproducto  = $perseo_config['perseocertificado'] . '://' . $perseo_config['perseoip'] . '/api/productos_consulta';
                        };
                        // echo "<br>- Producto---<br> ";
                        //echo  $perseo_urlproducto;
                        $perseo_bodyproducto = [
                            'api_key'       => $perseo_config['perseotoken'],
                            'productosid'   => $perseo_codigostock,
                            'productocodigo' => '',
                            'barras'        => '',
                            'contenido'     => ''
                        ];
                        //print_r(wp_json_encode($perseo_bodyproducto));
                        //echo "<br>";
                        $perseo_responseproducto = wp_remote_post(
                            $perseo_urlproducto,
                            array(
                                'method'      => 'POST',
                                'timeout'     => 55000,
                                'redirection' => 5,
                                'httpversion' => '1.0',
                                'blocking'    => true,
                                'headers'     => array('Content-Type' => 'application/json'),
                                'body'        => wp_json_encode($perseo_bodyproducto)
                            )
                        );
                        // print_r($perseo_responseproducto['body']);
                        if (!empty($perseo_responseproducto)) {
                            ////////////////////////////////////////////
                            //Verificar si hay conexion con el api
                            if (is_wp_error($perseo_responseproducto)) {
                                //no existe
                            } else {
                                if (isset($perseo_responseproducto['body'])) {

                                    $perseo_datosProductos = json_decode($perseo_responseproducto['body'], true);
                                    // print_r($perseo_datosProductos['productos']);
                                    //echo "<br>";   echo "<br>";
                                    foreach ($perseo_datosProductos['productos'] as $producto) {
                                        foreach ($producto['tarifas'] as $tarifa) {
                                            //print_r($tarifa);
                                            //echo "<br>";
                                            if (isset($tarifa)) {

                                                //$perseo_iva= ($producto['porcentajeiva']/100)+1;
                                                //// Si tuviera los mismos precios eliminar la promocion 
                                                if ($perseo_parametros['perseotarifaVenta'] == $perseo_parametros['perseotarifaAumento']) {
                                                    if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                        $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                        update_post_meta($ConsultaProductoUpd, '_price', $perseo_tarifaventa);
                                                        update_post_meta($ConsultaProductoUpd, '_sale_price', $perseo_tarifaventa);
                                                        update_post_meta($ConsultaProductoUpd, '_regular_price', $perseo_tarifaventa);
                                                    };
                                                } else {
                                                    if ($perseo_parametros['perseotarifaVenta'] == $tarifa['tarifasid']) {
                                                        // echo "tarifa venta";          
                                                        $perseo_tarifaventa = round($tarifa['precio'], 2);
                                                        //echo $perseo_tarifaventa;
                                                        //echo "<br>";
                                                        update_post_meta($ConsultaProductoUpd, '_price', $perseo_tarifaventa);
                                                        update_post_meta($ConsultaProductoUpd, '_sale_price', $perseo_tarifaventa);
                                                        update_post_meta($ConsultaProductoUpd, '_regular_price', $perseo_tarifaventa);

                                                        ///////////////////////////////////////////////////
                                                        //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                        $wpdb->update(
                                                            $table_prefix . 'wc_product_meta_lookup',
                                                            array('max_price'  =>  $perseo_tarifaventa),
                                                            array('product_id' => $ConsultaProductoUpd)
                                                        );
                                                    };
                                                    //precio 2
                                                    if ($perseo_parametros['perseotarifaAumento'] == $tarifa['tarifasid']) {
                                                        //echo "tarifa aumento";                                              
                                                        $perseo_tarifaaumento = round($tarifa['precio'], 2);
                                                        update_post_meta($ConsultaProductoUpd, '_price', $perseo_tarifaaumento);
                                                        update_post_meta($ConsultaProductoUpd, '_sale_price', $perseo_tarifaaumento);

                                                        ///////////////////////////////////////////////////
                                                        //insertamos Nuevo producto CUARTA TABLA plugin_wc_product_meta_lookup
                                                        $wpdb->update(
                                                            $table_prefix . 'wc_product_meta_lookup',
                                                            array('min_price'     =>  $perseo_tarifaaumento),
                                                            array('product_id' => $ConsultaProductoUpd)
                                                        );
                                                    };
                                                };
                                            };
                                        }
                                    }
                                }
                            };
                        };
                    };
                }
            };
        });
    }
}
