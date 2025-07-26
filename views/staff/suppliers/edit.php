<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Initialize variables to hold messages (for direct view rendering)
$display_success_message = '';
$display_error_message = '';

// Check for success message (from redirect OR direct view render)
if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $display_success_message = htmlspecialchars($_GET['success_message']);
} elseif (isset($success_message) && !empty($success_message)) {
    $display_success_message = htmlspecialchars($success_message);
}

// Check for error message (from redirect OR direct view render)
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $display_error_message = htmlspecialchars($_GET['error']);
} elseif (isset($error) && !empty($error)) {
    $display_error_message = htmlspecialchars($error);
}

// Ensure $supplier is defined for form use
$supplier = $supplier ?? null; // Should be passed from controller for edit view

if (!$supplier) {
    echo '<div class="alert alert-danger text-center mt-5">Supplier data not available for editing.</div>';
    // Optionally, log or redirect here
    return; // Stop execution if no supplier data
}
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Supplier: <?= htmlspecialchars($supplier->company_name ?? $supplier->contact_first_name . ' ' . $supplier->contact_last_name) ?></h3>

          <?php if (!empty($display_success_message)): ?>
            <div class="alert alert-success text-center mb-3" role="alert">
              <?= $display_success_message ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($display_error_message)): ?>
            <div class="alert alert-danger text-center mb-3" role="alert">
              <?= $display_error_message ?>
            </div>
          <?php endif; ?>

          <form action="/staff/suppliers/update" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($supplier->id) ?>">

            <div class="mb-3">
              <label for="supplier_type" class="form-label light-txt">Supplier Type</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="supplier_type" name="supplier_type" required>
                <option value="">Select Type</option>
                <option value="Individual" <?= ($supplier->supplier_type == 'Individual') ? 'selected' : '' ?>>Individual</option>
                <option value="Company" <?= ($supplier->supplier_type == 'Company') ? 'selected' : '' ?>>Company</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="company_name" class="form-label light-txt">Company Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="company_name" name="company_name"
                     value="<?php echo htmlspecialchars($supplier->company_name ?? ''); ?>" maxlength="255">
            </div>

            <div class="mb-3">
              <label for="contact_first_name" class="form-label light-txt">Contact Person First Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_first_name" name="contact_first_name"
                     value="<?php echo htmlspecialchars($supplier->contact_first_name ?? ''); ?>" required maxlength="100">
            </div>

            <div class="mb-3">
              <label for="contact_middle_name" class="form-label light-txt">Contact Person Middle Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_middle_name" name="contact_middle_name"
                     value="<?php echo htmlspecialchars($supplier->contact_middle_name ?? ''); ?>" maxlength="100">
            </div>

            <div class="mb-3">
              <label for="contact_last_name" class="form-label light-txt">Contact Person Last Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_last_name" name="contact_last_name"
                     value="<?php echo htmlspecialchars($supplier->contact_last_name ?? ''); ?>" required maxlength="100">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label light-txt">Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="email" name="email"
                     value="<?php echo htmlspecialchars($supplier->email ?? ''); ?>" required maxlength="255">
            </div>

            <div class="mb-3">
              <label for="phone_number" class="form-label light-txt">Phone Number</label>
              <input type="tel" class="form-control form-control-lg dark-txt light-bg" id="phone_number" name="phone_number"
                     value="<?php echo htmlspecialchars($supplier->phone_number ?? ''); ?>" required maxlength="20">
            </div>

            <div class="mb-3">
              <label for="address" class="form-label light-txt">Full Address</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="address" name="address"
                        rows="3" maxlength="500"><?php echo htmlspecialchars($supplier->address ?? ''); ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg lightgreen-bg">Update Supplier</button>
              <a href="/staff/suppliers_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
Logger::log('UI: On staff/suppliers/edit.php');
?>