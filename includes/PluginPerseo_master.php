<?php

class CPerseo_Master{

    protected $perseo_pluginpath;
    protected $perseo_pluginpathdir;
    protected $perseo_version;
    protected $perseo_cargador;
    protected $perseo_usuariosmail;
    protected $perseo_cron;


    public function __construct(){
      $this->perseo_version         ='1.0.0';
      $this->perseo_pluginpath      = plugin_dir_path(__FILE__);
      $this->perseo_pluginpathdir   = plugin_dir_path(__DIR__);
      $this->fperseo_cargardependencias();      
      $this->fperseo_cargarinstancias();
      $this->fperseo_definiradminhooks();

    }

    public function fperseo_cargardependencias() {
        require_once $this->perseo_pluginpath.'PluginPerseo_cargador.php';
        require_once $this->perseo_pluginpath.'PluginPerseo_cron.php';

    }
    public function fperseo_cargarinstancias(){
        $this->perseo_cargador = new CPerseo_Cargador;
        $this->perseo_cron     = new CPerseo_Cron;
    }
    public function fperseo_definiradminhooks(){
        
        ///Cron
        $this->perseo_cargador->add_action( 'cron_schedules', $this->perseo_cron, 'fperseo_intervalos');  
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_impuestos');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_pedidos');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_cliente');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_categoria');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_producto');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_stockproducto');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_enviarclientes');
        $this->perseo_cargador->add_action( 'perseo_cron', $this->perseo_cron, 'fperseo_ActualizarTarifas');

        $this->perseo_cargador->add_action( 'init', $this->perseo_cron, 'fperseo_inicializador');
    }

    public function fperseo_run(){
        $this->perseo_cargador->run();
    }
}
?>