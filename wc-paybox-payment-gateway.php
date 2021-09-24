<?php
/**
 * Plugin Name: Payment Gateway for Paybox on Woocommerce
 * Description: Payment Gateway Plugin for Paybox by Israel Discount Bank.
 * Author: adiel ben moshe
 * Version: 1.0
 * Text Domain: woo-paybox-payment-gateway
 * WC tested up to: 4.1
*/


// prefix 'idbp'

defined( 'ABSPATH' ) or exit;

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


/**
 * Add the gateway to WC Available Gateways
 */
function idbp_add_paybox_gateway_class( $methods ) {
    $methods[] = 'WC_Paybox_Gateway_idbp'; 
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'idbp_add_paybox_gateway_class' );



/**
 * Add to plugins page link
 */
function idbp_wc_paybox_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paybox_gateway' ) . '">' . __( 'Setting', 'woo-paybox-payment-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'idbp_wc_paybox_plugin_links' );




add_action( 'init', 'wpdocs_load_textdomain' );
  
/**
 * Load plugin textdomain.
 */
function wpdocs_load_textdomain() {
  load_plugin_textdomain( 'woo-paybox-payment-gateway', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' ); 
}
/**
 * Paybox Gateway Class
 *
 * @class 		WC_Paybox_Gatewayv
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		adiel ben moshe
 */
add_action( 'plugins_loaded', 'idbp_wc_paybox_gateway_init', 10 , 1 );

function idbp_wc_paybox_gateway_init() {

	class WC_Paybox_Gateway_idbp extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'paybox_gateway';
            $this->icon               = apply_filters('woocommerce_paybox_icon', plugins_url( 'img/paybox-logo.png', __FILE__ ));
			$this->has_fields         = false;
			$this->method_title       = __( 'Paybox Payment', 'woo-paybox-payment-gateway' );
			$this->method_description = __( 'Payment Gateway for Paybox by Israel Discount Bank. Orders are marked as "on-hold" when received.', 'woo-paybox-payment-gateway' );
			$this->pay_button_id 		= 'paybox-id-button';

 			// Load the settings.
			$this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->receiver_phone  = $this->get_option( 'receiver_phone' );
            $this->paybox_app_link  = $this->get_option( 'paybox_app_link' );

            
            // Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
            
            // Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }
        
        /**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woo-paybox-payment-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Paybox Payment', 'woo-paybox-payment-gateway' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'woo-paybox-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'woo-paybox-payment-gateway' ),
					'default'     => __( 'Paybox Payment', 'woo-paybox-payment-gateway' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'woo-paybox-payment-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-paybox-payment-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'woo-paybox-payment-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woo-paybox-payment-gateway' ),
					'default'     => __( 'Please forward a Paybox payment, use an order number in the Note field when making payment.', 'woo-paybox-payment-gateway' ),
					'desc_tip'    => true,
                ),

                'receiver_phone' => array(
					'title'       => __( 'Receiver Phone Number', 'woo-paybox-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'Payments are sent to this number.', 'woo-paybox-payment-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'paybox_app_link' => array(
					'title'       => __( 'Paybox App Link', 'woo-paybox-payment-gateway' ),
					'type'        => 'text',
					'description' => __( 'A link to the paybox app, which will appear on the thank you page. (The link can be found in the paybox app). The link looks like this: https://payboxapp.page.link/', 'woo-paybox-payment-gateway' ),
					'default'     => '',
					'desc_tip'    => false,
				)



			); 
		}
    
        
        /**
		 * Output for the order received page.
		*/
		public function thankyou_page() {
			echo '<div class="paybox_order_instruction">';
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );								
			}
			if ( $this->receiver_phone ) {						
				echo wpautop ('<img style="vertical-align: middle;" src="'.plugins_url( 'img/paybox-logo.png', __FILE__  ).'" width="70px;" height="70px;">'. wptexturize(__( 'Phone Number:', 'woo-paybox-payment-gateway' ). ' <a href="tel:'.$this->receiver_phone.'">'. $this->receiver_phone. '</a>' ) );
			}
			if ( $this->paybox_app_link ) {		
				echo '<a target="_blank" href="' . $this->paybox_app_link . '">'. __('Click here to go to the paybox app', 'woo-paybox-payment-gateway').'</a><br>';					
			}
			echo '</div>';
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

				if ( $this->receiver_phone ) {						
					echo wpautop ('<img style="vertical-align: middle;" src="'.plugins_url( 'img/paybox-logo.png', __FILE__  ).'" width="70px;" height="70px;">'. wptexturize(__( 'Phone Number:', 'woo-paybox-payment-gateway' ). ' <a href="tel:'.$this->receiver_phone.'">'. $this->receiver_phone. '</a>' ) );
				}
				if ( $this->paybox_app_link ) {		
					echo '<a target="_blank" href="' . $this->paybox_app_link . '">'. __('Click here to go to the paybox app', 'woo-paybox-payment-gateway').'</a><br>';					
				}

			}

        }
        

        	/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting Paybox payment', 'woo-paybox-payment-gateway' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
        }

    } // END CLASS: WC_Paybox_Gateway CLASS
} // END FUNCTION: wc_paybox_gateway_init



    // Put your plugin code here
}
	