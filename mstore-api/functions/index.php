<?php
define("ACTIVE_API", "https://active.fluxbuilder.com/api/v1/active");
define("DEACTIVE_API", "https://active.fluxbuilder.com/api/v1/deactive");
define("ACTIVE_TOKEN", "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmb28iOiJiYXIiLCJpYXQiOjE1ODY5NDQ3Mjd9.-umQIC6DuTS_0J0Jj8lcUuUYGjq9OXp3cIM-KquTWX0");

function verifyPurchaseCode($code)
{
    $website = get_home_url();
    $response = wp_remote_post(ACTIVE_API . "?token=" . ACTIVE_TOKEN, ["body" => ["code" => $code, "website" => $website, "plugin" => true], 'sslverify'   => false]);
    if (is_wp_error($response)) {
        return $response->get_error_message();
    }
    $statusCode = wp_remote_retrieve_response_code($response);
    $success = $statusCode == 200;
    if ($success) {
        update_option("mstore_purchase_code", true);
        update_option("mstore_purchase_code_key", $code);
    } else {
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body, true);
        return $body["error"];
    }
    return $success;
}


function one_signal_push_notification($title = '', $message = '', $user_ids = array()) {   
    if(!is_plugin_active('onesignal-free-web-push-notifications/onesignal.php')){
        return false;
    }

    $onesignal_wp_settings = OneSignal::get_onesignal_settings();
    $app_id = $onesignal_wp_settings['app_id'];
    $api_key = $onesignal_wp_settings['app_rest_api_key'];

    if(empty($app_id) || empty($api_key)){
        return false;
    }

    $content      = array(
        "en" => $message
    );
    $headings = array(
        "en" => $title
    );
	
	$external_ids = array();
	foreach($user_ids as $id){
		$external_ids[] = strval($id);
	}

    $fields = array(
        'app_id' => $app_id,
        'data' => array(
			'title' => $title,
			'message' => $message,
		),
        'include_external_user_ids' => $external_ids,
        'contents' => $content,
        'headings' => $headings,
    );
    $bodyAsJson = json_encode($fields);
    $response =  wp_remote_post("https://onesignal.com/api/v1/notifications", array(
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => array("Content-type" => "application/json;charset=UTF-8",
            "Authorization" => "Basic " . $api_key ),
        'body' => $bodyAsJson,
      )
    );
    return wp_remote_retrieve_response_code($response) == 200;
}

function pushNotification($title, $message, $deviceToken)
{
    $serverKey = get_option("mstore_firebase_server_key");
    if (isset($serverKey) && $serverKey != false) {
        $body = ["notification" => ["title" => $title, "body" => $message, "click_action" => "FLUTTER_NOTIFICATION_CLICK"], 
        "data" => ["title" => $title, "body" => $message, "click_action" => "FLUTTER_NOTIFICATION_CLICK"], 
        "apns" => ["headers"=>["apns-priority" => "10"], "payload"=>["aps" => ["sound"=>"default"],],],
        "to" => $deviceToken];
        $headers = ["Authorization" => "key=" . $serverKey, 'Content-Type' => 'application/json; charset=utf-8'];
        $response = wp_remote_post("https://fcm.googleapis.com/fcm/send", ["headers" => $headers, "body" => json_encode($body)]);
        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        return $statusCode == 200;
    }
    return false;
}

function sendNotificationToUser($userId, $orderId, $previous_status, $next_status)
{
    $user = get_userdata($userId);
    $deviceToken = get_user_meta($userId, 'mstore_device_token', true);
    $title = get_option("mstore_status_order_title");
    if (!isset($title) || $title == false) {
        $title = "Order Status Changed";
    }
    $message = get_option("mstore_status_order_message");
    if (!isset($message) || $message == false) {
        $message = "Hi {{name}}, Your order: #{{orderId}} changed from {{prevStatus}} to {{nextStatus}}";
    }
    $previous_status_label = wc_get_order_status_name( $previous_status );
    $next_status_label = wc_get_order_status_name( $next_status );
    
    $message = str_replace("{{name}}", $user->display_name, $message);
    $message = str_replace("{{orderId}}", $orderId, $message);
    $message = str_replace("{{prevStatus}}", $previous_status_label, $message);
    $message = str_replace("{{nextStatus}}", $next_status_label, $message);

    if (isset($deviceToken) && $deviceToken != false) {
        pushNotification($title, $message, $deviceToken);
    }
    one_signal_push_notification($title,$message,array($userId));
}

