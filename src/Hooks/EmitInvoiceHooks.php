<?php

namespace NotaFiscalForAsaas\Hooks;

use NotaFiscalForAsaas\Controllers\InvoiceController;
use NotaFiscalForAsaas\Controllers\UpdateCustomerController;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EmitInvoiceHooks {
    protected $invoice_controller;
    protected $update_customer_controller;
    protected $options;

    public function __construct( $access_token, $enable_logging ) {
        // Initialize controllers for invoice and customer updates
        $this->invoice_controller = new InvoiceController( $access_token, $enable_logging );
        $this->update_customer_controller = new UpdateCustomerController( $access_token, $enable_logging );
        $this->options = get_option( 'nf_asaas_options', array() );
    }

    /**
     * Initialize WooCommerce hooks for automatic invoice emission
     */
    public function init() {
        add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
    }

    /**
     * Handle order status changes to trigger invoice emission
     *
     * @param int      $order_id   Order ID
     * @param string   $old_status Previous status
     * @param string   $new_status New status
     * @param WC_Order $order      Order object
     */
    public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
        if ( isset( $this->options['emit_automatically'] ) && $this->options['emit_automatically'] === '1' ) {
            $required_status = isset( $this->options['order_status'] ) ? $this->options['order_status'] : 'processing';

            if ( $new_status === $required_status ) {
                $emit_result = $this->invoice_controller->emit_invoice_for_order( $order_id );

                // Log only critical errors or successful actions for production
                if ( is_wp_error( $emit_result ) ) {
                    $error_code = $emit_result->get_error_code();
                    $error_message = $emit_result->get_error_message();

                    // Check if error is due to incomplete customer address
                    if ( $error_code === 'invalid_action' && strpos( $error_message, 'Endereço do cliente incompleto.' ) !== false ) {
                        // Attempt to update customer data
                        $update_success = $this->update_customer_controller->update_customer_before_invoice( $order_id );

                        if ( $update_success ) {
                            // Retry invoice emission
                            $retry_emit_result = $this->invoice_controller->emit_invoice_for_order( $order_id );

                            if ( is_wp_error( $retry_emit_result ) ) {
                                // Log failure after retry
                                if ( function_exists( 'wc_get_logger' ) ) {
                                    wc_get_logger()->error( "Falha ao emitir nota fiscal após atualizar cliente para o Pedido ID {$order_id}: " . $retry_emit_result->get_error_message(), array( 'source' => 'NotaFiscalForAsaas' ) );
                                }
                            } else {
                                // Log success after retry
                                if ( function_exists( 'wc_get_logger' ) ) {
                                    wc_get_logger()->info( "Nota fiscal emitida com sucesso após atualizar cliente para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
                                }
                            }
                        } else {
                            // Log failure to update customer
                            if ( function_exists( 'wc_get_logger' ) ) {
                                wc_get_logger()->error( "Falha ao atualizar cliente para corrigir emissão da nota fiscal para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
                            }
                        }
                    } else {
                        // Log other errors
                        if ( function_exists( 'wc_get_logger' ) ) {
                            wc_get_logger()->error( "Falha ao emitir nota fiscal para o Pedido ID {$order_id}: " . $error_message, array( 'source' => 'NotaFiscalForAsaas' ) );
                        }
                    }
                } else {
                    // Log success when the invoice is issued successfully
                    if ( function_exists( 'wc_get_logger' ) ) {
                        wc_get_logger()->info( "Nota fiscal emitida com sucesso para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
                    }
                }
            }
        }
    }
}