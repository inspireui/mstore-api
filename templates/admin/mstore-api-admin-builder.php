<?php
$website = get_home_url();
global $wpdb;
$api_user = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}woocommerce_api_keys"));

?>
<iframe src="https://fluxbuilder.web.app?url=<?php echo $website ?>&consumerKey=<?php echo $api_user->consumer_key ?>&consumerSecret=<?php echo $api_user->consumer_secret ?>"
        frameborder="0" scrolling="yes" seamless="seamless" style="display:block; width:100%; height:100vh;"></iframe>