function trackOrderStatusChanged($id, $previous_status, $next_status)
{
    $order = wc_get_order($id);
    $userId = $order->get_customer_id();
    sendNotificationToUser($userId, $id, $previous_status, $next_status);
    $status = $order->get_status();
    sendNewOrderNotificationToDelivery($id, $status);
}

function sendNewOrderNotificationToDelivery($order_id, $status)
{
    global $wpdb;
    $title = "Order notification";
    $statusLabel = wc_get_order_status_name( $status );
    $message = "The order #{$order_id} has been {$statusLabel}";
    if (is_plugin_active('wc-frontend-manager-delivery/wc-frontend-manager-delivery.php')) {
        if ($status == 'cancelled' || $status == 'refunded') {
            $sql = "SELECT `{$wpdb->prefix}wcfm_delivery_orders`.delivery_boy FROM `{$wpdb->prefix}wcfm_delivery_orders`";
            $sql .= " WHERE 1=1";
            $sql .= " AND order_id = {$order_id}";
            $sql .= " AND is_trashed = 0";
            $sql .= " AND delivery_status = 'pending'";
            $result = $wpdb->get_results($sql);

            $user_ids = array();
            foreach ($result as $item) {
                $user_ids[]=$item->delivery_boy;
                $deviceToken = get_user_meta($item->delivery_boy, 'mstore_delivery_device_token', true);
                if (isset($deviceToken) && $deviceToken != false) {
                    pushNotification($title, $message, $deviceToken);
                }
            }
            one_signal_push_notification($title,$message, $user_ids);
        }

    }

    if (is_plugin_active('delivery-drivers-for-woocommerce/delivery-drivers-for-woocommerce.php')) {
        $order = wc_get_order($order_id);
        $driver_id = $order->get_meta('ddwc_driver_id');
        if ($driver_id) {
            global $WCFM, $wpdb;
            // include upgrade-functions for maybe_create_table;
            if (!function_exists('maybe_create_table')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }
            $table_name = $wpdb->prefix . 'delivery_woo_notification';
            $sql = "CREATE TABLE " . $table_name . "(
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            order_id text NOT NULL,
            delivery_boy text NOT NULL,
            created datetime NOT NULL,
            UNIQUE KEY id (id)
            );";
            maybe_create_table($table_name, $sql);
            $deviceToken = get_user_meta($driver_id, 'mstore_delivery_device_token', true);
            if (isset($deviceToken) && $deviceToken != false) {
                pushNotification($title, $message, $deviceToken);
                $wpdb->insert($table_name, array(
                    'message' => $message,
                    'order_id' => $order_id,
                    'delivery_boy' => $driver_id,
                    'created' => current_time('mysql')
                ));
            }
        }
    }
}

function sendNewOrderNotificationToVendor($order_seller_id, $order_id)
{
    $user = get_userdata($order_seller_id);
    $title = get_option("mstore_new_order_title");
    if (!isset($title) || $title == false) {
        $title = "New Order";
    }
    $message = get_option("mstore_new_order_message");
    if (!isset($message) || $message == false) {
        $message = "Hi {{name}}, Congratulations, you have received a new order! ";
    }
    $message = str_replace("{{name}}", $user->display_name, $message);
    $deviceToken = get_user_meta($order_seller_id, 'mstore_device_token', true);
    if (isset($deviceToken) && $deviceToken != false) {
        pushNotification($title, $message, $deviceToken);
    }
    $managerDeviceToken = get_user_meta($order_seller_id, 'mstore_manager_device_token', true);
    if (isset($managerDeviceToken) && $managerDeviceToken != false) {
        pushNotification($title, $message, $managerDeviceToken);
        if (is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php')) {
            wcfm_message_on_new_order($order_id);
        }
    }
    one_signal_push_notification($title, $message, array($order_seller_id));
}

