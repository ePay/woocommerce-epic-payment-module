<?php
/**
 * Plugin Name: ePay Payment Solutions - EPIC
 * Plugin URI: https://docs.epay.eu/plugins/woocommerce
 * Description: ePay Payment EPIC gateway for WooCommerce
 * Version: 7.0.1
 * Author: ePay Payment Solutions
 * Author URI: https://www.epay.dk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: epay-payment-solutions-epic
 * Requires Plugins: woocommerce
 *
 * @author ePay Payment Solutions
 * @package epay_epic_payment
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

define( 'EPAYEPIC_PATH_FILE',  __FILE__ );
define( 'EPAYEPIC_PATH', dirname( __FILE__ ) );
define( 'EPAYEPIC_VERSION', '7.0.1' );

add_action( 'plugins_loaded', 'init_epay_epic_payment', 0 );

/**
 * Initilize ePay Payment
 *
 * @return void
 */
function init_epay_epic_payment() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include( EPAYEPIC_PATH . '/lib/epay-payment-soap.php' );
	include( EPAYEPIC_PATH . '/lib/epay-payment-api.php' );
	include( EPAYEPIC_PATH . '/lib/epay-payment-helper.php' );
	include( EPAYEPIC_PATH . '/lib/epay-payment-log.php' );

	/**
	 * Gateway class
	 **/
	class Epay_EPIC_Payment extends WC_Payment_Gateway {

        public $enabled;
        public $title;
        public $description;
        private $merchant;
        private $windowid;
        private $md5key;
        private $instantcapture;
        private $group;
        private $authmail;
        private $ownreceipt;
        private $remoteinterface;
        private $remotepassword;
        private $enableinvoice;
        private $addfeetoorder;
        private $enablemobilepaymentwindow;
        private $roundingmode;
        private $captureonstatuscomplete;
        private $override_subscription_need_payment;
        private $rolecapturerefunddelete;
        private $orderstatusaftercancelledpayment;
        private $ageverificationmode;
        protected $paymenttype;
        protected $paymentcollection;
        private $apikey;
        private $posid;

		/**
		 * Singleton instance
		 *
		 * @var Epay_EPIC_Payment
		 */
		private static $_instance;

		/**
		 * @param Epay_EPIC_Payment_Log
		 */
		private $_boclassic_log;

		/**
		 * get_instance
		 *
		 * Returns a new instance of self, if it does not already exist.
		 *
		 * @access public
		 * @static
		 * @return Epay_EPIC_Payment
		 */
		public static function get_instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Construct
		 */
		public function __construct() {
			$this->id                 = 'epay_epic_dk';
			$this->method_title       = 'ePay Payment Solutions - EPIC';
			$this->method_description = 'ePay Payment Solutions - EPIC - Enables easy and secure payments on your shop';
			$this->has_fields         = true;
            $this->paymenttype        = false;
            $this->paymentcollection  = false;
			$this->icon               = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/epay-logo.svg';


			$this->supports = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
                
                // 'subscription_payment_method_change',

				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
				'multiple_subscriptions'
			);

			// Init the ePay Payment logger
			$this->_boclassic_log = new Epay_EPIC_Payment_Log();

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Initilize ePay Payment Settings
			$this->init_epay_payment_settings();

			if ( $this->remoteinterface === 'yes' ) {
				$this->supports = array_merge( $this->supports, array( 'refunds' ) );
			}
			// Allow store managers to manually set ePay Payment Solutions as the payment method on a subscription
			add_filter( 'woocommerce_subscription_payment_meta', array(
				$this,
				'add_subscription_payment_meta'
			), 10, 2 );
		}

		/**
		 * Initilize ePay Payment Settings
		 */
		public function init_epay_payment_settings() {
			// Define user set variables
			$this->enabled                            = array_key_exists( 'enabled', $this->settings ) ? $this->settings['enabled'] : 'yes';
			$this->title                              = array_key_exists( 'title', $this->settings ) ? $this->settings['title'] : 'ePay Payment Solutions';
			$this->description                        = array_key_exists( 'description', $this->settings ) ? $this->settings['description'] : 'Pay using ePay Payment Solutions';
			$this->merchant                           = array_key_exists( 'merchant', $this->settings ) ? $this->settings['merchant'] : '';
			$this->windowid                           = array_key_exists( 'windowid', $this->settings ) ? $this->settings['windowid'] : '1';
			$this->md5key                             = array_key_exists( 'md5key', $this->settings ) ? $this->settings['md5key'] : '';
			$this->instantcapture                     = array_key_exists( 'instantcapture', $this->settings ) ? $this->settings['instantcapture'] : 'no';
			$this->group                              = array_key_exists( 'group', $this->settings ) ? $this->settings['group'] : '';
			$this->authmail                           = array_key_exists( 'authmail', $this->settings ) ? $this->settings['authmail'] : '';
			$this->ownreceipt                         = array_key_exists( 'ownreceipt', $this->settings ) ? $this->settings['ownreceipt'] : 'no';
			$this->remoteinterface                    = array_key_exists( 'remoteinterface', $this->settings ) ? $this->settings['remoteinterface'] : 'no';
			$this->remotepassword                     = array_key_exists( 'remotepassword', $this->settings ) ? $this->settings['remotepassword'] : '';
			$this->enableinvoice                      = array_key_exists( 'enableinvoice', $this->settings ) ? $this->settings['enableinvoice'] : 'no';
			$this->addfeetoorder                      = array_key_exists( 'addfeetoorder', $this->settings ) ? $this->settings['addfeetoorder'] : 'no';
			$this->enablemobilepaymentwindow          = array_key_exists( 'enablemobilepaymentwindow', $this->settings ) ? $this->settings['enablemobilepaymentwindow'] : 'yes';
			$this->roundingmode                       = array_key_exists( 'roundingmode', $this->settings ) ? $this->settings['roundingmode'] : Epay_EPIC_Payment_Helper::ROUND_DEFAULT;
			$this->captureonstatuscomplete            = array_key_exists( 'captureonstatuscomplete', $this->settings ) ? $this->settings['captureonstatuscomplete'] : 'no';
			$this->override_subscription_need_payment = array_key_exists( 'overridesubscriptionneedpayment', $this->settings ) ? $this->settings['overridesubscriptionneedpayment'] : 'yes';
			$this->rolecapturerefunddelete            = array_key_exists( 'rolecapturerefunddelete', $this->settings ) ? $this->settings['rolecapturerefunddelete'] : 'shop_manager';
            $this->orderstatusaftercancelledpayment   = array_key_exists( 'orderstatusaftercancelledpayment', $this->settings ) ? $this->settings['orderstatusaftercancelledpayment'] : Epay_EPIC_Payment_Helper::STATUS_CANCELLED;
            $this->ageverificationmode                = array_key_exists( 'ageverificationmode', $this->settings ) ? $this->settings['ageverificationmode'] : Epay_EPIC_Payment_Helper::AGEVERIFICATION_DISABLED;
			$this->paymentcollection                  = array_key_exists( 'paymentcollection', $this->settings ) ? $this->settings['paymentcollection'] : '0';
			$this->apikey                             = array_key_exists( 'apikey', $this->settings ) ? $this->settings['apikey'] : '';
			$this->posid                              = array_key_exists( 'posid', $this->settings ) ? $this->settings['posid'] : '';
		}
    
        public function get_settings($key)
        {
            if(isset($this->settings[$key]))
            {
                return $this->settings[$key];
            }
        }
   
		/**
		 * Init hooks
		 */
		public function init_hooks() {

			// Actions
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array(
				$this,
				'epay_payment_callback'
			) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			if ( is_admin() ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );

				if ( $this->remoteinterface == 'yes' ) {
                    add_action( 'add_meta_boxes', array( $this, 'epay_epic_payment_meta_boxes' ) );
					add_action( 'wp_before_admin_bar_render', array( $this, 'epay_epic_payment_actions' ) );
					add_action( 'admin_notices', array( $this, 'epay_epic_payment_admin_notices' ) );
				}
			}
			if ( $this->remoteinterface == 'yes' ) {
				if ( $this->captureonstatuscomplete === 'yes' ) {
					add_action( 'woocommerce_order_status_completed', array(
						$this,
						'epay_payment_order_status_completed'
					) );
				}
			}
			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
				// Subscriptions
				add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
					$this,
					'scheduled_subscription_payment'
				), 10, 2 );
				add_action( 'woocommerce_subscription_cancelled_' . $this->id, array(
					$this,
					'subscription_cancellation'
				) );

				if ( ! is_admin() && $this->override_subscription_need_payment === 'yes' ) {
					// Maybe order don't need payment because lock.
					add_filter( 'woocommerce_order_needs_payment', array(
						$this,
						'maybe_override_needs_payment'
					), 10, 2 );
				}
			}
			// Register styles!
			add_action( 'admin_enqueue_scripts', array(
				$this,
				'enqueue_wc_epay_payment_admin_styles_and_scripts'
			) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wc_epay_payment_front_styles' ) );

		}

		/**
		 * Show messages in the Administration
		 */
		public function epay_epic_payment_admin_notices() {
			Epay_EPIC_Payment_Helper::echo_admin_notices();
		}

		/**
		 * Enqueue Admin Styles and Scripts
		 */
		public function enqueue_wc_epay_payment_admin_styles_and_scripts() {
			wp_register_style( 'epay_payment_admin_style', plugins_url( 'style/epay-payment-admin.css', __FILE__ ), array(), 1 );
			wp_enqueue_style( 'epay_payment_admin_style' );

			// Fix for load of Jquery time!
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'epay_payment_admin', plugins_url( 'scripts/epay-payment-admin.js', __FILE__ ), array(), 1, false );
		}

		/**
		 * Enqueue Frontend Styles and Scripts
		 */
		public function enqueue_wc_epay_payment_front_styles() {
			wp_register_style( 'epay_epic_payment_front_style', plugins_url( 'style/epay-epic-payment-front.css', __FILE__ ), array(), 1 );
			wp_enqueue_style( 'epay_epic_payment_front_style' );
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields() {
			$roles = wp_roles()->roles;
			unset( $roles["administrator"] ); // Administrator will always have access so we do not include this role here.
			foreach ( $roles as $role => $details ) {
				$roles_options[ $role ] = translate_user_role( $details['name'] );
			}
			$this->form_fields = array(
				'enabled'                         => array(
					'title'   => 'Activate module',
					'type'    => 'checkbox',
					'label'   => 'Enable ePay EPIC Payment Solutions as a payment option.',
					'default' => 'yes'
				),
				'title'                           => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'The title of the payment method displayed to the customers.',
                    'desc_tip'    => true,
					'default'     => 'ePay Payment Solutions',
                    'css'         => 'width: 450px;',
				),
				'description'                     => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'The description of the payment method displayed to the customers.',
                    'desc_tip'    => true,
					'default'     => 'Pay using ePay Payment Solutions',
                    'css'         => 'width: 450px;',
				),
				'apikey'                           => array(
					'title'       => 'API Key',
					'type'        => 'text',
					'description' => 'Find the API key by logging into ePay\'s Backoffice under Settings -> Developers.',
                    'desc_tip'    => true,
					'default'     => '',
                    'css'         => 'width: 450px;',
				),
				'posid'                           => array(
					'title'       => 'PointOfSale ID',
					'type'        => 'text',
					'description' => 'Find the PointOfSale ID by logging into ePay\'s Backoffice under Settings -> Points of Sale.',
                    'desc_tip'    => true,
					'default'     => '',
                    'css'         => 'width: 450px;',
				),
                'icons'                      => array(
                    'title'             => 'Credit card icons',
                    'type'              => 'multiselect',
                    'description'       => 'Select the card icons you would like displayed alongside the ePay payment option in your shop.',
                    'desc_tip'          => true,
                    'class'             => 'wc-enhanced-select',
                    'css'               => 'width: 450px;',
                    'custom_attributes' => array(
                        'data-placeholder' => 'Select icons',
                    ),
                    'default'           => '',
                    'options'           => self::get_card_icon_options(),
                ),
				'instantcapture'                  => array(
					'title'       => 'Instant capture',
					'type'        => 'checkbox',
					'description' => 'Capture the payments at the same time they are authorized. In some countries, this is only permitted if the consumer receives the products right away Ex. digital products.',
                    'desc_tip'    => true,
					'default'     => 'no'
				),
				'remoteinterface'                 => array(
					'title'       => 'Remote interface',
					'type'        => 'checkbox',
					'description' => 'Use remote interface',
                    'desc_tip'    => true,
					'default'     => 'no'
				),
				'captureonstatuscomplete'         => array(
					'title'       => 'Capture on status Completed',
					'type'        => 'checkbox',
					'description' => 'When this is enabled the full payment will be captured when the order status changes to Completed',
                    'desc_tip'    => true,
					'default'     => 'no'
				),
				'roundingmode'                    => array(
					'title'       => 'Rounding mode',
					'type'        => 'select',
					'description' => 'Please select how you want the rounding of the amount sendt to the payment system',
                    'desc_tip'    => true,
					'options'     => array(
						Epay_EPIC_Payment_Helper::ROUND_DEFAULT => 'Default',
						Epay_EPIC_Payment_Helper::ROUND_UP      => 'Always up',
						Epay_EPIC_Payment_Helper::ROUND_DOWN    => 'Always down'
					),
					'default'     => 'normal'
				),
				'orderstatusaftercancelledpayment'                    => array(
					'title'       => 'Status after cancel payment',
					'type'        => 'select',
					'description' => 'Please select order status after payment cancelled',
                    'desc_tip'    => true,
					'options'     => array(
						Epay_EPIC_Payment_Helper::STATUS_CANCELLED      => 'Cancelled',
						Epay_EPIC_Payment_Helper::STATUS_PENDING        => 'Pending payment'
					)
				),
				'overridesubscriptionneedpayment' => array(
					'title'       => 'Subscription payment override',
					'type'        => 'checkbox',
					'description' => 'When this is enabled it is possible to use coupons for x free payments on a subscription',
                    'desc_tip'    => true,
					'default'     => 'yes'
				),
				'rolecapturerefunddelete'         => array(
					'title'       => 'User role for access to capture/refund/delete',
					'type'        => 'select',
					'description' => 'Please select user role for access to capture/refund/delete (role administrator will always have access). The role also of course need to have access to view orders. ',
                    'desc_tip'    => true,
					'options'     => $roles_options,
					'label'       => 'User role',
					'default'     => 'shop_manager'
                ),
                'ageverificationmode'             => array(
					'title'       => 'Ageverification mode',
					'type'        => 'select',
					'description' => 'Activate Ageverification',
                    'desc_tip'    => true,
					'options'     => array(
                        Epay_EPIC_Payment_Helper::AGEVERIFICATION_DISABLED => 'Disabled',
                        Epay_EPIC_Payment_Helper::AGEVERIFICATION_ENABLED_ALL => 'Enabled on all orders',
                        Epay_EPIC_Payment_Helper::AGEVERIFICATION_ENABLED_DK => 'Enabled on DK orders'
					)
				)
			);
		}

		/**
		 * Admin Panel Options
		 */
		public function admin_options() {
			$version = EPAYEPIC_VERSION;

			$html = "<h3>{$this->method_title}  v{$version}</h3>";
			$html .= Epay_EPIC_Payment_Helper::create_admin_debug_section();
			$html .= '<h3 class="wc-settings-sub-title">Module Configuration</h3>';

			if ( class_exists( 'sitepress' ) ) {
				$html .= '<div class="form-table">
					<h2>You have WPML activated.</h2>
					If you need to configure another merchant number for another language translate them under
					<a href="admin.php?page=wpml-string-translation/menu/string-translation.php&context=admin_texts_woocommerce_epay_dk_settings" class="current" aria-currents="page">String Translation</a>
					</br>
					Subscriptions are currently only supported for the default merchant number.
					</br>	
</div>';
			}

			$html .= '<table class="form-table">';

			// Generate the HTML For the settings form.!
			$html .= $this->generate_settings_html( array(), false );
			$html .= '</table>';

			echo ent2ncr( $html );
		}

		/**
		 * When using a coupon for x free payments after the initial trial on a subscription then this will set the payment requirement to true
		 *
		 * @param bool $needs_payment
		 * @param WC_Order $order
		 *
		 * @return bool
		 */
		public function maybe_override_needs_payment( $needs_payment, $order ) {

			if ( ! $needs_payment && $this->id === $order->get_payment_method() && Epay_EPIC_Payment_Helper::get_order_contains_subscription( $order, array( 'parent' ) ) ) {
				$needs_payment = true;
			}

			return $needs_payment;
		}

		/**
		 * Capture the payment on order status completed
		 *
		 * @param mixed $order_id
		 */
		public function epay_payment_order_status_completed( $order_id ) {
			if ( ! $this->module_check( $order_id ) ) {
				return;
			}

			$order          = wc_get_order( $order_id );
			$order_total    = $order->get_total();
			$capture_result = $this->epay_payment_capture_payment( $order_id, $order_total, '' );

			if ( is_wp_error( $capture_result ) ) {
				$message = $capture_result->get_error_message( 'epay_payment_error' );
				$this->_boclassic_log->add( $message );
				Epay_EPIC_Payment_Helper::add_admin_notices( Epay_EPIC_Payment_Helper::ERROR, $message );
			} else {
				$message = sprintf( __( 'The Capture action was a success for order %s', 'epay-payment-solutions-epic' ), $order_id );
				Epay_EPIC_Payment_Helper::add_admin_notices( Epay_EPIC_Payment_Helper::SUCCESS, $message );
			}
		}


		/**
		 * There are no payment fields for epay, but we want to show the description if set.
         **/
		public function payment_fields() {
			$text_replace            = wptexturize( $this->description );
			$paymentFieldDescription = wpautop( $text_replace );
            
            /*
			$paymentLogos            = '<div id="boclassic_card_logos">';

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
			} else {
				$merchant_number = $this->merchant;
			}

			if ( $merchant_number ) {
				$paymentLogos .= '<script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber=' . $merchant_number . '&direction=2&padding=2&rows=1&showdivs=0&logo=0&cardwidth=40&divid=boclassic_card_logos"></script>';
			}

            $paymentLogos            .= '</div>';
			$paymentFieldDescription .= $paymentLogos;
            */

			echo esc_html($paymentFieldDescription);
        }

		/**
		 * Create invoice lines
		 *
		 * @param WC_Order $order
		 * @param int $minorunits
		 *
		 * @return string
		 * */
		protected function create_invoice( $order, $minorunits ) {
			if ( $this->enableinvoice == 'yes' ) {

				$invoice['customer']['emailaddress'] = $order->get_billing_email();
				$invoice['customer']['firstname']    = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_first_name() );
				$invoice['customer']['lastname']     = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_last_name() );
				$invoice['customer']['address']      = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_address_1() );
				$invoice['customer']['zip']          = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_postcode() );
				$invoice['customer']['city']         = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_city() );
				$invoice['customer']['country']      = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_billing_country() );

				$invoice['shippingaddress']['firstname'] = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_first_name() );
				$invoice['shippingaddress']['lastname']  = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_last_name() );
				$invoice['shippingaddress']['address']   = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_address_1() );
				$invoice['shippingaddress']['zip']       = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_postcode() );
				$invoice['shippingaddress']['city']      = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_city() );
				$invoice['shippingaddress']['country']   = Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $order->get_shipping_country() );

				$invoice['lines'] = $this->create_invoice_order_lines( $order, $minorunits );

				return wp_json_encode( $invoice, JSON_UNESCAPED_UNICODE );
			} else {
				return '';
			}
		}

		/**
		 * Create ePay Payment orderlines for invoice
		 *
		 * @param WC_Order $order
		 *
		 * @return array
		 */
		protected function create_invoice_order_lines( $order, $minorunits ) {
			$items               = $order->get_items();
			$invoice_order_lines = array();
			foreach ( $items as $item ) {
				$item_total = $order->get_line_total( $item, false, true );
				if ( $item['qty'] > 1 ) {
					$item_price = $item_total / $item['qty'];
				} else {
					$item_price = $item_total;
				}
				$item_vat_amount       = $order->get_line_tax( $item );
				$invoice_order_lines[] = array(
					'id'          => $item['product_id'],
					'description' => Epay_EPIC_Payment_Helper::json_value_remove_special_characters( $item['name'] ),
					'quantity'    => $item['qty'],
					'price'       => Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $item_price, $minorunits, $this->roundingmode ),
					'vat'         => $item_vat_amount > 0 ? ( $item_vat_amount / $item_total ) * 100 : 0,
				);
			}
			$shipping_methods = $order->get_shipping_methods();
			if ( $shipping_methods && count( $shipping_methods ) !== 0 ) {
				$shipping_total        = $order->get_shipping_total();
				$shipping_tax          = (float) $order->get_shipping_tax();
				$shipping_method       = reset( $shipping_methods );
				$invoice_order_lines[] = array(
					'id'          => $shipping_method->get_method_id(),
					'description' => $shipping_method->get_method_title(),
					'quantity'    => 1,
					'price'       => Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $shipping_total, $minorunits, $this->roundingmode ),
					'vat'         => $shipping_tax > 0 ? ( $shipping_tax / $shipping_total ) * 100 : 0,
				);
			}

			return $invoice_order_lines;
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 *
		 * @return string[]
		 */
		public function process_payment( $order_id ) {
			// $order = wc_get_order( $order_id );

			return array(
				'result'   => 'success',
				'redirect' => $this->createPaymentRequestLink($order_id),
			);
		}

		/**
		 * Process Refund
		 *
		 * @param int $order_id
		 * @param float|null $amount
		 * @param string $reason
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$user = wp_get_current_user();
			if ( in_array( $this->rolecapturerefunddelete, (array) $user->roles ) || in_array( 'administrator', (array) $user->roles ) ) {
				//The user has the role required for  "Capture, Refund, Delete"  and can perform those actions.
			} else {
				//The user can only view the data.
				return new WP_Error( 'notpermitted', __( "Your user role is not allowed to refund via Epay Payment", "epay-payment-solutions-epic" ) );
			}

			if ( ! isset( $amount ) ) {
				return true;
			}
			if ( $amount < 1 ) {
				return new WP_Error( 'toolow', __( "You have to refund a higher amount than 0.", "epay-payment-solutions-epic" ) );
			}

			$refund_result = $this->epay_payment_refund_payment( $order_id, $amount, '' );
			if ( is_wp_error( $refund_result ) ) {
				return $refund_result;
			} else {
				$message = __( "The Refund action was a success for order {$order_id}", 'epay-payment-solutions-epic' );
				Epay_EPIC_Payment_Helper::add_admin_notices( Epay_EPIC_Payment_Helper::SUCCESS, $message );
			}

			return true;
		}

		/**
		 * Handle scheduled subscription payments
		 *
		 * @param mixed $amount_to_charge
		 * @param WC_Order $renewal_order
		 */
		public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
			$subscription     = Epay_EPIC_Payment_Helper::get_subscriptions_for_renewal_order( $renewal_order );
			$result           = $this->process_subscription_payment( $amount_to_charge, $renewal_order, $subscription );
			$renewal_order_id = $renewal_order->get_id();

			// Remove the ePay Payment subscription id copyid from the subscription

			$renewal_order->delete_meta_data( Epay_EPIC_Payment_Helper::EPAY_PAYMENT_SUBSCRIPTION_ID );
			$renewal_order->save();
			if ( is_wp_error( $result ) ) {
				$message = sprintf( __( 'ePay Payment Solutions Subscription could not be authorized for renewal order # %s - %s', 'epay-payment-solutions-epic' ), $renewal_order_id, $result->get_error_message( 'epay_payment_error' ) );
				$renewal_order->update_status( 'failed', $message );
				$this->_boclassic_log->add( $message );
			}
		}

		/**
		 * Process a subscription renewal
		 *
		 * @param mixed $amount
		 * @param WC_Order $renewal_order
		 * @param WC_Subscription $subscription
		 */
		public function process_subscription_payment( $amount, $renewal_order, $subscription ) {
			try {
				$epay_subscription_id = Epay_EPIC_Payment_Helper::get_epay_payment_subscription_id( $subscription );
				if ( strlen( $epay_subscription_id ) === 0 ) {
					return new WP_Error( 'epay_payment_error', __( 'ePay Payment Solutions Subscription id was not found', 'epay-payment-solutions-epic' ) );
				}

				$order_currency   = $renewal_order->get_currency();
				$minorunits       = Epay_EPIC_Payment_Helper::get_currency_minorunits( $order_currency );
				$amount           = Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
				$renewal_order_id = $renewal_order->get_id();

				$webservice         = new Epay_EPIC_Payment_Soap( $this->remotepassword, true, $this->apikey, $this->posid );
				$authorize_response = $webservice->authorize( $this->merchant, $epay_subscription_id, $renewal_order_id, $amount, Epay_EPIC_Payment_Helper::get_iso_code( $order_currency ), (bool) Epay_EPIC_Payment_Helper::yes_no_to_int( $this->instantcapture ), $this->group, $this->authmail );
				if ( $authorize_response->authorizeResult === false ) {
					$error_message = '';
					if ( $authorize_response->epayresponse != '-1' ) {
						$error_message = $webservice->get_epay_error( $this->merchant, $authorize_response->epayresponse );
					} elseif ( $authorize_response->pbsresponse != '-1' ) {
						$error_message = $webservice->get_pbs_error( $this->merchant, $authorize_response->pbsresponse );
					}

					return new WP_Error( 'epay_payment_error', $error_message );
				}
                
                if(isset($authorize_response->transactionid) && !empty($authorize_response->transactionid))
                {
                    $renewal_order->payment_complete( $authorize_response->transactionid );
                }

				// Add order note
				$message = sprintf( __( 'ePay Payment Solutions Subscription was authorized for renewal order %s with transaction id %s', 'epay-payment-solutions-epic' ), $renewal_order_id, $authorize_response->transactionid );
				$renewal_order->add_order_note( $message );
				$subscription->add_order_note( $message );

				return true;
			} catch ( Exception $ex ) {
				return new WP_Error( 'epay_payment_error', $ex->getMessage() );
			}
		}

		/**
		 * Cancel a subscription
		 *
		 * @param WC_Subscription $subscription
		 * @param bool $force_delete
		 */
		public function subscription_cancellation( $subscription, $force_delete = false ) {
			if ( 'cancelled' === $subscription->get_status() || $force_delete ) {
				$result = $this->process_subscription_cancellation( $subscription );

				if ( is_wp_error( $result ) ) {
					$message = sprintf( __( 'ePay Payment Solutions Subscription could not be canceled - %s', 'epay-payment-solutions-epic' ), $result->get_error_message( 'epay_payment_error' ) );
					$subscription->add_order_note( $message );
					$this->_boclassic_log->add( $message );
				}
			}
		}

		/**
		 * Process canceling of a subscription
		 *
		 * @param WC_Subscription $subscription
		 */
		protected function process_subscription_cancellation( $subscription ) {
			try {
				if ( Epay_EPIC_Payment_Helper::order_is_subscription( $subscription ) ) {
					$epay_subscription_id = Epay_EPIC_Payment_Helper::get_epay_payment_subscription_id( $subscription );
					if ( strlen( $epay_subscription_id ) === 0 ) {
						$order_note = __( 'ePay Payment Solutions Subscription ID was not found', 'epay-payment-solutions-epic' );

						return new WP_Error( 'epay_payment_error', $order_note );
					}

					$webservice                   = new Epay_EPIC_Payment_Soap( $this->remotepassword, true, $this->apikey, $this->posid );
					$delete_subscription_response = $webservice->delete_subscription( $this->merchant, $epay_subscription_id );
					if ( $delete_subscription_response->deletesubscriptionResult === true ) {
						$subscription->add_order_note( sprintf( __( 'Subscription successfully Cancelled. - ePay Payment Solutions Subscription Id: %s', 'epay-payment-solutions-epic' ), $epay_subscription_id ) );
					} else {
						$order_note = sprintf( __( 'ePay Payment Solutions Subscription Id: %s', 'epay-payment-solutions-epic' ), $epay_subscription_id );
						if ( $delete_subscription_response->epayresponse != '-1' ) {
							$order_note .= ' - ' . $webservice->get_epay_error( $this->merchant, $delete_subscription_response->epayresponse );
						}

						return new WP_Error( 'epay_payment_error', $order_note );
					}
				}

				return true;
			} catch ( Exception $ex ) {
				return new WP_Error( 'epay_payment_error', $ex->getMessage() );
			}
		}

		/**
		 * receipt_page
		 **/
		// public function receipt_page( $order_id ) {
		public function createPaymentRequestLink( $order_id ) {
			$order                               = wc_get_order( $order_id );
			$is_request_to_change_payment_method = Epay_EPIC_Payment_Helper::order_is_subscription( $order );

			$order_currency = $order->get_currency();
			$order_total    = $order->get_total();
			$minorunits     = Epay_EPIC_Payment_Helper::get_currency_minorunits( $order_currency );

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
			} else {
				$merchant_number = $this->merchant;
			}

			$epay_args = array(
				'encoding'       => 'UTF-8',
				'cms'            => Epay_EPIC_Payment_Helper::get_module_header_info(),
				'windowstate'    => "3",
				'mobile'         => Epay_EPIC_Payment_Helper::yes_no_to_int( $this->enablemobilepaymentwindow ),
				'merchantnumber' => $merchant_number,
				'windowid'       => $this->windowid,
				'currency'       => $order_currency,
				'amount'         => Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $order_total, $minorunits, $this->roundingmode ),
				'orderid'        => $this->clean_order_number( $order->get_order_number() ),
				'accepturl'      => Epay_EPIC_Payment_Helper::get_accept_url( $order ),
				'cancelurl'      => Epay_EPIC_Payment_Helper::get_decline_url( $order, $this->orderstatusaftercancelledpayment),
				'callbackurl'    => apply_filters( 'epay_payment_callback_url', Epay_EPIC_Payment_Helper::get_epay_payment_callback_url( $order_id ) ),
				'mailreceipt'    => $this->authmail,
				'instantcapture' => Epay_EPIC_Payment_Helper::yes_no_to_int( $this->instantcapture ),
				'group'          => $this->group,
				'language'       => Epay_EPIC_Payment_Helper::get_language_code( get_locale() ),
				'ownreceipt'     => Epay_EPIC_Payment_Helper::yes_no_to_int( $this->ownreceipt ),
				'timeout'        => '60'
			);

            if(isset($this->paymenttype) && !empty($this->paymenttype)) {
                $epay_args['paymenttype'] = $this->paymenttype;
            } elseif(isset($this->paymentcollection) && !empty($this->paymentcollection)) {
                $epay_args['paymentcollection'] = $this->paymentcollection;
                $epay_args['lockpaymentcollection'] = 1;
            }

            if($this->ageverificationmode == Epay_EPIC_Payment_Helper::AGEVERIFICATION_ENABLED_ALL || ($this->ageverificationmode == Epay_EPIC_Payment_Helper::AGEVERIFICATION_ENABLED_DK && $order->get_shipping_country() == "DK"))
            {
                $minimumuserage = Epay_EPIC_Payment_Helper::get_minimumuserage($order);
                $countryId = false;
                
                if($minimumuserage > 0)
                {
                    $epay_args['minimumuserage'] = $minimumuserage;
                    $epay_args['ageverificationcountry'] = $order->get_shipping_country();
                }
                
            }
        
            if ( ! $is_request_to_change_payment_method ) {
                $epay_args['invoice'] = $this->create_invoice( $order, $minorunits );
            }

			if ( Epay_EPIC_Payment_Helper::woocommerce_subscription_plugin_is_active() && ( Epay_EPIC_Payment_Helper::order_contains_subscription( $order )) ) {
				$epay_args['subscription'] = 1;
			}
            elseif($is_request_to_change_payment_method)
            {
				$epay_args['subscription'] = 2;
                
                $subscription = Epay_EPIC_Payment_Helper::get_subscriptions_for_order($order->parent_id)[$order_id];
                $epay_args['subscriptionid'] =  Epay_EPIC_Payment_Helper::get_epay_payment_subscription_id($subscription);
            }

			if ( class_exists( 'sitepress' ) ) {
				$md5_key = Epay_EPIC_Payment_Helper::getWPMLOptionValue( 'md5key', Epay_EPIC_Payment_Helper::getWPMLOrderLanguage( $order ), $this->md5key );
			} else {
				$md5_key = $this->md5key;
			}

			if ( strlen( $md5_key ) > 0 ) {
				$hash = '';
				foreach ( $epay_args as $value ) {
					$hash .= $value;
				}
				$epay_args['hash'] = md5( $hash . $md5_key );
			}
			$epay_args      = apply_filters( 'epay_payment_epay_args', $epay_args, $order_id );
			$epay_args_json = wp_json_encode( $epay_args );

		    // $payment_link   = Epay_EPIC_Payment_Helper::create_epay_payment_payment_html( $epay_args_json, $this->apikey, $this->posid );

            $epay_epic_payment_api = new epay_epic_payment_api($this->apikey, $this->posid);
            $request = $epay_epic_payment_api->createPaymentRequest($epay_args_json);
            $request_data = json_decode($request);

            $paymentWindowUrl = $request_data->paymentWindowUrl;
            return $paymentWindowUrl;
		}

		/**
		 * Removes any special charactors from the order number
		 *
		 * @param string $order_number
		 *
		 * @return string
		 */
		protected function clean_order_number( $order_number ) {
			return preg_replace( '/[^a-z\d ]/i', "", $order_number );
		}

		/**
		 * Check for epay IPN Response
		 **/
		public function epay_payment_callback() {

            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            $payment_type_map = array ( "Dankort"=>1, "Visa"=>3, "Mastercard"=>4, "JCB"=>6, "Maestro"=>7, "Diners Club"=>8, "American Express"=>9);

            $params['txnid'] = $data['transaction']['id'];
            $params['wcorderid'] = $data['transaction']['reference'];
            if(isset($data['subscription']['id']))
            {
                $params['subscriptionid'] = $data['subscription']['id'];
            }
            $params['paymenttype'] = $payment_type_map[$data['transaction']['paymentMethodSubType']];
            $params['txnfee'] = 0; // $data['transaction'][''];

            $this->md5key = null;


			$message       = '';
			$order         = null;
			$response_code = 400;
			try {
				$is_valid_call = Epay_EPIC_Payment_Helper::validate_epay_payment_callback_params( $params, $this->md5key, $order, $message );
				if ( $is_valid_call ) {
					$message       = $this->process_epay_payment_callback( $order, $params );
					$response_code = 200;
				} else {
					if ( ! empty( $order ) ) {
						$order->update_status( 'failed', $message );
					}
					$this->_boclassic_log->separator();
					$this->_boclassic_log->add( "Callback failed - {$message} - GET params:" );
					$this->_boclassic_log->add( $params );
					$this->_boclassic_log->separator();
				}
			} catch ( Exception $ex ) {
				$message       = 'Callback failed Reason: ' . $ex->getMessage();
				$response_code = 500;
				$this->_boclassic_log->separator();
				$this->_boclassic_log->add( "Callback failed - {$message} - GET params:" );
				$this->_boclassic_log->add( $params );
				$this->_boclassic_log->separator();
			}

			$header = 'X-EPay-System: ' . Epay_EPIC_Payment_Helper::get_module_header_info();
			header( $header, true, $response_code );
			die( esc_html($message) );

		}

		/**
		 * Process the ePay Callback
		 *
		 * @param WC_Order $order
		 * @param mixed $epay_transaction
		 */
		protected function process_epay_payment_callback( $order, $params ) {
			try {
				$type                    = '';
				$epay_subscription_id = array_key_exists( 'subscriptionid', $params ) ? $params['subscriptionid'] : null;
				if ( ( Epay_EPIC_Payment_Helper::order_contains_subscription( $order ) || Epay_EPIC_Payment_Helper::order_is_subscription( $order ) ) && isset( $epay_subscription_id ) ) {
					$action = $this->process_subscription( $order, $params );
					$type   = "Subscription {$action}";
				} else {
					$action = $this->process_standard_payments( $order, $params );
					$type   = "Standard Payment {$action}";
				}
			} catch ( Exception $e ) {
				throw $e;
			}

			return "ePay Callback completed - {$type}";
		}

		/**
		 * Process standard payments
		 *
		 * @param WC_Order $order
		 * @param array $params
		 *
		 * @return string
		 */
		protected function process_standard_payments( $order, $params ) {
			$action             = '';
			$old_transaction_id = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );
			if ( empty( $old_transaction_id ) ) {
				$this->add_surcharge_fee_to_order( $order, $params );
				$order->add_order_note( sprintf( __( 'ePay Payment completed with transaction id %s', 'epay-payment-solutions-epic' ), $params['txnid'] ) );
				$this->add_or_update_payment_type_id_to_order( $order, $params['paymenttype'] );
				$action = 'created';
			} else {
				$action = 'created (Called multiple times)';
			}
			$order->payment_complete( $params['txnid'] );

            $payment_complete_time_start = microtime(true);
			$order->payment_complete( $params['txnid'] );
            
            $transaction_id = $order->get_transaction_id();
            $payment_complete_time_end = microtime(true);
            $payment_complete_time = $payment_complete_time_end - $payment_complete_time_start;
			$order->add_order_note('Payment complete Done in '.round($payment_complete_time, 4).' sec - Transaction id '.$params['txnid']);

			return $action;
		}

		/**
		 * Process the subscription
		 *
		 * @param WC_Order|WC_Subscription $order
		 * @param array $params
		 *
		 * @return string
		 */
		protected function process_subscription( $order, $params ) {
			$action                  = '';
			$epay_subscription_id = $params['subscriptionid'];
			if ( Epay_EPIC_Payment_Helper::order_is_subscription( $order ) ) {
				// Do not cancel subscription if the callback is called more than once !
				$old_epay_subscription_id = Epay_EPIC_Payment_Helper::get_epay_payment_subscription_id( $order );
				if ( $epay_subscription_id != $old_epay_subscription_id ) {
					$this->subscription_cancellation( $order, true );
					$action = 'changed';
					$order->add_order_note( sprintf( __( 'ePay Payment Subscription changed from: %s to: %s', 'epay-payment-solutions-epic' ), $old_epay_subscription_id, $epay_subscription_id ) );
					$order->payment_complete();
					$this->save_subscription_meta( $order, $epay_subscription_id, true );
				} else {
					$action = 'changed (Called multiple times)';
				}
			} else {
				// Do not add surcharge if the callback is called more than once!
				$old_transaction_id     = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );
				$epay_transaction_id = $params['txnid'];
				if ( $epay_transaction_id != $old_transaction_id ) {
					$this->add_surcharge_fee_to_order( $order, $params );
					$action = 'activated';
					$order->add_order_note( sprintf( __( 'ePay Payment Subscription activated with subscription id: %s', 'epay-payment-solutions-epic' ), $epay_subscription_id ) );
					$order->payment_complete( $epay_transaction_id );
					$this->save_subscription_meta( $order, $epay_subscription_id, false );
					$this->add_or_update_payment_type_id_to_order( $order, $params['paymenttype'] );
					do_action( 'processed_subscription_payments_for_order', $order );
				} else {
					$action = 'activated (Called multiple times)';
				}
			}

			return $action;
		}

		/**
		 * Add surcharge to order
		 *
		 * @param WC_Order $order
		 * @param array $params
		 */
		protected function add_surcharge_fee_to_order( $order, $params ) {
			$order_currency           = $order->get_currency();
			$minorunits               = Epay_EPIC_Payment_Helper::get_currency_minorunits( $order_currency );
			$fee_amount_in_minorunits = $params['txnfee'];
			if ( $fee_amount_in_minorunits > 0 && $this->addfeetoorder === 'yes' ) {
				$fee_amount = Epay_EPIC_Payment_Helper::convert_price_from_minorunits( $fee_amount_in_minorunits, $minorunits );
				$fee        = (object) array(
					'name'      => __( 'Surcharge Fee', 'epay-payment-solutions-epic' ),
					'amount'    => $fee_amount,
					'taxable'   => false,
					'tax_class' => null,
					'tax_data'  => array(),
					'tax'       => 0,
				);
				$fee_item   = new WC_Order_Item_Fee();
				$fee_item->set_props( array(
						'name'      => $fee->name,
						'tax_class' => $fee->tax_class,
						'total'     => $fee->amount,
						'total_tax' => $fee->tax,
						'order_id'  => $order->get_id(),
					)
				);
				$fee_item->save();
				$order->add_item( $fee_item );
				$total_incl_fee = $order->get_total() + $fee_amount;
				$order->set_total( $total_incl_fee );
			}
		}

		/**
		 * Add Payment Type id Meta to the order
		 *
		 * @param WC_Order $order
		 * @param mixed $payment_type_id
		 *
		 * @return void
		 */
		protected function add_or_update_payment_type_id_to_order( $order, $payment_type_id ) {
			$existing_payment_type_id = $order->get_meta( Epay_EPIC_Payment_Helper::EPAY_PAYMENT_PAYMENT_TYPE_ID, true );
			if ( ! isset( $existing_payment_type_id ) || $existing_payment_type_id !== $payment_type_id ) {
				$order->update_meta_data( Epay_EPIC_Payment_Helper::EPAY_PAYMENT_PAYMENT_TYPE_ID, $payment_type_id );
				$order->save();
			}
		}

		/**
		 * Store the ePay Payment subscription id on subscriptions in the order.
		 *
		 * @param WC_Order $order_id
		 * @param string $epay_subscription_id
		 * @param bool $is_subscription
		 */
		protected function save_subscription_meta( $order, $epay_subscription_id, $is_subscription ) {
			$epay_subscription_id = wc_clean( $epay_subscription_id );
			$order_id                = $order->get_id();
			if ( $is_subscription ) {
				$order->update_meta_data( Epay_EPIC_Payment_Helper::EPAY_PAYMENT_SUBSCRIPTION_ID, $epay_subscription_id );
				$order->save();
			} else {
				// Also store it on the subscriptions being purchased in the order
				$subscriptions = Epay_EPIC_Payment_Helper::get_subscriptions_for_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$subscription->update_meta_data( Epay_EPIC_Payment_Helper::EPAY_PAYMENT_SUBSCRIPTION_ID, $epay_subscription_id );
					$subscription->add_order_note( sprintf( __( 'ePay Payment Solutions Subscription activated with subscription id: %s by order %s', 'epay-payment-solutions-epic' ), $epay_subscription_id, $order_id ) );
					$subscription->save();
				}
			}
		}

		/**
		 * Handle ePay Payment Actions
		 */
		public function epay_epic_payment_actions() {
			if ( isset( $_GET['boclassicaction'] ) && isset( $_GET['boclassicnonce'] ) && wp_verify_nonce( wp_unslash($_GET['boclassicnonce']), 'boclassic_process_payment_action' ) ) {
				$params   = $_GET;
				$result   = $this->process_epay_payment_action( $params );
				$action   = $params['boclassicaction'];
				$order_id = $params['post'] ?? $params['id'];

				if ( is_wp_error( $result ) ) {
					$message = $result->get_error_message( 'epay_payment_error' );
					$this->_boclassic_log->add( $message );
					Epay_EPIC_Payment_Helper::add_admin_notices( Epay_EPIC_Payment_Helper::ERROR, $message );
				} else {
					global $post;
					$message = sprintf( __( 'The %s action was a success for order %s', 'epay-payment-solutions-epic' ), $action, $order_id );
					Epay_EPIC_Payment_Helper::add_admin_notices( Epay_EPIC_Payment_Helper::SUCCESS, $message, true );
					if ( ! isset( $post ) ) {
						$url = admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
					} else {
						$url = admin_url( 'post.php?post=' . $order_id . '&action=edit' );
					}
					wp_safe_redirect( $url );
				}
			}
		}

		/**
		 * Validate Action params
		 *
		 * @param array $get_params
		 * @param string $failed_message
		 *
		 * @return bool
		 */
		protected function validate_epay_payment_action( $get_params, &$failed_message ) {
			$required_params = array(
				'boclassicaction',
				'currency',
				'amount',
			);
			foreach ( $required_params as $required_param ) {
				if ( ! array_key_exists( $required_param, $get_params ) || empty( $get_params[ $required_param ] ) ) {
					$failed_message = $required_param;

					return false;
				}
			}

			return true;
		}

		/**
		 * Process the action
		 *
		 * @param array $params
		 *
		 * @return bool|WP_Error
		 */
		protected function process_epay_payment_action( $params ) {
			$failed_message = '';
			if ( ! $this->validate_epay_payment_action( $params, $failed_message ) ) {
				return new WP_Error( 'epay_payment_error', sprintf( __( 'The following get parameter was not provided "%s"' ), $failed_message ) );
			}

			try {
				$order_id = $params['post'] ?? $params['id'];
				if ( $order_id == null ) {
					return new WP_Error( 'epay_payment_error', __( 'Both id and post were null' ) );
				}
				$currency = $params['currency'];
				$action   = $params['boclassicaction'];
				$amount   = $params['amount'];

                if (!$this->module_check( $order_id ) )
                {
					return new WP_Error( 'epay_payment_error', __( 'No payment module match' ) );
                }

				switch ( $action ) {
					case 'capture':
						$capture_result = $this->epay_payment_capture_payment( $order_id, $amount, $currency );

						return $capture_result;
					case 'refund':
						$refund_result = $this->epay_payment_refund_payment( $order_id, $amount, $currency );

						return $refund_result;
					case 'delete':
						$delete_result = $this->epay_payment_delete_payment( $order_id );

						return $delete_result;
				}
			} catch ( Exception $ex ) {
				return new WP_Error( 'epay_payment_error', $ex->getMessage() );
			}

			return true;
		}


		/**
		 * Capture a payment
		 *
		 * @param mixed $order_id
		 * @param mixed $amount
		 * @param mixed $currency
		 *
		 * @return bool|WP_Error
		 */
		public function epay_payment_capture_payment( $order_id, $amount, $currency ) {

			$order = wc_get_order( $order_id );
			if ( empty( $currency ) ) {
				$currency = $order->get_currency();
			}
			$minorunits           = Epay_EPIC_Payment_Helper::get_currency_minorunits( $currency );
			$amount               = str_replace( ',', '.', $amount );
			$amount_in_minorunits = Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
			$transaction_id       = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
				$remote_password = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "remotepassword", $this->remotepassword );
			} else {
				$merchant_number = $this->merchant;
				$remote_password = $this->remotepassword;
			}
            
            $webservice       = new Epay_EPIC_Payment_Soap( $remote_password, false, $this->apikey, $this->posid );
			$capture_response = $webservice->capture( $merchant_number, $transaction_id, $amount_in_minorunits );

			if ( $capture_response->captureResult === true ) {

				do_action( 'epay_payment_after_capture', $order_id );

                $order->payment_complete();
				return true;
			} else {
				$message = sprintf( __( 'Capture action failed for order %s', 'epay-payment-solutions-epic' ), $order_id );
				if ( $capture_response->epayresponse != '-1' ) {
					$message .= ' - ' . $webservice->get_epay_error( $merchant_number, $capture_response->epayresponse );
				} elseif ( $capture_response->pbsResponse != '-1' ) {
					$message .= ' - ' . $webservice->get_pbs_error( $merchant_number, $capture_response->pbsResponse );
				}
				// $this->_boclassic_log->add( $message );

				return new WP_Error( 'epay_payment_error', $message );
			}
		}

		/**
		 * Refund a payment
		 *
		 * @param mixed $order_id
		 * @param mixed $amount
		 * @param mixed $currency
		 *
		 * @return bool|WP_Error
		 */
		public function epay_payment_refund_payment( $order_id, $amount, $currency ) {

			$order = wc_get_order( $order_id );

			if ( empty( $currency ) ) {
				$currency = $order->get_currency();
			}

			$minorunits           = Epay_EPIC_Payment_Helper::get_currency_minorunits( $currency );
			$amount               = str_replace( ',', '.', $amount );
			$amount_in_minorunits = Epay_EPIC_Payment_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
			$transaction_id       = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
				$remote_password = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "remotepassword", $this->remotepassword );
			} else {
				$merchant_number = $this->merchant;
				$remote_password = $this->remotepassword;
			}

			$webservice      = new Epay_EPIC_Payment_Soap( $remote_password, false, $this->apikey, $this->posid );
			$refund_response = $webservice->refund( $merchant_number, $transaction_id, $amount_in_minorunits );
			if ( $refund_response->creditResult === true ) {
				do_action( 'epay_payment_after_refund', $order_id );

				return true;
			} else {
				$message = sprintf( __( 'Refund action failed for order %s', 'epay-payment-solutions-epic' ), $order_id );
				if ( $refund_response->epayresponse != '-1' ) {
					$message .= ' - ' . $webservice->get_epay_error( $merchant_number, $refund_response->epayresponse );
				} elseif ( $refund_response->pbsResponse != '-1' ) {
					$message .= ' - ' . $webservice->get_pbs_error( $merchant_number, $refund_response->pbsResponse );
				}
				$this->_boclassic_log->add( $message );

				return new WP_Error( 'epay_payment_error', $message );
			}
		}

		/**
		 * Delete a payment
		 *
		 * @param mixed $order_id
		 *
		 * @return bool|WP_Error
		 */
		public function epay_payment_delete_payment( $order_id ) {
			$order          = wc_get_order( $order_id );
			$transaction_id = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
				$remote_password = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "remotepassword", $this->remotepassword );
			} else {
				$merchant_number = $this->merchant;
				$remote_password = $this->remotepassword;
			}

            $webservice      = new Epay_EPIC_Payment_Soap( $remote_password, false, $this->apikey, $this->posid );
            $delete_response = $webservice->delete( $merchant_number, $transaction_id );
            if ( $delete_response->deleteResult === true ) {
                do_action( 'epay_payment_after_delete', $order_id );

                return true;
            } else {
                $message = sprintf( __( 'Delete action failed for order %s', 'epay-payment-solutions-epic' ), $order_id );
                if ( $delete_response->epayresponse != '-1' ) {
                    $message .= ' - ' . $webservice->get_epay_error( $merchant_number, $delete_response->epayresponse );
                }
                $this->_boclassic_log->add( $message );

                return new WP_Error( 'epay_payment_error', $message );
            }        
        }

		/**
		 * Add subscripts payment meta, to allow for subscripts import to map tokens, and for admins to manually set a subscription token
		 *
		 * @Link https://github.com/woocommerce/woocommerce-subscriptions-importer-exporter#importing-payment-gateway-meta-data
		 */
		public function add_subscription_payment_meta( $payment_meta, $subscription ) {
			$payment_meta[ $this->id ] = array(
				'post_meta' => array(
					Epay_EPIC_Payment_Helper::EPAY_PAYMENT_SUBSCRIPTION_ID => array(
						'value'    => Epay_EPIC_Payment_Helper::get_epay_payment_subscription_id( $subscription ),
						'label'    => __( 'ePay subscription token', 'epay-payment-solutions-epic' ),
						'disabled' => false,
					),
				),
			);

			return $payment_meta;
		}

		/**
		 * Add ePay Payment Meta boxes
		 */
		public function epay_epic_payment_meta_boxes() {
			global $post;
			if ( ! isset( $post ) ) { //HPOS might be used
				$order = wc_get_order();
                
                if($order) {
				    $order_id = $order->get_id();
                }

			} else {
				$order_id = $post->ID;
				$order    = wc_get_order( $order_id );
			}
			if ( ! $order ) {
				return;
			}

			if ( ! $this->module_check( $order_id ) ) {
				return;
			}

			add_meta_box(
				'epay-payment-actions',
				'ePay Payment Solutions',
				array( &$this, 'epay_payment_meta_box_payment' ),
				'shop_order',
				'side',
				'high'
			);
			add_meta_box(
				'epay-payment-actions',
				'ePay Payment Solutions',
				array( &$this, 'epay_payment_meta_box_payment' ),
				'woocommerce_page_wc-orders',
				'side',
				'high'
			);

		}

		/**
		 * Create the ePay Payment Meta Box
		 */
		public function epay_payment_meta_box_payment() {
			global $post;
			$html = '';
			try {
				if ( ! isset( $post ) ) { //HPOS might be used
					$order    = wc_get_order();
					$order_id = $order->get_id();
				} else {
					$order_id = $post->ID;
					$order    = wc_get_order( $order_id );
				}
				if ( ! empty( $order ) ) {
					$transaction_id = Epay_EPIC_Payment_Helper::get_epay_payment_transaction_id( $order );
					if ( strlen( $transaction_id ) > 0 ) {
						$html = $this->epay_payment_meta_box_payment_html( $order, $transaction_id );
					} else {
						$html = sprintf( __( 'No transaction was found for order %s', 'epay-payment-solutions-epic' ), $order_id );
						$this->_boclassic_log->add( $html );
					}
				} else {
					$html = sprintf( __( 'The order with id %s could not be loaded', 'epay-payment-solutions-epic' ), $order_id );
					$this->_boclassic_log->add( $html );
				}
			} catch ( Exception $ex ) {
				$html = $ex->getMessage();
				$this->_boclassic_log->add( $html );
			}
			echo ent2ncr( $html );
		}

		/**
		 * Create the HTML for the ePay Payment Meta box payment field
		 *
		 * @param WC_Order $order
		 * @param string $transaction_id
		 *
		 * @return string
		 */
		protected function epay_payment_meta_box_payment_html( $order, $transaction_id ) {
			try {
				$html = '';
				if ( class_exists( 'sitepress' ) ) {
					$merchant_number = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "merchant", $this->merchant );
					$remote_password = Epay_EPIC_Payment_Helper::getWPMLOptionValue( "remotepassword", $this->remotepassword );
				} else {
					$merchant_number = $this->merchant;
					$remote_password = $this->remotepassword;
				}

                $webservice               = new Epay_EPIC_Payment_Soap( $remote_password, false, $this->apikey, $this->posid );
                $get_transaction_response = $webservice->get_transaction( $merchant_number, $transaction_id );
                if ( $get_transaction_response->gettransactionResult === false ) {
                    $html = __( 'Get Transaction action failed', 'epay-payment-solutions-epic' );
                    if ( $get_transaction_response->epayresponse != '-1' ) {
                        $html .= ' - ' . $webservice->get_epay_error( $merchant_number, $get_transaction_response->epayresponse );
                    }
                    return $html;
                }
                $transaction   = $get_transaction_response->transactionInformation;

                $currency_code = $transaction->currency;
				$currency      = Epay_EPIC_Payment_Helper::get_iso_code( $currency_code, false );
				$minorunits    = Epay_EPIC_Payment_Helper::get_currency_minorunits( $currency );

				$total_authorized      = Epay_EPIC_Payment_Helper::convert_price_from_minorunits( $transaction->authamount, $minorunits );
				$total_captured        = Epay_EPIC_Payment_Helper::convert_price_from_minorunits( $transaction->capturedamount, $minorunits );
				$total_credited        = Epay_EPIC_Payment_Helper::convert_price_from_minorunits( $transaction->creditedamount, $minorunits );
				$available_for_capture = $total_authorized - $total_captured;
				$transaction_status    = $transaction->status;

				// $card_info     = Epay_EPIC_Payment_Helper::get_cardtype_groupid_and_name( $transaction->cardtypeid );
				// $card_group_id = $card_info[1];
				// $card_name     = $card_info[0];

				if ( isset( $card_group_id ) && $card_group_id != '-1' ) {
					$this->add_or_update_payment_type_id_to_order( $order, $card_group_id );
				}

				$user = wp_get_current_user();
				if ( in_array( $this->rolecapturerefunddelete, (array) $user->roles ) || in_array( 'administrator', (array) $user->roles ) ) {
					//The user has the role required for  "Capture, Refund, Delete"  and can perform those actions.
					$canCaptureRefundDelete = true;
				} else {
					//The user can only view the data.
					$canCaptureRefundDelete = false;
				}

				$html = '<div class="boclassic-info">';
				// if ( isset( $card_group_id ) && $card_group_id != '-1' ) {
                    
                    $html .= '<img class="boclassic-paymenttype-img" src="'.esc_url(Epay_EPIC_Payment_Helper::get_card_logourl_by_type($transaction->paymentMethodSubType)).'">';

                    if($transaction->paymentMethodType != "CARD")
                    {
                        $html .= '<img class="boclassic-paymenttype-img" src="'.esc_url(Epay_EPIC_Payment_Helper::get_card_logourl_by_type($transaction->paymentMethodType)).'">';
                    }
				// }

				$html .= '<div class="boclassic-transactionid">';
				$html .= '<p>' . __( 'Transaction ID', 'epay-payment-solutions-epic' ) . '</p>';
				$html .= '<p>' . $transaction->transactionid . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-paymenttype">';
				$html .= '<p>' . __( 'Payment Type', 'epay-payment-solutions-epic' ) . '</p>';
				$html .= '<p>' . Epay_EPIC_Payment_Helper::get_card_name_by_type($transaction->paymentMethodSubType) . '</p>';
				$html .= '</div>';

				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Authorized:', 'epay-payment-solutions-epic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_authorized ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Captured:', 'epay-payment-solutions-epic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_captured ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Refunded:', 'epay-payment-solutions-epic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_credited ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '</div>';


				if ( $transaction_status === 'PAYMENT_NEW' || ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) ) {
					$html .= '<div class="boclassic-action-container">';
					$html .= '<input type="hidden" id="boclassic-currency" name="boclassic-currency" value="' . $currency . '">';
					wp_nonce_field( 'boclassic_process_payment_action', 'boclassicnonce' );
					if ( $transaction_status === 'PAYMENT_NEW' ) {
						$html .= '<input type="hidden" id="boclassic-capture-message" name="boclassic-capture-message" value="' . __( 'Are you sure you want to capture the payment?', 'epay-payment-solutions-epic' ) . '" />';
						$html .= '<div class="boclassic-action">';

						if ( $canCaptureRefundDelete ) {
							$html .= '<p>' . $currency . '</p>';
							$html .= '<input type="text" value="' . $available_for_capture . '" id="boclassic-capture-amount" class="boclassic-amount" name="boclassic-amount" />';
							$html .= '<input id="epay-capture-submit" class="button capture" name="boclassic-capture" type="submit" value="' . __( 'Capture', 'epay-payment-solutions-epic' ) . '" />';
						} else {
							$html .= __( 'Your role cannot capture or delete the payment', 'epay-payment-solutions-epic' );
						}
						$html .= '</div>';
						$html .= '<br />';
						if ( $total_captured === 0 ) {
							$html .= '<input type="hidden" id="boclassic-delete-message" name="boclassic-delete-message" value="' . __( 'Are you sure you want to delete the payment?', 'epay-payment-solutions-epic' ) . '" />';
							$html .= '<div class="boclassic-action">';
							if ( $canCaptureRefundDelete ) {
								$html .= '<input id="epay-delete-submit" class="button delete" name="boclassic-delete" type="submit" value="' . __( 'Delete', 'epay-payment-solutions-epic' ) . '" />';
							}
							$html .= '</div>';
						}
					} elseif ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) {
						$html .= '<input type="hidden" id="boclassic-refund-message" name="boclassic-refund-message" value="' . __( 'Are you sure you want to refund the payment?', 'epay-payment-solutions-epic' ) . '" />';
						$html .= '<div class="boclassic-action">';
						$html .= '<p>' . $currency . '</p>';
						$html .= '<input type="text" value="' . $total_captured . '" id="boclassic-refund-amount" class="boclassic-amount" name="boclassic-amount" />';
						if ( $canCaptureRefundDelete ) {
							$html .= '<input id="epay-refund-submit" class="button refund" name="boclassic-refund" type="submit" value="' . __( 'Refund', 'epay-payment-solutions-epic' ) . '" />';
						}
						$html .= '</div>';
						$html .= '<br />';
					}
					$html            .= '</div>';
					$warning_message = __( 'The amount you entered was in the wrong format.', 'epay-payment-solutions-epic' );

					$html .= '<div id="boclassic-format-error" class="boclassic boclassic-error"><strong>' . __( 'Warning', 'epay-payment-solutions-epic' ) . ' </strong>' . $warning_message . '<br /><strong>' . __( 'Correct format is: 1234.56', 'epay-payment-solutions-epic' ) . '</strong></div>';

				}

				$history_array = $transaction->history->TransactionHistoryInfo;

				if ( isset( $history_array ) && ! is_array( $history_array ) ) {
					$history_array = array( $history_array );
				}

				// Sort the history array based on when the history event is created
				$history_created = array();
				foreach ( $history_array as $history ) {
					$history_created[] = $history->created;
				}
				array_multisort( $history_created, SORT_ASC, $history_array );

				if ( count( $history_array ) > 0 ) {
					$html .= '<h4>' . __( 'TRANSACTION HISTORY', 'epay-payment-solutions-epic' ) . '</h4>';
					$html .= '<table class="boclassic-table">';

					foreach ( $history_array as $history ) {
						$html .= '<tr class="boclassic-transaction-row-header">';
						$html .= '<td>' . Epay_EPIC_Payment_Helper::format_date_time( $history->created ) . '</td>';
						$html .= '</tr>';
						if ( strlen( $history->username ) > 0 ) {
							$html .= '<tr class="boclassic-transaction-row-header boclassic-transaction-row-header-user">';
							$html .= '<td>' . sprintf( __( 'By: %s', 'epay-payment-solutions-epic' ), $history->username ) . '</td>';
							$html .= '</tr>';
						}
						$html .= '<tr class="boclassic-transaction">';
						$html .= '<td>' . $history->eventMsg . '</td>';
						$html .= '</tr>';
					}
					$html .= '</table>';
				}

				return $html;
			} catch ( Exception $ex ) {
				throw $ex;
			}
		}

		/**
		 * Get the ePay Payment checkout logger
		 *
		 * @return Epay_EPIC_Payment_Log
		 */
		public function get_boclassic_logger() {
			return $this->_boclassic_log;
		}

		public function module_check( $order_id ) {
			$order          = wc_get_order( $order_id );
            $payment_method = $order->get_payment_method();

			return $this->id === $payment_method;
		}

		/**
		 * Returns a plugin URL path
		 *
		 * @param string $path
		 *
		 * @return string
		 */
		public function plugin_url( $path ) {
			return plugins_url( $path, __FILE__ );
		}

        public function get_icon() {

            $icon_html = '<img src="' . $this->icon . '" alt="' . $this->method_title . '" class=""  />';

            $selected_icons = $this->get_settings('icons');

            // TODO
            $allicons = [
                'epay'           => plugins_url('epay-logo.svg', __FILE__),
                'visa'           => plugins_url('images/visa.svg', __FILE__),
                'mastercard'     => plugins_url('images/mastercard.svg', __FILE__),
                'americanexpress'=> plugins_url('images/american_express.svg', __FILE__),
                'dinersclub'     => plugins_url('images/diners_club.svg', __FILE__),
                'ideal'          => plugins_url('images/ideal.svg', __FILE__),
                'jcb'            => plugins_url('images/jcb.svg', __FILE__),
                'maestro'        => plugins_url('images/maestro.svg', __FILE__),
                'visa'           => plugins_url('images/visa.svg', __FILE__),
                'dankort'        => plugins_url('images/dankort.svg', __FILE__),
                'applepay'       => plugins_url('images/applepay.svg', __FILE__),
                'mobilepay'      => plugins_url('images/mobilepay.svg', __FILE__),
                'googlepay'      => plugins_url('images/googlepay.svg', __FILE__),
            ];

            if(preg_match("/epay-logo\.svg/", $this->icon) && is_array($selected_icons) && count($selected_icons))
            {
                $icon_html = '';
                foreach($selected_icons AS $cardname)
                {
			        $icon_html .= '<img src="' . $allicons[$cardname] . '" class="epay-card-icon" />';
                }
            }

            return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
        }

        public static function load_subgates($methods) {
            require_once( EPAYEPIC_PATH .'/lib/subgates/subgate.php' );

            $subgates = self::get_subgates();

            foreach($subgates AS $file_name => $class_name) {
                $file_path = EPAYEPIC_PATH . '/lib/subgates/' .$file_name. '.php';

                if( file_exists( $file_path ) ) {
                    require_once($file_path);
                    $methods[] = $class_name;
                }
            }

            return $methods;
        }

        public static function get_subgates() {
            return [
                // "mobilepay" => 'Epay_EPIC_MobilePay',
                // "applepay" => 'Epay_EPIC_ApplePay',
                // "viabill" => 'Epay_EPIC_ViaBill',
                // "paypal" => 'Epay_EPIC_PayPal',
                // "klarna" => 'Epay_EPIC_Klarna',
                // "ideal" => 'Epay_EPIC_iDEAL',
            ];
        }


        public static function get_card_icon_options() {
                return [
                        'dankort'               => 'Dankort',
                        'visa'                  => 'Visa',
                        'mastercard'            => 'Mastercard',
                        'mobilepay'             => 'MobilePay',
                        'applepay'             => 'Apple Pay',
                        'googlepay'            => 'Google Pay',
                        'maestro'               => 'Maestro',
                        'jcb'                   => 'JCB',
                        'americanexpress'       => 'American Express',
                        'diners'                => 'Diner\'s Club',
                        'discovercard'          => 'Discover Card',
                        'dinersclub'            => 'Diners Club',
                        'ideal'                 => 'iDeal',
                ];
        }
	}

    function WC_EP_EPIC(): Epay_EPIC_Payment {
        return Epay_EPIC_Payment::get_instance();
    }

    WC_EP_EPIC();
    WC_EP_EPIC()->init_hooks();
	// Epay_EPIC_Payment::get_instance()->init_hooks();


	/**
	 * Add the Gateway to WooCommerce
	 **/
	function add_epay_epic_payment_woocommerce( $methods ) {
		$methods[] = 'Epay_EPIC_Payment';

		return Epay_EPIC_Payment::load_subgates($methods);
	}

	add_filter( 'woocommerce_payment_gateways', 'add_epay_epic_payment_woocommerce' );

	$plugin_dir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'epay-payment-solutions-epic', false, $plugin_dir . '/languages' );

    /*
	add_action( 'before_woocommerce_init', function () {
		if ( class_exists( FeaturesUtil::class ) ) {
		    FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	} );
    */

    function epic_declare_cart_checkout_blocks_compatibility() {
        
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
        
    // Hook the custom function to the 'before_woocommerce_init' action
    add_action('before_woocommerce_init', 'epic_declare_cart_checkout_blocks_compatibility');


    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action( 'woocommerce_blocks_loaded', 'epic_register_order_approval_payment_method_type' );

    /**
    * Custom function to register a payment method type

    */





    function epic_register_order_approval_payment_method_type() {
        // Check if the required class exists
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        // Include the custom Blocks Checkout class
        require_once plugin_dir_path(__FILE__) . 'epay-payment-block.php';

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                // Register an instance of My_Custom_Gateway_Blocks
                foreach( wc()->payment_gateways()->payment_gateways() as $payment_gateway )
                {
                    if($payment_gateway instanceof Epay_EPIC_Payment)
                    {
                        $payment_method_registry->register( new Epay_EPIC_Payment_Blocks($payment_gateway) );
                    }
                }
            }
        );
    }







    /*
    * Display Age Verification Product Fields
    */
    function ep_epic_ageverification_add_product_field()
    {
        return woocommerce_wp_select(
            array(
                'id'      => 'ageverification',
                'label'   => __( 'Ageverification', 'woocommerce' ),
                'options' => Epay_EPIC_Payment_Helper::get_ageverification_options()
            )
        );
    }
    add_action( 'woocommerce_product_options_general_product_data', 'ep_epic_ageverification_add_product_field' );

    // Save Ageverification
    function save_ep_epic_ageverification_product( $post_id ){
        if( isset($_POST['ageverification']))
        {
            $value = sanitize_text_field( wp_unslash( $_POST['ageverification'] ) );
            update_post_meta( $post_id, 'ageverification', $value);
        }
    }
    add_action( 'woocommerce_process_product_meta', 'save_ep_epic_ageverification_product' );

    /*
    * Display Age Verification Category Fields
    */
    add_action('product_cat_add_form_fields', 'ep_epic_ageverification_add_category_field', 10, 1);
    add_action('product_cat_edit_form_fields', 'ep_epic_ageverification_edit_category_field', 10, 1);
    
    //Product Cat Create page
    function ep_epic_ageverification_add_category_field() {
        ?>
        <div class="form-field">
            <label for="ep_category_ageverification">Ageverification</label>
            <select name="ep_category_ageverification" id="ep_category_ageverification" >

            <?php
            foreach(Epay_EPIC_Payment_Helper::get_ageverification_options() AS $key => $option)
            {
                echo '<option value="'.esc_attr($key).'" '.($key==$ep_category_ageverification ? "selected" : "").'>'.esc_html($option).'</option>';
            }
            ?>
            </select>

            <p class="description">Activate ageverification on category</p>
        </div>
        <?php
    }

    function ep_epic_ageverification_edit_category_field($term) {

        $term_id = $term->term_id;

        $ep_category_ageverification = get_term_meta($term_id, 'ep_category_ageverification', true);
        ?>

        <tr class="form-field">
            <th scope="row" valign="top"><label for="ep_category_ageverification">Ageverification</label></th>
            <td>
                <select name="ep_category_ageverification" id="ep_category_ageverification" >
                <?php
                foreach(Epay_EPIC_Payment_Helper::get_ageverification_options() AS $key => $option)
                {
                    echo '<option value="'.esc_attr($key).'" '.($key==$ep_category_ageverification ? "selected" : "").'>'.esc_html($option).'</option>';
                }
                ?>
                </select>
                <p class="description">Activate ageverification category</p>
            </td>
        </tr>
        <?php
    }

    add_action('edited_product_cat', 'save_ep_epic_ageverification_category', 10, 1);
    add_action('create_product_cat', 'save_ep_epic_ageverification_category', 10, 1);

    // Save extra taxonomy fields callback function.
    function save_ep_epic_ageverification_category($term_id) {
        $ep_category_ageverification = filter_input(INPUT_POST, 'ep_category_ageverification');
        update_term_meta($term_id, 'ep_category_ageverification', $ep_category_ageverification);
    }
}
