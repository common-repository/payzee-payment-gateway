<?php

/**
 * Plugin Name:       Payzee Payment Gateway
 * Description:       Payzee
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Birlesik ödeme
 * Author URI:        https://www.birlesikodeme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if( ! in_array('woocommerce/woocommerce.php', apply_filters('active-plugins', get_option('active_plugins'))))return;

add_filter('woocommerce_payment_gateways', 'payzee_add_gateway_class' );
function payzee_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Payzee_Gateway'; 
	return $gateways;
}
 
add_action( 'plugins_loaded', 'payzee_init_gateway_class' );
function payzee_init_gateway_class() {
 
	class WC_Payzee_Gateway extends WC_Payment_Gateway {
		public function __construct() {
			$plugin_dir = plugin_dir_url(__FILE__);
			$this->id = 'payzee'; 
			$this->title = 'Payzee';
			$this->icon = apply_filters( 'woocommerce_gateway_icon', $plugin_dir.'\assets\icon.png' );
			$this->has_fields = true; 
			$this->method_title = 'Payzee';
			$this->method_description = 'Description of Payze payment gateway'; 
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			
			$this->apikey = $this->testmode ? $this->get_option( 'testapikey' ) : $this->get_option( 'apikey' );

			$this->email = $this->get_option( 'email' );
			$this->loginpassword =  $this->get_option( 'loginpassword' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_api_response', array( $this, 'payment_response' ) );

	}

		public function init_form_fields(){
 
			$this->form_fields = array(
				'apikey' => array(
					'title'       => 'Api key',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'usercode' => array(
					'title'       => 'User Code',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'merchantid' => array(
					'title'       => 'Merchant Id',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'customerid' => array(
					'title'       => 'Customer Id',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'yes',
					'desc_tip'    => true,
				),
				'testapikey' => array(
					'title'       => ' Test Api key',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'email' => array(
					'title'       => ' Mail Adresi',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				),
				'loginpassword' => array(
					'title'       => ' Giris sifresi',
					'type'        => 'text',
					'description' => '',
					'default'     => '',
				)
			);	 
		}

		public function process_payment( $order_id ) {
 
			global $woocommerce;	
			
			$testMode = $this->testmode;
			$testUrlToken = "https://ppgpayment-test.birlesikodeme.com:55002/api/ppg/Securities/authenticationMerchant";
			$prodUrlToken = "https://ppgpayment.birlesikodeme.com:20100/api/ppg/Securities/authenticationMerchant";
			$payzeeLoginUrl = $testMode ? $testUrlToken : $prodUrlToken;

			$order = wc_get_order( $order_id );

			$payzee_url = $testMode ? "https://ppgpayment-test.birlesikodeme.com:20000/api/ppg/Payment/Payment" : "https://ppgpayment.birlesikodeme.com/api/ppg/Payment/Payment";
			
			$argsToken = array(	
				'method' => 'POST',	 
				'timeout'     => '60',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => ['Content-Type' => 'application/json'],
				'cookies'     => array(),
				'data_format' => 'body',
				'body' => json_encode(  array (
					'Password'=>$this->loginpassword,
					'Email'=>$this->email,
					'Lang'=>"TR")
				)		 
			 );

		
			$responseToken = wp_remote_post($payzeeLoginUrl, $argsToken );
			$tokenValue="";

			if( !is_wp_error( $responseToken ) ) {
			$bodyToken = json_decode( $responseToken['body'], true );
			$token = $bodyToken['result']['token'];
			$tokenValue = $token;			 	
			}
			else {
				wc_add_notice(  'Connection payzee error.', 'error' );
				return;
			}
			
			
			$hashkey = $this->testmode ? $this->get_option( 'testapikey' ) : $this->get_option( 'apikey' );

			$rnd = 'wordpress';
			$userCode = $this->get_option( 'usercode' );
			$txnType = 'Auth';
			$customerId =$this->get_option( 'customerid' );
			$totalAmount =wc_float_to_string($order->get_total() * 100);
			$currency = '949';		
			$merchantId = 	$this->get_option( 'merchantid' );
			$url = get_site_url()."/wc-api/response";
			
			$hash = $hashkey . $userCode . $rnd . $txnType . $totalAmount . $customerId . $order_id . $url . $url;
			$hash= mb_convert_encoding($hash, "UTF-16LE");
			$hashString = hash("sha512", $hash);
		 
			$args = array(	
				'method' => 'POST',	 
				'timeout'     => '60',
				'redirection' => '5',
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => ['Content-Type' => 'application/json','authorization' =>'Bearer '.$tokenValue],
				'cookies'     => array(),
				'data_format' => 'body',
				'body' => json_encode(  array (
					'MemberId'=> 1, 
					'MerchantId'=> $merchantId, 
					'CustomerId'=> $customerId,
					'UserCode'=> $userCode,
					'TxnType'=> $txnType, 
					'InstallmentCount'=> '1', 
					'Currency'=> $currency, 
					'OkUrl'=> $url,
					'FailUrl'=> $url, 
					'OrderId'=> $order_id, 
					'TotalAmount' => $totalAmount,
					'Rnd'=> $rnd,
					'Hash'=> $hashString,
					'Description'=> "")
				)
		 
			 );

		
			$response = wp_remote_post($payzee_url, $args );

			if( !is_wp_error( $response ) ) {

			$body = json_decode( $response['body'], true );
			$order->update_status('on-hold', __('Pending', 'woocommerce'));	
						
			return array(
				'result' => 'success',
				'redirect' => $body['url']
			);

			}
			else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			}
		}
	
		public function payment_response() {
			global $woocommerce;	

			$orderId = sanitize_text_field( $_POST['OrderId'] );
			if(!empty($orderId)){			
				$order = wc_get_order( $orderId );	
				$responseCode = sanitize_text_field( $_POST['ResponseCode']);			
					if($responseCode == '00'){
						$order->payment_complete('completed');
						$order->update_status('completed', __('completed', 'woocommerce'));					
						$order-> reduce_order_stock();
						$woocommerce->cart->empty_cart();		
						return wp_redirect($this->get_return_url( $order ));
					}
					else{
						$order-> update_status('Failed', __('Payment has been cancelled.', 'woocommerce'));
						wc_add_notice( !empty($responseCode) ? $responseCode : 'Error' , 'error' );
						return wp_redirect(wc_get_cart_url());
					}				
			}
			else{
				wc_add_notice( 'Bir hata oluştu!' , 'error' );
				return wp_redirect(wc_get_cart_url());
			}		
			exit;						
		}
	}
}
