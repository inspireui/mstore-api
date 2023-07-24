<?php include_once(plugin_dir_path(dirname(__FILE__)) . 'functions/index.php'); ?>

<!doctype html>
<html <?php language_attributes(); ?> >
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
    <?php wp_head(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <style type="text/tailwindcss">
        .mstore-input-class { 
            @apply border border-gray-300 text-gray-900 text-sm rounded focus:border-blue-500 w-full sm:max-w-md px-2 py-3
        }
        .mstore-button-class {
            @apply mt-5 px-5 py-2 text-base font-medium text-center text-white bg-green-700 rounded hover:bg-green-800 
        }
        .mstore-file-input-class {
            @apply block w-full text-sm text-slate-500
      file:mr-4 file:py-2 file:px-4
      file:rounded-full file:border-0
      file:text-sm file:font-semibold
      file:bg-violet-50 file:text-violet-700
      hover:file:bg-violet-100
        }
    </style>
</head>
<body>

<div class="container mx-auto p-5 bg-white">
    <h4 class="text-xl text-semibold">MStore API Settings</h4> <br/>
    <?php echo load_template(dirname(__FILE__) . '/admin/mstore-api-admin-dashboard.php'); ?>
</div>

</body>
</html>