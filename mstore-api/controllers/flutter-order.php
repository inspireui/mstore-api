<?php

class CUSTOM_WC_REST_Orders_Controller extends WC_REST_Orders_Controller
{

    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_order';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_woo_routes'));
    }

    public function register_flutter_woo_routes()
    {
        register_rest_route($this->namespace, '/create', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_new_order'),
                'permission_callback' => array($this, 'custom_create_item_permissions_check'),
                'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
            ),
            'schema' => array($this, 'get_public_item_schema'),
        ));

        //some reasons can't use PUT method
        register_rest_route(
            $this->namespace,
            '/update' . '/(?P<id>[\d]+)',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'woocommerce'),
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'update_item'),
                    'permission_callback' => array($this, 'custom_create_item_permissions_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
		
		register_rest_route(
            $this->namespace,
            '/update' . '/(?P<id>[\d]+)',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'woocommerce'),
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_item'),
                    'permission_callback' => array($this, 'custom_create_item_permissions_check'),
                    'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
                ),
                'schema' => array($this, 'get_public_item_schema'),
            )
        );
    }

    function custom_create_item_permissions_check($request)
    {
        $cookie = $request->get_header("User-Cookie");
        $json = file_get_contents('php://input');
        $params = json_decode($json, TRUE);
        if (isset($cookie) && $cookie != null) {
            $user_id = validateCookieLogin($cookie);
            if (is_wp_error($user_id)) {
                return false;
            }
            $params["customer_id"] = $user_id;
            wp_set_current_user($user_id);
            $request->set_body_params($params);
            return true;
        } else {
            $params["customer_id"] = 0;
            $request->set_body_params($params);
            return true;
        }
    }

    function create_new_order($request)
    {
        $params = $request->get_body_params();
        if (isset($params['fee_lines']) && count($params['fee_lines']) > 0) {
            $fee_name = $params['fee_lines'][0]['name'];
            if ($fee_name == 'Via Wallet') {
                if (is_plugin_active('woo-wallet/woo-wallet.php')) {
                    $balance = woo_wallet()->wallet->get_wallet_balance($params["customer_id"], 'Edit');
                    $total = $params['fee_lines'][0]['total'];
                    if (floatval($balance) < floatval($total) * (-1)) {
                        return new WP_Error("invalid_wallet", "The wallet is not enough to checkout", array('status' => 400));
                    }
                }
            }
        }
        if (isset($params['payment_method']) && $params['payment_method'] == 'wallet' && isset($params['total'])) {
            if (is_plugin_active('woo-wallet/woo-wallet.php')) {
                $balance = woo_wallet()->wallet->get_wallet_balance($params["customer_id"], 'Edit');
                if (floatval($balance) < floatval($params['total'])) {
                    return new WP_Error("invalid_wallet", "The wallet is not enough to checkout", array('status' => 400));
                }
            }
        }

        $response = $this->create_item($request);
		$data = $response->get_data();

        // Send the customer invoice email.
       	$order = wc_get_order( $data['id'] );
        if($order->get_payment_method() == 'cod' || $order->has_status( array( 'processing', 'completed' ) )){
            WC()->payment_gateways();
            WC()->shipping();
            WC()->mailer()->customer_invoice( $order );
            WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order->get_id(), $order, true );
            add_filter( 'woocommerce_new_order_email_allows_resend', '__return_true' );
            WC()->mailer()->emails['WC_Email_New_Order']->trigger( $order->get_id(), $order, true );
        }

        //add order note if payment method is tap
        if (isset($params['payment_method']) && $params['payment_method'] == 'tap' && isset($params['transaction_id'])) {
            $order->payment_complete();
            $order->add_order_note('Tap payment successful.<br/>Tap ID: '.$params['transaction_id']);
        }
		
        return  $response;
    }
}

new CUSTOM_WC_REST_Orders_Controller();