function wcfm_message_on_new_order($order_id)
{
    global $WCFM, $wpdb;
    if (get_post_meta($order_id, '_wcfm_new_order_notified', true)) return;
    $author_id = -2;
    $author_is_admin = 1;
    $author_is_vendor = 0;
    $message_to = 0;
    $order = wc_get_order($order_id);

    // Admin Notification
    $wcfm_messages = sprintf(__('You have received an Order <b>#%s</b>', 'wc-frontend-manager'), '<a target="_blank" class="wcfm_dashboard_item_title" href="' . get_wcfm_view_order_url($order_id) . '">' . $order->get_order_number() . '</a>');
    $WCFM->wcfm_notification->wcfm_send_direct_message($author_id, $message_to, $author_is_admin, $author_is_vendor, $wcfm_messages, 'order', apply_filters('wcfm_is_allow_order_notification_email', false));

    $order_vendors = array();
    foreach ($order->get_items() as $item_id => $item) {
        if (version_compare(WC_VERSION, '4.4', '<')) {
            $product = $order->get_product_from_item($item);
        } else {
            $product = $item->get_product();
        }
        $product_id = 0;
        if (is_object($product)) {
            $product_id = $item->get_product_id();
        }
        if ($product_id) {
            $author_id = -1;
            $message_to = wcfm_get_vendor_id_by_post($product_id);

            if ($message_to) {
                if (apply_filters('wcfm_is_allow_itemwise_notification', true)) {
                    $wcfm_messages = sprintf(__('You have received an Order <b>#%s</b> for <b>%s</b>', 'wc-frontend-manager'), '<a target="_blank" class="wcfm_dashboard_item_title" href="' . get_wcfm_view_order_url($order_id) . '">' . $order->get_order_number() . '</a>', get_the_title($product_id));
                } elseif (!in_array($message_to, $order_vendors)) {
                    $wcfm_messages = sprintf(__('You have received an Order <b>#%s</b>', 'wc-frontend-manager'), '<a target="_blank" class="wcfm_dashboard_item_title" href="' . get_wcfm_view_order_url($order_id) . '">' . $order->get_order_number() . '</a>');
                } else {
                    continue;
                }
                $wcfm_messages = apply_filters('wcfm_new_order_vendor_notification_message', $wcfm_messages, $order_id, $message_to);
                $WCFM->wcfm_notification->wcfm_send_direct_message($author_id, $message_to, $author_is_admin, $author_is_vendor, $wcfm_messages, 'order', apply_filters('wcfm_is_allow_order_notification_email', false));
                $order_vendors[$message_to] = $message_to;
                do_action('wcfm_after_new_order_vendor_notification', $message_to, $product_id, $order_id);
            }
        }
    }

    update_post_meta($order_id, '_wcfm_new_order_notified', 'yes');
}

function trackNewOrder($order_id)
{
    $order = wc_get_order($order_id);
    if (is_plugin_active('dokan-lite/dokan.php')) {
        if (dokan_is_order_already_exists($order_id)) {
            return;
        }

        $order_seller_id = dokan_get_seller_id_by_order($order_id);
        if (isset($order_seller_id) && $order_seller_id != false) {
            sendNewOrderNotificationToVendor($order_seller_id, $order_id);
        }
    }

    if (is_plugin_active('wc-multivendor-marketplace/wc-multivendor-marketplace.php')) {
        $processed_vendors = array();
        if (function_exists('wcfm_get_vendor_store_by_post')) {
            $order = wc_get_order($order_id);
            if (is_a($order, 'WC_Order')) {
                $items = $order->get_items('line_item');
                if (!empty($items)) {
                    foreach ($items as $order_item_id => $item) {
                        $line_item = new WC_Order_Item_Product($item);
                        $product = $line_item->get_product();
                        $product_id = $line_item->get_product_id();
                        $vendor_id = wcfm_get_vendor_id_by_post($product_id);

                        if (!$vendor_id) continue;
                        if (in_array($vendor_id, $processed_vendors)) continue;

                        $store_name = wcfm_get_vendor_store($vendor_id);
                        if ($store_name) {
                            $processed_vendors[$vendor_id] = $vendor_id;
                        }
                    }
                }
            }
        }
        if (!empty($processed_vendors)) {
            foreach ($processed_vendors as $vendor_id) {
                sendNewOrderNotificationToVendor($vendor_id, $order_id);
            }
        }
    }

}

