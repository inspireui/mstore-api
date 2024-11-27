<?php
require_once(__DIR__ . '/flutter-base.php');

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package FlutterPhonePe
 */

class FlutterPhonePe extends FlutterBaseController
{
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_phonepe';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_phonepe_routes'));
    }

    public function register_flutter_phonepe_routes()
    {
        register_rest_route($this->namespace, '/callback', array(
            array(
                'methods' => "POST",
                'callback' => array($this, 'callback'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

    }

    public function callback($request)
    {
        $json = file_get_contents('php://input');
        $body = json_decode($json, TRUE);
        if($body['response']){
            $decoded = json_decode(base64_decode($body['response']),TRUE);
            $merchant_transaction_id = $decoded['data']['merchantTransactionId'];
            $order_id = substr($merchant_transaction_id, 0, -14);
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() == 'pending') {
                if ($decoded['code'] == 'PAYMENT_SUCCESS') {
                    $order->payment_complete($merchant_transaction_id);
                    $order->add_order_note("PhonePe Payment Solutions: Your payment is successful - merchant transaction id: " . $merchant_transaction_id);
                }else{
                    $order->update_status('failed');
                    $order->add_order_note("PhonePe Payment Solutions: Payment Transaction Failed" . ' - merchant transaction id: ' . $merchant_transaction_id);
                }
            }
        }
        return  true;
    }
}

new FlutterPhonePe;