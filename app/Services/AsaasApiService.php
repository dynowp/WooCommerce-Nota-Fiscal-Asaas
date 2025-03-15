<?php

namespace NotaFiscalForAsaas\Services;

use NotaFiscalForAsaas\Config\Config;
use WP_Error;

class AsaasApiService {
    protected $api_url;
    protected $access_token;
    protected $logger;
    public $options;

    public function __construct( $access_token, $enable_logging = false ) {
        $this->access_token = sanitize_text_field( $access_token );
        $this->options = get_option( 'nf_asaas_options', array() );

        $environment = isset( $this->options['environment'] ) ? $this->options['environment'] : 'sandbox';
        if ( $environment === 'production' ) {
            $this->api_url = Config::ASAAS_API_URL_PRODUCTION . 'invoices';
        } else {
            $this->api_url = Config::ASAAS_API_URL_SANDBOX . 'invoices';
        }

        if ( $enable_logging ) {
            $this->logger = wc_get_logger();
        }
    }

    /**
     * Issue an invoice via Asaas API
     *
     * @param array $data
     * @return array|WP_Error
     */
    public function emit_invoice( $data ) {
        $body = wp_json_encode( $data );

        $headers = array(
            'Accept'       => 'application/json',
            'access_token' => $this->access_token,
            'Content-Type' => 'application/json',
        );

        if ( $this->logger ) {
            $this->logger->info( 'Emitindo nota fiscal com ID: ' . (isset($data['id']) ? $data['id'] : 'N/A'), array( 'source' => 'NotaFiscalForAsaas' ) );
        }

        $response = wp_remote_post( $this->api_url, array(
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( ! in_array( $response_code, array( 200, 201 ), true ) ) {
            return new WP_Error( 'api_error', 'Erro ao emitir Nota Fiscal: ' . $response_body );
        }

        $response_data = json_decode( $response_body, true );

        if ( isset( $response_data['id'] ) ) {
            if ( $this->logger ) {
                $this->logger->info( 'Nota Fiscal emitida com sucesso com ID: ' . $response_data['id'], array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return $response_data;
        }

        return new WP_Error( 'unexpected_response', 'Estrutura de resposta inesperada.' );
    }
    
    /**
     * Recover a single invoice by ID -> in English
     *
     * @param string $invoice_id ID da nota fiscal
     * @return array|WP_Error Dados da nota fiscal ou objeto de erro
     */
    public function get_invoice( $invoice_id ) {
        if ( empty( $invoice_id ) ) {
            return new WP_Error( 'missing_id', 'ID da nota fiscal não informado.' );
        }
        
        $headers = array(
            'Accept'       => 'application/json',
            'access_token' => $this->access_token,
        );
        
        $invoice_url = $this->api_url . '/' . sanitize_text_field( $invoice_id );
        
        if ( $this->logger ) {
            $this->logger->info( 'Recuperando nota fiscal com ID: ' . $invoice_id, array( 'source' => 'NotaFiscalForAsaas' ) );
        }
        
        $response = wp_remote_get( $invoice_url, array(
            'headers' => $headers,
            'timeout' => 30,
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        if ( $response_code !== 200 ) {
            return new WP_Error( 'api_error', 'Erro ao recuperar Nota Fiscal: ' . $response_body );
        }
        
        $response_data = json_decode( $response_body, true );
        
        if ( isset( $response_data['id'] ) ) {
            if ( $this->logger ) {
                $this->logger->info( 'Nota Fiscal recuperada com sucesso: ' . $invoice_id, array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return $response_data;
        }
        
        return new WP_Error( 'unexpected_response', 'Estrutura de resposta inesperada.' );
    }
}
