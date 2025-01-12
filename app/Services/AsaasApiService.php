<?php

namespace NotaFiscalForAsaas\Services;

use NotaFiscalForAsaas\Config\Config;
use WC_Logger;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AsaasApiService {
    protected $api_url;
    protected $access_token;
    protected $logger;
    public $options;

    public function __construct( $access_token, $enable_logging = false ) {
        // Sanitizar token de acesso
        $this->access_token = sanitize_text_field( $access_token );
        
        // Carregar opções
        $this->options = get_option( 'nf_asaas_options', array() );

        // Determinar o ambiente e definir a URL da API
        $environment = isset( $this->options['environment'] ) ? $this->options['environment'] : 'sandbox';
        if ( $environment === 'production' ) {
            $this->api_url = Config::ASAAS_API_URL_PRODUCTION . 'invoices';
        } else {
            $this->api_url = Config::ASAAS_API_URL_SANDBOX . 'invoices';
        }

        // Ativar logging se necessário
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

        // Log only important info for production
        if ( $this->logger ) {
            $this->logger->info( 'Emitindo nota fiscal com ID: ' . (isset($data['id']) ? $data['id'] : 'N/A'), array( 'source' => 'NotaFiscalForAsaas' ) );
        }

        $response = wp_remote_post( $this->api_url, array(
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 60,
        ) );

        // Handle error if API request fails
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Check if response code is not successful
        if ( ! in_array( $response_code, array( 200, 201 ), true ) ) {
            return new WP_Error( 'api_error', 'Erro ao emitir Nota Fiscal: ' . $response_body );
        }

        $response_data = json_decode( $response_body, true );

        // Log success with important details
        if ( isset( $response_data['id'] ) ) {
            if ( $this->logger ) {
                $this->logger->info( 'Nota Fiscal emitida com sucesso com ID: ' . $response_data['id'], array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return $response_data;
        }

        return new WP_Error( 'unexpected_response', 'Estrutura de resposta inesperada.' );
    }
}
