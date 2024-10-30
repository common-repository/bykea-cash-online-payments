<?php

/**
 * Plugin Name:       Bykea.Cash - Online Payments
 * Plugin URI:        https://wordpress.org/plugins/bykea-cash-online-payments
 * Description:       This plugin helps WooCommerce (WordPress) customers pay via Debit / Credit Cards or Cash Pickups using Bykea Cash payment service.
 * Version:           3.2
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Bykea Technologies
 * Author URI:        https://bykea.com/online-payment-gateway-plugin/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wordpress.org/plugins/bykea-cash-online-payments
 * Text Domain:       bykea-cash-online-payments
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'opbc_init', 0 );
function opbc_init() {
	
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-bykeacash.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'opbc_gateway' );
	function opbc_gateway( $methods ) {
		$methods[] = 'OnlinePayments_BykeaCash';
		return $methods;
	}
}

// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'opbc_action_links' );
function opbc_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'opbc' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );	
}

// Hide trailing zeros on prices.
add_filter( 'woocommerce_price_trim_zeros', 'opbc_hide_trailing_zeros', 10, 1 );
function opbc_hide_trailing_zeros( $trim ) {
    // set to false to show trailing zeros
    return true;
}

function opbc_load_scripts() {
	wp_enqueue_style( 'opbc_admin_style', plugins_url('admin_style.css', __FILE__) );
	wp_enqueue_script( 'opbc_admin_script', plugins_url('admin_scripts.js', __FILE__), array(), '1.0.0', true );
	wp_localize_script( 'opbc_admin_script', 'bcashAjaxObject', array( 'bcashAjaxUrl' => admin_url( 'admin-ajax.php' ) ) );
}
add_action( 'admin_enqueue_scripts', 'opbc_load_scripts' );

/**
 * When plugin is activated
 */

function opbc_activation_activity() { 
	$to = 'plugins@bykea.com';
	$subject = 'Bykea Cash Installation Notification';
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$message = '<html><body>';
	$message .= '<p>Hello Bykea, a new website has activated your plugin. Please find the details below:</p>';
	$message .= '<p>Website Title: '.get_bloginfo('name').'</p>';
	$message .= '<p>Website URL: '.get_bloginfo('url').'</p>';
	$message .= '<p>Admin Email: '.get_bloginfo('admin_email').'</p>';
	$message .= '</body></html>';
	return wp_mail($to,$subject,$message,$headers);
}

register_activation_hook( __FILE__, 'opbc_activation_activity' );

/**
 * When plugin is deactivated
 */
function opbc_deactivation_activity() { 
    $to = 'plugins@bykea.com';
	$subject = 'Bykea Cash Uninstallation Notification';
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$message = '<html><body>';
	$message .= '<p>Hello Bykea, your plugin is deactivated from '.get_bloginfo('name').' - '.get_bloginfo('url').'. Please find the admin email below if you want to investigate it.</p>';
	$message .= '<p>Admin Email: '.get_bloginfo('admin_email').'</p>';
	$message .= '</body></html>';
	return wp_mail($to,$subject,$message,$headers);
}
register_deactivation_hook( __FILE__, 'opbc_deactivation_activity' );


/**
 * Callback method for successful payment
 * @return true after email is sent successfully
 */
function opbc_update_order($request){
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'woocommerce-bykeacash.php' );
	$bcashApi = new OnlinePayments_BykeaCash();
	$orderId = $request->get_param('id');
	$success = $request->get_param('success');
	$invoice = $request->get_param('invoice');
	$order = wc_get_order( $orderId );
	$orderData = $order->get_data();
	if($success=="true"){
		wp_redirect(home_url().'/checkout/order-received/'.$orderId.'/?key='.$order->get_order_key(), 301);
	}else{
		wp_redirect(home_url(), 301);
	}
	die();
}
add_action('rest_api_init', function () {
	register_rest_route( 'bcashapi/v1', '/order', array(
		'methods'             => 'GET',
		'callback'            => 'opbc_update_order',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				}
			),
			'success' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_string( $param );
				}
			),
			'invoice' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_string( $param );
				}
			),
		),
	) );
});

