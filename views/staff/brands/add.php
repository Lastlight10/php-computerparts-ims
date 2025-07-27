<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Add New Brand</h3>

          <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger text-center mb-3" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <form action="/staff/brands/store" method="POST">
            <div class="mb-3 lighterdark-bg">
              <label for="name" class="form-label light-txt">Brand Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="name" name="name"
                     value="<?php echo htmlspecialchars($brand->name ?? ''); ?>" required maxlength="50">
            </div>

            <div class="mb-3 lighterdark-bg">
              <label for="website" class="form-label light-txt">Website</label>
              <input type="url" class="form-control form-control-lg dark-txt light-bg" id="website" name="website"
                     value="<?php echo htmlspecialchars($brand->website ?? ''); ?>" maxlength="50" placeholder="e.g., https://www.example.com">
              <small class="form-text text-muted">Optional: Enter the brand's official website URL.</small>
            </div>

            <div class="mb-3 lighterdark-bg">
              <label for="contact_email" class="form-label light-txt">Contact Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="contact_email" name="contact_email"
                     value="<?php echo htmlspecialchars($brand->contact_email ?? ''); ?>" maxlength="50" placeholder="e.g., info@example.com">
              <small class="form-text text-muted">Optional: Enter a contact email for the brand.</small>
            </div>

            <div class="d-grid gap-2 mt-4 ">
              <button type="submit"
              class="btn btn-primary btn-lg lightgreen-bg"
              onclick="return confirm('Are you sure you want to add the brand?');"
              >Add Brand</button>
              <a href="/staff/brands_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>