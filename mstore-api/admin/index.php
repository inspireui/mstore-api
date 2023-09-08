<?php include_once('functions/common.php'); ?>
<!doctype html>
<html <?php language_attributes(); ?> >
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<script>
function onMyFrameLoad() {
  document.getElementById("back").style.visibility = 'visible';
};
</script>
<body>
   <div id="container" style="position:fixed; top:0; left:0; bottom:0; right:0; width:100%; height:100%; border:none; margin:0; padding:0; overflow:hidden; z-index:999999;">
    <iframe id="id-iframe" onload="onMyFrameLoad(this)" src="https://mobile-appbuilder.web.app/?extension=<?php echo AGENCY_ID ?>" style="width: 100%; height: 100%;" >
    </iframe>
    <a id="back" href="admin.php?page=mobile-app-builder-plugin" style="position: absolute; left: 5px; bottom: 5px; visibility: hidden;">
        <i class="fa fa-chevron-left"></i>
        <span>Back to Settings</span>
    </a>
   </div>
</body>
</html>