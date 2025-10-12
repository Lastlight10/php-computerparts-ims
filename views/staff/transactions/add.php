<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;
use Models\Transaction;
use Models\Customer;
use Models\Supplier;
use Illuminate\Support\Collection; // Ensure this is present for is_object/instanceof Collection checks

// Ensure variables are set for the view
$transaction = $transaction ?? new Transaction(); // Or an empty object if no default model exists
$customers = $customers ?? [];
$suppliers = $suppliers ?? [];

// For repopulating date input correctly in 'YYYY-MM-DD' format if it was previously set
$transaction_date_value = '';
if (isset($transaction->transaction_date) && !empty($transaction->transaction_date)) {
    // If it's an Eloquent Carbon object, format it. If it's a string, just use it.
    try {
        $transaction_date_value = date('Y-m-d', strtotime($transaction->transaction_date));
    } catch (\Exception $e) {
        $transaction_date_value = htmlspecialchars($transaction->transaction_date); // Fallback for string
    }
} else {
    $transaction_date_value = date('Y-m-d'); // Default to today's date
}

// Set default transaction type and status for new form
// Make sure these defaults match your DB allowed values
$default_transaction_type = $transaction->transaction_type ?? 'Sale'; // Default to Sale
$default_status = $transaction->status ?? 'Pending'; // Default to Pending

