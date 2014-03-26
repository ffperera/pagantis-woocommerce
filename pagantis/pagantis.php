<?php
/*
Plugin Name: WooCommerce Pagantis Payment Gateway
Plugin URI: http://www.pagantis.com
Description: Pagantis Payment gateway for woocommerce
Version: 1.0
Author: Epsilon Eridani CB (contact@epsilon-eridani.com)
Author URI: http://www.epsilon-eridani.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/ 


add_action('plugins_loaded', 'init_woocommerce_pagantis', 0);



function init_woocommerce_pagantis(){
  if(!class_exists('WC_Payment_Gateway')) return;
  
  // localization
  load_plugin_textdomain('pagantis.com', false, basename( dirname( __FILE__ ) ) . '/languages' );
 
  class WC_Pagantis extends WC_Payment_Gateway{
      
      
    public function __construct(){
        
      $this->id = 'pagantis';
      $this->medthod_title = 'Pagantis';

      $this->has_fields = false;
 
      $this->init_form_fields();
      $this->init_settings();
 
      
      // 2014-03-21 - textos se pasan al fichero de idiomas
      // $this->title = $this->get_option('title');
      // $this->description = $this->get_option('description');
      
      $this->title = __('Pay by Credit or Debit card.', 'pagantis.com');
      $this->description = __('Pay by Credit or Debit card through secure gateway.', 'pagantis.com');
      
      
      $this->notify_url = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Pagantis', home_url( '/' ) ) );
      
      // Pagantis account ID and secret
      $this->pagantis_account_id = $this->get_option('pagantis_account_id');
      $this->pagantis_secret = $this->get_option ('pagantis_secret');
      
      // custom
      $this->paymentsubject = $this->get_option('paymentsubject');
      $this->pagantis_redirectok = $this->get_option('pagantis_redirectok');
      $this->pagantis_redirectnok = $this->get_option('pagantis_redirectnok');
      
      
      // Pagantis gateway URL
      $this->pagantis_server = 'https://psp.pagantis.com/2/sale';
      
      
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_'. $this->id, array( &$this, 'receipt_page' ) );
      
      
      // Payment listener/API hook
      add_action( 'woocommerce_api_wc_pagantis', array( $this, 'check_pagantis_response' ) );


      
      
      if ( ! $this->is_valid_for_use() ) {
        $this->enabled = false;
      }      
        
        
    }
    
    
	/**
	 * Check if currency is accepted
	 *
	 * @access public
	 * @return bool
	 */
	function is_valid_for_use() {
		
        
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_paypal_supported_currencies', array( 'USD', 'EUR', 'GBP' ) ) ) ) {
			return false;
		}
        

		return true;
	}    
    
    
	/**
	 * Admin options (Pagantis module config page)
	 *
	 */    
    public function admin_options(){
        echo '<h3>'.__('Pagantis', 'pagantis.com').'</h3>';
        echo '<p>'.__('Notification URL. Use this URL in the Notification field of your Pagantis account', 'pagantis.com').'</p>';
        echo '<p><b>';
        echo $this->notify_url;
        echo '</b></p>';
        echo "\n";
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';

    }    
    
    
	/**
	 * Admin options (fields)
	 *
	 */      
    function init_form_fields(){
 
       $this->form_fields = array (
       
                'enabled' => array(
                    'title' => __('Enable/Disable', 'pagantis.com'),
                    'type' => 'checkbox',
                    'label' => __('Enable Pagantis Payment Module.', 'pagantis.com'),
                    'default' => 'no'),
                
                /*    
                'title' => array(
                    'title' => __('Title in checkout:', 'pagantis.com'),
                    'type'=> 'text',
                    'description' => __('Title shown in the checkout process.', 'pagantis.com'),
                    'default' => __('Pagantis', 'pagantis.com')),
                'description' => array(
                    'title' => __('Description:', 'pagantis.com'),
                    'type' => 'textarea',
                    'description' => __('Description shown to user in the checkout process.', 'pagantis.com'),
                    'default' => __('Pay by Credit or Debit card.', 'pagantis.com')),
                */ 
                
                'paymentsubject' => array(
                    'title' => __('Note for credit card operation:', 'pagantis.com'),
                    'type' => 'textarea',
                    'description' => __('Payment note for credit card owners.', 'pagantis.com'),
                    'default' => __('Your payment for order: ', 'pagantis.com')),                    
                'pagantis_account_id' => array(
                    'title' => __('Pagantis account ID', 'pagantis.com'),
                    'type' => 'text',
                    'description' => __('Identificator of your pagantis account', 'pagantis.com')
                ),
                'pagantis_secret' => array(
                    'title' => __('Pagantis secret key', 'pagantis.com'),
                    'type' => 'text',
                    'description' =>  __('Secret key of your pagantis account', 'pagantis.com'),
                ),
                'pagantis_redirectok' => array(
                    'title' => __('Return page on success', 'pagantis.com'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => __("URL (WordPress page) to return on payment success", 'pagantis.com')
                ),
                'pagantis_redirectnok' => array(
                    'title' => __('Return page on failure', 'pagantis.com'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => __("URL (WordPress page) to return on payment failure", 'pagantis.com')
                )
                      
            );
    }  
        
        
     
     /*
    function payment_fields(){
        if($this->description) echo wpautop(wptexturize($this->description));
    }
    */
    
    /**
     * Receipt Page
     **/
    public function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with Pagantis.', 'pagantis.com').'</p>';
      
        echo $this->generate_checkout_form($order);
    }

    /**
     * Generate checkout form to redirect to Pagantis gateway
     * 
     **/
	public function generate_checkout_form( $order_id ) {
        global $woocommerce;

		$order = new WC_Order( $order_id );
        


        $pagantis_ok_url_final = get_permalink($this->pagantis_redirectok);
        $pagantis_nok_url_final = get_permalink($this->pagantis_redirectnok);
    


        $amount = (int)($order->order_total * 100);
        $current_order_id = $order_id;

        $pagantis_account_id = trim( $this->pagantis_account_id );
        
        $currency = $order->get_order_currency();
        $pagantis_secret = trim( $this->pagantis_secret );
        
        
        $message = $pagantis_secret.$pagantis_account_id.$current_order_id.$amount.$currency.'SHA1'.$pagantis_ok_url_final.$pagantis_nok_url_final;
        
        $signature = sha1($message);

        $arrayHiddenFields = array (
        
            'order_id' => $current_order_id,
            'auth_method' => 'SHA1',
            'amount' => $amount,
            'currency' => $currency,
            'description' => $this->paymentsubject.' '.$current_order_id,   
            'ok_url' => $pagantis_ok_url_final,
            'nok_url' => $pagantis_nok_url_final,
            'account_id' => $pagantis_account_id,
            'signature' => $signature
        );


		$hidden_fields = '';

		foreach ( $arrayHiddenFields as $key => $value ) {
			$hidden_fields .= '<input type="hidden" name="'.esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
            //$hidden_fields .= "\n";
		}
                

    
		wc_enqueue_js( '
			$.blockUI({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Pagantis to make payment.', 'pagantis.com' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
						padding:        "20px",
						zindex:         "9999999",
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:		"24px",
					}
				});
			jQuery("#submit_pagantis_payment_form").click();
		' );
        
        

        
		return '<form action="' . esc_url( $this->pagantis_server ) . '" method="post" id="pagantis_payment_form" target="_top">
				' . $hidden_fields  . '
				<!-- Button Fallback -->
				<div class="payment_buttons">
					<input type="submit" class="button alt" id="submit_pagantis_payment_form" value="' . __( 'Pay', 'pagantis.com' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'pagantis.com' ) . '</a>
				</div>
                
                
			</form>';

	}    
    
    
    /**
     * This is triggered by Pagantis notification system
     * 
     **/    
    function check_pagantis_response(){
        global $woocommerce;
        


        $json = file_get_contents('php://input');
        $notification = json_decode($json, true);   
        
        
        if(isset($notification['event']) && $notification['event'] == 'sale.created')  {
            
            // customer is in the pagantis gateway page, but the payment is not complete
            // se ha abierto la pagina de pago, pero todavia no se ha realizado el cobro
            wp_die( "OK", "Pagantis", array( 'response' => 200 ) );
        }
        
        
        if(isset($notification['event']) && $notification['event'] == 'charge.created')  {           
            

            $order_id = 0;
            
            if(isset($notification['data']['order_id']))
            {
                $order_id = (int)$notification['data']['order_id'];
            }
            
            
            if($order_id > 0 ){
                
                try{
                    
                    $order = new WC_Order( $order_id );
                    
                    $amount = trim($notification['data']['amount']);
                    $currency = trim($notification['data']['currency']);
                    
                    if( trim($order->get_order_currency()) != $currency)
                    {
                        // fraud attempt?
                        $order->add_order_note('Pagantis CURRENCY mismatch');
                        $order->update_status('on-hold');
                        wp_die( "Pagantis OK", "Pagantis", array( 'response' => 200 ) );        
                    }
                    
                    $order_amount = (int) ($order->get_total() * 100);
                    
                    $amount_diff = abs($amount - $order_amount);
                    
                    if($amount_diff > 1)
                    {
                        // fraud attempt?
                        $order->add_order_note('Pagantis TOTAL mismatch');
                        $order->update_status('on-hold');
                        wp_die( "Pagantis OK", "Pagantis", array( 'response' => 200 ) );        
                       
                    }
                    
                    
                    
                    // only if the order is payment pending (or on-hold)
                    if($order->status =='on-hold' || $order->status == 'pending'){
                        

                        $order->payment_complete();
                        $order->add_order_note('Pagantis ID: '.$notification['data']['id']. ' | CODE: '.$notification['data']['authorization_code']. ' | T: '. $notification['data']['created_at']);
                        
                        $woocommerce->cart->empty_cart();

                        wp_die( "Pagantis OK", "Pagantis", array( 'response' => 200 ) );                        

                    }
                    
                }catch(Exception $e){
                    // $errorOccurred = true;
                    wp_die( "Pagantis Notification ko", "Pagantis", array( 'response' => 200 ) );
                }

            }

            

		} else {
            
            
            // this is a Failed payment
            
            $order_id = 0;
            
            if(isset($notification['data']['order_id']))
            {
                $order_id = (int)$notification['data']['order_id'];
            }
            
            
            if($order_id > 0 ){
                            
                try{
                    
                    $order = new WC_Order( $order_id );
                    
                    // only if the order is payment pending
                    if($order->status =='on-hold' || $order->status == 'pending' || $order->status == 'failed'){
                        
                        // the payment is not valid
                        $order->update_status('failed');
                        
                        $order->add_order_note('Pagantis ERROR');
                        
                        // keep the cart 
                        // $woocommerce->cart->empty_cart();
                        wp_die( "Pagantis Notification ko", "Pagantis", array( 'response' => 200 ) );
                    }
                    
                }catch(Exception $e){
                    
                    // a problem with the order?
                    wp_die( "Pagantis Notification ko", "Pagantis", array( 'response' => 200 ) );
                }  
            }          

			wp_die( "Pagantis Notification ko", "Pagantis", array( 'response' => 200 ) );
		}        
        
        
        wp_die( "Pagantis Notification ko", "Pagantis", array( 'response' => 200 ) );
    
    }   
    
    
    
    
    /**
     * Process the payment and return the result
     * 
     * We keep the order status on pending, because it is needed for woocommerce to redirect user to Pagantis
     * 
     **/
    function process_payment($order_id){
        global $woocommerce;
    	$order = new WC_Order( $order_id );
        
        // order is 'pending' by default
        // we add a note to the order
        $order->add_order_note('Waiting Payment confirmation!');
        
        
        return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
        );
    }    
    
    
    /**
     * Get all wordpress pages and return them in an array 
     * 
     * This is used by Pagantis woocommerce admin page to select returning URLs
     * 
     **/    
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
    

        
          
    
  }
 
   /**
     * Add the Gateway to WooCommerce
     **/
    function add_woocommerce_pagantis_gateway($methods) {
        $methods[] = 'WC_Pagantis';
        return $methods;
    }
 
    add_filter('woocommerce_payment_gateways', 'add_woocommerce_pagantis_gateway' );
}
