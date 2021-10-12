<?php include_once(plugin_dir_path(dirname(dirname(__FILE__))) . 'functions/index.php'); ?>

    <div class="wrap">
        <div class="thanks">
            <p style="font-size: 16px;">Thank you for installing Mstore API plugins.</p>
            <?php
            $verified = get_option("mstore_purchase_code");
            if (isset($verified) && $verified == "1") {
                ?>
                <p style="font-size: 16px;color: green">Your website have been license and all the API features are
                    unlocked. </p>
                <?php
            }
            ?>
        </div>
    </div>
<?php
$verified = get_option("mstore_purchase_code");
if (!isset($verified) || $verified === "" || $verified === false) {
    ?>
    <form action="" enctype="multipart/form-data" method="post" style="margin-bottom:50px">
        <?php
        if (isset($_POST['but_verify'])) {
            $verified = verifyPurchaseCode($_POST['code']);

            if ($verified !== true) {
                ?>
                <p style="font-size: 16px;color: red;"><?php echo esc_attr($verified); ?></p>
                <?php
            } else {
                ?>
                <p style="font-size: 16px;color: green">Your website have been license and all the API features are
                    unlocked. </p>
                <?php
            }
        }
        ?>
        <div class="form-group" style="margin-top:10px">
            <input name="code" placeholder="Purchase Code" type="text" class="mstore_input">
        </div>
        <div>
            <h4 class="mstore_title">What is purchase code?</h4>
            <ul class="mstore_list">
                <li>A purchase code is a license identifier which is issued with the item once a purchase has been made
                    and included with your download.
                </li>
                <li>One purchase code is used for one website only.</li>
                <li>It's required to active to unlock the API use to connect with the app.</li>
            </ul>
            <h4 class="mstore_title">How can I get my purchase code? </h4>
            <ul class="mstore_list mstore_number_list">
                <li>Log into your Envato Market account.</li>
                <li>Hover the mouse over your username at the top of the screen.</li>
                <li>Click ‘Downloads’ from the drop-down menu.`</li>
                <li>Click ‘License certificate & purchase code’ (available as PDF or text file).</li>
            </ul>
            <a class="mstore_link"
               href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-">https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code-</a>
        </div>
        <button type="submit" class="mstore_button" name='but_verify'>Verify</button>
    </form>
    <?php
}

if (isset($verified) && $verified == "1") {
    ?>
    <div class="thanks">
        <p style="font-size: 16px;">This setting limit the number of product per category to use cache data in home
            screen</p>
    </div>
    <form action="" method="post">
        <?php
        $limit = get_option("mstore_limit_product");
        ?>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <input type="number" value="<?php echo (!isset($limit) || $limit == false) ? 10 : esc_attr($limit) ?>"
                   class="mstore-update-limit-product">
        </div>
    </form>

    <div class="thanks">
        <p style="font-size: 16px;">The server key firebase is used to push notification when order status changed.</p>
        <p style="font-size: 12px;">(Firebase project -> Project Settings -> Cloud Messaging -> Server key)</p>
    </div>
    <form action="" method="post">
        <?php
        $serverKey = get_option("mstore_firebase_server_key");
        ?>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea class="mstore-update-firebase-server-key mstore_input"
                      style="height: 120px"><?php echo esc_attr($serverKey) ?></textarea>
        </div>
    </form>

    <div class="thanks">
        <p style="font-size: 16px;">New Order Message</p>
    </div>
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
        <div class="form-group" style="margin-top:10px;">
            <input type="text" placeholder="Title" value="<?php echo esc_attr($newOrderTitle); ?>"
                   class="mstore-update-new-order-title mstore_input">
        </div>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea placeholder="Message" class="mstore-update-new-order-message mstore_input"
                      style="height: 120px"><?php echo esc_attr($newOrderMsg); ?></textarea>
        </div>
    </form>

    <div class="thanks">
        <p style="font-size: 16px;">Order Status Changed Message</p>
    </div>
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
            <input type="text" placeholder="Title" value="<?php echo esc_attr($statusOrderTitle); ?>"
                   class="mstore-update-status-order-title mstore_input">
        </div>
        <div class="form-group" style="margin-top:10px;margin-bottom:40px">
            <textarea placeholder="Message" class="mstore-update-status-order-message mstore_input"
                      style="height: 120px"><?php echo esc_attr($statusOrderMsg); ?></textarea>
        </div>
    </form>

    <div class="thanks">
        <p style="font-size: 16px;">This setting help to speed up the mobile app performance, upload the config_xx.json
            from the common folder:</p>
    </div>
    <?php
    FlutterUtils::create_json_folder();
    $configs = FlutterUtils::get_all_json_files();
    if (!empty($configs)) {
        ?>
        <form action="" method="POST">
            <table class="mstore_table">
                <tr>
                    <th>File</th>
                    <th>Download / Delete</th>
                </tr>
                <?php
                foreach ($configs as $file) {
                    ?>
                    <tr>
                        <td><?php echo esc_attr($file); ?></td>
                        <td><a href="<?php echo esc_url(FlutterUtils::get_json_file_url($file)); ?>" target="_blank">Download</a>
                            / <a data-id="<?php echo getLangCodeFromConfigFile($file); ?>" data-nonce="<?php echo wp_create_nonce('delete_config_json_file'); ?>" class="mstore-delete-json-file">Delete</a></td>
                    </tr>
                    <?php
                }
                ?>
            </table>
        </form>
        <?php
    }
    ?>
    <form action="" enctype="multipart/form-data" method="post">

        <div class="form-group" style="margin-top:30px">
            <input id="fileToUpload" accept=".json" name="fileToUpload" type="file" class="form-control-file">
        </div>

        <p style="font-size: 14px; color: #1B9D0D; margin-top:10px">
            <?php
            if (isset($_POST['but_submit'])) {
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
            }
            ?>
        </p>

        <?php
        if (isset($_POST['but_deactive'])) {
            $success = deactiveMStoreApi();
            if ($success !== true) {
                ?>
                <p style="font-size: 16px;color: red;"><?php echo esc_attr($success); ?></p>
                <?php
            } else {
                echo "<script type='text/javascript'>
      location.reload();
        </script>";
            }
        }
        ?>

        <button type="submit" class="mstore_button" name='but_submit'>Save</button>
        <button type="submit" class="mstore_button mstore_deactive_button" name='but_deactive'
                onclick="return confirm('Are you sure to deactivate the license on this domain?');">Deactivate License
        </button>
    </form>
    <?php
}
?>