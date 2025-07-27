<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Check for success message in URL query parameters
if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
    echo '<div class="alert alert-success text-center mb-3" role="alert">' . $success_message . '</div>';
}

// Check for error message in URL query parameters
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
    echo '<div class="alert alert-danger text-center mb-3" role="alert">' . $error_message . '</div>';
}
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Add New Category</h3>

          <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger text-center mb-3" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <form action="/staff/categories/store" method="POST">
            <div class="mb-3">
              <label for="name" class="form-label light-txt">Category Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg"
              id="name"
              name="name"
              value="<?php echo htmlspecialchars($category->name ?? ''); ?>"
              required
              maxlength="20">
            </div>

            <div class="mb-3">
              <label for="description" class="form-label light-txt">Description</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="description" name="description"
                        rows="4" maxlength="50"><?php echo htmlspecialchars($category->description ?? ''); ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit"
              class="btn btn-primary btn-lg lightgreen-bg"
                onclick="return confirm('Are you sure you want to add the category?');"
                >Add Category</button>
              <a href="/staff/categories_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
Logger::log('UI: On staff/categories/add.php');
?>