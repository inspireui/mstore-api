
<?php include_once(plugin_dir_path(dirname(dirname(__FILE__))) . 'functions/index.php'); ?>
<?php

if (true) {
    ?>
    <div class="thanks">
        <p>This setting limit the number of product per category to use cache data in home
            screen</p>
    </div>
    <form action="" method="post">
        <?php
        $limit = get_option("mstore_limit_product");
        ?>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <input type="number" data-nonce="<?php echo wp_create_nonce('update_limit_product'); ?>" value="<?php echo (!isset($limit) || $limit == false) ? 10 : esc_attr($limit) ?>"
                   class="mstore-input-class">
        </div>
    </form>

    <div class="thanks">
        <p>The server key firebase is used to push notification when order status changed.</p>
        <p style="font-size: 12px;">(Firebase project -> Project Settings -> Cloud Messaging -> Server key)</p>
    </div>
    <form action="" method="post">
        <?php
        $serverKey = get_option("mstore_firebase_server_key");
        ?>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea class="mstore-input-class mstore-update-firebase-server-key" data-nonce="<?php echo wp_create_nonce('update_firebase_server_key'); ?>"
                      style="height: 120px"><?php echo esc_attr($serverKey) ?></textarea>
        </div>
    </form>

    <p>New Order Message</p>
    <form action="" method="post">
        <?php
        $newOrderTitle = get_option("mstore_new_order_title");
        if (!isset($newOrderTitle) || $newOrderTitle == false) {
            $newOrderTitle = "New Order";
        }
        $newOrderMsg = get_option("mstore_new_order_message");
        if (!isset($newOrderMsg) || $newOrderMsg == false) {
            $newOrderMsg = "Hi {{name}}, Congratulations, you have received a new order! ";
        }
        ?>
        <input type="text" class="mstore-input-class" placeholder="Title" data-nonce="<?php echo wp_create_nonce('update_new_order_title'); ?>" value="<?php echo esc_attr($newOrderTitle); ?>">
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea placeholder="Message" data-nonce="<?php echo wp_create_nonce('update_new_order_message'); ?>" class="mstore-update-new-order-message mstore-input-class"
                      style="height: 120px"><?php echo esc_attr($newOrderMsg); ?></textarea>
        </div>
    </form>

    <p>Order Status Changed Message</p>
    <form action="" method="post">
        <?php
        $statusOrderTitle = get_option("mstore_status_order_title");
        if (!isset($statusOrderTitle) || $statusOrderTitle == false) {
            $statusOrderTitle = "Order Status Changed";
        }
        $statusOrderMsg = get_option("mstore_status_order_message");
        if (!isset($statusOrderMsg) || $statusOrderMsg == false) {
            $statusOrderMsg = "Hi {{name}}, Your order: #{{orderId}} changed from {{prevStatus}} to {{nextStatus}}";
        }
        ?>
        <div class="form-group" style="margin-top:10px;">
            <input type="text" placeholder="Title" data-nonce="<?php echo wp_create_nonce('update_status_order_title'); ?>" value="<?php echo esc_attr($statusOrderTitle); ?>"
                   class="mstore-input-class mstore-update-status-order-title">
        </div>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea placeholder="Message" data-nonce="<?php echo wp_create_nonce('update_status_order_message'); ?>" class="mstore-input-class mstore-update-status-order-message"
                      style="height: 120px"><?php echo esc_attr($statusOrderMsg); ?></textarea>
        </div>
    </form>

    <p>The apple key is used to login on the app via Apple Sign In.</p>
    <form id="appleFileToUploadForm" action="" enctype="multipart/form-data" method="post">
        <?php wp_nonce_field( 'upload_apple_file', 'upload_apple_file_nonce' ); ?>
        <?php 
        if(FlutterAppleSignInUtils::is_file_existed()){
            ?>
            <div class="flex-row items-center justify-between">
                <a  href="<?php echo esc_url(FlutterAppleSignInUtils::get_config_file_url()); ?>" target="_blank" class="mr-2 text-sm text-gray-700"><?=FlutterAppleSignInUtils::get_file_name()?></a>
                <button type="button" data-nonce="<?php echo wp_create_nonce('delete_config_apple_file'); ?>" class="mstore-delete-apple-file">
                    <svg class="w-5 h-5 text-red-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm3.707 11.793a1 1 0 1 1-1.414 1.414L10 11.414l-2.293 2.293a1 1 0 0 1-1.414-1.414L8.586 10 6.293 7.707a1 1 0 0 1 1.414-1.414L10 8.586l2.293-2.293a1 1 0 0 1 1.414 1.414L11.414 10l2.293 2.293Z"/>
                    </svg>
                </button>
            </div>
            <?php
        }else{
            ?>
            <input type="file" id="appleFileToUpload" accept=".p8" name="appleFileToUpload" class="mstore-file-input-class"/>

            <button type="submit" hidden="hidden" class="mstore_button" name='but_apple_sign_in_submit'>Upload</button>
            <?php
                if (isset($_POST['but_apple_sign_in_submit']) && wp_verify_nonce($_POST['upload_apple_file_nonce'], 'upload_apple_file')) {
                    $errMsg = FlutterAppleSignInUtils::upload_file_by_admin($_FILES['appleFileToUpload']);
                    if($errMsg != null){
                        echo "<script type='text/javascript'>
                        alert('You need to upload AuthKey_XXXX.p8 file');
                        </script>";
                    }else{
                        echo "<script type='text/javascript'>
                        location.reload();
                        </script>";
                    }
                }
            ?>
            <?php
        }
        ?>
    </form>

    <p class="mt-5">This token is used for uploading the config files on App Builder.</p>
    <form action="" method="post">
    <?php wp_nonce_field( 'generate_token', 'generate_token_nonce' ); ?>
        <?php
            if (isset($_POST['but_generate']) && wp_verify_nonce($_POST['generate_token_nonce'], 'generate_token')) {
                $user = wp_get_current_user();
                $cookie = generateCookieByUserId($user->ID);
                ?>
                <div class="form-group" style="margin-top:10px;margin-bottom:10px">
                    <textarea class="mstore_input" style="height: 150px"><?php echo esc_attr($cookie) ?></textarea>
                </div>
                <?php
            }
            ?>
        <button type="submit" class="mstore-button-class" name='but_generate'>Generate Token</button>
    </form>
    
    <p class="mt-5">This setting help to speed up the mobile app performance, upload the config_xx.json</p>
    <?php
    FlutterUtils::create_json_folder();
    $configs = FlutterUtils::get_all_json_files();
    if (!empty($configs)) {
        ?>
        <form action="" method="POST">

        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">
                            File
                        </th>
                        <th scope="col" class="px-6 py-3">
                            Download / Delete
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ($configs as $file) {
                    ?>
                    <tr class="bg-white border-b">
                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap">
                        <?php echo esc_attr($file); ?>
                        </th>
                        <td class="px-6 py-4">
                        <a href="<?php echo esc_url(FlutterUtils::get_json_file_url($file)); ?>" target="_blank" class="text-green-700">Download</a>
                            / <a data-id="<?php echo getLangCodeFromConfigFile($file); ?>" data-nonce="<?php echo wp_create_nonce('delete_config_json_file'); ?>" class="text-red-900 mstore-delete-json-file">Delete</a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
        </div>
        </form>
        <?php
    }
    ?>
    <form action="" enctype="multipart/form-data" method="post">
    <?php wp_nonce_field( 'upload_file', 'upload_file_nonce' ); ?>
    <input type="file" id="fileToUpload" accept=".json" name="fileToUpload" class="mstore-file-input-class" data-nonce="<?php echo wp_create_nonce('upload_file'); ?>"/>
        <p style="font-size: 14px; color: #1B9D0D; margin-top:10px">
            <?php
            if (isset($_POST['but_submit'])) {
                if(wp_verify_nonce($_POST['upload_file_nonce'], 'upload_file')){
                    $errMsg = FlutterUtils::upload_file_by_admin($_FILES['fileToUpload']);
                    if($errMsg != null){
                        echo "<script type='text/javascript'>
                        alert('You need to upload config_xx.json file');
                        </script>";
                    }else{
                        echo "<script type='text/javascript'>
                        location.reload();
                          </script>";
                    }
                }else{
                    wp_send_json_error('No Permission',401);
                }
            }
            ?>
        </p>

        <button type="submit" class="mstore-button-class" name='but_submit'>Save</button>
    </form>
    <?php
}
?>