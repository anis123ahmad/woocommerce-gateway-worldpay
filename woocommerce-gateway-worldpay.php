<?php
/**
 * Plugin Name: Worldpay WooCommerce Payment Gateway using HPP Method
 * Plugin URI: http://impex4u.com
 * Description: Worldpay eCommerce helps enhance your online checkout experience and payments processing, so your customers can easily and safely pay how they want, which may result in fewer abondoned carts, less fraud and more sales.
 * Author: Anis
 * Author URI: https://github.com/anis123ahmad
 * Version: 1.0.2
 * Text Domain: woocommerce-gateway-worldpay
 */
 
defined( 'ABSPATH' ) or exit;
//ob_start();

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the WorldPay Gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + WorldPay gateway
 */

function wc_worldpay_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_WorldPay';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_worldpay_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_worldpay_plugin_links( $links ) {

	$plugin_links = array(
'<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=worldpay').'">'.__('Configure','woocommerce-gateway-worldpay').'</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_worldpay_plugin_links' );


add_action( 'plugins_loaded', 'wc_worldpay_init', 0 );
function wc_worldpay_init() {

	
	class WC_Gateway_WorldPay extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		
		public function __construct() {
	  
			$plugin_dir 			  = plugin_dir_url(__FILE__);
			$this->id                 = 'worldpay'; //key is very important
			$this->icon               = apply_filters('woocommerce_worldpay_icon', $plugin_dir.'img/visa-mastercard-american-express-logo.gif' );
			
			$this->has_fields         = false;
			
			$this->method_title       = __( 'WorldPay Credit/Debit Card Payment Gateway', 'woocommerce-gateway-worldpay' );
			
			
			$this->method_description = __( 'Allows online payments using Credit/Debit Card. Very handy if you use your Payment By WorldPay gateway for another payment method, and can help with testing. Orders are marked as "completed" when received.', 'woocommerce-gateway-worldpay' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			$this->title = $this->get_option( 'title' );
			$this->testmode = $this->settings['testmode'];
			
			$this->test_username = $this->settings['test_username'];
			$this->test_password = $this->settings['test_password'];
			
			$this->live_username = $this->settings['live_username'];
			$this->live_password = $this->settings['live_password'];
			
			$this->merchant_entity = $this->settings['merchant_entity'];
            $this->merchant_narrative = $this->settings['merchant_narrative'];
            
			$this->description      = $this->get_option( 'description' );
			$this->instructions		= "";
        
		  	// Actions
			//	/wc-api/worldpay/
			add_action( 'woocommerce_api_worldpay', array( $this, 'worldpay' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action('woocommerce_receipt_worldpay', array( &$this, 'receipt_page' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			
			//woocommerce_api_{id}_success
			add_action( 'woocommerce_api_worldpay_success', array( $this, 'worldpay_success' ) );
			add_action( 'woocommerce_api_worldpay_pending', array( $this, 'worldpay_pending' ) );
			add_action( 'woocommerce_api_worldpay_failure', array( $this, 'worldpay_failure' ) );
			add_action( 'woocommerce_api_worldpay_error', array( $this, 'worldpay_error' ) );
			add_action( 'woocommerce_api_worldpay_cancel', array( $this, 'worldpay_cancel' ) );
			
									
		} //end of function
	
		
		//Send Payment Request to WorldPay
		public function send_worldpay($order) 
		{
			    global $woocommerce;
				
								
				if( count($order->get_items()) == 1 )
				{
				  
				  foreach ( $order->get_items() as $item ) 
				  {         
                   	$goodsInfo = $item['name'];
				  }
				
				}
				else
				{
					$goodsInfo = count($order->get_items())." Items - ".get_bloginfo( 'name' ); 
				}
				
				$oid = $order->get_id();
				
				if( $this->testmode == 'yes' ) {
				
					$app_code = $this->test_username;
					$api_key = $this->test_password;
					
				} else {
					
					$app_code = $this->live_username;
					$api_key = $this->live_password;
					
				}
								
				$base64 = base64_encode("$app_code:$api_key"); 
               			    
				//The payment amount. This is a whole number with an exponent e.g. if exponent is two, 250 is 2.50 
				$amount = $order->get_total() * 100;
				
				$entity = $this->merchant_entity;
				
				$billingAddressName = $order->get_billing_first_name() . ' ' .$order->get_billing_last_name(); 
				
				$success = home_url( '/' ) . 'wc-api/worldpay_success/?oid=' . $oid;
				$pending = home_url( '/' ) . 'wc-api/worldpay_pending/?oid=' . $oid;
				$fail = home_url( '/' ) . 'wc-api/worldpay_failure/?oid=' . $oid;
				$error = home_url( '/' ) . 'wc-api/worldpay_error/?oid=' . $oid;
				$cancel = home_url( '/' ) . 'wc-api/worldpay_cancel/?oid=' . $oid;
				
				
				$postArgs = array(
        
					'transactionReference' => $oid,
					'merchant' => array(
						'entity' => $entity
					),
					//narrative 	An object that helps your customers better identify you on their statement.
					'narrative' => array(
						'line1' => $this->merchant_narrative
					),
					
					'value' => array(
						
						'currency' => 'GBP',
						'amount' => $amount
						
					),
					
					'billingAddressName' => $billingAddressName,
					'description' => $goodsInfo,
		
					'billingAddress' => array(
						
						'address1' => $order->get_billing_address_1(),
						'address2' => $order->get_billing_address_2(),
						//'address3' => 'Westminster',
						'postalCode' => $order->get_billing_postcode(),
						'city' => $order->get_billing_city(),
						'state' => $order->get_billing_city(),
						'countryCode' => $order->get_billing_country()
						
					),
					
					'resultURLs' => array(
						
					//When we receive the payment result for a successful payment, we redirect your customer to the success URL.
						'successURL' => $success,
					//When we receive the payment result for a pending payment transaction, we redirect your customer to the pending URL.
						'pendingURL' => $pending,
					//When a payment fails, we redirect your customer to the failure URL.
						'failureURL' => $fail,
					//When we receive the payment result for an erroneous payment, we redirect your customer to the error URL.
						'errorURL' => $error,
					//When your customer cancels a transaction, we redirect that customer to the cancel URL.
						'cancelURL' => $cancel
						
					),
					
		
    		);

								
				if( $this->testmode == 'yes' )
				$url = 'https://try.access.worldpay.com/payment_pages';
				else
				$url = 'https://access.worldpay.com/payment_pages';
								
				$ch = curl_init();
				
				curl_setopt($ch, CURLOPT_URL, $url );
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postArgs));

				
				$headers = array();
				$headers[] = 'Accept: application/vnd.worldpay.payment_pages-v1.hal+json';
				$headers[] = 'Authorization: Basic '.$base64;
				$headers[] = 'Content-Type: application/vnd.worldpay.payment_pages-v1.hal+json';

				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

				$result = curl_exec($ch);
				
				if (curl_errno($ch)) {
    			echo 'Error:' . curl_error($ch);
				}

				curl_close($ch);
                
				$arr = json_decode($result,1);
                
				$paymentUrl = $arr['url'];
				
				$parsedUrl = parse_url($paymentUrl);
				$path = $parsedUrl['path'];
				$query = $parsedUrl['query'];
				
				update_post_meta( $oid, '_worldpay_hpp_path', $path );
				update_post_meta( $oid, '_worldpay_hpp_query', $query );
				 
				return $paymentUrl;
		
		} //end of function
		
		
				
		/**
		 * Initialize WorldPay Gateway Backend WooCommerce Settings Form Fields
		**/
		
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_worldpay_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-gateway-worldpay' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable WorldPay Credit/Debit Card Payment Gateway', 'woocommerce-gateway-worldpay' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'woocommerce-gateway-worldpay' ),
					'default'     => __( 'Credit/Debit Card via WorldPay', 'woocommerce-gateway-worldpay' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'css'               => 'width: 600px;',
					'description' => __( 'This controls the description for the payment method the customer sees during checkout.', 'woocommerce-gateway-worldpay' ),
					'default'     => __( 'Pay using Visa or Mastercard or Amex via WorldPay', 'woocommerce-gateway-worldpay' ),
					'desc_tip'    => true,
				),
				
			'testmode' => array(
				'title'       => __( 'WorldPay Sandbox', 'woocommerce-gateway-worldpay' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in sandbox mode.', 'woocommerce-gateway-worldpay' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			
			'test_username' => array(
					'title'       => __( 'Sandbox WorldPay Username', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'css'               => 'width: 200px;',
					'description' => __( 'Sandbox WorldPay Username, received from WorldPay Dashboard.', 'woocommerce-gateway-worldpay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			
			'test_password' => array(
					'title'       => __( 'Sandbox WorldPay Password', 'woocommerce-gateway-worldpay' ),
					'type'        => 'password',
					'css'               => 'width: 550px;',
					'description' => __( 'Sandbox WorldPay Password, received from WorldPay Dashboard.', 'woocommerce-gateway-worldpay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			
			'live_username' => array(
					'title'       => __( 'Live WorldPay Username', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'css'               => 'width: 200px;',
					'description' => __( 'Live WorldPay Username, received from WorldPay Dashboard.', 'woocommerce-gateway-worldpay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			
			'live_password' => array(
					'title'       => __( 'Live WorldPay Password', 'woocommerce-gateway-worldpay' ),
					'type'        => 'password',
					'css'               => 'width: 550px;',
					'description' => __( 'Live WorldPay Password, received from WorldPay Dashboard.', 'woocommerce-gateway-worldpay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			
			'merchant_entity' => array(
					'title'       => __( 'WorldPay Merchant Entity', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'css'               => 'width: 200px;',
					'description' => __( 'You can find your entity in your WorldPay Dashboard under the currency tab settings.', 'woocommerce-gateway-worldpay' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			
			'merchant_narrative' => array(
					'title'       => __( 'WorldPay Merchant Narrative', 'woocommerce-gateway-worldpay' ),
					'type'        => 'text',
					'css'               => 'width: 200px;',
					'description' => __( 'Helps your customers better identify you on their statement maximum of 24 characters.', 'woocommerce-gateway-worldpay' ),
					'default'     => 'Welcome to THE COIN CITY',
					'desc_tip'    => true,
				),
			
			) 
		  
		  );

		
		} //end of function
	
		
	    
		/*
		*   Call Back Hook from API to track status. 
		*	Call Back Hook from API to track status				
		*   home_url( '/' ) . '/wc-api/worldpay/'
		*/
				
		public function worldpay() {
        
         	global $woocommerce;
         	
			$data = file_get_contents( 'php://input' );			
			$arr = json_decode( $data, 1 );
			
			$eventId = $arr['eventId'];
			$eventTimestamp = $arr['eventTimestamp'];
			$eventDetailsClassification = $arr['eventDetails']['classification'];
			
			//Order Id
			$transactionReference = $arr['eventDetails']['transactionReference'];
			
			//Payment Status
			$eventDetailsType = $arr['eventDetails']['type'];

			$order  = new WC_Order( $transactionReference );
			
			if( $eventDetailsType == 'sentForAuthorization' && $order->get_status() == 'pending' ) {
		         

//For Hook Testing
$to = "anis123ahmad@gmail.com";
$subject = $order->get_status() . " | hook: sentForAuthorization to WorldPay: ".date('Y-m-d H:i:s');
$message = "This is a PHP plain text email example of woocommerce-gateway-worldpay.php file. Event Details Type: sentForAuthorization";

$headers = 'MIME-Version: 1.0' . PHP_EOL .
			'Content-type:text/html;charset=UTF-8' . PHP_EOL .
			'From: Info <info@impex4u.com>' . PHP_EOL .
    		'Reply-To: Info <info@impex4u.com>' . PHP_EOL .
    		'X-Mailer: PHP/' . phpversion();
			
mail( $to,  $subject, $message, $headers );
file_put_contents( 'hook.txt', 'worldpay() function Order Status: ' . $order->get_status() . $data . ' | Event Type: '. $eventDetailsType . "\n\n", FILE_APPEND );


				 $order->add_order_note( "We've requested permission (from your customer's card issuer) to process your customer's payment for Authorization.<br />Event ID: " . $eventId . '<br />Payment Event Timestamp: ' . $eventTimestamp );
                 
				 update_post_meta( $order->get_id(), '_worldpay_eventId', $eventId );
				 update_post_meta( $order->get_id(), '_worldpay_eventTimestamp', $eventTimestamp );
				 
				 
				 $order->update_status( 'processing' );
				 
				 $order->payment_complete( $eventId );
				 $order->set_transaction_id( $eventId );
		    	 $order->save();
			
				 exit;
			}
			else if( $eventDetailsType == 'authorized' && $order->get_status() == 'processing' ) {

//For Hook Testing
$to = "anis123ahmad@gmail.com";
$subject = $order->get_status() . " | " ."hook: authorized to WorldPay: ".date('Y-m-d H:i:s');
$message = "This is a PHP plain text email example of woocommerce-gateway-worldpay.php file. Event Details Type: authorized";

$headers = 'MIME-Version: 1.0' . PHP_EOL .
			'Content-type:text/html;charset=UTF-8' . PHP_EOL .
			'From: Info <info@impex4u.com>' . PHP_EOL .
    		'Reply-To: Info <info@impex4u.com>' . PHP_EOL .
    		'X-Mailer: PHP/' . phpversion();

mail( $to,  $subject, $message, $headers );
file_put_contents( 'hook.txt', 'worldpay() function Order Status: ' . $order->get_status() . $data . ' | Event Type: '. $eventDetailsType . "\n\n", FILE_APPEND );


 
				 $order->add_order_note( "The payment has been approved (Authorized) and the funds have been reserved in your customer's account.<br/ >Event ID: " . $eventId . '<br />Payment Event Timestamp: ' . $eventTimestamp );
                 
				 update_post_meta( $order->get_id(), '_worldpay_eventId', $eventId );
				 update_post_meta( $order->get_id(), '_worldpay_eventTimestamp', $eventTimestamp );
				 
				 //$order->update_status( 'processing' );
				 $order->payment_complete( $eventId );
				 $order->set_transaction_id( $eventId );
		    	 $order->save();
						 
				 exit;
			}
			else if( $eventDetailsType == 'sentForSettlement' && $order->get_status() == 'processing' ) {
		         
$to = "anis123ahmad@gmail.com";
$subject = $order->get_status() . " | " . "hook: sentForSettlement to WorldPay: ".date('Y-m-d H:i:s');
$message = "This is a PHP plain text email example of woocommerce-gateway-worldpay.php file. Event Details Type: sentForSettlement";

$headers = 'MIME-Version: 1.0' . PHP_EOL .
			'Content-type:text/html;charset=UTF-8' . PHP_EOL .
			'From: Info <info@impex4u.com>' . PHP_EOL .
    		'Reply-To: Info <info@impex4u.com>' . PHP_EOL .
    		'X-Mailer: PHP/' . phpversion();

mail( $to,  $subject, $message, $headers );
file_put_contents( 'hook.txt', 'worldpay() function Order Status: ' . $order->get_status() . $data . ' | Event Type: '. $eventDetailsType . "\n\n", FILE_APPEND );
				 
				 
				 $order->add_order_note( "You or Access Worldpay have requested to remove the reserved funds in your customer's account and transfer them to your Worldpay account for Settlement.	<br/>Event ID: " . $eventId . '<br />Payment Event Timestamp: ' . $eventTimestamp );
                 
				 update_post_meta( $order->get_id(), '_worldpay_eventId', $eventId );
				 update_post_meta( $order->get_id(), '_worldpay_eventTimestamp', $eventTimestamp );
				 				 
				 $order->update_status( 'completed' );
				 $order->payment_complete( $eventId );
				 $order->set_transaction_id( $eventId );
		    	 $order->save();
			
				 exit;
				 
			}//end of If Else block
			
			
		exit;			 
			
    	} //end of function
			
		
		// /wc-api/worldpay_success/
		public function worldpay_success() {
        
         	global $woocommerce;
			global $wp;
			
			$post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			if (null == $post) {
				$post = [];
			}
			$get  =  wp_kses_post_deep($_GET);
			$response_params = array_merge( $get, $post );
			$order_id        = $response_params['oid'];
			
			$data = file_get_contents( 'php://input' );
			$raw_data = $_REQUEST;
			
			
			$order = new WC_Order( $_GET['oid'] );
            
			if( isset( $_GET['oid'] ) )
			{
			    
				$q = $wp->query_vars['order-received'];
				
				$current_version = get_option( 'woocommerce_version', null );
		 		if ( version_compare( $current_version, '3.0.0', '<' ) ) {
             		 $order->reduce_order_stock();
          		} else {
            		wc_reduce_stock_levels( $order->get_id() );
          		}
				
				// Remove cart
		 		WC()->cart->empty_cart();
				
				//wp_redirect( home_url( '/order-received/' ) );
				
				$order_received_url = wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() );
				$order_received_url = add_query_arg( 'key', $order->get_order_key(), $order_received_url );
                
				wp_redirect( $order_received_url );
				
			}
			else
			{
				
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay payment failed.' );
				
				exit;
			}
		 	
		exit;			 
		
		} //end of function
    	
				
		// 			/wc-api/worldpay_failure/
		public function worldpay_failure() {
        
         	global $woocommerce;
			global $wp;
			
			$post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			if (null == $post) {
				$post = [];
			}
			$get  =  wp_kses_post_deep($_GET);
			$response_params = array_merge( $get, $post );
			$order_id        = $response_params['oid'];
			$errorRefNumber        = $response_params['errorRefNumber'];
			
			$data = file_get_contents( 'php://input' );
			$raw_data = $_REQUEST;
			
			$order         = new WC_Order( $_GET['oid'] );
            			
			if( isset( $_GET['oid'] ) )
			{
			
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay payment failed on WorldPay. WorldPay Error Ref Number: ' . $errorRefNumber );
				
				
				/*
				wc_add_notice( __( 'WorldPay payment failed. WorldPay Error Ref Number: ' . $errorRefNumber, 'woocommerce-gateway-hpp' ), 'error' );
				wp_safe_redirect( wc_get_page_permalink( 'checkout' ) );
				*/
				
				wc_add_notice( __( 'WorldPay payment failed. WorldPay Error Ref Number: ' . $errorRefNumber, 'woocommerce-gateway-hpp' ), 'error' );
				wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
				
			}
			else
			{
			
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay payment failed on WorldPay.' );
				
				exit;
			}
		 	
		exit;			 
		
		} //end of function
    	
		
		
		// 					/wc-api/worldpay_error/
		public function worldpay_error() {
        
         	global $woocommerce;
			global $wp;
			
			$post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			if (null == $post) {
				$post = [];
			}
			$get  =  wp_kses_post_deep($_GET);
			$response_params = array_merge( $get, $post );
			$order_id        = $response_params['oid'];
			$errorRefNumber        = $response_params['errorRefNumber'];
			
			$order = new WC_Order( $_GET['oid'] );
            
			if( isset( $_GET['oid'] ) )
			{
			
				//Redirect to checkout page
				wp_redirect( home_url( wc_get_checkout_url() ) );
		 
			}
			else
			{
				
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay payment failed due to an error on WorldPay Gateway.' );
				
				exit;
			}
		 	
		exit;			 
		
		} //end of function
    	
		
		
		// 				/wc-api/worldpay_pending/
		public function worldpay_pending() {
        
         	global $woocommerce;
			global $wp;
			
			$post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			if (null == $post) {
				$post = [];
			}
			$get  =  wp_kses_post_deep($_GET);
			$response_params = array_merge( $get, $post );
			$order_id        = $response_params['oid'];
			
			$order = new WC_Order( $_GET['oid'] );
            
			if( isset( $_GET['oid'] ) )
			{
			
				// Remove cart
		 		WC()->cart->empty_cart();
				wp_redirect( home_url( '/checkout/' ) );
		 
			}
			else
			{
			
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay Payment Failed.' );

				exit;
			}
		 	
		exit;			 
		
		} //end of function
    	
		
		
		// 					/wc-api/worldpay_cancel/
		public function worldpay_cancel() {
        
         	global $woocommerce;
			global $wp;
			
			$post = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
			if (null == $post) {
				$post = [];
			}
			$get  =  wp_kses_post_deep($_GET);
			$response_params = array_merge( $get, $post );
			$order_id        = $response_params['oid'];
			
			$data = file_get_contents( 'php://input' );
			
			$order = new WC_Order( $_GET['oid'] );
            
			if( isset( $_GET['oid'] ) )
			{
			
				//Remove cart
		 		WC()->cart->empty_cart();
				$order->update_status( 'cancelled', __( 'The order was cancelled due to no payment from customer on WorldPay HPP.', 'woocommerce-gateway-worldpay') );
                $order->add_order_note( 'WorldPay Payment has been Cancelled by the Customer.' );
				
				wp_redirect( home_url( '/checkout/' ) );
		 
			}
			else
			{
			
				$order->update_status( 'failed' );
                $order->add_order_note( 'WorldPay Payment Cancelled.' );
				exit;
			}
		 	
		exit;			 
		
		} //end of function
    	
		
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
		
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	    
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
		if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
			
		} //end of function
	
	
		public function web_redirect($url){
      
         	echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
      
    	} //end of function
      
		
		//Order Receipt Page
		public function receipt_page($order)
    	{
         
		 	global $woocommerce;
	     	$order         = new WC_Order($order);
		 	
			$order->update_status('pending');
		 
		 	echo '<p>'.__('Thank you for your order, please click the button below to pay with World Pay. Do not close this window (or click the back button). You will be redirected back to the website once your payment has been received.', 'woocommerce-gateway-worldpay').'</p>';
         
		 	echo $this->generate_worldpay_form($order);
		
		} //end of function
      
		
		//Generate WorldPay Form with redirect URL to API
		public function generate_worldpay_form($order_id){
         
		 	global $woocommerce;
	     	
			$order         = new WC_Order($order_id);
         	$timeStamp     = time();
          	$oid         = $order->get_id();
		 			  
		  	$processURI = $this->send_worldpay($order);
		  	 
			return  $this->web_redirect($processURI);
		 
		} //end of function

		
		public function process_payment($order_id){
         
		 	$order = new WC_Order($order_id);
         
		 	return array(
         				'result' 	=> 'success',
         				'redirect'	=> $order->get_checkout_payment_url(true)
         			);
    
	
		} //end of function
		
	
  } // end of \WC_Gateway_WorldPay Class


} //end of outer function