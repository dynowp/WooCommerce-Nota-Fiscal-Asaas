<?php

namespace NotaFiscalForAsaas\Controllers;

use NotaFiscalForAsaas\Services\AsaasApiService;
use NotaFiscalForAsaas\Controllers\UpdateCustomerController;
use WC_Order;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class InvoiceController {
    protected $asaas_service;
    protected $update_customer_controller;
    protected $enable_logging;

    public function __construct( $access_token, $enable_logging ) {
        $this->enable_logging = $enable_logging;
        $this->asaas_service = new AsaasApiService( $access_token, $enable_logging );
        $this->update_customer_controller = new UpdateCustomerController( $access_token, $enable_logging );
    }

    /**
     * Issue invoice for a specific order with payment details
     *
     * @param int $order_id Order ID
     * @return array|WP_Error
     */
    public function emit_invoice_for_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Pedido não encontrado: ID {$order_id}", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return new WP_Error( 'order_not_found', 'Pedido não encontrado.' );
        }

        $total = $order->get_total();
        $formatted_total = number_format( floatval( $total ), 2, '.', '' ); // Format "00.00"

        // Build service description
        $service_description = 'Pedido #' . intval( $order_id );

        // Observations: blank space
        $observations = ' ';

        // Retrieve the Asaas ID from the order
        $asaas_id = $this->get_asaas_id_from_order( $order );

        if ( ! $asaas_id ) {
            if ( $this->enable_logging ) {
                wc_get_logger()->error( "Asaas ID não encontrado para o Pedido ID {$order_id}", array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return new WP_Error( 'asaas_id_not_found', 'Asaas ID não encontrado para o pedido.' );
        }

        // Build taxes array
        $taxes = array(
            'cofins' => isset( $this->asaas_service->options['aliquota_cofins'] ) ? floatval( $this->asaas_service->options['aliquota_cofins'] ) : 0,
            'csll'    => isset( $this->asaas_service->options['aliquota_csll'] ) ? floatval( $this->asaas_service->options['aliquota_csll'] ) : 0,
            'inss'    => isset( $this->asaas_service->options['aliquota_inss'] ) ? floatval( $this->asaas_service->options['aliquota_inss'] ) : 0,
            'ir'      => isset( $this->asaas_service->options['aliquota_ir'] ) ? floatval( $this->asaas_service->options['aliquota_ir'] ) : 0,
            'pis'     => isset( $this->asaas_service->options['aliquota_pis'] ) ? floatval( $this->asaas_service->options['aliquota_pis'] ) : 0,
            'iss'     => isset( $this->asaas_service->options['aliquota_iss'] ) ? floatval( $this->asaas_service->options['aliquota_iss'] ) : 0,
        );

        // Build data array according to specifications
        $data = array(
            'serviceDescription'   => $service_description,
            'observations'         => $observations,
            'value'                => (float) $formatted_total,
            'deductions'           => 0,
            'effectiveDate'        => current_time( 'Y-m-d' ),
            'municipalServiceCode' => isset( $this->asaas_service->options['service_code'] ) ? sanitize_text_field( $this->asaas_service->options['service_code'] ) : '1.01',
            'taxes'                => $taxes,
            'payment'              => $asaas_id,
        );

        // Attempt to issue the invoice
        $response = $this->asaas_service->emit_invoice( $data );

        if ( is_wp_error( $response ) ) {
            // Check if the error is related to an incomplete address
            if (
                $response->get_error_code() === 'api_error' &&
                strpos( $response->get_error_message(), 'invalid_action' ) !== false &&
                strpos( $response->get_error_message(), 'Endereço do cliente incompleto' ) !== false
            ) {
                if ( $this->enable_logging ) {
                    wc_get_logger()->info( "Endereço do cliente incompleto para Pedido ID {$order_id}. Tentando atualizar o cliente.", array( 'source' => 'NotaFiscalForAsaas' ) );
                }

                // Delegate customer update to UpdateCustomerController
                $update_success = $this->update_customer_controller->update_customer_before_invoice( $order_id );

                if ( ! $update_success ) {
                    if ( $this->enable_logging ) {
                        wc_get_logger()->error( "Falha ao atualizar cliente antes da emissão da nota fiscal para o Pedido ID {$order_id}.", array( 'source' => 'NotaFiscalForAsaas' ) );
                    }
                    return new WP_Error( 'customer_update_failed', 'Falha ao atualizar cliente antes da emissão da nota fiscal.' );
                }

                if ( $this->enable_logging ) {
                    wc_get_logger()->info( "Cliente atualizado com sucesso na Asaas para o Pedido ID {$order_id}. Tentando emitir a nota fiscal novamente.", array( 'source' => 'NotaFiscalForAsaas' ) );
                }

                // Attempt to issue the invoice again
                $response = $this->asaas_service->emit_invoice( $data );

                if ( is_wp_error( $response ) ) {
                    if ( $this->enable_logging ) {
                        wc_get_logger()->error( "Falha ao emitir nota fiscal após atualizar o cliente para o Pedido ID {$order_id}: " . $response->get_error_message(), array( 'source' => 'NotaFiscalForAsaas' ) );
                    }
                    return $response;
                }
            } else {
                if ( $this->enable_logging ) {
                    wc_get_logger()->error( "Falha ao emitir nota fiscal para o Pedido ID {$order_id}: " . $response->get_error_message(), array( 'source' => 'NotaFiscalForAsaas' ) );
                }
                return $response;
            }
        }

        // Store the issued invoice ID
        if ( isset( $response['id'] ) ) {
            update_post_meta( $order_id, '_NotaFiscalForAsaas_invoice_id', sanitize_text_field( $response['id'] ) );
        }

        return $response;
    }

    /**
     * Retrieve the asaas_id from the order
     *
     * @param WC_Order $order
     * @return string|false
     */
    protected function get_asaas_id_from_order( $order ) {
        $asaas_ids = array();
        $meta_data = $order->get_meta_data();
        foreach ( $meta_data as $meta ) {
            if ( '_asaas_id' === $meta->key ) {
                $asaas_ids[] = sanitize_text_field( $meta->value );
            }
        }
        // Return the last asaas_id found
        if ( ! empty( $asaas_ids ) ) {
            return end( $asaas_ids );
        }
        return false;
    }
}