add_action('wp_ajax_nopriv_get_secret_otp', 'opbc_get_secret_otp' );
add_action('wp_ajax_get_secret_otp', 'opbc_get_secret_otp' );
function opbc_get_secret_otp(){  
    $otpNumber = esc_html(sanitize_text_field($_POST['otp_number']));
	$otpEmail = esc_html(sanitize_text_field($_POST['otp_email']));
	$curl = curl_init();
	curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://invoice.bykea.cash/open/api/generate/otp',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS =>'{
		"number": "'.$otpNumber.'",
		"email": "'.$otpEmail.'"
	}',
	CURLOPT_HTTPHEADER => array(
		'Content-Type: application/json'
	),
	));
	$response = json_decode(curl_exec($curl));
	curl_close($curl);
	if($response->subcode=='10010'){
		update_option( 'bcash_otp_sent' , 'yes' );
		wp_send_json(['message'=>'An OTP has been send to your mobile number. Please check.'],200);
	}else{
		if($response->data->messages){
			wp_send_json(['message'=>$response->data->messages->number[0]],500);
		}elseif($response->message){
			wp_send_json(['message'=>$response->message],500);
		}else{
			wp_send_json(['message'=>'An error occured. Please try again later.'],500);
		}
	}
	die();
}

add_action('wp_ajax_nopriv_submit_otp_for_secret', 'opbc_submit_otp_for_secret' );
add_action('wp_ajax_submit_otp_for_secret', 'opbc_submit_otp_for_secret' );
function opbc_submit_otp_for_secret(){ 
	$otpNumber = esc_html(sanitize_text_field($_POST['otp_number']));
	$otpEmail = esc_html(sanitize_text_field($_POST['otp_email']));
	$otp = esc_html(sanitize_text_field($_POST['otp']));
	$curl = curl_init();
	curl_setopt_array($curl, array(
	CURLOPT_URL => 'https://invoice.bykea.cash/open/api/verify/otp',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => '',
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => 'POST',
	CURLOPT_POSTFIELDS =>'{
		"number": "'.$otpNumber.'",
		"email": "'.$otpEmail.'",
		"auth_otp": "'.$otp.'"
	}',
	CURLOPT_HTTPHEADER => array(
		'Content-Type: application/json'
	),
	));
	$response = json_decode(curl_exec($curl));
	curl_close($curl);
	if($response->subcode=='10001'){
		update_option( 'otp_verified' , 'yes' );
		wp_send_json(['secret'=>$response->secret],200);
	}else{
		if($response->data->messages){
			wp_send_json(['message'=>$response->data->messages->auth_otp[0]],500);
		}elseif($response->message){
			wp_send_json(['message'=>$response->message],500);
		}elseif($response->error){
			wp_send_json(['message'=>$response->error],500);
		}else{
			wp_send_json(['message'=>'An error occured. Please try again later.'],500);
		}
	}
	die();
}

