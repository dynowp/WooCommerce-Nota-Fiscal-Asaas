<?php

namespace NotaFiscalForAsaas\Admin;

use NotaFiscalForAsaas\Controllers\InvoiceController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderInvoiceMenu {
    protected $controller;

    public function __construct() {
        // Load options and initialize controller with API key and logging setting
        $options = get_option( 'nf_asaas_options' );
        $enable_logging = isset( $options['enable_logging'] ) && $options['enable_logging'] === '1';
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

        $this->controller = new InvoiceController( $api_key, $enable_logging );
    }

    /**
     * Initialize hooks for WooCommerce order actions
     */
    public function init() {
        add_filter( 'woocommerce_order_actions', array( $this, 'add_emit_invoice_action' ) );
        add_action( 'woocommerce_order_action_emit_invoice', array( $this, 'process_emit_invoice_action' ) );
    }

    /**
     * Add "Emitir Nota Fiscal" to available order actions
     *
     * @param array $actions Existing actions.
     * @return array Modified actions.
     */
    public function add_emit_invoice_action( $actions ) {
        $actions['emit_invoice'] = 'Emitir Nota Fiscal'; // Keep log messages in Portuguese
        return $actions;
    }

    /**
     * Process the "Emitir Nota Fiscal" action when button is clicked
     *
     * @param \WC_Order $order Order object.
     */
    public function process_emit_invoice_action( $order ) {
        $order_id = $order->get_id();
        $result = $this->controller->emit_invoice_for_order( $order_id );

        // Display success notice if invoice is issued successfully
        if ( $result ) {
            add_action( 'admin_notices', function() use ( $order_id ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo 'Nota Fiscal emitida para o Pedido #' . esc_html( $order_id ) . '.'; ?></p>
                </div>
                <?php
            });
        } else {
            // Display error notice if invoice issuance fails
            add_action( 'admin_notices', function() use ( $order_id ) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo 'Falha ao emitir Nota Fiscal para o Pedido #' . esc_html( $order_id ) . '.'; ?></p>
                </div>
                <?php
            });
        }
    }
}