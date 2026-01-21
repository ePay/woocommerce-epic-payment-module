<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Epay_EPIC_Payment_Blocks extends AbstractPaymentMethodType {

    private $gateway;
    // protected $name = 'Epay_EPIC_Payment';// your payment gateway name

    public function __construct($payment_gateway)
    {
        // var_dump($payment_gateway);
        // echo get_class($payment_gateway);
        // echo "<br><br>";
        // die('hard');
        $this->name = get_class($payment_gateway);
        $this->gateway = $payment_gateway;
    }

    public function initialize() {

        // echo "initialize"; 

        // $epayHandler = new EpayPaymentApi("test_3ef44f6a-2e4b-4a61-80b5-bab8fa005195", "01935342-783e-791d-b757-c83698747d28");
        // $result = $epayHandler->createPaymentRequest(json_encode(array("orderid"=>null, "amount"=>39995, "currency"=>"DKK", "instant"=>"OFF", "accepturl"=>"https://mmn-dev.sprex.dk/temp/epay/hosted/?success", "cancelurl"=>"https://mmn-dev.sprex.dk/temp/epay/hosted/?failure", "callbackurl"=>"https://mmn-dev.sprex.dk/temp/epay/hosted/?notification")));

        // $this->createPaymentRequestLink;

        // $this->settings = get_option( 'woocommerce_my_custom_gateway_settings', [] );
        // $this->gateway = new Epay_EPIC_Payment();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {

        wp_register_script(
            'epay_epic_payment-blocks-integration',
            plugin_dir_url(__FILE__) . 'scripts/checkout.js',
            [
                // 'wc-blocks-checkout',
                'wp-element',
                'wc-blocks-registry',
                // 'wc-settings',
                // 'wp-html-entities',
                // 'wp-i18n',
            ],
            '1.0.0',
            true
        );
        if( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'epay_epic_payment-blocks-integration');

        }
        return [ 'epay_epic_payment-blocks-integration' ];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports' => $this->gateway->supports,
            'icon' => $this->gateway->get_icon(),
        ];
    }
}
?>
