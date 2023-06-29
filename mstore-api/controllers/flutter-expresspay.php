<?php
require_once(__DIR__ . '/flutter-base.php');

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package PayStack
 */

class FlutterExpressPay extends FlutterBaseController
{
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_expresspay';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_expresspay_routes'));
    }

    public function register_flutter_expresspay_routes()
    {
        register_rest_route($this->namespace, '/verify_payment', array(
            array(
                'methods' => "POST",
                'callback' => array($this, 'verify_payment'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));
    }

    public function verify_payment($request)
    {

        if (!is_plugin_active('woo-web-payment-getaway/web-payment-gateway.php')) {
            return parent::sendError("invalid_plugin", "You need to install ShahbandrPay plugin to use this api", 404);
        }

        $json = file_get_contents('php://input');
        $body = json_decode($json, TRUE);
        $order_id = sanitize_text_field($body['order_id']);
        $transaction_id = sanitize_text_field($body['transaction_id']);

        $options  = get_option( 'woocommerce_shahbandrpay_settings');
        $password = $options['password'];
        $secret   = $options['secret'];
        $new_order_status = !empty($options['new_order_status']) ? $options['new_order_status'] : 'processing';

        $hash = sha1(md5(strtoupper($transaction_id . $password)));
        $url = 'https://pay.expresspay.sa/api/v1/payment/status';

        $main_json = [
            "merchant_key" => $secret,
            "payment_id" => $transaction_id,
            "hash" => $hash
        ];

        $getter = curl_init($url); //init curl
        curl_setopt($getter, CURLOPT_POST, 1); //post
        curl_setopt($getter, CURLOPT_POSTFIELDS, json_encode($main_json)); //json
        curl_setopt($getter, CURLOPT_HTTPHEADER, array('Content-Type:application/json')); //header
        curl_setopt($getter, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($getter);

        $response = json_decode($result, true);

        if ( $response['status'] == 'settled' ) {
            $order = wc_get_order($order_id);
            update_post_meta( $order_id, 'trans_id', $transaction_id );
            update_post_meta( $order_id, 'trans_date', $response['date'] );

            $order->update_status( $new_order_status, 'ShahbandrPay successfully paid');
            $order->add_order_note( 'ShahbandrPay successfully paid' );
            return ['success' => true];
        } else {
            return ['message' => 'expresspay error'];
        }
    }
}

new FlutterExpressPay;