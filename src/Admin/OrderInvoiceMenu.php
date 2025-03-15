<?php

namespace NotaFiscalForAsaas\Admin;

use NotaFiscalForAsaas\Controllers\InvoiceController;

class OrderInvoiceMenu {
    protected $controller;

    public function __construct() {
        $options = get_option( 'nf_asaas_options' );
        $enable_logging = isset( $options['enable_logging'] ) && $options['enable_logging'] === '1';
        $api_key = isset( $options['api_key'] ) ? $options['api_key'] : '';

        $this->controller = new InvoiceController( $api_key, $enable_logging );
    }

    /**
     * Initialize hooks for WooCommerce order actions
     */
    public function init() {
        add_action( 'add_meta_boxes', array( $this, 'add_nota_fiscal_meta_box' ) );
        add_action( 'wp_ajax_emit_invoice_ajax', array( $this, 'emit_invoice_ajax_handler' ) );
    }
    
    /**
     * Add a meta box to display invoice information
     */
    public function add_nota_fiscal_meta_box() {
        $screen = 'shop_order';

        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1', '>=' ) ) {
			$hpos_enabled = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
			$screen       = $hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		}

        add_meta_box(
            'woocommerce-order-nota-fiscal-asaas',
            'Nota Fiscal Asaas',
            array( $this, 'display_nota_fiscal_meta_box' ),
            $screen,
            'side',
            'default'
        );
    }
    
    /**
     * Displays the contents of the Invoice meta box
     * 
     * @param \WP_Post $post The post object.
     */
    public function display_nota_fiscal_meta_box( $post ) {
        $order_id = $post->ID;
        $order = wc_get_order( $order_id );
        
        if ( ! $order ) {
            echo 'Pedido não encontrado.';
            return;
        }
        
        $invoice_id = get_post_meta( $order_id, '_NotaFiscalForAsaas_invoice_id', true );
        $pdf_url = get_post_meta( $order_id, '_NotaFiscalForAsaas_pdf_url', true );
        $xml_url = get_post_meta( $order_id, '_NotaFiscalForAsaas_xml_url', true );
        $status = get_post_meta( $order_id, '_NotaFiscalForAsaas_status', true );
        
        // If we have an invoice ID but no URLs, try to retrieve from the API
        if ( !empty( $invoice_id ) && ( empty( $pdf_url ) || empty( $xml_url ) ) ) {
            $invoice_data = $this->controller->get_invoice_by_id( $invoice_id );
            
            if ( !is_wp_error( $invoice_data ) && is_array( $invoice_data ) ) {
                if ( isset( $invoice_data['status'] ) ) {
                    update_post_meta( $order_id, '_NotaFiscalForAsaas_status', sanitize_text_field( $invoice_data['status'] ) );
                    $status = $invoice_data['status'];
                }
                
                if ( isset( $invoice_data['pdfUrl'] ) ) {
                    update_post_meta( $order_id, '_NotaFiscalForAsaas_pdf_url', esc_url_raw( $invoice_data['pdfUrl'] ) );
                    $pdf_url = $invoice_data['pdfUrl'];
                }
                
                if ( isset( $invoice_data['xmlUrl'] ) ) {
                    update_post_meta( $order_id, '_NotaFiscalForAsaas_xml_url', esc_url_raw( $invoice_data['xmlUrl'] ) );
                    $xml_url = $invoice_data['xmlUrl'];
                }
            }
        }
        
        wp_nonce_field( 'nota_fiscal_asaas_nonce', 'nota_fiscal_asaas_nonce' );
        
        // If we have an invoice ID and it's in processing
        if ( !empty( $invoice_id ) && ( $status === 'SCHEDULED' ) ) {
            ?>
            <div class="nota-fiscal-processing">
                <p><?php echo 'Nota Fiscal #' . esc_html( $invoice_id ) . ' em processamento.'; ?></p>
                <p>A nota fiscal está sendo processada pelo Asaas. Por favor, recarregue a página mais tarde.</p>
            </div>
            <?php
        } 
        // If we have both URLs, show the download buttons
        else if ( !empty( $pdf_url ) && !empty( $xml_url ) ) {
            ?>
            <div class="nota-fiscal-download">
                <p><?php echo 'Nota Fiscal #' . esc_html( $invoice_id ) . ' emitida.'; ?></p>
                <div class="nota-fiscal-buttons">
                    <a href="<?php echo esc_url( $pdf_url ); ?>" class="button" target="_blank">Baixar PDF</a>
                    <a href="<?php echo esc_url( $xml_url ); ?>" class="button" target="_blank">Baixar XML</a>
                </div>
            </div>
            <?php
        } else {
            ?>
            <div class="nota-fiscal-emit">
                <button type="button" id="emit-invoice-button" class="button" data-order-id="<?php echo esc_attr( $order_id ); ?>">
                    Emitir Nota Fiscal
                </button>
                <span class="spinner" style="float: none; visibility: hidden;"></span>
                <div id="nota-fiscal-message"></div>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#emit-invoice-button').on('click', function() {
                    var button = $(this);
                    var spinner = button.next('.spinner');
                    var messageDiv = $('#nota-fiscal-message');
                    
                    button.prop('disabled', true);
                    spinner.css('visibility', 'visible');
                    messageDiv.html('');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'emit_invoice_ajax',
                            order_id: button.data('order-id'),
                            nonce: $('#nota_fiscal_asaas_nonce').val()
                        },
                        success: function(response) {
                            spinner.css('visibility', 'hidden');
                            
                            if (response.success) {
                                if (response.data.status === 'SCHEDULED' || response.data.status === 'PROCESSING') {
                                    // Note in processing
                                    var html = '<p>Nota Fiscal #' + response.data.invoice_id + ' em processamento.</p>' +
                                        '<p>A nota fiscal está sendo processada pelo Asaas. Por favor, recarregue a página mais tarde.</p>';
                                    $('.nota-fiscal-emit').html(html);
                                } else if (response.data.pdf_url && response.data.xml_url) {
                                    // Note issued successfully and URLs available
                                    var html = '<p>Nota Fiscal #' + response.data.invoice_id + ' emitida.</p>' +
                                        '<div class="nota-fiscal-buttons">' +
                                        '<a href="' + response.data.pdf_url + '" class="button" target="_blank">Baixar PDF</a> ' +
                                        '<a href="' + response.data.xml_url + '" class="button" target="_blank">Baixar XML</a>' +
                                        '</div>';
                                    $('.nota-fiscal-emit').html(html);
                                } else {
                                    // Note issued but no URLs yet
                                    var html = '<p>Nota Fiscal #' + response.data.invoice_id + ' emitida.</p>' +
                                        '<p>Os links para download estarão disponíveis em breve. Por favor, recarregue a página mais tarde.</p>';
                                    $('.nota-fiscal-emit').html(html);
                                }
                            } else {
                                messageDiv.html('<div class="error"><p>' + response.data.message + '</p></div>');
                                button.prop('disabled', false);
                            }
                        },
                        error: function() {
                            spinner.css('visibility', 'hidden');
                            messageDiv.html('<div class="error"><p>Erro ao comunicar com o servidor.</p></div>');
                            button.prop('disabled', false);
                        }
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Process the AJAX request to issue an invoice
     */
    public function emit_invoice_ajax_handler() {
        check_ajax_referer( 'nota_fiscal_asaas_nonce', 'nonce' );
        
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_send_json_error( array( 'message' => 'Permissão negada.' ) );
            return;
        }
        
        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        
        if ( ! $order_id ) {
            wp_send_json_error( array( 'message' => 'ID do pedido inválido.' ) );
            return;
        }
        
        $result = $this->controller->emit_invoice_for_order( $order_id );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
            return;
        }
        
        if ( isset( $result['id'] ) ) {
            update_post_meta( $order_id, '_NotaFiscalForAsaas_invoice_id', sanitize_text_field( $result['id'] ) );
            
            if ( isset( $result['status'] ) ) {
                update_post_meta( $order_id, '_NotaFiscalForAsaas_status', sanitize_text_field( $result['status'] ) );
            }
            
            if ( isset( $result['pdfUrl'] ) ) {
                update_post_meta( $order_id, '_NotaFiscalForAsaas_pdf_url', esc_url_raw( $result['pdfUrl'] ) );
            }
            
            if ( isset( $result['xmlUrl'] ) ) {
                update_post_meta( $order_id, '_NotaFiscalForAsaas_xml_url', esc_url_raw( $result['xmlUrl'] ) );
            }
            
            wp_send_json_success( array(
                'invoice_id' => sanitize_text_field( $result['id'] ),
                'status' => isset( $result['status'] ) ? sanitize_text_field( $result['status'] ) : '',
                'pdf_url' => isset( $result['pdfUrl'] ) ? esc_url_raw( $result['pdfUrl'] ) : '',
                'xml_url' => isset( $result['xmlUrl'] ) ? esc_url_raw( $result['xmlUrl'] ) : ''
            ) );
            return;
        }
        
        wp_send_json_error( array( 'message' => 'Falha ao emitir nota fiscal.' ) );
    }
}