<?php
require_once(__DIR__ . '/flutter-base.php');

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package FlutterIyzico
 */

class FlutterIyzico extends FlutterBaseController
{
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_iyzico';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_iyzico_routes'));
    }

    public function register_flutter_iyzico_routes()
    {
        register_rest_route($this->namespace, '/checkout', array(
            array(
                'methods' => "POST",
                'callback' => array($this, 'checkout'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

        register_rest_route($this->namespace, '/payment_success', array(
            array(
                'methods' => "GET",
                'callback' => array($this, 'payment_success'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

    }

    public function payment_success($request)
    {
        if (!class_exists('Iyzico\IyzipayWoocommerce\Pwi\Pwi')) {
            return parent::send_invalid_plugin_error("You need to install iyzico WooCommerce plugin to use this api");
        }

        $options = $this->createOptions();
		$req = new \Iyzipay\Request\RetrieveCheckoutFormRequest();
		$req->setLocale( 'en' );
		$req->setToken( $request['token'] );
        $req->setConversationId( $request['order_id'] );

		$checkoutFormResult = \Iyzipay\Model\CheckoutForm::retrieve( $req, $options );

        if ( ! $checkoutFormResult || $checkoutFormResult->getStatus() !== 'success' ) {
			return parent::sendError("invalid_data", 'Payment process failed. Please try again or choose a different payment method.', 400);
		}else{
            $order_id = $checkoutFormResult->getConversationId();
            $order = wc_get_order($order_id);
            if (!$order) {
                return parent::sendError("invalid_data", 'Order '.$order_id.' not found.', 400); 
            }
            if ( $checkoutFormResult->getPaymentStatus() === 'FAILURE' && $checkoutFormResult->getStatus() === 'success' ) {
                return parent::sendError("invalid_data", 'Payment failed.', 400); 
            }

            $message = "Payment ID: " . $checkoutFormResult->getPaymentId();
            $order->add_order_note( $message, 0, true );

            if ( $options->getBaseUrl() === "https://sandbox-api.iyzipay.com" ) {
                $message = '<strong><p style="color:red">TEST ÖDEMESİ</a></strong>';
                $order->add_order_note( $message, 0, true );
            }

            if ( $checkoutFormResult->getPaymentStatus() === 'SUCCESS' && $checkoutFormResult->getStatus() === 'success' ) {
                $order->payment_complete();
                $order->save();
    
                $orderStatus = $this->getOrderStatusSetting();
    
                if ( $orderStatus !== 'default' && ! empty( $orderStatus ) ) {
                    $order->update_status( $orderStatus );
                }
            }
    
            if ( $checkoutFormResult->getPaymentStatus() === "INIT_BANK_TRANSFER" && $checkoutFormResult->getStatus() === "success" ) {
                $order->update_status( "on-hold" );
                $orderMessage = __( 'iyzico Bank transfer/EFT payment is pending.', 'woocommerce-iyzico' );
                $order->add_order_note( $orderMessage, 0, true );
            }
    
            if ( $checkoutFormResult->getPaymentStatus() === "PENDING_CREDIT" && $checkoutFormResult->getStatus() === "success" ) {
                $order->update_status( "on-hold" );
                $orderMessage = __( 'The shopping credit transaction has been initiated.', 'woocommerce-iyzico' );
                $order->add_order_note( $orderMessage, 0, true );
            }
        }

        return  true;
    }

    public function checkout($request)
    {
        if (!class_exists('Iyzico\IyzipayWoocommerce\Pwi\Pwi')) {
            return parent::send_invalid_plugin_error("You need to install iyzico WooCommerce plugin to use this api");
        }

        $user_id = 1;

        $json = file_get_contents('php://input');
        $body = json_decode($json, TRUE);
        $order_id = sanitize_text_field($body['order_id']);

        $order    = wc_get_order( $order_id );

        $callback_url = $order->get_checkout_order_received_url();
        $request = new \Iyzipay\Request\CreateCheckoutFormInitializeRequest();
        $request->setLocale('en');
        $request->setConversationId($order_id);
        $request->setPrice(round( $order->get_total(), 2 ));
        $request->setPaidPrice(round( $order->get_total(), 2 ) );
        $request->setCurrency(\Iyzipay\Model\Currency::TL);
        $request->setBasketId($order_id);
        $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);
        $request->setPaymentSource( 'App' );
        $request->setCallbackUrl($callback_url);
        $request->setForceThreeDS( "0" );

        $customer =  get_userdata($user_id);

        $checkoutSettings   = new \Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings();
        $priceHelper      = new \Iyzico\IyzipayWoocommerce\Common\Helpers\PriceHelper();
        $logger = new \Iyzico\IyzipayWoocommerce\Common\Helpers\Logger();
		$checkoutDataFactory = new \Iyzico\IyzipayWoocommerce\Common\Helpers\DataFactory( $priceHelper, $checkoutSettings, $logger );

        $checkoutData = $checkoutDataFactory->prepareCheckoutData( $customer, $order, array() );

        $request->setBuyer( $checkoutData['buyer'] );
		$request->setBillingAddress( $checkoutData['billingAddress'] );
		isset( $checkoutData['shippingAddress'] ) ? $request->setShippingAddress( $checkoutData['shippingAddress'] ) : $request->setShippingAddress( $checkoutData['billingAddress'] );
        $firstBasketItem = new \Iyzipay\Model\BasketItem();
        $firstBasketItem->setId($order_id);
        $firstBasketItem->setName("App checkout order ".$order_id);
        $firstBasketItem->setCategory1("App");
        $firstBasketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
        $firstBasketItem->setPrice(round( $order->get_total(), 2 ));
        $request->setBasketItems( [$firstBasketItem] );

        $options = $this->createOptions();
        $checkoutFormResponse = Iyzipay\Model\CheckoutFormInitialize::create( $request, $options );

        if ( $checkoutFormResponse->getStatus() === "success" ) {
            return [
                'payment_url' => $checkoutFormResponse->getPaymentPageUrl(),
                'callback_url' => $callback_url
            ];
		} else if($checkoutFormResponse->getErrorMessage()){
            return parent::sendError("invalid_data", $checkoutFormResponse->getErrorMessage(), 400);
		}else{
            $rawResult         = $checkoutFormResponse->getRawResult();
            return parent::sendError("invalid_data", $rawResult, 400);
        }
    }

    private function createOptions(){
        $checkoutSettings   = new \Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings();
		$settings = $checkoutSettings->getSettings();

        $options = new \Iyzipay\Options();
		$options->setApiKey( $settings['api_key'] );
		$options->setSecretKey( $settings['secret_key'] );
		$options->setBaseUrl( $settings['api_type'] );

        return $options;
    }

    private function getOrderStatusSetting(){
        $checkoutSettings   = new \Iyzico\IyzipayWoocommerce\Checkout\CheckoutSettings();
		$settings = $checkoutSettings->getSettings();

        return $settings['order_status'];
    }
}

new FlutterIyzico;