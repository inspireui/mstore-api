<?php
require_once(__DIR__ . '/flutter-base.php');

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package Auction
 */

class FlutterAuction extends FlutterBaseController
{
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_auction';

    /**
     * Register all routes releated with stores
     *
     * @return void
     */
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_auction_routes'));
    }

    public function register_flutter_auction_routes()
    {
        register_rest_route($this->namespace, '/bid', array(
            array(
                'methods' => "POST",
                'callback' => array($this, 'placebid'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));

        register_rest_route(
            $this->namespace,
            '/history' . '/(?P<id>[\d]+)',
            array(
                'args' => array(
                    'id' => array(
                        'description' => __('Unique identifier for the resource.', 'woocommerce'),
                        'type' => 'integer',
                    ),
                ),
                array(
                    'methods' => "GET",
                    'callback' => array($this, 'get_auction_history'),
                    'permission_callback' => function () {
                        return parent::checkApiPermission();
                    }
                ),
            )
        );
    }

    public function placebid($request)
    {
        if (!class_exists('WooCommerce_simple_auction')) {
            return parent::send_invalid_plugin_error("You need to install WooCommerce Simple Auction plugin to use this api");
        }

        $cookie = $request->get_header("User-Cookie");
        if (isset($cookie) && $cookie != null) {
            $user_id = validateCookieLogin($cookie);
            if (is_wp_error($user_id)) {
                return $user_id;
            }
            $user = get_userdata($user_id);
            wp_set_current_user($user_id, $user->user_login);

            if (defined('WC_ABSPATH')) {
                include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
            }

            if (null === WC()->session) {
                $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');

                WC()->session = new $session_class();
                WC()->session->init();
            }
        }

        $json = file_get_contents('php://input');
        $params = json_decode($json, TRUE);

        $product_id = sanitize_text_field($params['product_id']); 
        $bid_value = sanitize_text_field($params['bid_value']); 
        
        $bid = new WC_Bid();
        $result = $bid->placebid($product_id, $bid_value);
        if ($result == false) {
            $notices = WC()->session->get( 'wc_notices', array() );
            $lastNotice = null;
            if (count($notices) > 0 && array_key_exists('success', $notices) && count($notices['success']) > 0) {
                $lastNotice = array_slice($notices['success'], -1)[0];
            }
            if (count($notices) > 0 && array_key_exists('error', $notices) && count($notices['error']) > 0) {
                $lastNotice = array_slice($notices['error'], -1)[0];
            }
            if($lastNotice != null){
                return parent::sendError("invalid_bid", $lastNotice['notice'] ?? 'Error bid' , 400);
            }
        }
        return true;
    }

    public function get_auction_history($request)
    {
        if (!class_exists('WooCommerce_simple_auction')) {
            return parent::send_invalid_plugin_error("You need to install WooCommerce Simple Auction plugin to use this api");
        }

        $product = wc_get_product($request['id']);
        if ($product) {
            if ($product->is_sealed()) {
                return parent::sendError("is_sealed", 'This auction is sealed. Upon auction finish auction history and winner will be available to the public.' , 200);
            }else{
                $datetimeformat = get_option('date_format').' '.get_option('time_format');
                $results = [];
                $auction_history = apply_filters('woocommerce__auction_history_data', $product->auction_history());
                if (!empty($auction_history)) {
                    foreach ($auction_history as $history_value) {
                        $results[] = [
                            'date' => $history_value->date, 
                            'bid' => $history_value->bid, 
                            'displayname' => apply_filters( 'woocommerce_simple_auctions_displayname', get_userdata($history_value->userid)->display_name, $product )
                        ];
                    }
                }
                return $results;
            }
        }else{
            return parent::sendError("invalid", $request['id'] . ' not found' , 400);
        }
    }
}

new FlutterAuction;