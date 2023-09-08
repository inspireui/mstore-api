<?php include_once('common.php');
function revokeProduct()
{
    $website = get_home_url();
    $response = wp_remote_post(PARTNER_API . "/revoke", ["body" => ["host" => $website, "agency_id" => AGENCY_ID]]);
    update_option("mobile-app-builder-product", null);
    if (is_wp_error($response)) {
        return $response->get_error_message();
    }
    $statusCode = wp_remote_retrieve_response_code($response);
    $success = $statusCode == 200;
    if ($success) {
        return true;
    } else {
        return false;
    }
}

function syncProduct($product_id)
{
    $website = get_home_url();
    $response = wp_remote_post(PARTNER_API . "/sync", ["body" => ["host" => $website, "product_id" => $product_id, "agency_id" => AGENCY_ID ]]);
    if (is_wp_error($response)) {
        return $response->get_error_message();
    }
    $statusCode = wp_remote_retrieve_response_code($response);
    $success = $statusCode == 200;
    if ($success) {
        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body, true);
        update_option("mobile-app-builder-product", $body['product_id']);
    } else {
        return false;
    }
    return true;
}

?>