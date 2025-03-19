<?php
/*
Plugin Name: WHMCS Register Clave
Description: Formulário de registro WHMCS para Clave Internet
Version: 1.0
Author: Vinicius
*/

if (!defined('ABSPATH')) exit;

class WHMCS_Register_Clave {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        // Verifica se o Elementor está instalado e ativado
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', [$this, 'admin_notice_missing_elementor']);
            return;
        }

        // Adiciona o widget personalizado
        add_action('elementor/widgets/register', [$this, 'register_widgets']);
        
        // Adiciona o endpoint para processar o formulário
        add_action('init', [$this, 'add_form_endpoint']);

        // Registra os scripts e estilos
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets() {
        // jQuery Mask
        wp_enqueue_script(
            'jquery-mask',
            'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js',
            ['jquery'],
            '1.14.16',
            true
        );

        // CSS personalizado
        wp_enqueue_style(
            'whmcs-register-style',
            plugins_url('assets/css/style.css', __FILE__),
            [],
            '1.0'
        );
    }

    public function admin_notice_missing_elementor() {
        if (isset($_GET['activate'])) unset($_GET['activate']);

        $message = sprintf(
            'O plugin %1$s requer o %2$s para funcionar.',
            '<strong>WHMCS Register Clave</strong>',
            '<strong>Elementor</strong>'
        );

        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }

    public function register_widgets($widgets_manager) {
        require_once(__DIR__ . '/widgets/whmcs-form-widget.php');
        $widgets_manager->register(new \WHMCS_Form_Widget());
    }

    public function add_form_endpoint() {
        add_rewrite_endpoint('whmcs-register-process', EP_ROOT);
        
        // Adiciona o handler para processar o formulário
        add_action('template_redirect', function() {
            if (!isset($_GET['whmcs-register-process'])) return;
            
            require_once(__DIR__ . '/process.php');
            exit;
        });
    }
}

new WHMCS_Register_Clave();
