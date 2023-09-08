<?php include_once('functions/index.php'); include_once(DIR_PATH . 'functions/index.php');?>
<!doctype html>
<html <?php language_attributes(); header('Access-Control-Allow-Origin: *'); ?> >
<?php $installed = get_option("mobile-app-builder-product"); ?>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
    <meta charset="<?php bloginfo('charset'); ?>">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php wp_head(); ?>
    <style>
        .mstore_input {
            margin-bottom: 10px;
            width: 400px !important;
            padding: .857em 1.214em !important;
            background-color: transparent;
            color: #818181 !important;
            line-height: 1.286em !important;
            outline: 0;
            border: 0;
            -webkit-appearance: none;
            border-radius: 1.571em !important;
            box-sizing: border-box;
            border-width: 1px;
            border-style: solid;
            border-color: #ddd;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, .07) !important;
            transition: 50ms border-color ease-in-out;
            font-family: "Open Sans", HelveticaNeue-Light, "Helvetica Neue Light", "Helvetica Neue", Helvetica, Arial, "Lucida Grande", sans-serif;
            touch-action: manipulation;
        }

        .mstore_button {
            position: relative;
            border: 0 none;
            border-radius: 3px !important;
            color: #fff !important;
            display: inline-block;
            font-family: 'Poppins', 'Open Sans', Helvetica, Arial, sans-serif;
            font-size: 12px;
            letter-spacing: 1px;
            line-height: 1.5;
            text-transform: uppercase;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            margin-bottom: 21px;
            margin-right: 10px;
            line-height: 1;
            padding: 12px 30px;
            background: #39c36e !important;
            -webkit-transition: all 0.21s ease;
            -moz-transition: all 0.21s ease;
            -o-transition: all 0.21s ease;
            transition: all 0.21s ease;
        }

        .mstore_title {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: .5em;
            line-height: 1.1;
            display: block;
            margin-inline-start: 0px;
            margin-inline-end: 0px;
        }

        .mstore_list {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
            display: block;
            margin-block-start: 1em;
            margin-block-end: 1em;
            margin-inline-start: 0px;
            margin-inline-end: 0px;
            padding-inline-start: 40px;
            list-style: none;
        }

        .mstore_list li {
            list-style-type: square;
            font-size: 14px;
            font-weight: normal;
            margin-bottom: 6px;
            display: list-item;
            text-align: -webkit-match-parent;
        }

        .mstore_number_list li {
            list-style-type: decimal;
        }

        .mstore_link {
            margin-inline-start: 0px;
            margin-inline-end: 0px;
            color: #0099ff;
            text-decoration: none;
            outline: 0;
            transition-property: border, background, color;
            transition-duration: .05s;
            transition-timing-function: ease-in-out;
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
            margin-bottom: 20px;
            display: block;
        }

        .mstore_table {
            width: 100%;
            max-width: 100%;
            margin-bottom: 1.236rem;
            background-color: transparent;
            border-spacing: 0;
            border-collapse: collapse;
            display: table;
            border-color: grey;
        }

        .mstore_table a {
            color: #0099ff;
            text-decoration: none;
        }

        .mstore_table th, .mstore_table td {
            text-align: left;
        }

        .mstore_deactive_button {
            background: #C84B31 !important;
        }
    </style>
</head>
<body>
    <?php
	wp_enqueue_script('my_script', plugins_url('assets/js/mstore-inspireui.js', MSTORE_PLUGIN_FILE), array('jquery'), '1.0.0', true);
            wp_localize_script('my_script', 'MyAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
	?>
  <h4>Settings</h4>
  <label class="col-auto col-form-label">Product Key</label>
  <form action="" enctype="multipart/form-data" method="post">
    <?php
        if ($installed != null) {
            ?>
            <div class="mb-3 row">
                <div class="col-sm-7">
                    <input class="form-control" type="text" value=<?php echo $installed ?> disabled>
                </div>
                <button type="submit" name="revoke_product" class="btn btn-danger btn-sm col-auto">Revoke Product</button>
            </div>
            <?php
        } else {
            ?>
            <div class="mb-3 row">
            <div class="col-sm-7">
                <input class="form-control" type="text" name="product_id">
            </div>
                <button type="submit" name="sync_product" class="btn btn-primary btn-sm col-auto">Sync Product</button>
            </div>
            <?php
        }
    ?>
  </form>
  <div class="h4 pb-2 mb-4 text-danger border-bottom border-secondary"></div>
  <?php 
    if ($installed != null) {
        ?>
<div id="mstore-api-settings-container">
    <h4>API Settings</h4> <br/>
    <?php echo load_template(DIR_PATH . 'templates/admin/mstore-api-admin-dashboard.php'); ?>
  </div>
    <?php }
  ?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
    crossorigin="anonymous"></script>
  <script src="js/mobile-app-builder-admin.js"></script>
  <?php
    if (isset($_POST['revoke_product'])) {
        revokeProduct();
        ?> <script>
        window.location.reload();
    </script> <?php
    }
    if (isset($_POST['sync_product'])) {
        $sync_product = syncProduct(sanitize_text_field($_POST['product_id']));
        if ($sync_product !== true) {
            ?>
            <p style="font-size: 16px;color: red;"><?php echo esc_attr('Your product key is incorrectly'); ?></p>
            <?php
        } else {
            ?>
                <script>window.location.reload();</script>
            <?php
        }
    }
  ?>
</body>

</html>