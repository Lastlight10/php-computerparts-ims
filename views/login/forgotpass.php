<?php ob_start(); ?>
<section class="page-wrapper dark-bg">
  <!-- Content -->
  <div class="container-fluid page-content">
    <div class="row w-100 justify-content-center align-items-center">
      <div class="col-12 col-md-4">
        <div class="login-header text-center mb-4">
          <h3 class="text-white">CHANGE PASSWORD</h3>
        </div>
        <form method="POST" action="send_code">
          <div class="mb-4">
            <label class="form-label light-txt">Email</label>
            <input type="email" name="email" class="form-control form-control-lg dark-txt light-bg"
                  required maxlength="50"/>
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-primary btn-lg w-100">Send Code</button>
          </div>
        </form>
       
        <form method="POST" action="verify_code">
          <div class="mb-3">
            <label class="form-label light-txt">Code</label>
            <input type="text" name="code" class="form-control form-control-lg dark-txt light-bg"
                  required maxlength="50"/>
          </div>
          <div class="text-center">
            <button type="submit" class="btn btn-primary btn-lg w-100">Verify</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php
$content = ob_get_clean();
require_once 'layout.php';

require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on forgotpass.php");
?>