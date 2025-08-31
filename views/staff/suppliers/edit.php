<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

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
                     value="<?php echo htmlspecialchars($supplier->company_name ?? ''); ?>" maxlength="30">
            </div>

            <div class="mb-3">
              <label for="contact_first_name" class="form-label light-txt">Contact Person First Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_first_name" name="contact_first_name"
                     value="<?php echo htmlspecialchars($supplier->contact_first_name ?? ''); ?>" required maxlength="30">
            </div>

            <div class="mb-3">
              <label for="contact_middle_name" class="form-label light-txt">Contact Person Middle Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_middle_name" name="contact_middle_name"
                     value="<?php echo htmlspecialchars($supplier->contact_middle_name ?? ''); ?>" maxlength="20">
            </div>

            <div class="mb-3">
              <label for="contact_last_name" class="form-label light-txt">Contact Person Last Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_last_name" name="contact_last_name"
                     value="<?php echo htmlspecialchars($supplier->contact_last_name ?? ''); ?>" required maxlength="30">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label light-txt">Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="email" name="email"
                     value="<?php echo htmlspecialchars($supplier->email ?? ''); ?>" required maxlength="30">
            </div>

            <div class="mb-3">
              <label for="phone_number" class="form-label light-txt">Phone Number</label>
              <input type="tel" class="form-control form-control-lg dark-txt light-bg" id="phone_number" name="phone_number"
                     value="<?php echo htmlspecialchars($supplier->phone_number ?? ''); ?>" required data-maxlength="11">
            </div>

            <div class="mb-3">
              <label for="address" class="form-label light-txt">Address</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="address" name="address"
                        rows="3" maxlength="50"><?php echo htmlspecialchars($supplier->address ?? ''); ?></textarea>
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
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("JavaScript for phone number input validation is running!");

        // This function will specifically handle phone number validation
        function enforcePhoneNumberRules(event) {
            const input = event.target;
            let value = input.value;
            const maxLength = parseInt(input.getAttribute('data-maxlength'), 10);

            // 1. Strip non-numeric characters (only digits allowed for phone number)
            value = value.replace(/[^0-9]/g, '');

            // 2. Enforce maxlength
            if (!isNaN(maxLength) && value.length > maxLength) {
                value = value.slice(0, maxLength);
            }

            // Update the input value
            input.value = value;
        }

        const phoneNumberInput = document.getElementById('phone_number');

        if (phoneNumberInput) {
            phoneNumberInput.addEventListener('input', enforcePhoneNumberRules);
        }
    function lettersOnly(id, allowSpaces = false, allowHyphens = false) {
        const el = document.getElementById(id);
        el.addEventListener('input', () => {
          let regex = '[^A-Za-z';
          if (allowSpaces) regex += ' ';
          if (allowHyphens) regex += '-';
          regex += ']';
          el.value = el.value.replace(new RegExp(regex, 'g'), '');
        });
      }

      // Apply restrictions
      lettersOnly("contact_first_name",true);              // Letters only
      lettersOnly("contact_middle_name");             // Letters only
      lettersOnly("contact_last_name",false, true);
    });
</script>
<?php
Logger::log('UI: On staff/suppliers/edit.php');
?>