<?php ob_start(); // Start output buffering ?>
<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card bg-light-dark p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Brand: <?php echo htmlspecialchars($brand->name ?? ''); ?></h3>

          <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger text-center mb-3" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <?php if (isset($success_message) && !empty($success_message)): ?>
            <div class="alert alert-success text-center mb-3" role="alert">
              <?php echo htmlspecialchars($success_message); ?>
            </div>
          <?php endif; ?>

          <form action="/staff/brands/update" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($brand->id ?? ''); ?>">

            <div class="mb-3">
              <label for="name" class="form-label light-txt">Brand Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="name" name="name"
                     value="<?php echo htmlspecialchars($brand->name ?? ''); ?>" required maxlength="100">
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg">Update Brand</button>
              <a href="/staff/brands_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php
$content = ob_get_clean(); // Get the buffered content
require_once 'layout.php'; // Include your main layout
?>