?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Create New Transaction</h3>

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

          <form action="/staff/transactions/store" method="POST" id="transactionForm">

            <div class="mb-3">
              <label for="transaction_type" class="form-label light-txt">Transaction Type</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="transaction_type" name="transaction_type" required>
                <option value="">Select Type</option>
                <option value="Sale" <?= ($default_transaction_type == 'Sale') ? 'selected' : '' ?>>Sale</option>
                <option value="Purchase" <?= ($default_transaction_type == 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                <option value="Customer Return" <?= ($default_transaction_type == 'Customer Return') ? 'selected' : '' ?>>Customer Return</option>
                <option value="Supplier Return" <?= ($default_transaction_type == 'Supplier Return') ? 'selected' : '' ?>>Supplier Return</option>
                <option value="Stock Adjustment" <?= ($default_transaction_type == 'Stock Adjustment') ? 'selected' : '' ?>>Stock Adjustment</option>
              </select>
            </div>

            <div class="mb-3" id="customer_field">
                <label for="customer_id" class="form-label light-txt">Customer</label>
                <select class="form-select form-select-lg dark-txt light-bg" id="customer_id" name="customer_id">
                    <option value="">Select Customer</option>
                    <?php
                    if (isset($customers) && (is_array($customers) || $customers instanceof Collection)) {
                        foreach ($customers as $customer) {
                            if (is_object($customer)) {
                                $customerId = htmlspecialchars($customer->id ?? '');
                                $customerDisplayName = '';
                                if (!empty($customer->company_name)) {
                                    $customerDisplayName = htmlspecialchars($customer->company_name);
                                } else {
                                    $firstName = htmlspecialchars($customer->contact_first_name ?? '');
                                    $lastName = htmlspecialchars($customer->contact_last_name ?? '');
                                    $customerDisplayName = trim($firstName . ' ' . $lastName);
                                }
                                if (empty($customerDisplayName)) {
                                    $customerDisplayName = 'ID: ' . $customerId;
                                }

                                $selected = '';
                                if (isset($transaction) && $transaction->customer_id == $customer->id) {
                                    $selected = 'selected';
                                } elseif (isset($_SESSION['error_data']['customer_id']) && $_SESSION['error_data']['customer_id'] == $customer->id) {
                                    $selected = 'selected';
                                }
                                ?>
                                <option value="<?= $customerId ?>" <?= $selected ?>>
                                    <?= $customerDisplayName ?>
                                </option>
                                <?php
                            } else {
                                error_log('Warning: $customer is not an object in transactions/add.php customer loop. Customer data: ' . print_r($customer, true));
                            }
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3" id="supplier_field">
    <label for="supplier_id" class="form-label light-text">Supplier</label>
    <select class="form-select form-select-lg dark-txt light-bg" id="supplier_id" name="supplier_id">
        <option value="">Select Supplier</option>
        <?php
        if (!empty($suppliers) && (is_array($suppliers) || $suppliers instanceof Collection)) {
            foreach ($suppliers as $supplier) {
                if (is_object($supplier)) {
                    // Prepare ID and display name
                    $supplierId = $supplier->id;
                    $displayName = !empty($supplier->company_name) 
                        ? htmlspecialchars($supplier->company_name) 
                        : htmlspecialchars(trim(($supplier->contact_first_name ?? '') . ' ' . ($supplier->contact_last_name ?? '')));
                    
                    if (empty($displayName)) {
                        $displayName = 'ID: ' . $supplierId;
                    }

                    // Determine selected
                    $selected = '';
                    if (isset($transaction) && $transaction->supplier_id == $supplierId) {
                        $selected = 'selected';
                    } elseif (!empty($_SESSION['error_data']['supplier_id']) && $_SESSION['error_data']['supplier_id'] == $supplierId) {
                        $selected = 'selected';
                    }
                    ?>
                    <option value="<?= $supplierId ?>" <?= $selected ?>><?= $displayName ?></option>
                    <?php
                } else {
                    error_log('Warning: $supplier is not an object in transactions/add.php. Supplier data: ' . print_r($supplier, true));
                }
            }
        }
        ?>
    </select>
</div>


            <div class="mb-3">
              <label for="transaction_date" class="form-label light-txt">Transaction Date</label>
              <input type="date" class="form-control form-control-lg dark-txt light-bg" id="transaction_date" name="transaction_date"
                     value="<?= $transaction_date_value ?>" required readonly>
            </div>

            <div class="mb-3">
              <label for="status" class="form-label light-txt">Status</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="status" name="status" required>
                <option value="Pending" <?= ($default_status == 'Pending') ? 'selected' : '' ?>>Pending</option>
                <!-- <option value="Completed" <?= ($default_status == 'Completed') ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($default_status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option> -->
              </select>
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label light-txt">Notes (Optional)</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="notes" name="notes" rows="3"
                        maxlength="100"><?= htmlspecialchars($transaction->notes ?? '') ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btnmary btn-lg lightgreen-bg">Create Transaction</button>
              <a href="/staff/transactions_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const transactionTypeSelect = document.getElementById('transaction_type');
    const customerField = document.getElementById('customer_field');
    const supplierField = document.getElementById('supplier_field');
    const customerSelect = document.getElementById('customer_id');
    const supplierSelect = document.getElementById('supplier_id');

    let returnInfoText = document.createElement('small');
    returnInfoText.className = 'form-text text-muted mt-1';
    returnInfoText.id = 'returnHelpText';
    // Append it once, ideally after the transaction_type select
    // Check if parentNode exists before appending
    if (transactionTypeSelect && transactionTypeSelect.parentNode) {
        transactionTypeSelect.parentNode.appendChild(returnInfoText);
    }


    function toggleFields() {
        const type = transactionTypeSelect.value;

        returnInfoText.style.display = 'none'; // Hide info text by default
        customerSelect.value = ''; // Reset selected values
        supplierSelect.value = ''; // Reset selected values

        // --- IMPORTANT: Reset both displays at the start of each toggle ---
        customerField.style.display = 'none';
        supplierField.style.display = 'none';
        customerSelect.removeAttribute('required');
        supplierSelect.removeAttribute('required');
        // --- End of Reset ---

        if (type === 'Sale') {
            customerField.style.display = 'block';
            customerSelect.setAttribute('required', 'required');
        } else if (type === 'Purchase') {
            supplierField.style.display = 'block';
            supplierSelect.setAttribute('required', 'required');
        } else if (type === 'Customer Return') { // Only Customer field visible for Customer Return
            customerField.style.display = 'block';
            returnInfoText.innerHTML = 'Please select the Customer from whom the item was returned.';
            returnInfoText.style.display = 'block';
        } else if (type === 'Supplier Return') { // Only Supplier field visible for Supplier Return
            supplierField.style.display = 'block';
            returnInfoText.innerHTML = 'Please select the Supplier to whom the item is being returned.';
            returnInfoText.style.display = 'block';
        } else if (type === 'Stock Adjustment') {
            // Both customerField and supplierField remain 'none'
        } else {
            // Default or empty selection - both remain 'none'
        }
    }

    // Initial call to set correct state based on default or re-populated values
    // Only call toggleFields if transactionTypeSelect exists
    if (transactionTypeSelect) {
        toggleFields();
        // Add event listener for changes
        transactionTypeSelect.addEventListener('change', toggleFields);
    }
});
</script>

<?php
Logger::log('UI: On staff/transactions/add.php');
?>