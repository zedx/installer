<?php include __DIR__.'/install_files/libs/bootstrap.php'; ?>
<!DOCTYPE html>
<html>
  <head>
    <title>ZEDx v3.0</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="install_files/assets/css/bootstrap.min.css" rel="stylesheet" />
    <link href="install_files/assets/css/font-awesome.min.css" rel="stylesheet" />
    <link href="install_files/assets/css/flat-ui.min.css" rel="stylesheet" />
    <link href="install_files/assets/css/installer.css" rel="stylesheet" />
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="install_files/assets/js/vendor/html5shiv-3.7.0.js"></script>
    <script src="install_files/assets/js/vendor/respond-1.3.0.min.js"></script>
    <![endif]-->
  </head>
  <body>
    <header>
      <div class="container">
        <div class="row">
          <div class="col-md-3">
            <img src="https://zedx.io/assets/img/zedx-logo-white.png" class="zedx-logo"></div>
            <div class="col-md-4 col-md-offset-5 col-sm-12">
              <ul class="nav nav-tabs" id="steps">
                 <li class="disabled"> <a href="#check" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="SYSTEM CHECK"> <span class="fa-stack tab system-check"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-cogs fa-stack-1x"></i></span></a></li>
                 <li class="disabled"><a href="#database" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="DATABASE"> <span class="fa-stack tab database"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-database fa-stack-1x"></i></span> </a> </li>
                 <li class="disabled"><a href="#admin" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="ADMINISTRATION AREA"> <span class="fa-stack tab admin"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-user fa-stack-1x"></i></span> </a> </li>
                 <li class="disabled"><a href="#settings" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="SETTINGS"> <span class="fa-stack tab settings"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-wrench fa-stack-1x"></i></span> </a></li>
                 <li class="disabled"><a href="#build" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="BUILDING"> <span class="fa-stack tab build"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-spinner fa-stack-1x"></i></span> </a></li>
                 <li class="disabled"><a href="#complete" data-toggle="tab" data-tooltip="tooltip" data-placement="bottom" title="COMPLETE"> <span class="fa-stack tab complete"> <i class="fa fa-circle-thin fa-stack-2x"></i><i class="fa fa-check fa-stack-1x"></i></span> </a> </li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </header>
    <section>
     <div class="container">
        <div class="row">
          <form id="installer-form" method="post" enctype="multipart/form-data">
            <div class="board">
              <div class="tab-content">
                 <div class="tab-pane fade in active" id="license"></div>
                 <div class="col-sm-12 col-md-8 col-md-offset-2 tab-pane fade" id="check"></div>
                 <div class="col-sm-12 col-md-8 col-md-offset-2 tab-pane fade" id="database"></div>
                 <div class="col-sm-12 col-md-8 col-md-offset-2 tab-pane fade" id="admin"></div>
                 <div class="col-sm-12 col-md-8 col-md-offset-2 tab-pane fade" id="settings"></div>
                 <div class="col-sm-12 tab-pane fade" id="build"></div>
                 <div class="col-sm-12 col-md-8 col-md-offset-2 tab-pane fade" id="complete"></div>
              </div>
            </div>
          </form>
        </div>
     </div>
    </section>
    <?php foreach (getTemplates() as $template): ?>
    <script type="x-tmpl-mustache" id="<?php echo $template->id ?>">
      <?php include $template->path; ?>
    </script>
    <?php endforeach ?>

    <script src="install_files/assets/js/vendor/jquery-2.0.3.min.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/bootstrap.min.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/prettify.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/mustache.min.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/eventsource.min.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/jquery.progress.js" type="text/javascript"></script>
    <script src="install_files/assets/js/vendor/jquery.knob.min.js" type="text/javascript"></script>
    <script src="install_files/assets/js/installer.js" type="text/javascript"></script>
  </body>
</html>
