<?php
/**
 * Class OnlinePayments_BykeaCash file.
 *
 * @package WooCommerce\Gateways
 */

use Automattic\Jetpack\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Cash on Delivery Gateway.
 *
 * Provides a Cash on Delivery Payment Gateway.
 *
 * @class       OnlinePayments_BykeaCash
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce\Classes\Payment
 */
class OnlinePayments_BykeaCash extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );

		//BykeaCash Mobile Number
		$this->mobile_number = $this->get_option( 'mobile_number' );
        //BykeaCash Details Prefix
		$this->details_prefix = $this->get_option( 'details_prefix' );
		//BykeaCash Notification Email
		$this->email = $this->get_option( 'email' );
		//BykeaCash Business Name
		$this->business_name = $this->get_option( 'business_name' );
		//Site URL
		$this->site_url = site_url();
		//Secret Key
		$this->secret_key = $this->get_option( 'api_secret' );
		//Selected Payment Option
		$this->payment_option = $this->get_option( 'payment_option' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'bykea_cash';
		$this->icon = plugins_url('/bykea-cash-online-payments/bykea-cash-logo.png');
		$this->method_title       = __( 'Bykea Cash', 'online-payments-bykeacash' );
		$this->method_description = __( 'Online Payment Gateway Plugin for WooCommerce', 'online-payments-bykeacash' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$secretKey = $this->get_option( 'api_secret' );
		$otpVerified = get_option( 'otp_verified' );
		if($secretKey!=''){
			$showClass='show-option';
			$OtpOptionsClass = 'hide-option';
		}else{
			$showClass='hide-option';
			$OtpOptionsClass = 'show-option';
		}
		$ipnRegistered = get_option( 'bykea_cash_ipn_registered' );
		if($ipnRegistered=='yes'){
			$ipnClass='hide-ipn-field';
		}else{
			$ipnClass='';
		}
		$this->form_fields = array(
			'otp_mobile' => array(
				'title'		=> __( 'Bykea Mobile Number', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Enter your Bykea registered mobile number, a one time code will be sent on that mobile.', 'online-payments-bykeacash' ),
				'class'     => $OtpOptionsClass." otp_requirements",
				'custom_attributes' => array('autocomplete' => 'off'),
				'placeholder' => '03021234567'
			),
			'otp_email' => array(
				'title'		=> __( 'Your Email Address', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Enter your email address.', 'online-payments-bykeacash' ),
				'class'     => $OtpOptionsClass." otp_requirements",
				'custom_attributes' => array('autocomplete' => 'off'),
			),
			'otp_to_verify' => array(
				'title'		=> __( 'OTP', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Enter the OTP which you have received on your number.', 'online-payments-bykeacash' ),
				'class'     => 'otp-field',
			),
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'online-payments-bykeacash' ),
				'label'		=> __( 'Enable this payment gateway', 'online-payments-bykeacash' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
				'class'     => $showClass." plugin-details-fields",
			),
			'title' => array(
				'title'		=> __( 'Title', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'class'     => $showClass." plugin-details-fields",
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'online-payments-bykeacash' ),
				'default'	=> __( 'Debit or Credit Card', 'online-payments-bykeacash' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'online-payments-bykeacash' ),
				'type'		=> 'textarea',
				'class'     => $showClass." plugin-details-fields",
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'online-payments-bykeacash' ),
				'default'	=> __( 'Pay by doorstep cash pick (Karachi, Lahore & Islamabad) or Visa / MasterCard.', 'online-payments-bykeacash' ),
				'css'		=> 'max-width:350px;'
			),
			'business_name' => array(
				'title'		=> __( 'Business Name', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Your Business Name', 'online-payments-bykeacash' ),
				'class'     => $showClass." plugin-details-fields",
				'default'   => get_bloginfo('name'),
			),
            'details_prefix' => array(
				'title'		=> __( 'Details', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'class'     => $showClass." plugin-details-fields",
				'desc_tip'	=> __( 'Please enter text that you want to show on every invoice.', 'online-payments-bykeacash' ),
				'default'	=> $_SERVER['HTTP_HOST'],
			),
			'email' => array(
				'title'		=> __( 'Email Address', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'class'     => $showClass." plugin-details-fields",
				'desc_tip'	=> __( 'Email address assosiated with your account.', 'online-payments-bykeacash' ),
			),
			'mobile_number' => array(
				'title'		=> __( 'Bykea Account Mobile Number', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'class'     => $showClass." plugin-details-fields",
				'desc_tip'	=> __( 'Your Bykea account mobile number', 'online-payments-bykeacash' ),
			),
			'api_secret' => array(
				'title'		=> __( 'API Secret Key', 'online-payments-bykeacash' ),
				'type'		=> 'text',
				'class'     => $showClass." plugin-details-fields",
				'description'	=> __( 'In case you are facing any issues with the plugin, contact us at plugins@bykea.com.', 'online-payments-bykeacash' )
			),
			'payment_option' => array(
				'title'		=> __( 'Payment Options', 'online-payments-bykeacash' ),
				'desc_tip'	=> __( 'Select the payment option that you want to show to your customers', 'online-payments-bykeacash' ),
				'type'      => 'select',
				'class'     => $showClass." plugin-details-fields",
				'default'   => 'card_payments',
				'options'   => array(
					'card_payments' => 'Show Visa/Mastercard Payment Option',
					'cash_pickup' => 'Show Cash Pickup Payment Option',
					'both' => 'Show All Payment Options',
				)
			),
			'ipn_url' => array(
				'title'		=> __( 'IPN URL', 'online-payments-bykeacash' ),
				'type' => 'text',
				'class'     => $showClass." ".$ipnClass." plugin-details-fields",
				'description' => __( "Please click on the Register IPN button to register your IPN url with Bykea so that you can receive your order status updates from Bykea. Without this your order statuses won't get updated.", 'online-payments-bykeacash' ),
				'default'	=> __( home_url().'/wp-json/bcashapi/v1/ipn', 'online-payments-bykeacash' ),
				'custom_attributes' => array(
					'readonly' => 'readonly'
				),
			)
		);		
	}

	function payment_fields(){
		echo '<div style="margin-top: 5px;"><img style="float: none;" src="'.esc_html(plugins_url('bykea-cash-online-payments/bykea-full-payment-option.png')).'" alt="Bykea.Cash"></div><div style="padding-top:5px;font-size:14px;" class="op-bykeacash-fulloptions">'.esc_html($this->description).'</div>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order->get_total() > 0 ) {
			// Mark as processing or on-hold (payment won't be taken until delivery).
			$order->update_status( apply_filters( 'woocommerce_bykeacash_process_payment_order_status', $order->has_downloadable_item() ? 'on-hold' : 'pending', $order ), __( 'Payment to be processed through Bykea Cash.', 'online-payments-bykeacash' ) );
		} else {
			$order->payment_complete();
		}
		//Total Cost
		$totalCost = ceil($order->total);
		//Converting other currency order amount into PKR
		if($order->currency!='PKR'){
			$currency = $order->currency;
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://api.exchangerate.host/latest?base='.$currency.'&symbols=PKR&format=json',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'GET',
			));
			$response = json_decode(curl_exec($curl));
			curl_close($curl);
			$exchangeRate = 1;
			if($response->success){
				$exchangeRate = $response->rates->PKR;
			}
			$totalCost = ceil($totalCost*$exchangeRate);
		}
		//Order ID to pass in the BykeaCash API
		$order_id = $order->id;
		//Mobile Number Assosited with BykeaCash
		$mobile_number = esc_html(sanitize_text_field($this->mobile_number));
		//Business name Assosited with BykeaCash
		$businessName = esc_html(sanitize_text_field($this->business_name));
		//Site URL
		$url = site_url();
        // Details Prefix
        $detailsPrefix = esc_html(sanitize_text_field($this->details_prefix));
		// API Secret Key
        $secretKey = esc_html(sanitize_text_field($this->secret_key));
		$order_data = $order->get_data(); 
		$billingName = esc_html(sanitize_text_field($order_data['billing']['first_name']." ".$order_data['billing']['last_name']));
		$billingPhone = esc_html(sanitize_text_field($order_data['billing']['phone']));
		$businessEmail = esc_html(sanitize_text_field($this->email));
		if(strlen($order_data['billing']['address_1']) > 3){
			$shippingAddress = esc_html(sanitize_text_field($order_data['billing']['address_1']));
		}else{
			$shippingAddress = 'Not Available';
		}
		if(strlen($order_data['billing']['city']) > 3){
			$shippingCity = esc_html(sanitize_text_field($order_data['billing']['city']));
		}else{
			$shippingCity = 'N/A';
		}
		if($order_data['billing']['state']){
			$shippingState = 'PK - '.esc_html(sanitize_text_field($order_data['billing']['state']));
		}else{
			$shippingState = 'N/A';
		}
		if($order_data['billing']['country']){
			$shippingCountry = esc_html(sanitize_text_field($order_data['billing']['country']));
		}else{
			$shippingCountry = 'N/A';
		}
		if(strlen($order_data['billing']['postcode']) > 3){
			$shippingPostCode = esc_html(sanitize_text_field($order_data['billing']['postcode']));
		}else{
			$shippingPostCode = 'N/A';
		}
		if(strncmp($billingPhone, "0092", 4) === 0) {
			$billingPhone = str_replace("0092","0",$billingPhone);
	   	}
		if(strncmp($billingPhone, "+92", 3) === 0) {
			$billingPhone = str_replace("+92","0",$billingPhone);
	   	}
		if(strncmp($billingPhone, "92", 2) === 0) {
			$billingPhone = str_replace("92","0",$billingPhone);
	   	}
		$billingPhone = str_replace("-","",str_replace(" ","",$billingPhone));
		$billingPhone = str_replace("(","",str_replace(")","",$billingPhone));
		$billingPhone = str_replace("+","",$billingPhone);
		if(strlen($billingPhone) == 10 && substr($billingPhone,0,1) == '3'){
			$billingPhone = '0'.$billingPhone;
		}
		if($secretKey==''){
			wc_add_notice( 'Payment gateway is not properly configured, please contact website support.', 'error' );
			return;
		}
		$curl = curl_init();
		curl_setopt_array($curl, array(
		CURLOPT_URL => 'https://invoice.bykea.cash/open/api/invoice/create',
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_ENCODING => '',
		CURLOPT_MAXREDIRS => 10,
		CURLOPT_TIMEOUT => 0,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		CURLOPT_CUSTOMREQUEST => 'POST',
		CURLOPT_POSTFIELDS =>'{
			"name": "'.$billingName.'", 
			"phone": "'.$billingPhone.'", 
			"amount": '.$totalCost.', 
			"amount_type": "PKR", 
			"return_url": "'.home_url().'/wp-json/bcashapi/v1/order?id='.$order_id.'", 
			"cancel_url": "'.home_url().'/wp-json/bcashapi/v1/order/cancel?id='.$order_id.'", 
			"order": "'.$order_id.'",
			"shipping": {
				"name": "'.$billingName.'",
				"address": {
					"line1":"'.$shippingAddress.'",
					"city": "'.$shippingCity.'",
					"state": "'.$shippingState.'",
					"country": "'.$shippingCountry.'",
					"postal_code": "'.$shippingPostCode.'"
				}
			},		 
			"detail": { 
				"notes": "'.$detailsPrefix.'" 
			},
			"from": { 
				"mobile": "'.$mobile_number.'",
				"email": "'.$businessEmail.'",
				"reference": "Order Number: '.$order_id.' - '.$_SERVER['HTTP_HOST'].'",
				"business": "'.$businessName.'"
			},
			"metadata": {
				"source": "plugin",
				"medium": "wordpress 3.2"
			}
		}',
		CURLOPT_HTTPHEADER => array(
			'secret: '.$secretKey,
			'Content-Type: application/json',
			'Accept: application/json'
		),
		));
		$response = json_decode(curl_exec($curl));
		curl_close($curl);
		if($response->subcode=='10001'){
			// Remove cart.
			WC()->cart->empty_cart();
			update_post_meta($order_id, 'invoice_number', esc_html(sanitize_text_field($response->invoice_no)));
			$orderNote = __("Bykea Cash invoice is successfully created. Invoice number is <a href='https://invoice.bykea.cash/receive/openapi/invoice/".$response->invoice_no."' target='_blank'>".$response->invoice_no."</a>");
			$order->add_order_note( $orderNote );
			// Redirect to Bykea Cash.
			if($this->payment_option=="card_payments"){
				$invoiceURL = 'https://invoice.bykea.cash/receive/openapi/card/'.$response->invoice_no;
			}elseif($this->payment_option=="cash_pickup"){
				$curl = curl_init();
				curl_setopt_array($curl, array(
				CURLOPT_URL => 'https://invoice.bykea.cash/api/setInvoiceCODWeb',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_POSTFIELDS =>'{"invoiceId":"'.$response->invoice_no.'"}',
				CURLOPT_HTTPHEADER => array(
					'Content-Type: application/json'
				),
				));
				$cashResponse = json_decode(curl_exec($curl));
				curl_close($curl);
				if($cashResponse->code=='11'){
					$invoiceURL = $cashResponse->url;
				}else{
					$invoiceURL = $response->invoice_url;
				}
			}else{
				$invoiceURL = $response->invoice_url;
			}
			return array(
				'result'   => 'success',
				'redirect' => $invoiceURL,
			);
		}else{
			if($response->messages){
				foreach($response->messages as $key => $value){
					wc_add_notice( $value[0], 'error' );
				}
			}elseif($response->message){
				wc_add_notice( $response->message, 'error' );
			}elseif($response->data->error){
				wc_add_notice( $response->data->error, 'error' );
			}else{
				wc_add_notice( 'There is some error in the payment option. Please try again later or choose another payment option.', 'error' );
			}
			return;
		}
	}
}