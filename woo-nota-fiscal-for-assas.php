<?php
/**
 * Plugin Name: Nota Fiscal for Asaas
 * Description: Integração do WooCommerce com o Asaas para emissão automática de Nota Fiscal.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: DynoWP
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nota-fiscal-for-asaas
 * Requires Plugins: woocommerce
 * Tested up to: WordPress 6.6.2
 * WooCommerce tested up to: 9.3.1
 */

 if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define the absolute path to the plugin directory
define( 'NF_ASAAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Autoload classes using the PSR-4 standard
spl_autoload_register( function ( $class ) {
    $prefix = 'NotaFiscalForAsaas\\';
    $base_dirs = [
        __DIR__ . '/src/',
        __DIR__ . '/app/'
    ];

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );

    foreach ( $base_dirs as $base_dir ) {
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        if ( file_exists( $file ) ) {
            require $file;
            return;
        }
    }
} );

// Initialize the plugin components
function nf_asaas_init() {
    // Load plugin options
    $options = get_option( 'nf_asaas_options', array() );
    $enable_logging = isset( $options['enable_logging'] ) && $options['enable_logging'] === '1';
    $access_token = isset( $options['api_key'] ) ? $options['api_key'] : '';

    // Check if the access token is configured
    if ( empty( $access_token ) ) {
        // Optional: Add an error message or notify the admin
        add_action( 'admin_notices', 'nfa_missing_access_token_notice' );
        return;
    }

    // Initialize plugin settings
    if ( class_exists( 'NotaFiscalForAsaas\Admin\SettingsPage' ) ) {
        $settings = new NotaFiscalForAsaas\Admin\SettingsPage();
        $settings->init();
    }

    // Initialize the order invoice menu
    if ( class_exists( 'NotaFiscalForAsaas\Admin\OrderInvoiceMenu' ) ) {
        $order_invoice_menu = new NotaFiscalForAsaas\Admin\OrderInvoiceMenu();
        $order_invoice_menu->init();
    }

    // Initialize WooCommerce hooks for customer updates
    if ( class_exists( 'NotaFiscalForAsaas\Hooks\UpdateCustomerHooks' ) ) {
        $update_customer_hooks = new NotaFiscalForAsaas\Hooks\UpdateCustomerHooks( $access_token, $enable_logging );
        $update_customer_hooks->init();
    }

    // Initialize WooCommerce hooks for invoice emission
    if ( class_exists( 'NotaFiscalForAsaas\Hooks\EmitInvoiceHooks' ) ) {
        $emit_invoice_hooks = new NotaFiscalForAsaas\Hooks\EmitInvoiceHooks( $access_token, $enable_logging );
        $emit_invoice_hooks->init();
    }
}
add_action( 'init', 'nf_asaas_init' );

// Add an error notification if the access token is not configured
function nfa_missing_access_token_notice() {
    echo '<div class="error"><p>';
    echo 'O plugin Nota Fiscal for Asaas está ativo, mas a chave de API (access token) não está configurada. Por favor, configure-a nas configurações do plugin.';
    echo '</p></div>';
}