function getAddOns($categories)
{
    $addOns = [];
    if (is_plugin_active('woocommerce-product-addons/woocommerce-product-addons.php')) {
        $addOnGroup = WC_Product_Addons_Groups::get_all_global_groups();
        foreach ($addOnGroup as $addOn) {
            $cateIds = array_keys($addOn["restrict_to_categories"]);
            if (count($cateIds) == 0) {
                $addOns = array_merge($addOns, $addOn["fields"]);
                break;
            }
            $isSupported = false;
            foreach ($categories as $cate) {
                if (in_array($cate["id"], $cateIds)) {
                    $isSupported = true;
                    break;
                }
            }
            if ($isSupported) {
                $addOns = array_merge($addOns, $addOn["fields"]);
            }
        }
    }

    return $addOns;
}

function deactiveMStoreApi()
{
    $website = get_home_url();
    $code = get_option('mstore_purchase_code_key');
    $response = wp_remote_post(DEACTIVE_API . "?token=" . ACTIVE_TOKEN, ["body" => ["code" => $code, "website" => $website]]);
    $statusCode = wp_remote_retrieve_response_code($response);
    $success = $statusCode == 200;
    if ($success) {
        update_option("mstore_purchase_code", false);
        update_option("mstore_purchase_code_key", "");
    } else {
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body, true);
        return $body["error"];
    }
    return $success;
}

function parseMetaDataForBookingProduct($product)
{
    if (is_plugin_active('woocommerce-appointments/woocommerce-appointments.php')) {
        //add meta_data to $_POST to use for booking product
        $meta_data = [];
        foreach ($product["meta_data"] as $key => $value) {
            if ($value["key"] == "staff_ids" && isset($value["value"]) && is_array($value["value"])) {
                $staffs = json_decode($value["value"], true);
                if (count($staffs) > 0) {
                    $meta_data["wc_appointments_field_staff"] = sanitize_text_field($staffs[0]);
                }
            } elseif ($value["key"] == "product_id") {
                $meta_data["add-to-cart"] = sanitize_text_field($value["value"]);
            } else {
                $meta_data[$value["key"]] = sanitize_text_field($value["value"]);
            }
        }
        $_POST = $meta_data;
    }
}

function isPHP8()
{
    return version_compare(phpversion(), '8.0.0') >= 0;
}

