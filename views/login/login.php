<?php ob_start(); ?>
<section class="page-wrapper dark-bg">
  <!-- Content -->
  <div class="container-fluid page-content">
    <div class="row w-100 justify-content-center align-items-center">
      <!-- Left Image -->
      <div class="col-12 col-md-6 mb-4 text-center">
        <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-login-form/draw2.webp"
             alt="Login image" class="img-fluid d-none d-md-block" style="max-height: 400px;">
      </div>

      <!-- Right Form -->
      <div class="col-12 col-md-4">
        <div class="login-header text-center mb-4">
          <h3 class="text-white">COMPUTER IMS</h3>
        </div>
        <form method="POST" action="login">
          <div class="mb-4">
            <label class="form-label light-txt">Username</label>
            <input type="text" name="username" class="form-control form-control-lg dark-txt light-bg"
                  required maxlength="50"/>
          </div>
          <div class="mb-3">
            <label class="form-label light-txt">Password</label>
            <input type="password" name="password" class="form-control form-control-lg dark-txt light-bg"
                  required maxlength="50"/>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check mb-0">
              <input class="form-check-input me-2" type="checkbox" id="showpass" name="showpass"  onchange="togglePassword()" />
              <label class="form-check-label light-txt" for="showpass">Show Password</label>
            </div>
            <a href="/login/forgotpass" class="forgotpass">Forgot password?</a>
          </div>

          <div class="text-center">
            <button type="submit" class="btn btn-primary btn-lg w-100">Login</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <footer class="bg-primary text-white text-center py-3 mt-auto">
    <div>&copy; 2020. All rights reserved.</div>
  </footer>
</section>
<script>
function togglePassword() {
    const passwordInput = document.querySelector('input[name="password"]');
    const showPassCheckbox = document.querySelector('input[name="showpass"]');
    passwordInput.type = showPassCheckbox.checked ? 'text' : 'password';
}
</script>

<?php
$content = ob_get_clean();
require_once 'layout.php';

require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on login.php");
?>
