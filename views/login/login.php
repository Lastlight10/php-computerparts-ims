

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row w-100 justify-content-center align-items-center">
      <div class="col-12 col-md-6 mb-4 text-center">
        <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-login-form/draw2.webp"
             alt="Login image" class="img-fluid d-none d-md-block" style="max-height: 400px;">
      </div>

      <div class="col-12 col-md-4">
        <div class="login-header text-center mb-4">
          <h3 class="text-white">COMPUTER IMS</h3>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
          <div class="alert alert-danger text-center mb-3" role="alert">
            <?php echo htmlspecialchars($error); // Use htmlspecialchars to prevent XSS ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="/login/login_acc">
          <div class="mb-4">
            <label class="form-label light-txt">Username</label>
            <input type="text" id="username" name="username" class="form-control form-control-lg dark-txt light-bg"
                  required maxlength="50" value="<?php echo htmlspecialchars($user_username ?? ''); // Retain username if re-displaying after error ?>"/>
          </div>
          <div class="mb-3">
            <label class="form-label light-txt">Password</label>
            <input type="password" id="password" name="password" class="form-control form-control-lg dark-txt light-bg"
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
use App\Core\Logger;
Logger::log('UI: On login.php')
?>