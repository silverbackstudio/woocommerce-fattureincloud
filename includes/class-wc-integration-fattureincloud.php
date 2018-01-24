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
    
	public $api_key;
	public $api_uid;
	public $wallet;
	public $default_tax_class = 0;
	public $additional_tax_classes;
	public $generate_on_status = array();
	public $debug = false;       
    
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
		$this->init_settings();
		
		// Define user set variables.
		$this->api_uid          = $this->get_option( 'api_uid' );
		$this->api_key          = $this->get_option( 'api_key' );
		$this->wallet           = $this->get_option( 'wallet' );
		$this->default_tax_class    = $this->get_option( 'default_tax_class' );
		$this->additional_tax_classes    = $this->get_option( 'additional_tax_classes' );
		$this->generate_on_status    = (array)$this->get_option( 'generate_on_status' );
		$this->debug            = $this->get_option( 'debug' );
		
		$this->client = new FattureInCloud\Client( $this->api_uid, $this->api_key );		
		
		$this->init_form_fields();
		
		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
		
		foreach ( $this->generate_on_status as $generate_status ) {
	        add_action( 'woocommerce_order_status_' . $generate_status, array( $this, 'generate_invoice'), 99, 2 );
		}
	    
	    add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
        add_filter( 'woocommerce_billing_fields', array( $this, 'billing_fields' ) );
        add_filter( 'woocommerce_admin_billing_fields' ,  array( $this, 'admin_billing_fields')  );  
        
        add_filter( 'woocommerce_admin_order_actions', array( $this, 'admin_order_actions'), 10 ,2 );
        add_filter( 'woocommerce_my_account_my_orders_actions' , array( $this, 'user_orders_actions' ), 10, 2);
        
        add_action( 'wp_ajax_woocommerce_fattureincloud_gen_invoice' , array( $this, 'generate_invoice_action' ) );
        add_action( 'wp_ajax_woocommerce_fattureincloud_dl_invoice' , array( $this, 'download_invoice_action' ) );
        
        add_filter( 'option_woocommerce_tax_classes', array( $this, 'append_tax_classes' ) );
        
	}
	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
	    
	    $wallets = array( '' => __( '-- Auto Select --', 'woocommerce-fattureincloud' ) ) + wp_list_pluck($this->getInfo('lista_conti'), 'nome_conto', 'id');
	    
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
				'type'              => 'password',
				'description'       => __( 'Enter with your API Key. You can find this at fattureincloud.it in the "API" left main menu.', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'wallet' => array(
				'title'             => __( 'Wallet', 'woocommerce-fattureincloud' ),
				'type'              => 'select',
				'description'       => __( 'Enter your default Wallet', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => '',
				'options'           => $wallets,
			),	
			'default_tax_class' => array(
				'title'             => __( 'Default Tax Class', 'woocommerce-fattureincloud' ),
				'type'              => 'select',
				'description'       => __( 'Select the default tax class', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => $this->default_tax_class,
				'options'           => $this->tax_classes_names(),
			),			
			'additional_tax_classes' => array(
				'title'             => __( 'Additional Tax Classes', 'woocommerce-fattureincloud' ),
				'type'              => 'multiselect',
				'description'       => __( 'Add this tax classes to the Additional Tax Classes', 'woocommerce-fattureincloud' ),
				'desc_tip'          => true,
				'default'           => '',
				'options'           => $this->tax_classes_names(),
			),				
			'generate_on_status' => array(
				'title'             => __( 'Generate automatically on', 'woocommerce-fattureincloud' ),
				'type'              => 'multiselect',
				'default'           => '',
				'options'           => self::order_statuses(),
				'description'       => __( 'Generate invoices automatically when order is on this statuses', 'woocommerce-fattureincloud' ),
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
    				'data_saldo' => $payment_date,
    			)
    		);
    		
    		if( $this->wallet ) {
    		    $invoicePayment->metodo = $this->wallet;
    		}
    		
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
            				'cod_iva' => $this->default_tax_class,
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
        
        if( ! $order->has_status( wc_get_is_paid_statuses() ) ) {
            return $actions;
        }
        
		if ( $order->get_meta( 'fattureincloud_invoice_id' ) ) {
			$actions['fic-invoice-download'] = array(
				'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_fattureincloud_dl_invoice&order_id=' . $order->get_id() ), 'woocommerce-fattureincloud-invoice-dl' ),
				'name'      => __( 'Download Invoice', 'woocommerce-fattureincloud' ),
				'action'    => "invoice-download",
			);		    
		} else {
		    $actions['fic-invoice-generate'] = array(
				'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_fattureincloud_gen_invoice&order_id=' . $order->get_id() ), 'woocommerce-fattureincloud-invoice-gen' ),
				'name'      => __( 'Generate Invoice', 'woocommerce-fattureincloud' ),
				'action'    => "invoice-generate",
			);
		}        
		
		return $actions;
        
    }
    
	public function user_orders_actions($actions, $order) {
	    
    	if ( $order->has_status( wc_get_is_paid_statuses() ) && $order->get_meta( 'fattureincloud_invoice_id' ) ) {
			$actions['fattureincloud-invoice'] = array(
				'url'       => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_fattureincloud_dl_invoice&order_id=' . $order->get_id() ), 'woocommerce-fattureincloud-invoice-dl' ),
				'name'      => __( 'Download Invoice', 'woocommerce-fattureincloud' ),
				'action'    => "invoice",
			);	    
        }	
		
		return $actions;
	}    

	public function generate_invoice_action() { 
	    
	    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, 
	        array(
                'options' => array(
                    'min_range' => 0,
                )
            )
        );
	    
		if ( ( current_user_can( 'manage_woocommerce_orders' ) || current_user_can( 'edit_shop_orders' ) ) && check_admin_referer( 'woocommerce-fattureincloud-invoice-gen' ) ) {
			$order  = wc_get_order( $order_id );

			if ( ! $order ) {
			    exit;
			}	    
			
            $invoice_id = $order->get_meta( 'fattureincloud_invoice_id' );

        	if ( $invoice_id ) {
                wp_die( esc_html__( 'Invoice already generated', 'woocommerce-fattureincloud' ) );
        	}
        	
        	if( $this->generate_invoice( $order_id ) ) {
        	    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		        exit;
        	} else {
        	    wp_die( esc_html__( 'Error generating invoice, please try later or notify system administrator', 'woocommerce-fattureincloud' ) );
        	}
        	
		} else {
		    wp_die( __( 'You do not have sufficient permissions to do this.', 'woocommerce-fattureincloud' ) );
		}
	    
	}

	public function download_invoice_action() {
	    
	    $order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT, 
	        array(
                'options' => array(
                    'min_range' => 0,
                )
            )
        );
	    
		if ( $order_id && current_user_can( 'view_order', $order_id )  && check_admin_referer( 'woocommerce-fattureincloud-invoice-dl' ) ) {
			$order  = wc_get_order( $order_id );

			if ( ! $order ) {
			    exit;
			}
			
            $invoice_id = $order->get_meta( 'fattureincloud_invoice_id' );

        	if ( ! $invoice_id ) {
                wp_die( esc_html__( 'Invoice not yet available, please contact and administrative to get more info', 'woocommerce-fattureincloud' ) );
        	}
        
            $invoice_url = $this->get_invoice_url( $invoice_id );
        
            if( $invoice_url ) {
        		wp_redirect( $invoice_url );
        		die();
        	} else {
            	wp_die( esc_html__( 'Invoice service not available, please try later or contact business owner', 'woocommerce-fattureincloud' ) );             
        	}
                
		} else {
		    wp_die( __( 'You do not have sufficient permissions to view this invoice.', 'woocommerce-fattureincloud' ) );
		}

		exit;
	}  
    
	public function get_invoice_url( $invoice_id ) {
	    
	    $cache_key = 'woocommerce_fattureincloud_invoice_url_' . $invoice_id ;
    	$invoice_url = get_transient( $cache_key );
	    
	    if ( false === $invoice_url ) {
    	        
        	$dettagli_request = new FattureInCloud\Struct\DocDettagliRequest(
        		array(
        			'id' => $invoice_id,
        		)
        	);
        	
        	$result = $this->client->getDettagliDoc( FattureInCloud\Client::TYPE_FATTURA, $dettagli_request );
        
        	if ( $result && $result->success ) {
        		$invoice_url = $result->dettagli_documento->link_doc;
                set_transient( $cache_key, $invoice_url, DAY_IN_SECONDS );        		
        	} 
        	
	    }
    	
    	return $invoice_url;
    
	}    
	
    public function getInfo( $info, $cache = true, $cache_time = 2 * DAY_IN_SECONDS ){
    
    	$cache_key = 'woocommerce_fattureincloud_info_' . $info ;
    	$values = get_transient( $cache_key );
    
    	if ( (false === $values) || !$cache ) {
    		$response = $this->client->getInfoList( array( $info ) );
    
    		if ( ($response !== false) && $response->success ) {
    			$values = $response->$info;
    			set_transient( $cache_key, $values, $cache_time );
    		}
    	}
    
    	return $values;        
    }
    
    public function tax_classes_names(){
        
        $liste_iva = $this->getInfo('lista_iva'); 
	    
	    $tax_classes = array();
	    
	    foreach( $liste_iva as $aliquota_iva ) {
	        $tax_classes[$aliquota_iva['cod_iva']] = sprintf( 
	            $aliquota_iva['descrizione_iva'] ? '%s' : __('%2$s%% VAT', 'woocommerce-fattureincloud' ), 
	            $aliquota_iva['descrizione_iva'],
	            $aliquota_iva['valore_iva']
	       );
	    }
	    
	    return $tax_classes;
    }
    
    public static function order_statuses(){
        
        $native_statuses  = wc_get_order_statuses();
        $statuses = array();
        
        foreach ($native_statuses as $status => $name ) {
               $statuses[ substr( $status, 3 ) ] = $name;
        }
        
        $statuses = array_intersect_key($statuses, array_flip(wc_get_is_paid_statuses()) );
        
        return $statuses;
    }
    
    public function append_tax_classes( $tax_classes ) {
        
        if( $this->additional_tax_classes ) {
            $new_tax_classes = array_intersect_key($this->tax_classes_names(), array_flip( $this->additional_tax_classes ) );
            $tax_classes .= "\n" . join( "\n", $new_tax_classes);
        }
        
        return $tax_classes;
    }
    
}
endif;