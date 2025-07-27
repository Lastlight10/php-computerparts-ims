<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Initialize variables to hold messages
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

// Ensure $customer is defined for form use
$customer = $customer ?? null; // Should be passed from controller for edit view

if (!$customer) {
    echo '<div class="alert alert-danger text-center mt-5">Customer data not available for editing.</div>';
    // Optionally, log or redirect here
    return; // Stop execution if no customer data
}
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Customer: <?= htmlspecialchars($customer->company_name ?? $customer->contact_first_name . ' ' . $customer->contact_last_name) ?></h3>

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

          <form action="/staff/customers/update" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($customer->id) ?>">

            <div class="mb-3">
              <label for="customer_type" class="form-label light-txt">Customer Type</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="customer_type" name="customer_type" required>
                <option value="">Select Type</option>
                <option value="Individual" <?= ($customer->customer_type == 'Individual') ? 'selected' : '' ?>>Individual</option>
                <option value="Company" <?= ($customer->customer_type == 'Company') ? 'selected' : '' ?>>Company</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="company_name" class="form-label light-txt">Company Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="company_name" name="company_name"
                     value="<?php echo htmlspecialchars($customer->company_name ?? ''); ?>" maxlength="50">
            </div>

            <div class="mb-3">
              <label for="contact_first_name" class="form-label light-txt">Contact Person First Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_first_name" name="contact_first_name"
                     value="<?php echo htmlspecialchars($customer->contact_first_name ?? ''); ?>" required maxlength="50">
            </div>

            <div class="mb-3">
              <label for="contact_middle_name" class="form-label light-txt">Contact Person Middle Name (Optional)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_middle_name" name="contact_middle_name"
                     value="<?php echo htmlspecialchars($customer->contact_middle_name ?? ''); ?>" maxlength="50">
            </div>

            <div class="mb-3">
              <label for="contact_last_name" class="form-label light-txt">Contact Person Last Name</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="contact_last_name" name="contact_last_name"
                     value="<?php echo htmlspecialchars($customer->contact_last_name ?? ''); ?>" required maxlength="50">
            </div>

            <div class="mb-3">
              <label for="email" class="form-label light-txt">Email</label>
              <input type="email" class="form-control form-control-lg dark-txt light-bg" id="email" name="email"
                     value="<?php echo htmlspecialchars($customer->email ?? ''); ?>" required maxlength="50">
            </div>

            <div class="mb-3">
              <label for="phone_number" class="form-label light-txt">Phone Number</label>
              <input type="tel" class="form-control form-control-lg dark-txt light-bg" id="phone_number" name="phone_number"
                    value="<?php echo htmlspecialchars($customer->phone_number ?? ''); ?>" required data-maxlength="11">
            </div>

            <div class="mb-3">
              <label for="address" class="form-label light-txt">Address (Optional)</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="address" name="address"
                        rows="3" maxlength="50"><?php echo htmlspecialchars($customer->address ?? ''); ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit"
              onclick="return confirm('Are you sure you want to edit the customer?');"
              class="btn btn-primary btn-lg lightgreen-bg">Update Customer</button>
              <a href="/staff/customers_list" class="btn btn-secondary btn-lg">Cancel</a>
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
    });
</script>
<?php
Logger::log('UI: On staff/customers/edit.php');
?>