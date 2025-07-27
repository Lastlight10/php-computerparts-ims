<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Category: <?php echo htmlspecialchars($category->name ?? ''); ?></h3>

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

          <form action="/staff/categories/update" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($category->id ?? ''); ?>">

            <div class="mb-3">
              <label for="name" class="form-label light-txt">Category Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="name" name="name"
                     value="<?php echo htmlspecialchars($category->name ?? ''); ?>" required maxlength="20">
            </div>
            <div class="mb-3">
              <label for="description" class="form-label light-txt">Description</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="description" name="description"
                        rows="4" maxlength="50"><?php echo htmlspecialchars($category->description ?? ''); ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit"
              onclick="return confirm('Are you sure you want to update the category?');"
              class="btn btn-primary btn-lg">Update Category</button>
              <a href="/staff/categories_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
