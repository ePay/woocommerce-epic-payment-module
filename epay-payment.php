<?php
/**
 * Plugin Name: ePay Payment Solutions - EPIC
 * Plugin URI: https://docs.epay.eu/plugins/woocommerce
 * Description: ePay Payment gateway for WooCommerce
 * Version: 7.0.10
 * Author: ePay Payment Solutions
 * Author URI: https://www.epay.dk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: epay-payment-solutions
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Tested up to: 6.9
 * Requires PHP: 7.4
 *
 * WC requires at least: 7.0
 * WC tested up to: 9.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

define( 'EPAY_PLUGIN_SLUG', 'epay-payment-solutions' );
define( 'EPAY_PLUGIN_FILE', __FILE__ );
define( 'EPAY_PLUGIN_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'EPAY_PLUGIN_URL',  trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'EPAY_PLUGIN_VERSION', '7.0.9' );

add_action( 'plugins_loaded', 'epayPaymentInit', 0 );

/**
 * Initialize ePay Payment
 *
 * @return void
 */
function EpayPaymentInit() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include( EPAY_PLUGIN_DIR . 'lib/epay-payment-api.php' );
	include( EPAY_PLUGIN_DIR . 'lib/epay-payment-helper.php' );
	include( EPAY_PLUGIN_DIR . 'lib/epay-payment-log.php' );

	/**
	 * Gateway class
	 **/
	class EpayPayment extends WC_Payment_Gateway {

        public $enabled = null;
        public $hosted_fields = null;
        public $title = null;
        public $description = null;
        private $merchant = null;
        private $windowid = null;
        private $md5key = null;
        private $instantcapture = null;
        private $group = null;
        private $authmail = null;
        private $ownreceipt = null;
        private $remoteinterface = null;
        private $remotepassword = null;
        private $enableinvoice = null;
        private $addfeetoorder = null;
        private $enablemobilepaymentwindow = null;
        private $roundingmode = null;
        private $captureonstatuscomplete = null;
        private $override_subscription_need_payment = null;
        private $rolecapturerefunddelete = null;
        private $orderstatusaftercancelledpayment = null;
        private $ageverificationmode = null;
        protected $paymenttype = null;
        protected $paymentcollection = null;
        private $apikey = null;
        private $posid = null;

		/**
		 * Singleton instance
		 *
		 * @var EpayPayment
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
		 * @return EpayPayment
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
			$this->id                 = 'epay_payment_solutions';
			$this->method_title       = 'ePay Payment Solutions';
			$this->method_description = 'ePay Payment Solutions - Enables easy and secure payments on your shop';
			$this->has_fields         = true;
            $this->paymenttype        = false;
            $this->paymentcollection  = false;
			$this->icon               = EPAY_PLUGIN_URL . '/epay-logo.svg';


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
			$this->_boclassic_log = new EpayPaymentLog();

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Initialize ePay Payment Settings
			$this->initEpayPaymentSettings();

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
		 * Initialize ePay Payment Settings
		 */
		public function initEpayPaymentSettings() {
			// Define user set variables
			$this->enabled                            = array_key_exists( 'enabled', $this->settings ) ? $this->settings['enabled'] : 'yes';
			$this->hosted_fields                      = array_key_exists( 'hosted_fields', $this->settings ) ? $this->settings['hosted_fields'] : 'yes';
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
			$this->roundingmode                       = array_key_exists( 'roundingmode', $this->settings ) ? $this->settings['roundingmode'] : EpayPaymentHelper::ROUND_DEFAULT;
			$this->captureonstatuscomplete            = array_key_exists( 'captureonstatuscomplete', $this->settings ) ? $this->settings['captureonstatuscomplete'] : 'no';
			$this->override_subscription_need_payment = array_key_exists( 'overridesubscriptionneedpayment', $this->settings ) ? $this->settings['overridesubscriptionneedpayment'] : 'yes';
			$this->rolecapturerefunddelete            = array_key_exists( 'rolecapturerefunddelete', $this->settings ) ? $this->settings['rolecapturerefunddelete'] : 'shop_manager';
            $this->orderstatusaftercancelledpayment   = array_key_exists( 'orderstatusaftercancelledpayment', $this->settings ) ? $this->settings['orderstatusaftercancelledpayment'] : EpayPaymentHelper::STATUS_CANCELLED;
            $this->ageverificationmode                = array_key_exists( 'ageverificationmode', $this->settings ) ? $this->settings['ageverificationmode'] : EpayPaymentHelper::AGEVERIFICATION_DISABLED;
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

        public function get_id()
        {
            return $this->id;
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
                    add_action( 'add_meta_boxes', array( $this, 'epayPaymentMetaBoxes' ) );
					add_action( 'wp_before_admin_bar_render', array( $this, 'epayPaymentActions' ) );
					add_action( 'admin_notices', array( $this, 'epayPaymentAdminNotices' ) );
				}
			}
			if ( $this->remoteinterface == 'yes' ) {
				if ( $this->captureonstatuscomplete === 'yes' ) {
					add_action( 'woocommerce_order_status_completed', array(
						$this,
						'epayPaymentOrderStatusCompleted'
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
				'enqueueEpayPaymentAdminStylesAndScripts'
			) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueEpayPaymentFrontStyles' ) );

            add_action('rest_api_init', function () {
                register_rest_route('epay/v1', '/create-session', [
                    'methods' => 'POST',
                    'callback' => [ $this, 'epay_create_session' ],
                    'permission_callback' => '__return_true',
                ]);
            }); 
		}

        function epay_create_session($request) {
            // $params = $request->get_json_params();
            // $order_id = $params['order_id'] ?? null;

            $epayHandler = new EpayPaymentApi($this->apikey, $this->posid);
            
            $result = $epayHandler->createPaymentRequest(json_encode(array("orderid"=>null, "amount"=>154990, "currency"=>"DKK", "instant"=>"OFF", "accepturl"=>"https:///temp/epay/hosted/?success", "cancelurl"=>"https:///temp/epay/hosted/?failure", "callbackurl"=>"https:///temp/epay/hosted/?notification")));
            
            $result = json_decode($result);
            
            /*
            if ( ! function_exists( 'WC' ) ) {
                return new WP_Error('no_wc', 'WooCommerce ikke tilgængelig', ['status' => 500]);
            }

            if ( ! WC()->session ) {
                WC()->initialize_session();
            }

            if ( ! WC()->cart ) {
                wc_load_cart();
            }
            */

	        // $total = WC()->cart ? WC()->cart->get_total('edit') : 'NO CART';

            
	        // $total = WC()->cart->get_total('edit');
	        // $items = WC()->cart->get_cart_contents();

            // error_log('Cart total: ' . WC()->cart->get_total('edit'));

	        return rest_ensure_response([
		        'payment_session_js_url' => $result->javascript,
                'cart_total' => 123,
                'cart_items' => 4,
                'payment_session' => $result,
	        ]);
        }

		/**
		 * Show messages in the Administration
		 */
		public function epayPaymentAdminNotices() {
			EpayPaymentHelper::echo_admin_notices();
		}

		/**
		 * Enqueue Admin Styles and Scripts
		 */
		public function enqueueEpayPaymentAdminStylesAndScripts() {
			wp_register_style( 'epay_payment_admin_style', EPAY_PLUGIN_URL . 'style/epay-payment-admin.css', array(), 1 );
			wp_enqueue_style( 'epay_payment_admin_style' );

			// Fix for load of Jquery time!
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'epay_payment_admin', EPAY_PLUGIN_URL . 'scripts/epay-payment-admin.js', array(), 1, false );
		}

		/**
		 * Enqueue Frontend Styles and Scripts
		 */
		public function enqueueEpayPaymentFrontStyles() {
			wp_register_style( 'epay_epic_payment_front_style', EPAY_PLUGIN_URL . 'style/epay-epic-payment-front.css', array(), 1 );
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
					'label'   => 'Enable ePay Payment Solutions as a payment option.',
					'default' => 'yes'
				),
                /*
				'hosted_fileds' => array(
					'title'       => 'Use hosted fields',
					'type'        => 'checkbox',
					'description' => 'When this is enabled the checkout will use hosted fields.',
                    'desc_tip'    => true,
					'default'     => 'yes'
				),
                */
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
						EpayPaymentHelper::ROUND_DEFAULT => 'Default',
						EpayPaymentHelper::ROUND_UP      => 'Always up',
						EpayPaymentHelper::ROUND_DOWN    => 'Always down'
					),
					'default'     => 'normal'
				),
				'orderstatusaftercancelledpayment'                    => array(
					'title'       => 'Status after cancel payment',
					'type'        => 'select',
					'description' => 'Please select order status after payment cancelled',
                    'desc_tip'    => true,
					'options'     => array(
						EpayPaymentHelper::STATUS_CANCELLED      => 'Cancelled',
						EpayPaymentHelper::STATUS_PENDING        => 'Pending payment'
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
                        EpayPaymentHelper::AGEVERIFICATION_DISABLED => 'Disabled',
                        EpayPaymentHelper::AGEVERIFICATION_ENABLED_ALL => 'Enabled on all orders',
                        EpayPaymentHelper::AGEVERIFICATION_ENABLED_DK => 'Enabled on DK orders'
					)
				)
			);
		}

		/**
		 * Admin Panel Options
		 */
		public function admin_options() {
            $version = EPAY_PLUGIN_VERSION;

            echo '<h3>' . esc_html( $this->method_title ) . ' v' . esc_html( $version ) . '</h3>';

            $debug_html = EpayPaymentHelper::create_admin_debug_section();
            echo wp_kses_post( $debug_html );

            echo '<h3 class="wc-settings-sub-title">' . esc_html__( 'Module Configuration', 'epay-payment-solutions' ) . '</h3>';

            if ( class_exists( 'sitepress' ) ) {
                $url = admin_url( 'admin.php?page=wpml-string-translation/menu/string-translation.php&context=admin_texts_woocommerce_epay_dk_settings' );

                echo '<div class="form-table">';
                echo '<h2>' . esc_html__( 'You have WPML activated.', 'epay-payment-solutions' ) . '</h2>';

                echo '<p>' . esc_html__( 'If you need to configure another merchant number for another language translate them under', 'epay-payment-solutions' ) . ' ';
                echo '<a href="' . esc_url( $url ) . '" class="current" aria-current="page">'
                        . esc_html__( 'String Translation', 'epay-payment-solutions' ) . '</a></p>';

                echo '<p>' . esc_html__( 'Subscriptions are currently only supported for the default merchant number.', 'epay-payment-solutions' ) . '</p>';
                echo '</div>';
            }

            echo '<table class="form-table">';

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted HTML from WooCommerce settings renderer
            $settings_html = $this->generate_settings_html( array(), false );

            echo wp_kses( $settings_html, EpayPaymentHelper::get_allowed_tags() );

            echo '</table>';
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

			if ( ! $needs_payment && $this->id === $order->get_payment_method() && EpayPaymentHelper::get_order_contains_subscription( $order, array( 'parent' ) ) ) {
				$needs_payment = true;
			}

			return $needs_payment;
		}

		/**
		 * Capture the payment on order status completed
		 *
		 * @param mixed $order_id
		 */
		public function epayPaymentOrderStatusCompleted( $order_id ) {
			if ( ! $this->module_check( $order_id ) ) {
				return;
			}

			$order          = wc_get_order( $order_id );
			$order_total    = $order->get_total();
			$capture_result = $this->epayPaymentCapturePayment( $order_id, $order_total, '' );

			if ( is_wp_error( $capture_result ) ) {
				$message = $capture_result->get_error_message( 'epay_payment_error' );
				$this->_boclassic_log->add( $message );
				EpayPaymentHelper::add_admin_notices( EpayPaymentHelper::ERROR, $message );
			} else {
                /* translators: %s is the WooCommerce order ID */
				$message = sprintf( __( 'The Capture action was a success for order %s', 'epay-payment-solutions' ), $order_id );
				EpayPaymentHelper::add_admin_notices( EpayPaymentHelper::SUCCESS, $message );
			}
		}


		/**
		 * There are no payment fields for epay, but we want to show the description if set.
         **/
        public function payment_fields() {
            if ( ! empty( $this->description ) ) {
                echo wp_kses_post( wpautop( $this->description ) );
            }
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
				$invoice['customer']['firstname']    = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_first_name() );
				$invoice['customer']['lastname']     = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_last_name() );
				$invoice['customer']['address']      = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_address_1() );
				$invoice['customer']['zip']          = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_postcode() );
				$invoice['customer']['city']         = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_city() );
				$invoice['customer']['country']      = EpayPaymentHelper::json_value_remove_special_characters( $order->get_billing_country() );

				$invoice['shippingaddress']['firstname'] = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_first_name() );
				$invoice['shippingaddress']['lastname']  = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_last_name() );
				$invoice['shippingaddress']['address']   = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_address_1() );
				$invoice['shippingaddress']['zip']       = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_postcode() );
				$invoice['shippingaddress']['city']      = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_city() );
				$invoice['shippingaddress']['country']   = EpayPaymentHelper::json_value_remove_special_characters( $order->get_shipping_country() );

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
					'description' => EpayPaymentHelper::json_value_remove_special_characters( $item['name'] ),
					'quantity'    => $item['qty'],
					'price'       => EpayPaymentHelper::convert_price_to_minorunits( $item_price, $minorunits, $this->roundingmode ),
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
					'price'       => EpayPaymentHelper::convert_price_to_minorunits( $shipping_total, $minorunits, $this->roundingmode ),
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
			$order = wc_get_order( $order_id );

			return array(
				'result'   => 'success',
                'redirect' => $this->createPaymentRequestLink($order_id), // EPIC PaymentWindow
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
				return new WP_Error( 'notpermitted', __( "Your user role is not allowed to refund via Epay Payment", "epay-payment-solutions" ) );
			}

			if ( ! isset( $amount ) ) {
				return true;
			}
			if ( $amount < 1 ) {
				return new WP_Error( 'toolow', __( "You have to refund a higher amount than 0.", "epay-payment-solutions" ) );
			}

			$refund_result = $this->epayPaymentRefundPayment( $order_id, $amount, '' );
			if ( is_wp_error( $refund_result ) ) {
				return $refund_result;
			} else {
                /* translators: %s is the WooCommerce order ID */
                $message = sprintf( __( 'The Refund action was a success for order %s', 'epay-payment-solutions' ), $order_id );
				EpayPaymentHelper::add_admin_notices( EpayPaymentHelper::SUCCESS, $message );
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
			$subscription     = EpayPaymentHelper::get_subscriptions_for_renewal_order( $renewal_order );
			$result           = $this->process_subscription_payment( $amount_to_charge, $renewal_order, $subscription );
			$renewal_order_id = $renewal_order->get_id();

			// Remove the ePay Payment subscription id copyid from the subscription

			$renewal_order->delete_meta_data( EpayPaymentHelper::EPAY_PAYMENT_SUBSCRIPTION_ID );
			$renewal_order->save();
			if ( is_wp_error( $result ) ) {
                /* translators: 1: Renewal order ID, 2: Error message */
				$message = sprintf( __( 'ePay Payment Solutions Subscription could not be authorized for renewal order # %1$s - %2$s', 'epay-payment-solutions' ), $renewal_order_id, $result->get_error_message( 'epay_payment_error' ) );
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
			// try {
				$epay_subscription_id = EpayPaymentHelper::get_epay_payment_subscription_id( $subscription );
				if ( strlen( $epay_subscription_id ) === 0 ) {
					return new WP_Error( 'epay_payment_error', __( 'ePay Payment Solutions Subscription id was not found', 'epay-payment-solutions' ) );
				}

				$order_currency   = $renewal_order->get_currency();
				$minorunits       = EpayPaymentHelper::get_currency_minorunits( $order_currency );
				$amount           = EpayPaymentHelper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
				$renewal_order_id = $renewal_order->get_id();

                $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
                $instantcapture = ((bool) EpayPaymentHelper::yes_no_to_int( $this->instantcapture ) ? "NO_VOID" : "OFF");
                $authorizeResultJson = $epayPaymentApi->authorize($epay_subscription_id, $amount, $order_currency, (string) $renewal_order_id, $instantcapture, $renewal_order_id, EpayPaymentHelper::get_epay_payment_callback_url());
                $authorizeResult = json_decode($authorizeResultJson, true);

                if($authorizeResult['transaction']['state'] == "PENDING")
                {
                    $renewal_order->payment_complete( $authorizeResult['transaction']['id'] );

                    $message = sprintf( __( 'ePay Payment Solutions Subscription was authorized for renewal order %1$s with transaction id %2$s', 'epay-payment-solutions' ), $renewal_order_id, $authorizeResult['transaction']['id'] );
                    $renewal_order->add_order_note( $message );
                    $subscription->add_order_note( $message );

                    return true;

                } else {
                    $error_message = sprintf(
                            __( 'Failed to authorize the ePay Payment Solutions subscription for renewal order %1$s.', 'epay-payment-solutions' ),
                            $renewal_order_id
                    );

                    $renewal_order->add_order_note( $error_message );
                    $subscription->add_order_note( $error_message );

                    return new WP_Error( 'epay_payment_error', $error_message );
                }
			//} catch ( Exception $ex ) {
			//	return new WP_Error( 'epay_payment_error', $ex->getMessage() );
			//}
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
                    /* translators: 1: Error message */
					$message = sprintf( __( 'ePay Payment Solutions Subscription could not be canceled - %s', 'epay-payment-solutions' ), $result->get_error_message( 'epay_payment_error' ) );
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
				if ( EpayPaymentHelper::order_is_subscription( $subscription ) ) {
					$epay_subscription_id = EpayPaymentHelper::get_epay_payment_subscription_id( $subscription );
                    if ( empty( $epay_subscription_id ) ) {
						$order_note = __( 'ePay Payment Solutions Subscription ID was not found', 'epay-payment-solutions' );

						return new WP_Error( 'epay_payment_error', $order_note );
					}

                    $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
                    $deleteSubscriptionResult = $epayPaymentApi->delete_subscription($epay_subscription_id);

					if ( $deleteSubscriptionResult === true ) {
                        /* translators: 1: ePay subscription id */
						$subscription->add_order_note( sprintf( __( 'Subscription successfully Cancelled. - ePay Payment Solutions Subscription Id: %s', 'epay-payment-solutions' ), $epay_subscription_id ) );
					} else {
                        /* translators: 1: ePay subscription id */
						$order_note = sprintf( __( 'Failed to cancel the subscription. ePay Payment Solutions Subscription ID: %s', 'epay-payment-solutions' ), $epay_subscription_id );

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
		public function createPaymentRequestLink( $order_id, $return_obj=false ) {
			$order                               = wc_get_order( $order_id );
			$is_request_to_change_payment_method = EpayPaymentHelper::order_is_subscription( $order );

			$order_currency = $order->get_currency();
			$order_total    = $order->get_total();
			$minorunits     = EpayPaymentHelper::get_currency_minorunits( $order_currency );

			if ( class_exists( 'sitepress' ) ) {
				$merchant_number = EpayPaymentHelper::getWPMLOptionValue( "merchant", $this->merchant );
			} else {
				$merchant_number = $this->merchant;
			}

			$epay_args = array(
				'encoding'       => 'UTF-8',
				'cms'            => EpayPaymentHelper::get_module_header_info(),
				'windowstate'    => "3",
				'mobile'         => EpayPaymentHelper::yes_no_to_int( $this->enablemobilepaymentwindow ),
				'merchantnumber' => $merchant_number,
				'windowid'       => $this->windowid,
				'currency'       => $order_currency,
				'amount'         => EpayPaymentHelper::convert_price_to_minorunits( $order_total, $minorunits, $this->roundingmode ),
				'orderid'        => $this->clean_order_number( $order->get_order_number() ),
                'wcorderid'      => $order_id,
				'accepturl'      => EpayPaymentHelper::get_accept_url( $order ),
				'cancelurl'      => EpayPaymentHelper::get_decline_url( $order, $this->orderstatusaftercancelledpayment),
				'callbackurl'    => apply_filters( 'epay_payment_callback_url', EpayPaymentHelper::get_epay_payment_callback_url() ),
				'mailreceipt'    => $this->authmail,
				'instantcapture' => EpayPaymentHelper::yes_no_to_int( $this->instantcapture ),
				'group'          => $this->group,
				'language'       => EpayPaymentHelper::get_language_code( get_locale() ),
				'ownreceipt'     => EpayPaymentHelper::yes_no_to_int( $this->ownreceipt ),
				'timeout'        => '60'
			);

            if(isset($this->paymenttype) && !empty($this->paymenttype)) {
                $epay_args['paymenttype'] = $this->paymenttype;
            } elseif(isset($this->paymentcollection) && !empty($this->paymentcollection)) {
                $epay_args['paymentcollection'] = $this->paymentcollection;
                $epay_args['lockpaymentcollection'] = 1;
            }

            if($this->ageverificationmode == EpayPaymentHelper::AGEVERIFICATION_ENABLED_ALL || ($this->ageverificationmode == EpayPaymentHelper::AGEVERIFICATION_ENABLED_DK && $order->get_shipping_country() == "DK"))
            {
                $minimumuserage = EpayPaymentHelper::get_minimumuserage($order);
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

			if ( EpayPaymentHelper::woocommerce_subscription_plugin_is_active() && ( EpayPaymentHelper::order_contains_subscription( $order )) ) {
				$epay_args['subscription'] = 1;
			}
            elseif($is_request_to_change_payment_method)
            {
				$epay_args['subscription'] = 2;
                
                $subscription = EpayPaymentHelper::get_subscriptions_for_order($order->parent_id)[$order_id];
                $epay_args['subscriptionid'] =  EpayPaymentHelper::get_epay_payment_subscription_id($subscription);
            }

			if ( class_exists( 'sitepress' ) ) {
				$md5_key = EpayPaymentHelper::getWPMLOptionValue( 'md5key', EpayPaymentHelper::getWPMLOrderLanguage( $order ), $this->md5key );
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

		    // $payment_link   = EpayPaymentHelper::create_epay_payment_payment_html( $epay_args_json, $this->apikey, $this->posid );

            $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
            $request = $epayPaymentApi->createPaymentRequest($epay_args_json);
            $request_data = json_decode($request);

            if($return_obj)
            {
                return $request_data;
            }

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

            // Read raw JSON payload from the payment gateway callback request.
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            $payment_type_map = array ( "Dankort"=>1, "Visa"=>3, "Mastercard"=>4, "JCB"=>6, "Maestro"=>7, "Diners Club"=>8, "American Express"=>9);

            $params['txnid'] = $data['transaction']['id'];
            $params['wcorderid'] = $data['session']['attributes']['wcorderid'];
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
				$is_valid_call = EpayPaymentHelper::validate_epay_payment_callback_params( $params, $this->md5key, $order, $message );
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

			$header = 'X-EPay-System: ' . EpayPaymentHelper::get_module_header_info();
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
				if ( ( EpayPaymentHelper::order_contains_subscription( $order ) || EpayPaymentHelper::order_is_subscription( $order ) ) && isset( $epay_subscription_id ) ) {
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
			$old_transaction_id = EpayPaymentHelper::getEpayPaymentTransactionId( $order );
			if ( empty( $old_transaction_id ) ) {
				$this->add_surcharge_fee_to_order( $order, $params );
                /* translators: 1: ePay transaction id */
				$order->add_order_note( sprintf( __( 'ePay Payment completed with transaction id %s', 'epay-payment-solutions' ), $params['txnid'] ) );
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
			if ( EpayPaymentHelper::order_is_subscription( $order ) ) {
				// Do not cancel subscription if the callback is called more than once !
				$old_epay_subscription_id = EpayPaymentHelper::get_epay_payment_subscription_id( $order );
				if ( $epay_subscription_id != $old_epay_subscription_id ) {
					$this->subscription_cancellation( $order, true );
					$action = 'changed';
                    /* translators: 1: Old subscription ID, 2: New subscription ID */
					$order->add_order_note( sprintf( __( 'ePay Payment Subscription changed from: %1$s to: %2$s', 'epay-payment-solutions' ), $old_epay_subscription_id, $epay_subscription_id ) );
					$order->payment_complete();
					$this->save_subscription_meta( $order, $epay_subscription_id, true );
				} else {
					$action = 'changed (Called multiple times)';
				}
			} else {
				// Do not add surcharge if the callback is called more than once!
				$old_transaction_id     = EpayPaymentHelper::getEpayPaymentTransactionId( $order );
				$epay_transaction_id = $params['txnid'];
				if ( $epay_transaction_id != $old_transaction_id ) {
					$this->add_surcharge_fee_to_order( $order, $params );
					$action = 'activated';
                    /* translators: 1: ePay Subscription ID */
					$order->add_order_note( sprintf( __( 'ePay Payment Subscription activated with subscription id: %s', 'epay-payment-solutions' ), $epay_subscription_id ) );
					$order->payment_complete( $epay_transaction_id );
					$this->save_subscription_meta( $order, $epay_subscription_id, false );
					$this->add_or_update_payment_type_id_to_order( $order, $params['paymenttype'] );
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
			$minorunits               = EpayPaymentHelper::get_currency_minorunits( $order_currency );
			$fee_amount_in_minorunits = $params['txnfee'];
			if ( $fee_amount_in_minorunits > 0 && $this->addfeetoorder === 'yes' ) {
				$fee_amount = EpayPaymentHelper::convert_price_from_minorunits( $fee_amount_in_minorunits, $minorunits );
				$fee        = (object) array(
					'name'      => __( 'Surcharge Fee', 'epay-payment-solutions' ),
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
			$existing_payment_type_id = $order->get_meta( EpayPaymentHelper::EPAY_PAYMENT_PAYMENT_TYPE_ID, true );
			if ( ! isset( $existing_payment_type_id ) || $existing_payment_type_id !== $payment_type_id ) {
				$order->update_meta_data( EpayPaymentHelper::EPAY_PAYMENT_PAYMENT_TYPE_ID, $payment_type_id );
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
				$order->update_meta_data( EpayPaymentHelper::EPAY_PAYMENT_SUBSCRIPTION_ID, $epay_subscription_id );
				$order->save();
			} else {
				// Also store it on the subscriptions being purchased in the order
				$subscriptions = EpayPaymentHelper::get_subscriptions_for_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$subscription->update_meta_data( EpayPaymentHelper::EPAY_PAYMENT_SUBSCRIPTION_ID, $epay_subscription_id );
                    /* translators: 1: ePay subscription ID, 2: WooCommerce order ID */
					$subscription->add_order_note( sprintf( __( 'ePay Payment Solutions Subscription activated with subscription id: %1$s by order %2$s', 'epay-payment-solutions' ), $epay_subscription_id, $order_id ) );
					$subscription->save();
				}
			}
		}

		/**
		 * Handle ePay Payment Actions
		 */
		public function epayPaymentActions() {

            // Ensure required GET parameters exist
            if ( ! isset( $_GET['boclassicaction'], $_GET['boclassicnonce'] ) ) {
                return;
            }

            // Sanitize + verify nonce
            $nonce = sanitize_text_field( wp_unslash( $_GET['boclassicnonce'] ) );
            if ( ! wp_verify_nonce( $nonce, 'boclassic_process_payment_action' ) ) {
                EpayPaymentHelper::add_admin_notices(
                        EpayPaymentHelper::ERROR,
                        __( 'Security check failed.', 'epay-payment-solutions' )
                );
                return;
            }

            // Define a whitelist of allowed actions
            $allowed_actions = array( 'capture', 'delete', 'refund' );

            // Sanitize the requested action and validate it against the whitelist
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $action_raw = wp_unslash( $_GET['boclassicaction'] );
            $action     = sanitize_key( $action_raw );
            if ( ! in_array( $action, $allowed_actions, true ) ) {
                EpayPaymentHelper::add_admin_notices(
                        EpayPaymentHelper::ERROR,
                        __( 'Unsupported action.', 'epay-payment-solutions' )
                );
                return;
            }

            // Determine order ID safely from "post" or "id"
            $order_id = 0;
            if ( isset( $_GET['post'] ) ) {
                $order_id = absint( $_GET['post'] );
            } elseif ( isset( $_GET['id'] ) ) {
                $order_id = absint( $_GET['id'] );
            }
            if ( ! $order_id ) {
                EpayPaymentHelper::add_admin_notices(
                        EpayPaymentHelper::ERROR,
                        __( 'Missing order ID.', 'epay-payment-solutions' )
                );
                return;
            }

            // Sanitize all GET parameters before passing them to the handler
            $params[ 'boclassicnonce' ] = isset($_GET['boclassicnonce']) ? sanitize_text_field( wp_unslash( $_GET['boclassicnonce'] ) ) : false;
            $params[ 'boclassicaction' ] = isset($_GET['boclassicaction']) ? sanitize_text_field( wp_unslash( $_GET['boclassicaction'] ) ) : false;
            $params[ 'post' ] = isset($_GET['post']) ? sanitize_text_field( wp_unslash( $_GET['post'] ) ) : null;
            $params[ 'id' ] = isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : null;
            $params[ 'currency' ] = isset($_GET['currency']) ? sanitize_text_field( wp_unslash( $_GET['currency'] ) ) : false;
            $params[ 'amount' ] = isset($_GET['amount']) ? sanitize_text_field( wp_unslash( $_GET['amount'] ) ) : false;

            // Process the requested action
            $result = $this->processEpayPaymentAction( $params );

            // Handle error or success
            if ( is_wp_error( $result ) ) {
                $message = $result->get_error_message( 'epay_payment_error' );
                $this->_boclassic_log->add( $message );
                EpayPaymentHelper::add_admin_notices( EpayPaymentHelper::ERROR, $message );
                return;
            }

            // Success message + redirect back to the order page
            /* translators: 1: Action name (e.g. refund), 2: Order ID */
            $message = sprintf( __( 'The %1$s action was a success for order %2$s', 'epay-payment-solutions' ), $action, $order_id );
            EpayPaymentHelper::add_admin_notices( EpayPaymentHelper::SUCCESS, $message, true );

            // Redirect back to the correct admin order view
            global $post;
            $url = isset( $post )
                    ? admin_url( 'post.php?post=' . $order_id . '&action=edit' )
                    : admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );

            wp_safe_redirect( $url );
            exit;

		}

		/**
		 * Validate Action params
		 *
		 * @param array $get_params
		 * @param string $failed_message
		 *
		 * @return bool
		 */
		protected function validateEpayPaymentAction($get_params, &$failed_message ) {
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
		protected function processEpayPaymentAction($params ) {
			$failed_message = '';
			if ( ! $this->validateEpayPaymentAction( $params, $failed_message ) ) {
                /* translators: %s is the name of the missing GET parameter */
				return new WP_Error( 'epay_payment_error', sprintf( __( 'The following get parameter was not provided "%s"', 'epay-payment-solutions' ), $failed_message ) );
			}

			try {
				$order_id = $params['post'] ?? $params['id'];
				if ( $order_id == null ) {
					return new WP_Error( 'epay_payment_error', __( 'Both id and post were null', 'epay-payment-solutions' ) );
				}
				$currency = $params['currency'];
				$action   = $params['boclassicaction'];
				$amount   = $params['amount'];

                if (!$this->module_check( $order_id ) )
                {
					return new WP_Error( 'epay_payment_error', __( 'No payment module match', 'epay-payment-solutions' ) );
                }

				switch ( $action ) {
					case 'capture':
						$capture_result = $this->epayPaymentCapturePayment( $order_id, $amount, $currency );

						return $capture_result;
					case 'refund':
						$refund_result = $this->epayPaymentRefundPayment( $order_id, $amount, $currency );

						return $refund_result;
					case 'delete':
						$delete_result = $this->epayPaymentDeletePayment( $order_id );

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
		public function epayPaymentCapturePayment( $order_id, $amount, $currency ) {

			$order = wc_get_order( $order_id );
			if ( empty( $currency ) ) {
				$currency = $order->get_currency();
			}
			$minorunits           = EpayPaymentHelper::get_currency_minorunits( $currency );
			$amount               = str_replace( ',', '.', $amount );
			$amount_in_minorunits = EpayPaymentHelper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
			$transaction_id       = EpayPaymentHelper::getEpayPaymentTransactionId( $order );

			if ( class_exists( 'sitepress' ) ) {
                // $this->posid = EpayPaymentHelper::getWPMLOptionValue( "posid", $this->posid );
			}
            
            $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
            $captureResultJson = $epayPaymentApi->capture($transaction_id, $amount_in_minorunits);

            $captureResult = json_decode($captureResultJson, true);

            if(is_array($captureResult) && $captureResult['success'] == true) {
                do_action( 'epay_payment_after_capture', $order_id );

                $message = sprintf(__( 'Capture action completed successfully for order %s', 'epay-payment' ), $order_id );
                $order->add_order_note( $message );
                $order->payment_complete();
                return true;
            } else {
                // translators: %s is the WooCommerce order ID
                $message = sprintf( __( 'Capture action failed for order %s', 'epay-payment-solutions' ), $order_id );
                $message .= ' - '.$captureResult['message'];
                $order->add_order_note( $message );
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
		public function epayPaymentRefundPayment($order_id, $amount, $currency ) {

			$order = wc_get_order( $order_id );

			if ( empty( $currency ) ) {
				$currency = $order->get_currency();
			}

			$minorunits           = EpayPaymentHelper::get_currency_minorunits( $currency );
			$amount               = str_replace( ',', '.', $amount );
			$amount_in_minorunits = EpayPaymentHelper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
			$transaction_id       = EpayPaymentHelper::getEpayPaymentTransactionId( $order );

			if ( class_exists( 'sitepress' ) ) {
                // $this->posid = EpayPaymentHelper::getWPMLOptionValue( "posid", $this->posid );
			}

            $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
            $refundResultJson = $epayPaymentApi->refund($transaction_id, $amount_in_minorunits);
            $refundResult = json_decode($refundResultJson, true);

            if(is_array($refundResult) && $refundResult['success'] == true) {
                do_action( 'epay_payment_after_refund', $order_id );

                return true;
            } else {
                /* translators: %s is the WooCommerce order ID */
                $message = sprintf( __( 'Refund action failed for order %s', 'epay-payment-solutions' ), $order_id );
                $message .= ' - '.$refundResult['errorCode']['message'];

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
		public function epayPaymentDeletePayment($order_id ) {
			$order          = wc_get_order( $order_id );
			$transaction_id = EpayPaymentHelper::getEpayPaymentTransactionId( $order );

			if ( class_exists( 'sitepress' ) ) {
                // $this->posid = EpayPaymentHelper::getWPMLOptionValue( "posid", $this->posid );
			}

            $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
            $deleteResultJson = $epayPaymentApi->void($transaction_id);
            $deleteResult = json_decode($deleteResultJson, true);

            if(is_array($deleteResult) && $deleteResult['success'] == true) {
                do_action( 'epay_payment_after_delete', $order_id );

                return true;
            } else {
                /* translators: %s is the WooCommerce order ID */
                $message = sprintf( __( 'Delete action failed for order %s', 'epay-payment-solutions' ), $order_id );
                $message .= ' - '.$refundResult['errorCode']['message'];

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
					EpayPaymentHelper::EPAY_PAYMENT_SUBSCRIPTION_ID => array(
						'value'    => EpayPaymentHelper::get_epay_payment_subscription_id( $subscription ),
						'label'    => __( 'ePay subscription token', 'epay-payment-solutions' ),
						'disabled' => false,
					),
				),
			);

			return $payment_meta;
		}

		/**
		 * Add ePay Payment Meta boxes
		 */
		public function epayPaymentMetaBoxes() {
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
				array( &$this, 'epayPaymentMetaBoxPayment'),
				'shop_order',
				'side',
				'high'
			);
			add_meta_box(
				'epay-payment-actions',
				'ePay Payment Solutions',
				array( &$this, 'epayPaymentMetaBoxPayment'),
				'woocommerce_page_wc-orders',
				'side',
				'high'
			);

		}

		/**
		 * Create the ePay Payment Meta Box
		 */
		public function epayPaymentMetaBoxPayment() {
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
					$transaction_id = EpayPaymentHelper::getEpayPaymentTransactionId( $order );
					if ( strlen( $transaction_id ) > 0 ) {
						$html = $this->epayPaymentMetaBoxPaymentHtml( $order, $transaction_id );
					} else {
                        /* translators: %s is the WooCommerce order ID */
						$html = sprintf( __( 'No transaction was found for order %s', 'epay-payment-solutions' ), $order_id );
						$this->_boclassic_log->add( $html );
					}
				} else {
                    /* translators: %s is the WooCommerce order ID */
					$html = sprintf( __( 'The order with id %s could not be loaded', 'epay-payment-solutions' ), $order_id );
					$this->_boclassic_log->add( $html );
				}
			} catch ( Exception $ex ) {
				$html = $ex->getMessage();
				$this->_boclassic_log->add( $html );
			}

            echo wp_kses( $html, EpayPaymentHelper::get_allowed_tags() );
		}

		/**
		 * Create the HTML for the ePay Payment Meta box payment field
		 *
		 * @param WC_Order $order
		 * @param string $transaction_id
		 *
		 * @return string
		 */
		protected function epayPaymentMetaBoxPaymentHtml($order, $transaction_id ) {
			try {

				if ( class_exists( 'sitepress' ) ) {
                    // $this->posid = EpayPaymentHelper::getWPMLOptionValue( "posid", $this->posid );
				}

                $epayPaymentApi = new EpayPaymentApi($this->apikey, $this->posid);
                $transactionResultJson = $epayPaymentApi->payment_info($transaction_id);
                $transactionResult = json_decode($transactionResultJson);

                if(!isset($transactionResult->transaction->id)) {
                    $html = __( 'Get Transaction action failed', 'epay-payment-solutions' );
                    $html .= ' - '.$$transactionResult->errorCode;
                    return $html;
                }

                $transaction   = $transactionResult;

                $currency_code = $transaction->currency;
				$currency      = EpayPaymentHelper::get_iso_code( $currency_code, false );
				$minorunits    = EpayPaymentHelper::get_currency_minorunits( $currency );

				$total_authorized      = EpayPaymentHelper::convert_price_from_minorunits( $transaction->authamount, $minorunits );
				$total_captured        = EpayPaymentHelper::convert_price_from_minorunits( $transaction->capturedamount, $minorunits );
				$total_credited        = EpayPaymentHelper::convert_price_from_minorunits( $transaction->creditedamount, $minorunits );
				$available_for_capture = $total_authorized - $total_captured;
				$transaction_status    = $transaction->status;

				// $card_info     = EpayPaymentHelper::get_cardtype_groupid_and_name( $transaction->cardtypeid );
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
                    
                    $html .= '<img class="boclassic-paymenttype-img" src="'.esc_url(EpayPaymentHelper::get_card_logourl_by_type($transaction->paymentMethodSubType)).'">';

                    if($transaction->paymentMethodType != "CARD")
                    {
                        $html .= '<img class="boclassic-paymenttype-img" src="'.esc_url(EpayPaymentHelper::get_card_logourl_by_type($transaction->paymentMethodType)).'">';
                    }
				// }

				$html .= '<div class="boclassic-transactionid">';
				$html .= '<p>' . __( 'Transaction ID', 'epay-payment-solutions' ) . '</p>';
				$html .= '<p>' . $transaction->transactionid . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-paymenttype">';
				$html .= '<p>' . __( 'Payment Type', 'epay-payment-solutions' ) . '</p>';
				$html .= '<p>' . EpayPaymentHelper::get_card_name_by_type($transaction->paymentMethodSubType) . '</p>';
				$html .= '</div>';

				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Authorized:', 'epay-payment-solutions' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_authorized ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Captured:', 'epay-payment-solutions' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_captured ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Refunded:', 'epay-payment-solutions' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_credited ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '</div>';


				if ( $transaction_status === 'PAYMENT_NEW' || ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) ) {
					$html .= '<div class="boclassic-action-container">';
					$html .= '<input type="hidden" id="boclassic-currency" name="boclassic-currency" value="' . $currency . '">';
					wp_nonce_field( 'boclassic_process_payment_action', 'boclassicnonce' );
					if ( $transaction_status === 'PAYMENT_NEW' ) {
						$html .= '<input type="hidden" id="boclassic-capture-message" name="boclassic-capture-message" value="' . __( 'Are you sure you want to capture the payment?', 'epay-payment-solutions' ) . '" />';
						$html .= '<div class="boclassic-action">';

						if ( $canCaptureRefundDelete ) {
							$html .= '<p>' . $currency . '</p>';
							$html .= '<input type="text" value="' . $available_for_capture . '" id="boclassic-capture-amount" class="boclassic-amount" name="boclassic-amount" />';
							$html .= '<input id="epay-capture-submit" class="button capture" name="boclassic-capture" type="submit" value="' . __( 'Capture', 'epay-payment-solutions' ) . '" />';
						} else {
							$html .= __( 'Your role cannot capture or delete the payment', 'epay-payment-solutions' );
						}
						$html .= '</div>';
						$html .= '<br />';
						if ( $total_captured === 0 ) {
							$html .= '<input type="hidden" id="boclassic-delete-message" name="boclassic-delete-message" value="' . __( 'Are you sure you want to delete the payment?', 'epay-payment-solutions' ) . '" />';
							$html .= '<div class="boclassic-action">';
							if ( $canCaptureRefundDelete ) {
								$html .= '<input id="epay-delete-submit" class="button delete" name="boclassic-delete" type="submit" value="' . __( 'Delete', 'epay-payment-solutions' ) . '" />';
							}
							$html .= '</div>';
						}
					} elseif ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) {
						$html .= '<input type="hidden" id="boclassic-refund-message" name="boclassic-refund-message" value="' . __( 'Are you sure you want to refund the payment?', 'epay-payment-solutions' ) . '" />';
						$html .= '<div class="boclassic-action">';
						$html .= '<p>' . $currency . '</p>';
						$html .= '<input type="text" value="' . $total_captured . '" id="boclassic-refund-amount" class="boclassic-amount" name="boclassic-amount" />';
						if ( $canCaptureRefundDelete ) {
							$html .= '<input id="epay-refund-submit" class="button refund" name="boclassic-refund" type="submit" value="' . __( 'Refund', 'epay-payment-solutions' ) . '" />';
						}
						$html .= '</div>';
						$html .= '<br />';
					}
					$html            .= '</div>';
					$warning_message = __( 'The amount you entered was in the wrong format.', 'epay-payment-solutions' );

					$html .= '<div id="boclassic-format-error" class="boclassic boclassic-error"><strong>' . __( 'Warning', 'epay-payment-solutions' ) . ' </strong>' . $warning_message . '<br /><strong>' . __( 'Correct format is: 1234.56', 'epay-payment-solutions' ) . '</strong></div>';

				}

                if(isset($transaction->history->TransactionHistoryInfo)) {
                    $history_array = $transaction->history->TransactionHistoryInfo;

                    if (isset($history_array) && !is_array($history_array)) {
                        $history_array = array($history_array);
                    }

                    // Sort the history array based on when the history event is created
                    $history_created = array();
                    foreach ($history_array as $history) {
                        $history_created[] = $history->created;
                    }
                    array_multisort($history_created, SORT_ASC, $history_array);

                    if (count($history_array) > 0) {
                        $html .= '<h4>' . __('TRANSACTION HISTORY', 'epay-payment-solutions') . '</h4>';
                        $html .= '<table class="boclassic-table">';

                        foreach ($history_array as $history) {
                            $html .= '<tr class="boclassic-transaction-row-header">';
                            $html .= '<td>' . EpayPaymentHelper::format_date_time($history->created) . '</td>';
                            $html .= '</tr>';
                            if (strlen($history->username) > 0) {
                                $html .= '<tr class="boclassic-transaction-row-header boclassic-transaction-row-header-user">';
                                /* translators: %s ePay username */
                                $html .= '<td>' . sprintf(__('By: %s', 'epay-payment-solutions'), $history->username) . '</td>';
                                $html .= '</tr>';
                            }
                            $html .= '<tr class="boclassic-transaction">';
                            $html .= '<td>' . $history->eventMsg . '</td>';
                            $html .= '</tr>';
                        }
                        $html .= '</table>';
                    }
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
                'epay'           => EPAY_PLUGIN_URL . 'epay-logo.svg',
                'visa'           => EPAY_PLUGIN_URL . 'images/visa.svg',
                'mastercard'     => EPAY_PLUGIN_URL . 'images/mastercard.svg',
                'americanexpress'=> EPAY_PLUGIN_URL . 'images/american_express.svg',
                'dinersclub'     => EPAY_PLUGIN_URL . 'images/diners_club.svg',
                'ideal'          => EPAY_PLUGIN_URL . 'images/ideal.svg',
                'jcb'            => EPAY_PLUGIN_URL . 'images/jcb.svg',
                'maestro'        => EPAY_PLUGIN_URL . 'images/maestro.svg',
                'visa'           => EPAY_PLUGIN_URL . 'images/visa.svg',
                'dankort'        => EPAY_PLUGIN_URL . 'images/dankort.svg',
                'applepay'       => EPAY_PLUGIN_URL . 'images/applepay.svg',
                'mobilepay'      => EPAY_PLUGIN_URL . 'images/mobilepay.svg',
                'googlepay'      => EPAY_PLUGIN_URL . 'images/googlepay.svg',
            ];

            if(preg_match("/epay-logo\.svg/", $this->icon) && is_array($selected_icons) && count($selected_icons))
            {
                $icon_html = '';
                foreach($selected_icons AS $cardname)
                {
			        $icon_html .= '<img src="' . $allicons[$cardname] . '" class="epay-card-icon" />';
                }
            }

            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
        }

        public static function load_subgates($methods) {
            require_once EPAY_PLUGIN_DIR . 'lib/subgates/subgate.php';

            $subgates = self::get_subgates();

            foreach($subgates AS $file_name => $class_name) {
                $file_path = EPAY_PLUGIN_DIR . 'lib/subgates/subgate.php';

                if( file_exists( $file_path ) ) {
                    require_once($file_path);
                    $methods[] = $class_name;
                }
            }

            return $methods;
        }

        public static function get_subgates() {
            return [
                // "mobilepay" => 'EpayPaymentMobilePay',
                // "applepay" => 'EpayPaymentApplePay',
                // "viabill" => 'EpayPaymentViaBill',
                // "paypal" => 'EpayPaymentPayPal',
                // "klarna" => 'EpayPaymentKlarna',
                // "ideal" => 'EpayPaymentIdeal',
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

    function epayPaymentInstance(): EpayPayment {
        return EpayPayment::get_instance();
    }

    epayPaymentInstance();
    epayPaymentInstance()->init_hooks();

	/**
	 * Add the Gateway to WooCommerce
	 **/
	function epayPaymentAddPaymentWoocommerce( $methods ) {
		$methods[] = 'EpayPayment';

		return EpayPayment::load_subgates($methods);
	}

	add_filter( 'woocommerce_payment_gateways', 'epayPaymentAddPaymentWoocommerce' );


    add_action( 'before_woocommerce_init', function () {
        if ( class_exists( FeaturesUtil::class ) ) {
            FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    } );


    function epayPaymentDeclareCartCheckoutBlocksCompatibility() {
        
        // Check if the required class exists
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Declare compatibility for 'cart_checkout_blocks'
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
        
    // Hook the custom function to the 'before_woocommerce_init' action
    add_action('before_woocommerce_init', 'epayPaymentDeclareCartCheckoutBlocksCompatibility');


    // Hook the custom function to the 'woocommerce_blocks_loaded' action
    add_action( 'woocommerce_blocks_loaded', 'epayPaymentRegisterOrderApprovalPaymentMethodType' );

    /**
    * Custom function to register a payment method type

    */


    function epayPaymentRegisterOrderApprovalPaymentMethodType() {
        // Check if the required class exists
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        // Include the custom Blocks Checkout class
        require_once EPAY_PLUGIN_DIR . 'epay-payment-block.php';

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                // Register an instance of My_Custom_Gateway_Blocks
                foreach( wc()->payment_gateways()->payment_gateways() as $payment_gateway )
                {
                    if($payment_gateway instanceof EpayPayment)
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
    function epayPaymentAgeverificationAddProductField()
    {
        global $post;

        wp_nonce_field( 'ep_ageverification_save', 'ep_ageverification_nonce' );

        return woocommerce_wp_select(
            array(
                'id'      => 'ageverification',
                'label'   => __( 'Ageverification', 'epay-payment-solutions' ),
                'options' => EpayPaymentHelper::get_ageverification_options(),
                'value'   => get_post_meta( $post->ID, 'ageverification', true ),
            )
        );
    }
    add_action( 'woocommerce_product_options_general_product_data', 'epayPaymentAgeverificationAddProductField', 10 );

    // Save Ageverification
    function epayPaymentSaveAgeverificationProduct( $post_id ){

        if ( 'product' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['ep_ageverification_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ep_ageverification_nonce'] ) ), 'ep_ageverification_save' )) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $value = isset( $_POST['ageverification'] )
                ? sanitize_text_field( wp_unslash( $_POST['ageverification'] ) )
                : '';

        if ( '' === $value ) {
            delete_post_meta( $post_id, 'ageverification' );
        } else {
            update_post_meta( $post_id, 'ageverification', $value );
        }

    }
    add_action( 'woocommerce_process_product_meta', 'epayPaymentSaveAgeverificationProduct' );

    /*
    * Display Age Verification Category Fields
    */
    add_action('product_cat_add_form_fields', 'epayPaymentAgeverificationAddCategoryField', 10);
    add_action('product_cat_edit_form_fields', 'epayPaymentAgeverificationEditCategoryField', 10, 1);
    
    //Product Cat Create page
    function epayPaymentAgeverificationAddCategoryField() {
        $ep_category_ageverification = '';

        wp_nonce_field( 'ep_cat_ageverification_save', 'ep_cat_ageverification_nonce' );
        ?>
        <div class="form-field">
            <label for="ep_category_ageverification"><?php esc_html_e( 'Ageverification', 'epay-payment-solutions' ); ?></label>

            <select name="ep_category_ageverification" id="ep_category_ageverification">
                <?php foreach ( EpayPaymentHelper::get_ageverification_options() as $key => $option ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $ep_category_ageverification ); ?>>
                        <?php echo esc_html( $option ); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="description"><?php esc_html_e( 'Activate ageverification on category', 'epay-payment-solutions' ); ?></p>
        </div>
        <?php
    }

    function epayPaymentAgeverificationEditCategoryField($term) {

        $term_id = $term->term_id;
        $ep_category_ageverification = get_term_meta( $term_id, 'ep_category_ageverification', true );

        // Nonce til taxonomy-save
        wp_nonce_field( 'ep_cat_ageverification_save', 'ep_cat_ageverification_nonce' );
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="ep_category_ageverification"><?php esc_html_e( 'Ageverification', 'epay-payment-solutions' ); ?></label>
            </th>
            <td>
                <select name="ep_category_ageverification" id="ep_category_ageverification">
                    <?php foreach ( EpayPaymentHelper::get_ageverification_options() as $key => $option ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $ep_category_ageverification ); ?>>
                            <?php echo esc_html( $option ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p class="description"><?php esc_html_e( 'Activate ageverification category', 'epay-payment-solutions' ); ?></p>
            </td>
        </tr>
        <?php
    }

    add_action('create_product_cat', 'epayPaymentSaveAgeverificationCategory', 10, 1);
    add_action('edited_product_cat', 'epayPaymentSaveAgeverificationCategory', 10, 1);

    // Save extra taxonomy fields callback function.
    function epayPaymentSaveAgeverificationCategory($term_id) {

        if ( empty( $_POST ) ) {
            return;
        }

        if ( ! isset( $_POST['ep_cat_ageverification_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ep_cat_ageverification_nonce'] ) ), 'ep_cat_ageverification_save' )) {
            return;
        }

        if ( ! current_user_can( 'manage_product_terms' ) ) {
            return;
        }

        $value = isset( $_POST['ep_category_ageverification'] )
                ? sanitize_text_field( wp_unslash( $_POST['ep_category_ageverification'] ) )
                : '';

        if ( $value === '' ) {
            delete_term_meta( $term_id, 'ep_category_ageverification' );
        } else {
            update_term_meta( $term_id, 'ep_category_ageverification', $value );
        }
    }
}