function customProductResponse($response, $object, $request)
{
    global $woocommerce_wpml;

    $is_purchased = false;
    if (isset($request['user_id'])) {
        $user_id = $request['user_id'];
        $user_data = get_userdata($user_id);
        if ($user_data) {
            $user_email = $user_data->user_email;
            $is_purchased = wc_customer_bought_product($user_email, $user_id, $response->data['id']);
        }
    }
    $response->data['is_purchased'] = $is_purchased;

    if (!empty($woocommerce_wpml->multi_currency) && !empty($woocommerce_wpml->settings['currencies_order'])) {

        $type = $response->data['type'];
        $price = $response->data['price'];

        foreach ($woocommerce_wpml->settings['currency_options'] as $key => $currency) {
            $rate = (float)$currency["rate"];
            $response->data['multi-currency-prices'][$key]['price'] = $rate == 0 ? $price : sprintf("%.2f", $price * $rate);
        }
    }

    $product = wc_get_product($response->data['id']);

    /* Update price for product variant */
    if ($product->is_type('variable')) {
        $prices = $product->get_variation_prices();
        if (!empty($prices['price'])) {
            $response->data['price'] = current($prices['price']);
            $response->data['regular_price'] = current($prices['regular_price']);
            $response->data['sale_price'] = current($prices['sale_price']);
            $response->data['min_price'] = $product->get_variation_price();
            $response->data['max_price'] = $product->get_variation_price('max');
            
            if(!$response->data['min_price']){
                $response->data['min_price'] = '0';
            }
            if(!$response->data['max_price']){
                $response->data['max_price'] = '0';
            }
            $variations = $response->data['variations'];
            $variation_arr = array();
            foreach($variations as $variation_id){
                $variation_data = array();
                $variation_p = new WC_Product_Variation($variation_id);
                $variation_data['id'] = $variation_id;
                $variation_data['product_id'] = $product->get_id();
                $variation_data['price'] = $variation_p->get_price();
                $variation_data['regular_price'] = $variation_p->get_regular_price() ;
                $variation_data['sale_price'] =$variation_p->get_sale_price() ;
                $variation_data['date_on_sale_from'] = $variation_p->get_date_on_sale_from();
                $variation_data['date_on_sale_to'] = $variation_p->get_date_on_sale_to();
                $variation_data['on_sale'] = $variation_p->is_on_sale();
                $variation_data['in_stock'] =$variation_p->is_in_stock() ;
                $variation_data['stock_quantity'] = $variation_p->get_stock_quantity();
                $variation_data['stock_status'] = $variation_p->get_stock_status();
                $feature_image = wp_get_attachment_image_src( $variation_p->get_image_id(), 'single-post-thumbnail' );
                $variation_data['feature_image'] = $feature_image ? $feature_image[0] : null;
        
                $attr_arr = array();
                $variation_attributes = $variation_p->get_attributes();
                foreach($variation_attributes as $k=>$v){
                    $attr_data = array();
                    $attr_data['name'] = $k;
                    $attr_data['slug'] = $v;
                    $meta = get_post_meta($variation_id, 'attribute_'.$k, true);
                    $term = get_term_by('slug', $meta, $k);
                    $attr_data['attribute_name'] = $term->name;
                    $attr_arr[]=$attr_data;
                }
                $variation_data['attributes_arr'] = $attr_arr;
                $variation_arr[]=$variation_data;
            }
            $response->data['variation_products'] = $variation_arr;
        }
    }

    $attributes = $product->get_attributes();
    $attributesData = [];
    foreach ($attributes as $attr) {
        $check = $attr->is_taxonomy();
        if ($check) {
            $taxonomy = $attr->get_taxonomy_object();
            $label = $taxonomy->attribute_label;
        } else {
            $label = $attr->get_name();
        }
        $attrOptions = wc_get_product_terms($response->data['id'], $attr["name"]);
        $attr["options"] = empty($attrOptions) ? array_map(function ($v){
            return ['name'=>$v, 'slug' => $v];
        },$attr["options"]) : $attrOptions;
        $attributesData[] = array_merge($attr->get_data(), ["label" => $label]);
    }
    $response->data['attributesData'] = $attributesData;

    /* Product Add On */
    $addOns = getAddOns($response->data["categories"]);
    $meta_data = $response->data['meta_data'];
    $new_meta_data = [];
    foreach ($meta_data as $meta_data_item) {
        if ($meta_data_item->get_data()["key"] == "_product_addons") {
            if(class_exists('WC_Product_Addons_Helper')){
                $product_addons = WC_Product_Addons_Helper::get_product_addons( $response->data['id'], false );
                $meta_data_item->__set("value", count($addOns) == 0 ? $product_addons : array_merge($product_addons, $addOns));
                $meta_data_item->apply_changes();
            }
        }
        $new_meta_data[] = $meta_data_item;
    }
    $response->data['meta_data'] = $new_meta_data;

    /* Product Booking */
    if (is_plugin_active('woocommerce-appointments/woocommerce-appointments.php')) {
        $terms = wp_get_post_terms($product->id, 'product_type');
        if ($terms != false && count($terms) > 0 && $terms[0]->name == 'appointment') {
            $response->data['type'] = 'appointment';
        }
    }

    return $response;
}

function getLangCodeFromConfigFile ($file) {
    return str_replace('config_', '', str_replace('.json', '',$file));
}

function generateCookieByUserId($user_id, $seconds = 1209600){
    $expiration = time() + 365 * DAY_IN_SECONDS;
    $cookie = wp_generate_auth_cookie($user_id, $expiration, 'logged_in');
    return $cookie;
}

function validateCookieLogin($cookie){
    if(isset($cookie) && strlen($cookie) > 0){
        $userId = wp_validate_auth_cookie($cookie, 'logged_in');
        if($userId == false){
            return new WP_Error("invalid_login", "Your session has expired. Please logout and login again.", array('status' => 401));
        }else{
            return $userId;
        }
    }else{
        return new WP_Error("invalid_login", "Cookie is required", array('status' => 401));
    }
}
?>