<?php

namespace NotaFiscalForAsaas\Hooks;

use NotaFiscalForAsaas\Controllers\UpdateCustomerController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateCustomerHooks {
    protected $controller;

    /**
     * Constructor to initialize the controller.
     *
     * @param string $access_token    The access token for Asaas API.
     * @param bool   $enable_logging  Flag to enable or disable logging.
     */
    public function __construct( $access_token, $enable_logging ) {
        $this->controller = new UpdateCustomerController( $access_token, $enable_logging );
    }

    /**
     * Initialize WooCommerce hooks for customer updates.
     */
    public function init() {
        // Hook to handle the thank you page (ensures Customer ID is already added)
        add_action( 'woocommerce_thankyou', array( $this, 'handle_thankyou_page' ), 10, 1 );
    }

    /**
     * Handles the thank you page to update the customer in Asaas.
     *
     * @param int $order_id The order ID.
     */
    public function handle_thankyou_page( $order_id ) {
        if ( ! $order_id ) {
            if ( $this->controller->enable_logging ) {
                wc_get_logger()->error( "ID do pedido inválido na página de agradecimento.", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return;
        }

        $update_success = $this->controller->update_customer_before_invoice( $order_id );

        if ( $update_success ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->info( "Cliente atualizado com sucesso na Asaas para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
        } else {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->error( "Falha ao atualizar cliente na Asaas para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
        }
    }
}
