<?php

namespace Svbk\WP\Plugins\WooCommerce\FattureInCloud;

use WC_Integration;
use Svbk\WP\Helpers\Lists\Utils;
use Svbk\FattureInCloud;
use Svbk\FattureInCloud\Struct\DocNuovoArticolo as Articolo;
use Svbk\FattureInCloud\Struct\DocNuovoRequest as Fattura;
use Svbk\FattureInCloud\Struct\DocNuovoPagamento as Pagamento;

/**
 * FattureInCloud Integration.
 *
 * @package  WC_Integration_FattureInCloud
 * @category Integration
 * @author   Brando Meniconi
 */
if ( ! class_exists( __NAMESPACE__ . '\\WC_Integration_FattureInCloud' ) ) :
    
class WC_Integration_FattureInCloud extends WC_Integration {
    
	protected $api_key;
	protected $api_uid;
	protected $wallet;
	protected $debug = false;       
    
    protected $client;
    
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		
		$this->id                 = 'fattureincloud';
		$this->method_title       = __( 'Fatture in Cloud', 'woocommerce-fattureincloud' );
		$this->method_description = __( 'Manage WooCommerce order invoices with Fattureincloud', 'woocommerce-fattureincloud' );
		
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		
		// Define user set variables.
		$this->api_uid          = $this->get_option( 'api_uid' );
		$this->api_key          = $this->get_option( 'api_key' );
		$this->wallet          = $this->get_option( 'wallet' );
		$this->debug            = $this->get_option( 'debug' );
		
		$this->client = new FattureInCloud\Client( $this->api_uid, $this->api_key );
		
		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		
	    add_action( 'woocommerce_order_status_completed', array( $this, 'generate_invoice'), 99, 2 );
	    
	    add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
        add_filter( 'woocommerce_billing_fields', array( $this, 'billing_fields' ) );
        add_filter( 'woocommerce_admin_billing_fields' ,  array( $this, 'admin_billing_fields')  );  
        
