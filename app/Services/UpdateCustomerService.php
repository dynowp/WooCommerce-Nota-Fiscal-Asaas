<?php

namespace NotaFiscalForAsaas\Services;

use NotaFiscalForAsaas\Config\Config;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UpdateCustomerService {
    protected $api_url_template;
    protected $access_token;
    protected $logger;
    public $options;

    public function __construct( $access_token, $enable_logging = false ) {
        // Carregar opções
        $this->options = get_option( 'nf_asaas_options', array() );

        // Determinar o ambiente e definir a URL da API
        $environment = isset( $this->options['environment'] ) ? $this->options['environment'] : 'sandbox';
        if ( $environment === 'production' ) {
            $base_url = Config::ASAAS_API_URL_PRODUCTION . 'customers/%s';
        } else {
            $base_url = Config::ASAAS_API_URL_SANDBOX . 'customers/%s';
        }
        $this->api_url_template = $base_url;

        // Sanitizar token de acesso
        $this->access_token = sanitize_text_field( $access_token );

        // Ativar logging se necessário
        if ( $enable_logging ) {
            $this->logger = wc_get_logger();
        }
    }

    /**
     * Update customer's province in Asaas using wp_remote_request()
     *
     * @param string $customer_id Customer ID in Asaas
     * @param string $province Province to be updated
     * @return array|WP_Error
     */
    public function update_customer_province( $customer_id, $province ) {
        $api_url = sprintf( $this->api_url_template, urlencode( $customer_id ) );

        // Format the request body as JSON
        $data = array(
            'province' => sanitize_text_field( $province ), 
        );
        $body = wp_json_encode( $data );

        // Define HTTP headers
        $headers = array(
            'access_token' => $this->access_token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
        );

        // Log the access token (Production log)
        if ( $this->logger ) {
            $this->logger->info( 'Token de Acesso usado para atualizar cliente.', array( 'source' => 'NotaFiscalForAsaas' ) );
        }

        // Setup request arguments
        $args = array(
            'method'    => 'PUT',
            'headers'   => $headers,
            'body'      => $body,
            'timeout'   => 30,
        );

        // Send the HTTP request using wp_remote_request
        $response = wp_remote_request( $api_url, $args );

        // Check for request errors
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            if ( $this->logger ) {
                $this->logger->error( 'Erro ao conectar ao servidor Asaas: ' . $error_message, array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return new WP_Error( 'request_failed', 'Erro ao conectar ao servidor Asaas: ' . $error_message );
        }

        // Retrieve the HTTP status code
        $http_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Log the response if not successful (Production log)
        if ( ! in_array( $http_code, array( 200, 204 ), true ) ) {
            if ( $this->logger ) {
                $this->logger->error( 'Erro ao atualizar cliente na Asaas: ' . $response_body, array( 'source' => 'NotaFiscalForAsaas' ) );
            }
            return new WP_Error( 'api_error', 'Erro ao atualizar cliente na Asaas: ' . $response_body );
        }

        // Log success message (Production log)
        if ( $this->logger ) {
            $this->logger->info( 'Cliente atualizado com sucesso na Asaas: ID ' . $customer_id, array( 'source' => 'NotaFiscalForAsaas' ) );
        }

        return array( 'success' => true );
    }
}
