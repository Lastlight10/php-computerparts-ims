<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Brand: <?php echo htmlspecialchars($brand->name ?? ''); ?></h3>

          <?php
          if (isset($_SESSION['success_message'])) {
              echo '
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['success_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['success_message']); // fix: previously unsetting error instead
          }
          if (isset($_SESSION['warning_message'])) {
              echo '
              <div class="alert alert-warning alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['warning_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['warning_message']);
          }

          if (isset($_SESSION['error_message'])) {
              echo '
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['error_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['error_message']);
          }
          ?>

          <form action="/staff/brands/update" method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($brand->id ?? ''); ?>">

            <div class="mb-3 lighterdark-bg">
              <label for="name" class="form-label light-txt">Brand Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg"
                id="name"
                name="name"
                value="<?php echo htmlspecialchars($brand->name ?? ''); ?>"
                required maxlength="20">
            </div>

            <div class="mb-3 lighterdark-bg">
              <label for="website" class="form-label light-txt">Website</label>
              <input type="url" class="form-control form-control-lg dark-txt light-bg" id="website" name="website"
                     value="<?php echo htmlspecialchars($brand->website ?? ''); ?>" maxlength="30" placeholder="e.g., https://www.example.com">
              <small class="form-text text-muted">Optional: Enter the brand's official website URL.</small>
            </div>

            <div class="mb-3 lighterdark-bg">
              <label for="contact_email" class="form-label light-txt">Contact Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="contact_email" name="contact_email"
                     value="<?php echo htmlspecialchars($brand->contact_email ?? ''); ?>" maxlength="30" placeholder="e.g., info@example.com">
              <small class="form-text text-muted">Optional: Enter a contact email for the brand.</small>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit"
              onclick="return confirm('Are you sure you want to update the brand details?');"
              class="btn btn-primary btn-lg">Update Brand</button>
              <a href="/staff/brands_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
