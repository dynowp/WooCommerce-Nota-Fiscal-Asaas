<?php

namespace NotaFiscalForAsaas\Controllers;

use NotaFiscalForAsaas\Services\UpdateCustomerService;
use WC_Order;

class UpdateCustomerController {
    protected $update_customer_service;
    public $enable_logging;

    public function __construct( $access_token, $enable_logging ) {
        $this->enable_logging = $enable_logging;
        $this->update_customer_service = new UpdateCustomerService( $access_token, $enable_logging );
    }

    /**
     * Update the customer's neighborhood (address_2) in Asaas before issuing the invoice
     *
     * @param int $order_id Order ID
     * @return bool
     */
    public function update_customer_before_invoice( $order_id ) {
        // Log the start of the update
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Pedido não encontrado: ID {$order_id}", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return false;
        }

        // Retrieve neighborhood (address_2)
        $billing_address_2 = $order->get_billing_address_2();
        $province = ! empty( $billing_address_2 ) ? $billing_address_2 : '';

        if ( empty( $province ) ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Bairro (address_2) não encontrado para o Pedido ID {$order_id}", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return false;
        }

        // Retrieve customer_id from Asaas
        $customer_id = $this->get_customer_id_from_order( $order );

        if ( ! $customer_id ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Customer ID da Asaas não encontrado para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return false;
        }

        // Update customer in Asaas
        $update_response = $this->update_customer_service->update_customer_province( $customer_id, $province );

        if ( is_wp_error( $update_response ) ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Falha ao atualizar cliente na Asaas para o Pedido ID {$order_id}: " . $update_response->get_error_message(), array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            update_post_meta( $order_id, '_NotaFiscalForAsaas_customer_update', 'failed' );
            return false;
        }

        update_post_meta( $order_id, '_NotaFiscalForAsaas_customer_update', 'success' );

        return true;
    }

    /**
     * Retrieve the customer_id from Asaas based on order metadata
     *
     * @param WC_Order $order
     * @return string|false
     */
    protected function get_customer_id_from_order( $order ) {
        // Retrieve order metadata
        $meta_data = $order->get_meta_data();
        foreach ( $meta_data as $meta ) {
            if ( '__ASAAS_ORDER' === $meta->key ) {
                $asaas_order = json_decode( $meta->value, true );
                if ( isset( $asaas_order['customer'] ) ) {
                    return sanitize_text_field( $asaas_order['customer'] );
                }
            }
        }

        return false;
    }
}
