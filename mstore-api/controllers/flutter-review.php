<?php
require_once(__DIR__ . '/flutter-base.php');

/*
 * Base REST Controller for flutter
 *
 * @since 1.4.0
 *
 * @package Review
 */

class FlutterReview extends FlutterBaseController
{
    /**
     * Endpoint namespace
     *
     * @var string
     */
    protected $namespace = 'api/flutter_review';

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_flutter_review_routes'));
    }

    public function register_flutter_review_routes()
    {
        register_rest_route($this->namespace, '/products', array(
            array(
                'methods' => "GET",
                'callback' => array($this, 'get_products_to_rate'),
                'permission_callback' => function () {
                    return parent::checkApiPermission();
                }
            ),
        ));
    }

    public function get_products_to_rate($request)
    {
        $cookie = $request->get_header("User-Cookie");
        if (isset($cookie) && $cookie != null) {
            $user_id = validateCookieLogin($cookie);
            if (is_wp_error($user_id)) {
                return $user_id;
            }

            // GET USER ORDERS (COMPLETED + PROCESSING)
            $customer_orders = wc_get_orders( array(
                'limit' => -1,
                'customer_id' => $user_id,
                'status' => array_values( wc_get_is_paid_statuses() ),
                'return' => 'ids',
            ) );
        
            // LOOP THROUGH ORDERS AND GET PRODUCT IDS
            if ( ! $customer_orders ) return [];
            $product_ids = array();
            foreach ( $customer_orders as $customer_order_id ) {
                $order = wc_get_order( $customer_order_id );
                $items = $order->get_items();
                foreach ( $items as $item ) {
                    $product_id = $item->get_product_id();
                    $product_ids[] = $product_id;
                }
            }
            $product_ids = array_unique( $product_ids );

            //get reviewed product ids
            $commentArg = array(
                'user_id' => $user_id,   
            );
            $reviewed_ids = [];
            $comments = get_comments( $commentArg );
            if($comments){
                foreach($comments as $commentData){
                    $reviewed_ids[] = $commentData->comment_post_ID;
                }
            }
            $reviewed_ids = array_unique( $reviewed_ids );

            $result = array_diff($product_ids, $reviewed_ids);

            if(count($result) > 0){
                $controller = new CUSTOM_WC_REST_Products_Controller();
                $req = new WP_REST_Request('GET');
                $params = array('status' =>'published', 'include' => $result, 'page'=>1, 'per_page'=>count($result), 'orderby' => 'id', 'order' => 'DESC');
                $req->set_query_params($params);
                $pRes = $controller->get_items($req);
                return $pRes->get_data();
            }
            return [];
        } else {
            return parent::sendError("no_permission", "You need to add User-Cookie in header request", 400);
        }
    }
}

new FlutterReview;