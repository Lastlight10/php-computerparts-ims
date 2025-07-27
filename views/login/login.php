<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Link to your external favicon.ico file -->
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- MDBootstrap CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.css" rel="stylesheet" />

  <!-- Your custom CSS -->
  <link href="/resources/css/login.css" rel="stylesheet">

</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- MDBootstrap JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.0/mdb.min.js"></script>
  <?php
  // Initialize variables to hold messages
  $success_message = '';
  $error_message = '';

  // Check for success message in URL query parameters
  if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
      $success_message = htmlspecialchars($_GET['success_message']);
  }

  // Check for error message in URL query parameters
  if (isset($_GET['error']) && !empty($_GET['error'])) {
      $error_message = htmlspecialchars($_GET['error']);
  }
  // If your controller also passes $error/$success directly when rendering,
  // you might need to add checks like:
  // if (isset($error) && !empty($error)) { $error_message = htmlspecialchars($error); }
  // if (isset($success_message_from_controller) && !empty($success_message_from_controller)) { $success_message = htmlspecialchars($success_message_from_controller); }
  // Ensure $suppliers_info is an object with an isEmpty method to prevent errors (if this view is used elsewhere)
  if (!isset($suppliers_info)) {
      $suppliers_info = new \Illuminate\Database\Eloquent\Collection();
  }
  ?>
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

        <?php if (!empty($success_message)): ?>
          <div class="alert alert-success text-center mb-3" role="alert">
            <?= $success_message ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div class="alert alert-danger text-center mb-3" role="alert">
            <?= $error_message ?>
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
</body>
</html>