        add_filter( 'woocommerce_admin_order_actions', array( $this, 'admin_order_actions'), 10 ,2 );
        add_action( 'wp_ajax_woocommerce_fattureincloud_invoice' , array( $this, 'download_invoice' ) );
        
	}
	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'api_uid' => array(
				'title'             => __( 'API UID', 'woocommerce-fattureincloud' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your API UID. You can find this at fattureincloud.it in the "API" left main menu.', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => ''
			),		    
			'api_key' => array(
				'title'             => __( 'API Key', 'woocommerce-fattureincloud' ),
				'type'              => 'text',
				'description'       => __( 'Enter with your API Key. You can find this at fattureincloud.it in the "API" left main menu.', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'wallet' => array(
				'title'             => __( 'Wallet', 'woocommerce-fattureincloud' ),
				'type'              => 'text',
				'description'       => __( 'Enter your default Wallet', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => ''
			),			
			'debug' => array(
				'title'             => __( 'Debug Log', 'woocommerce-fattureincloud' ),
				'type'              => 'checkbox',
				'label'             => __( 'Enable logging', 'woocommerce-fattureincloud' ),
				'default'           => 'no',
				'description'       => __( 'Log events such as API requests', 'woocommerce-fattureincloud' ),
			),
		);
	}
	
	/**
	 * Santize our settings
	 * @see process_admin_options()
	 */
	public function sanitize_settings( $settings ) {
		// We're just going to make the api key all upper case characters since that's how our imaginary API works
		if ( isset( $settings ) &&
		     isset( $settings['api_key'] ) ) {
			$settings['api_key'] = strtoupper( $settings['api_key'] );
		}
		return $settings;
	}
	/**
	 * Validate the API key
	 * @see validate_settings_fields()
	 */
	public function validate_api_key_field( $key ) {
		// get the posted value
		$value = $_POST[ $this->plugin_id . $this->id . '_' . $key ];
		// check if the API key is longer than 20 characters. Our imaginary API doesn't create keys that large so something must be wrong. Throw an error which will prevent the user from saving.
		if ( isset( $value ) &&
			 20 < strlen( $value ) ) {
			$this->errors[] = $key;
		}
		return $value;
	}
	/**
	 * Display errors by overriding the display_errors() method
	 * @see display_errors()
	 */
	public function display_errors( ) {
		// loop through each error and display it
		foreach ( $this->errors as $key => $value ) {
			?>
			<div class="error">
				<p><?php _e( 'Looks like you made a mistake with the ' . $value . ' field. Make sure it isn&apos;t longer than 20 characters', 'woocommerce-integration-demo' ); ?></p>
			</div>
			<?php
		}
	}
	
	
    public function generate_invoice( $order_id, $order = null ) {
    
        $order = wc_get_order( $order ?: $order_id );
        
        if( false === $order ) {
            return;
        }
    
    	$invoice_id = $order->get_meta( 'fattureincloud_invoice_id' );

    	if ( ! $invoice_id ) {
    
    		$payment_date =  FattureInCloud\Date::createFromMutable( $order->get_date_paid() );

    		$invoicePayment = new Pagamento(
    			array(
    				'data_scadenza' => $payment_date,
    				'importo' => 'auto',
    				//'metodo' => $this->wallet,
    				'data_saldo' => $payment_date,
    			)
    		);
    		
    		$order_items = array();
    		
    		foreach( $order->get_items() as $item ) {
    		    if( $item->is_type('line_item') ) {
    		        $product = $item->get_product();
    		        
        		    $order_items[] = new Articolo(
            			array(
            			    //'id' => $item->get_product_id(),
            			    'codice' => $product->get_sku(),
            			    'quantita' => $item->get_quantity(),
            				'nome' => $item->get_name(),
            				'prezzo_netto' => $item->get_subtotal(),
            				'prezzo_lordo' => $item->get_subtotal() + $item->get_subtotal_tax(),
            				'cod_iva' => 0,
            			)
        		    );
    		    }
    		}
        	
            $invoice_data = array(
    			'nome' => $order->get_billing_company() ?: ($order->get_formatted_billing_full_name()),
    			'indirizzo_via' => $order->get_billing_address_1(),
    			'indirizzo_extra' => $order->get_billing_address_2(),
    			'indirizzo_cap' => $order->get_billing_postcode(),
    			'indirizzo_citta' => $order->get_billing_city(),
    			'indirizzo_provincia' => $order->get_billing_state(),
    			'piva' => $order->get_meta('_billing_company_tax_code'),
    			'cf' =>  $order->get_meta('_billing_fiscal_code'),
    			'valuta' => $order->get_currency(),
    			'paese_iso' => $order->get_billing_country(),
    			'lista_articoli' => $order_items,
    			'lista_pagamenti' => array( $invoicePayment ),
    			'prezzi_ivati' => wc_prices_include_tax(),
    		);
    
    		$newInvoice = new Fattura( $invoice_data );
    
    		$result = $this->client->createDoc( FattureInCloud\Client::TYPE_FATTURA, $newInvoice );
    
    		if ( $result && $result->success ) {
    			$id_fattura = $result->new_id;
    			$order->add_meta_data( 'fattureincloud_invoice_id', $id_fattura, true );
    			$order->save_meta_data();
    		} else {
    		    var_dump($result);
    		    return false;
    		}
    
    	}
    
    	return $id_fattura;
    }

    public function billing_fields( $fields ){
    
         $new_fields = array( 
         	'billing_company_tax_code' => array(
            	'label'     	=> __( 'Company Tax Code', 'woocommerce-fattureincloud' ),
    	    	'placeholder'   => _x('IT012345678910', 'vat id placeholder', 'woocommerce-fattureincloud'),
    	    	'required'  	=> false,
    	    	'class'     	=> array( 'form-row-first' ),
    		    'clear'     	=> true
    		),
         	'billing_fiscal_code' => array(
            	'label'     	=> __( 'Fiscal Code', 'woocommerce-fattureincloud' ),
    	    	'placeholder'   => _x('ABCZXY00A00A000N', 'fiscal code placeholder', 'woocommerce-fattureincloud'),
    	    	'required'  	=> false,
    	    	'class'     	=> array( 'form-row-last' ),
    		    'clear'     	=> true
    		)
         );
    	
    	$fields = Utils::keyInsert($fields, $new_fields, 'billing_company' );
    	
    	return $fields;	
    }
    
    public function admin_billing_fields( $fields ){
    
         $new_fields = array( 
    			'company_tax_code' => array(
            		'label'        => __( 'Company Tax Code', 'woocommerce-fattureincloud' ),
    				'show'  => true,
    			),
    			'fiscal_code' => array(
            		'label'        => __( 'Fiscal Code', 'woocommerce-fattureincloud' ),
    				'show'  => true,
    			),
         );
    	
    	$fields = Utils::keyInsert($fields, $new_fields, 'company' );
    	
    	return $fields;	
    }
    
    public function admin_order_actions( $actions, $order ){
        
		if ( $order->has_status( wc_get_is_paid_statuses() ) ) {
			$actions['fattureincloud-invoice'] = array(
				'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_fattureincloud_invoice&order_id=' . $order->get_id() ), 'woocommerce-fattureincloud-invoice' ),
				'name'      => __( 'Download Invoice', 'woocommerce' ),
				'action'    => "download",
			);
		}        
		
		return $actions;
        
    }
    
	public function download_invoice() {
	    
		if ( current_user_can( 'edit_shop_orders' ) && check_admin_referer( 'woocommerce-fattureincloud-invoice' ) ) {
			$order  = wc_get_order( absint( $_GET['order_id'] ) );

			if ( ! $order ) {
			    exit;
			}

            $invoice_id = $order->get_meta( 'fattureincloud_invoice_id' );

        	if ( ! $invoice_id ) {
        	    
        	    $invoice_id = $this->generate_invoice( $order->get_id() );
                
                if( ! $invoice_id ) {   
        		    wp_die( esc_html__( 'Invoice not yet available, please contact and administrative to get more info', 'woocommerce-fattureincloud' ) );
                }
        	}
        
        	$dettagliRequest = new FattureInCloud\Struct\DocDettagliRequest(
        		array(
        			'id' => $invoice_id,
        		)
        	);
        	
        	$result = $this->client->getDettagliDoc( FattureInCloud\Client::TYPE_FATTURA, $dettagliRequest );
        
        	if ( $result && $result->success ) {
        		$invoice_url = $result->dettagli_documento->link_doc;
        		wp_redirect( $invoice_url );
        		die();
        	}
        
        	wp_die( esc_html__( 'Invoice service not available, please try later or contact business owner', 'woocommerce-fattureincloud' ) );             
                
		}

		exit;
	}    
	
    public function countries( $countries ) {
    
    	$cache_key = 'woocommerce_fattureincloud_countries';
    	$countries = get_transient( $cache_key );
    
    	if ( false === $countries ) {
    		$response = $this->client->getInfoList( array( 'lista_paesi' ) );
    
    		if ( ($response !== false) && $response->success ) {
    			$countries = $response->lista_paesi;
    			set_transient( $cache_key, $countries, 2 * DAY_IN_SECONDS );
    		}
    	}
    
    	return $countries;
    }
    
}
endif;