// IPN functionality for Order update
function opbc_manage_IPN_Data( $request ) {
	$reqHeaders = getallheaders();
	$ipnData = $request->get_json_params();
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'woocommerce-bykeacash.php' );
	$bcashApi = new OnlinePayments_BykeaCash();
	$orderId = $ipnData['details']['order'];
	$invoice = $ipnData['invoice_number'];
	$order = wc_get_order( $orderId );
	$orderData = $order->get_data();
	$adminEmail = $bcashApi->email;
	$apiSecretKey = $bcashApi->secret_key;
	$customerEmail = $orderData['billing']['email'];
	if(isset($reqHeaders['Authorization'])){
		if($reqHeaders['Authorization']=='Bearer '.$apiSecretKey){
			if ( $order->has_status('pending') ) {
				if($ipnData['payment_status'] == 'completed'){
					$order->update_status( 'processing' );
					$orderNote = __("Payment against invoice no: <a href='https://invoice.bykea.cash/receive/openapi/invoice/".$invoice."' target='_blank'>".$invoice."</a> has been confirmed.");
					$order->add_order_note( $orderNote );
					// Payment confirmation to admin
					$to = $adminEmail;
					$subject = 'Payment Notification from Bykea Cash';
					$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
					$message = '<html><body>';
					$message .= '<p>Dear Admin, we have received your payment against Order ID: '.$orderId.'. It will get transferred into your bank account shortly. Bykea Cash Invoice ID for this payment is "'.$invoice.'".</p>';
					$message .= '</body></html>';
					wp_mail($to,$subject,$message,$headers);
					// Payment confirmation to customer
					$to = $customerEmail;
					$subject = 'Payment Confirmation Notification';
					$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
					$message = '<html><body>';
					$message .= '<p>Dear Customer, your payment against Order ID: '.$orderId.' on '.home_url().' has been confirmed. You will receive update about your order shortly.</p>';
					$message .= '</body></html>';
					wp_mail($to,$subject,$message,$headers);
				}else{
					$order->update_status( 'failed' );
					$orderNote = __("Payment against invoice no: <a href='https://invoice.bykea.cash/receive/openapi/invoice/".$invoice."' target='_blank'>".$invoice."</a> has been declined.");
					$order->add_order_note( $orderNote );
					// Payment cancellation notification to admin
					$to = $adminEmail;
					$subject = 'Payment Notification from Bykea Cash';
					$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
					$message = '<html><body>';
					$message .= '<p>Dear Admin, payment made against Order ID: '.$orderId.'. has been declined. For more details please contact Bykea Cash Support. Bykea Cash Invoice ID for this payment is "'.$invoice.'".</p>';
					$message .= '</body></html>';
					wp_mail($to,$subject,$message,$headers);
					// Payment cancellation notification to customer
					$to = $customerEmail;
					$subject = 'Payment Decline Notification';
					$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
					$message = '<html><body>';
					$message .= '<p>Dear Customer, your payment against Order ID: '.$orderId.' on '.home_url().' has been voided. For more details, please contact website administrator.</p>';
					$message .= '</body></html>';
					wp_mail($to,$subject,$message,$headers);
				}
			}
		}
	}
}
add_action( 'rest_api_init', function () {
	register_rest_route( 'bcashapi/v1', '/ipn', array(
	  'methods' => 'POST',
	  'callback' => 'opbc_manage_IPN_Data',
	  'permission_callback' => '__return_true',
	) );
});

/**
 * Callback method for cancelled payment
 * @return true after email is sent successfully
 */
function opbc_cancel_order($request){
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'woocommerce-bykeacash.php' );
	$bcashApi = new OnlinePayments_BykeaCash();
	$orderId = $request->get_param('id');
	$order = wc_get_order( $orderId );
	$orderData = $order->get_data();
	$adminEmail = $bcashApi->email;
	$customerEmail = $orderData['billing']['email'];
	$order->update_status( 'cancelled' );
	// Payment cancellation notification to admin
	$to = $adminEmail;
	$subject = 'Order Cancellation from Bykea Cash';
	$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
	$message = '<html><body>';
	$message .= '<p>Dear Admin, order having ID: '.$orderId.', has been cancelled by customer.</p>';
	$message .= '</body></html>';
	wp_mail($to,$subject,$message,$headers);
	// Payment cancellation notification to customer
	$to = $customerEmail;
	$subject = 'Order Cancellation Notification';
	$headers = array('Content-Type: text/html; charset=UTF-8','From: '.get_bloginfo( 'name' ).' <wordpress@'.$_SERVER['HTTP_HOST'].'>');
	$message = '<html><body>';
	$message .= '<p>Dear Customer, your order having ID: '.$orderId.' on '.home_url().' has been canceled as per your request. For more details, please contact website administrator.</p>';
	$message .= '</body></html>';
	wp_mail($to,$subject,$message,$headers);
	wp_redirect(home_url(), 301);
	die();
}
add_action('rest_api_init', function () {
	register_rest_route( 'bcashapi/v1', '/order/cancel', array(
		'methods'             => 'GET',
		'callback'            => 'opbc_cancel_order',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				}
			),
		),
	) );
});


add_action('wp_ajax_nopriv_register_merchant_ipn', 'opbc_register_merchant_ipn' );
add_action('wp_ajax_register_merchant_ipn', 'opbc_register_merchant_ipn' );
function opbc_register_merchant_ipn(){ 
	$apiSecretKey = esc_html(sanitize_text_field($_POST['secret_key']));
	$ipnRegistered = get_option( 'bykea_cash_ipn_registered' );
	if($ipnRegistered!='yes'){
		$curl = curl_init();
		curl_setopt_array($curl, array(
  			CURLOPT_URL => 'https://invoice.bykea.cash/open/api/register/ipn',
  			CURLOPT_RETURNTRANSFER => true,
  			CURLOPT_ENCODING => '',
  			CURLOPT_MAXREDIRS => 10,
  			CURLOPT_TIMEOUT => 0,
  			CURLOPT_FOLLOWLOCATION => true,
  			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  			CURLOPT_CUSTOMREQUEST => 'POST',
  			CURLOPT_POSTFIELDS =>'{
    			"ipn_endpoint": "'.home_url().'/wp-json/bcashapi/v1/ipn",
    			"header_field" : "Authorization",
    			"header_value" : "Bearer '.$apiSecretKey.'"
			}',
  			CURLOPT_HTTPHEADER => array(
    			'secret: '.$apiSecretKey,
    			'Content-Type: application/json'
  			),
		));
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		if($response->subcode=='10002'){
			update_option( 'bykea_cash_ipn_registered' , 'yes' );
		}
	}
	wp_send_json(['message'=>'IPN registration done.'],200);
	die();
}

