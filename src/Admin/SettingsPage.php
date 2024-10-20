<?php
namespace NotaFiscalForAsaas\Admin;

use NotaFiscalForAsaas\Controllers\InvoiceController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SettingsPage {
    /**
     * Initialize the settings page by adding hooks.
     */
    public function init() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add the settings submenu under WooCommerce.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Nota Fiscal for Asaas',
            'Nota Fiscal for Asaas',
            'manage_options',
            'nota-fiscal-for-asaas',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Render the settings page HTML.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Configurações Nota Fiscal for Asaas</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'nf_asaas_settings_group' );
                    do_settings_sections( 'nota-fiscal-for-asaas' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting(
            'nf_asaas_settings_group',
            'nf_asaas_options',
            array( $this, 'sanitize_settings' )
        );

        add_settings_section(
            'nf_asaas_main_section',
            'Configurações Principais',
            null,
            'nota-fiscal-for-asaas'
        );

        // Enable Logging
        add_settings_field(
            'enable_logging',
            'Ativar Logs',
            array( $this, 'enable_logging_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );

        // Automatic Issuance
        add_settings_field(
            'emit_automatically',
            'Ativar Emissão Automática de Nota Fiscal',
            array( $this, 'emit_automatically_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );

        // Order Status for Issuance
        add_settings_field(
            'order_status',
            'Status do Pedido para Emissão',
            array( $this, 'order_status_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );

        // Retain ISS
        add_settings_field(
            'retain_iss',
            'Reter ISS',
            array( $this, 'retain_iss_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );

        // Municipal Service Code
        add_settings_field(
            'service_code',
            'Código do Serviço Municipal',
            array( $this, 'service_code_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );

        // Tax Rates
        $tax_fields = array(
            'aliquota_iss'    => 'Alíquota ISS (%)',
            'aliquota_cofins' => 'Alíquota COFINS (%)',
            'aliquota_csll'   => 'Alíquota CSLL (%)',
            'aliquota_inss'   => 'Alíquota INSS (%)',
            'aliquota_ir'     => 'Alíquota IR (%)',
            'aliquota_pis'    => 'Alíquota PIS (%)',
        );

        foreach ( $tax_fields as $field_key => $field_label ) {
            add_settings_field(
                $field_key,
                $field_label,
                array( $this, 'text_field_callback' ),
                'nota-fiscal-for-asaas',
                'nf_asaas_main_section',
                array( 'field' => $field_key )
            );
        }

        // API Key
        add_settings_field(
            'api_key',
            'Chave de API',
            array( $this, 'api_key_callback' ),
            'nota-fiscal-for-asaas',
            'nf_asaas_main_section'
        );
    }

    /**
     * Sanitize and validate settings input.
     *
     * @param array $input The input values.
     * @return array The sanitized values.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        // Enable Logging
        $sanitized['enable_logging'] = isset( $input['enable_logging'] ) && $input['enable_logging'] === '1' ? '1' : '0';

        // Automatic Issuance
        $sanitized['emit_automatically'] = isset( $input['emit_automatically'] ) && $input['emit_automatically'] === '1' ? '1' : '0';

        // Order Status
        $allowed_statuses = array( 'processing', 'completed' );
        $sanitized['order_status'] = isset( $input['order_status'] ) && in_array( $input['order_status'], $allowed_statuses, true ) ? $input['order_status'] : 'processing';

        // Retain ISS
        $sanitized['retain_iss'] = isset( $input['retain_iss'] ) && $input['retain_iss'] === '1' ? '1' : '0';

        // Municipal Service Code
        if ( isset( $input['service_code'] ) ) {
            $sanitized['service_code'] = sanitize_text_field( $input['service_code'] );
        }

        // Tax Rates
        $tax_fields = array(
            'aliquota_iss',
            'aliquota_cofins',
            'aliquota_csll',
            'aliquota_inss',
            'aliquota_ir',
            'aliquota_pis',
        );

        foreach ( $tax_fields as $field ) {
            if ( isset( $input[ $field ] ) ) {
                $sanitized[ $field ] = floatval( $input[ $field ] );
            }
        }

        // API Key
        if ( isset( $input['api_key'] ) ) {
            $sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
        }

        return $sanitized;
    }

    /**
     * Callback for the Enable Logging field.
     */
    public function enable_logging_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <input type="checkbox" id="enable_logging" name="nf_asaas_options[enable_logging]" value="1" <?php checked( 1, isset( $options['enable_logging'] ) ? $options['enable_logging'] : 0 ); ?> />
        <label for="enable_logging">Ativar Logs do WooCommerce para Depuração</label>
        <?php
    }

    /**
     * Callback for the Automatic Issuance field.
     */
    public function emit_automatically_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <input type="checkbox" id="emit_automatically" name="nf_asaas_options[emit_automatically]" value="1" <?php checked( 1, isset( $options['emit_automatically'] ) ? $options['emit_automatically'] : 0 ); ?> />
        <label for="emit_automatically">Ativar Emissão Automática de Nota Fiscal</label>
        <?php
    }

    /**
     * Callback for the Order Status field.
     */
    public function order_status_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <select id="order_status" name="nf_asaas_options[order_status]" <?php echo ( isset( $options['emit_automatically'] ) && $options['emit_automatically'] === '1' ) ? '' : 'disabled'; ?>>
            <option value="processing" <?php selected( 'processing', isset( $options['order_status'] ) ? $options['order_status'] : 'processing' ); ?>>
                Processando
            </option>
            <option value="completed" <?php selected( 'completed', isset( $options['order_status'] ) ? $options['order_status'] : 'processing' ); ?>>
                Concluído
            </option>
        </select>
        <?php
    }

    /**
     * Callback for the Retain ISS field.
     */
    public function retain_iss_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <input type="checkbox" id="retain_iss" name="nf_asaas_options[retain_iss]" value="1" <?php checked( 1, isset( $options['retain_iss'] ) ? $options['retain_iss'] : 0 ); ?> />
        <label for="retain_iss">Reter ISS</label>
        <?php
    }

    /**
     * Callback for the Municipal Service Code field.
     */
    public function service_code_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <input type="text" id="service_code" name="nf_asaas_options[service_code]" value="<?php echo isset( $options['service_code'] ) ? esc_attr( $options['service_code'] ) : '1.01'; ?>" />
        <?php
    }

    /**
     * Callback for text fields (Tax Rates).
     *
     * @param array $args The field arguments.
     */
    public function text_field_callback( $args ) {
        $options = get_option( 'nf_asaas_options' );
        $field = $args['field'];
        ?>
        <input type="text" id="<?php echo esc_attr( $field ); ?>" name="nf_asaas_options[<?php echo esc_attr( $field ); ?>]" value="<?php echo isset( $options[ $field ] ) ? esc_attr( $options[ $field ] ) : ''; ?>" />
        <?php
    }

    /**
     * Callback for the API Key field.
     */
    public function api_key_callback() {
        $options = get_option( 'nf_asaas_options' );
        ?>
        <input type="text" id="api_key" name="nf_asaas_options[api_key]" value="<?php echo isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : ''; ?>" />
        <?php
    }
}
