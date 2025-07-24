<!DOCTYPE html>
<html>
<head>
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
  <?php if (isset($content)) echo $content; ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
      const passwordInput = document.querySelector('input[name="password"]');
      const showPassCheckbox = document.querySelector('input[name="showpass"]');
      
      if (passwordInput && showPassCheckbox) {
          showPassCheckbox.addEventListener('change', function() {
              passwordInput.type = this.checked ? 'text' : 'password';
          });
      }
  });
  </script>
</body>
</html>