// Adding a custom metabox for admins to check Bykea Cash invoice status
add_action( 'add_meta_boxes', 'opbc_add_invoice_status_metabox' );
function opbc_add_invoice_status_metabox()
{
    add_meta_box( 'custom_order_meta_box', __( 'Bykea Cash Invoice Status' ),
        'opbc_add_invoice_status_metabox_content', 'shop_order', 'side', 'high');
}

function opbc_add_invoice_status_metabox_content(){
	$orderId = isset($_GET['post']) ? $_GET['post'] : false;
    if(! $orderId ) return; // Exit
	$invoiceNumber = get_post_meta( $orderId, 'invoice_number', true );
    ?>
	    <div class="invoice-status-btn-container">
			<button type="button" class="bykea-btn check_invoice_status" data-invoice-id="<?php echo $invoiceNumber; ?>"><?php _e('Check Invoice Status'); ?></button>
			<div class="status_api_response"></div>
		</div>
    <?php
}

add_action('wp_ajax_nopriv_check_bykeacash_invoice_status', 'opbc_check_bykeacash_invoice_status' );
add_action('wp_ajax_check_bykeacash_invoice_status', 'opbc_check_bykeacash_invoice_status' );
function opbc_check_bykeacash_invoice_status(){ 
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	include_once( 'woocommerce-bykeacash.php' );
	$bcashApi = new OnlinePayments_BykeaCash();
	$apiSecretKey = $bcashApi->secret_key;
	$invoiceNumber = esc_html(sanitize_text_field($_POST['invoice_number']));
	$curl = curl_init();
	curl_setopt_array($curl, array(
  		CURLOPT_URL => 'https://invoice.bykea.cash/open/api/invoice/status',
  		CURLOPT_RETURNTRANSFER => true,
  		CURLOPT_ENCODING => '',
  		CURLOPT_MAXREDIRS => 10,
  		CURLOPT_TIMEOUT => 0,
  		CURLOPT_FOLLOWLOCATION => true,
  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  		CURLOPT_CUSTOMREQUEST => 'POST',
  		CURLOPT_POSTFIELDS =>'{
    		"invoice_id" : "'. $invoiceNumber .'"
		}',
  		CURLOPT_HTTPHEADER => array(
    		'secret: '.$apiSecretKey,
    		'Content-Type: application/json',
    		'Accept: application/json'
  		),
	));
	$response = json_decode(curl_exec($curl));
	curl_close($curl);
	if($response->code=='11'){
		$responsetext = '';
		$responseClass = '';
		$invoiceStatus = $response->status;
		switch ($invoiceStatus) {
			case "requires_payment":
				$responsetext = 'Payment required - Payment has not been made by the customer.';
				$responseClass = 'error';
			  	break;
			case "succeeded":
				$responsetext = 'Payment transfered - Payment has been collected from customer and deposited into your bank account from Bykea';
				$responseClass = 'success';
				break;
			case "pending_processing":
				$responsetext = 'Payment collected - Payment has been collected from customer and will be transferred into your bank account shortly.';
				$responseClass = 'moderate';
				break;
			case "invoice_expired":
				$responsetext = 'Invoice has been expired';
				$responseClass = 'error';
				break;
			default:
				$responsetext = "The invoice id sent in the request does not exist in our system.";
		}
		wp_send_json(['status' => 'success', 'class' => $responseClass, 'message' => $responsetext],200);
	}else{
		wp_send_json(['status' => 'error', 'class' => 'error', 'message' => 'The invoice id sent in the request does not exist in our system.'],500);
	}
	die();
}