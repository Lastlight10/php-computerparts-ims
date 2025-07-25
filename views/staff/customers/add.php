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
      <div class="col-12 col-md-8 col-lg-6">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Add New Customer</h3>

          <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger text-center mb-3" role="alert">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>

          <form action="/staff/customers/store" method="POST">
            <div class="mb-3">
              <label for="customer_type" class="form-label light-txt">Customer Type</label>
              <!-- CHANGED: from input type="text" to select dropdown -->
              <select class="form-select form-select-lg dark-txt light-bg" id="customer_type" name="customer_type" required>
                <option value="">Select Type</option>
                <option value="Individual" <?= (isset($customer->customer_type) && $customer->customer_type == 'Individual') ? 'selected' : '' ?>>Individual</option>
                <option value="Company" <?= (isset($customer->customer_type) && $customer->customer_type == 'Company') ? 'selected' : '' ?>>Company</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="company_name" class="form-label light-txt">Company Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="company_name" name="company_name"
                     value="<?php echo htmlspecialchars($customer->company_name ?? ''); ?>" maxlength="255">
            </div>

            <div class="mb-3">
              <label for="contact_person_first_name" class="form-label light-txt">Contact Person First Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_person_first_name" name="contact_person_first_name"
                     value="<?php echo htmlspecialchars($customer->contact_person_first_name ?? ''); ?>" required maxlength="100">
            </div>

            <div class="mb-3">
              <label for="contact_person_last_name" class="form-label light-txt">Contact Person Last Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_person_last_name" name="contact_person_last_name"
                     value="<?php echo htmlspecialchars($customer->contact_person_last_name ?? ''); ?>" required maxlength="100">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label light-txt">Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="email" name="email"
                     value="<?php echo htmlspecialchars($customer->email ?? ''); ?>" required maxlength="255">
            </div>

            <div class="mb-3">
              <label for="phone_number" class="form-label light-txt">Phone Number</label>
              <input type="tel" class="form-control form-control-lg dark-txt light-bg" id="phone_number" name="phone_number"
                     value="<?php echo htmlspecialchars($customer->phone_number ?? ''); ?>" required maxlength="20">
            </div>

            <div class="mb-3">
              <label for="address" class="form-label light-txt">Address (Optional)</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="address" name="address"
                        rows="3" maxlength="500"><?php echo htmlspecialchars($customer->address ?? ''); ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg lightgreen-bg">Add Customer</button>
              <a href="/staff/customers_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
Logger::log('UI: On staff/customers/add.php');
?>