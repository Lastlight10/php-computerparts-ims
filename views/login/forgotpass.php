<section class="page-wrapper dark-bg">
  <!-- Content -->
  <div class="container-fluid page-content">
    <div class="row w-100 justify-content-center align-items-center">
      <div class="col-12 col-md-4">
        <div class="login-header text-center mb-4">
          <h3 class="text-white">CHANGE PASSWORD</h3>
        </div>

        <div class="form-wrapper">

          <form method="POST" action="send_code">
            <div class="mb-4">
              <label class="form-label light-txt">Email</label>
              <input type="email" name="email" class="form-control form-control-lg dark-txt light-bg" required maxlength="50" />
            </div>
            <div class="mb-4">
              <button type="submit" class="btn btn-primary btn-lg w-100">SEND CODE</button>
            </div>
          </form>

          <form method="POST" action="verify_code">
            <div class="mb-4">
              <label class="form-label light-txt">Code</label>
              <input type="text" name="code" class="form-control form-control-lg dark-txt light-bg" required maxlength="50" />
            </div>
            <div>
              <button type="submit" class="btn btn-primary btn-lg w-100">VERIFY</button>
            </div>
          </form>
        </div>

        
      </div>
    </div>
  </div>
</section>

<?php 
use App\Core\Logger;
Logger::log('UI: On forgotpass.php')
?>