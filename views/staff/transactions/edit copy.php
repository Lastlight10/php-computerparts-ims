<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;
use Models\Transaction; // Explicitly use the Transaction model for type hinting if needed
use Models\ProductInstance; // Make sure this is used for ProductInstance data
Logger::log('DEBUG: Rendering edit.php for transaction ID: ' . ($transaction['id'] ?? 'NULL'));
// Initialize variables for messages
$transaction = $transaction ?? [];

// Ensure these are available, even if empty
$customers = $customers ?? [];
$suppliers = $suppliers ?? [];

// Error and success messages passed from the controller via session or GET
$error_data = $_SESSION['error_data'] ?? [];
$error_message = $_SESSION['error_message'] ?? null;
$success_messa = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_data']);
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
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

// Ensure $transaction is passed. If not, this page can't function.
if (!isset($transaction) || !$transaction) {
    echo '<div class="alert alert-danger text-center mt-5">Transaction data not available for editing.</div>';
    Logger::log('ERROR: Transaction object not available in staff/transactions/edit.php');
    return; // Stop rendering the form if no transaction
}

// Ensure customers and suppliers are passed (empty arrays if not provided)
$customers = $customers ?? [];
$suppliers = $suppliers ?? [];

// For repopulating date input correctly in 'YYYY-MM-DD' format
$transaction_date_value = '';
if (isset($transaction->transaction_date) && !empty($transaction->transaction_date)) {
    // Attempt to parse date to YYYY-MM-DD for input type="date"
    try {
        $transaction_date_value = date('Y-m-d', strtotime($transaction->transaction_date));
    } catch (\Exception $e) {
        $transaction_date_value = htmlspecialchars($transaction->transaction_date); // Fallback
    }
}

// Error data for repopulating submitted serial numbers
// Passed from controller on validation error, e.g., $this->view('...', ['error_data' => ['submitted_serial_numbers' => $submitted_serial_numbers]])
$error_data = $error_data ?? [];

// Ensure available_serial_numbers_by_product, potential_customer_return_serials_by_product, potential_supplier_return_serials_by_product are passed (empty array if not provided)
$available_serial_numbers_by_product = $available_serial_numbers_by_product ?? []; // For Sales
$potential_customer_return_serials_by_product = $potential_customer_return_serials_by_product ?? []; // For Customer Returns
$potential_supplier_return_serials_by_product = $potential_supplier_return_serials_by_product ?? []; // For Supplier Returns
$potential_adjusted_out_serials_by_product = $potential_adjusted_out_serials_by_product ?? []; // For Adjustment Outflow

// Get submitted adjustment directions from error data for sticky form, or default from item if available (e.g., from old adjustment)
$submitted_adjustment_directions = $error_data['item_adjustment_direction'] ?? [];

// Determine if the entire form should be read-only based on the transaction status
// This is the initial state from the database
$initial_is_form_readonly = ($transaction->status === 'Completed' || $transaction->status === 'Cancelled');
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Transaction: #<?= htmlspecialchars($transaction->invoice_bill_number) ?></h3>

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

          <form action="/staff/transactions/update" method="POST" id="transactionForm">
            <input type="hidden" name="id" value="<?= htmlspecialchars($transaction->id) ?>">
            <input type="hidden" id="initialStatus" value="<?= htmlspecialchars($transaction->status) ?>">
            <input type="hidden" id="initialTransactionType" value="<?= htmlspecialchars($transaction->transaction_type) ?>">
            <input type="hidden" name="items[]" value="" style="display: none;">


            <div class="mb-3">
              <label for="invoice_bill_number" class="form-label light-txt">Invoice/Bill Number</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="invoice_bill_number" value="<?= htmlspecialchars($transaction->invoice_bill_number) ?>" readonly>
            </div>

            <div class="mb-3">
              <label for="transaction_type" class="form-label light-txt">Transaction Type</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="transaction_type" name="transaction_type" required <?= $initial_is_form_readonly ? 'disabled' : '' ?>>
                <option value="Sale" <?= ($transaction->transaction_type == 'Sale') ? 'selected' : '' ?>>Sale</option>
                <option value="Purchase" <?= ($transaction->transaction_type == 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                <option value="Customer Return" <?= ($transaction->transaction_type == 'Customer Return') ? 'selected' : '' ?>>Customer Return</option>
                <option value="Supplier Return" <?= ($transaction->transaction_type == 'Supplier Return') ? 'selected' : '' ?>>Supplier Return</option>
                <option value="Stock Adjustment" <?= ($transaction->transaction_type == 'Stock Adjustment') ? 'selected' : '' ?>>Stock Adjustment</option>
              </select>
            </div>

            <div class="mb-3" id="customer_field">
              <label for="customer_id" class="form-label light-txt">Customer</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="customer_id" name="customer_id"
                      data-initial-value="<?= htmlspecialchars($transaction->customer_id ?? '') ?>" <?= $initial_is_form_readonly ? 'disabled' : '' ?>> <option value="">Select Customer (Optional)</option>
                <?php foreach ($customers as $customer): // Changed from object to array access ?>
                  <option value="<?= htmlspecialchars($customer['id']) ?>"
                    <?php
                    if ($transaction->transaction_type !== 'Stock Adjustment' && isset($transaction->customer_id) && $transaction->customer_id == $customer['id']) {
                        echo 'selected';
                    }
                    ?>
                  >
                    <?= htmlspecialchars($customer['company_name'] ?? ($customer['contact_first_name'] . ' ' . $customer['contact_last_name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3" id="supplier_field">
              <label for="supplier_id" class="form-label light-txt">Supplier</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="supplier_id" name="supplier_id"
                      data-initial-value="<?= htmlspecialchars($transaction->supplier_id ?? '') ?>" <?= $initial_is_form_readonly ? 'disabled' : '' ?>> <option value="">Select Supplier (Optional)</option>
                <?php foreach ($suppliers as $supplier): // Changed from object to array access ?>
                  <option value="<?= htmlspecialchars($supplier['id']) ?>"
                    <?php
                    if ($transaction->transaction_type !== 'Stock Adjustment' && isset($transaction->supplier_id) && $transaction->supplier_id == $supplier['id']) {
                        echo 'selected';
                    }
                    ?>
                  >
                    <?= htmlspecialchars($supplier['company_name'] ?? ($supplier['contact_first_name'] . ' ' . $supplier['contact_last_name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label for="transaction_date" class="form-label light-txt">Transaction Date</label>
              <input type="date" class="form-control form-control-lg dark-txt light-bg" id="transaction_date" name="transaction_date"
                     value="<?= $transaction_date_value ?>" required <?= $initial_is_form_readonly ? 'disabled' : '' ?>>
            </div>

            <div class="mb-3">
              <label for="status" class="form-label light-txt">Status</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="status" name="status" required>
                <option value="Draft" <?= ($transaction->status == 'Draft') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Draft</option>
                <option value="Pending" <?= ($transaction->status == 'Pending') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Pending</option>
                <option value="Confirmed" <?= ($transaction->status == 'Confirmed') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Confirmed</option>
                <option value="Completed" <?= ($transaction->status == 'Completed') ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($transaction->status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
              </select>
            </div>

            <h3 class="text-white mt-4 mb-3">Transaction Items</h3>
            <div class="transaction-items-list">
                <?php if (!empty($transaction->items) && count($transaction->items) > 0): ?>
                    <?php foreach ($transaction->items as $index => $item): ?>
                        <div class="card lighterdark-bg mb-3 p-3 shadow-sm" data-product-id="<?= htmlspecialchars($item->product->id); ?>" data-is-serialized="<?= htmlspecialchars((int)$item->product->is_serialized); ?>" data-quantity="<?= htmlspecialchars($item->quantity); ?>">
                            <div class="card-body">
                                <h5 class="card-title text-white"><?= htmlspecialchars($item->product->name ?? 'N/A') ?> (SKU: <?= htmlspecialchars($item->product->sku ?? 'N/A') ?>)</h5>
                                <p class="card-text light-txt">
                                    Quantity: <?= htmlspecialchars($item->quantity) ?><br>
                                    Unit Price: $<?= number_format($item->product->unit_price, 2) ?><br>
                                    Line Total: $<?= number_format($item->line_total, 2) ?>
                                </p>

                                <input type="hidden" name="items[<?= $index ?>][id]" value="<?= htmlspecialchars($item->id) ?>">
                                <input type="hidden" name="items[<?= $index ?>][product_id]" value="<?= htmlspecialchars($item->product_id) ?>">
                                <input type="hidden" name="items[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item->quantity) ?>">
                                <input type="hidden" name="items[<?= $index ?>][unit_price]" value="<?= htmlspecialchars($item->unit_price) ?>">
                                <input type="hidden" name="items[<?= $index ?>][line_total]" value="<?= htmlspecialchars($item->line_total) ?>">

                                <?php
                                // Common flags for serial number sections
                                $is_serialized_product = ($item->product->is_serialized ?? false);
                                // The initial_allow_serial_number_interaction is no longer used to control disabled state of serials
                                ?>

                                <?php if ($is_serialized_product && $transaction->transaction_type === 'Purchase'): ?>
                                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="purchase" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                        <h6 class="text-white">Serial Numbers (Purchase - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                                        <p class="text-muted">Enter a unique serial number for each unit purchased.</p>

                                        <?php
                                        $current_purchase_serials = [];
                                        // Priority 1: From submitted error data (sticky form repopulation)
                                        if (isset($error_data['submitted_serial_numbers'][$item->id]) && is_array($error_data['submitted_serial_numbers'][$item->id])) {
                                            $current_purchase_serials = $error_data['submitted_serial_numbers'][$item->id];
                                        }
                                        // Priority 2: From existing ProductInstances if the transaction is already completed
                                        // This requires 'items.purchasedInstances' to be eager-loaded in the controller.
                                        elseif ($item->relationLoaded('purchasedInstances') && !empty($item->purchasedInstances)) {
                                            foreach ($item->purchasedInstances as $instance) {
                                                $current_purchase_serials[] = $instance->serial_number;
                                            }
                                        }
                                        ?>

                                        <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                            <div class="form-group mb-2">
                                                <label for="purchase_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Serial #<?= ($i + 1); ?>:</label>
                                                <input type="text"
                                                       class="form-control form-control-sm dark-txt light-bg serial-number-input"
                                                       id="purchase_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                       name="serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                       value="<?= htmlspecialchars($current_purchase_serials[$i] ?? ''); ?>"
                                                       data-product-id="<?= htmlspecialchars($item->product->id); ?>"
                                                       data-item-id="<?= htmlspecialchars($item->id); ?>"
                                                       required>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_serialized_product && $transaction->transaction_type === 'Sale'): ?>
                                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="sale" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                        <h6 class="text-white">Serial Numbers (Sale - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                                        <p class="text-muted">Select the specific serial numbers being sold from available stock.</p>

                                        <?php
                                        // Get available instances for this product from the data passed by controller
                                        $available_instances_for_product = $available_serial_numbers_by_product[$item->product->id] ?? [];
                                        // Sort available instances by serial number for readability
                                        usort($available_instances_for_product, function($a, $b) {
                                            return strcmp($a['serial_number'], $b['serial_number']);
                                        });

                                        // Prepare pre-filled selected serial numbers for sales
                                        $current_sale_serials = [];
                                        // Priority 1: From submitted error data (sticky form repopulation)
                                        if (isset($error_data['selected_serial_numbers'][$item->id]) && is_array($error_data['selected_serial_numbers'][$item->id])) {
                                            $current_sale_serials = $error_data['selected_serial_numbers'][$item->id];
                                        }
                                        // Priority 2: From existing ProductInstances if the transaction is already completed
                                        // This requires 'items.soldInstances' to be eager-loaded in the controller.
                                        elseif ($item->relationLoaded('soldInstances') && !empty($item->soldInstances)) {
                                            foreach ($item->soldInstances as $instance) {
                                                $current_sale_serials[] = $instance->serial_number; // This is a real Eloquent object
                                            }
                                        }
                                        ?>

                                        <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                            <div class="form-group mb-2">
                                                <label for="sale_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
                                                <select class="form-select form-control-sm dark-txt light-bg serial-number-input"
                                                        id="sale_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                        name="selected_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                        data-product-id="<?= htmlspecialchars($item->product->id); ?>"
                                                        data-item-id="<?= htmlspecialchars($item->id); ?>"
                                                        required>
                                                    <option value="">-- Select a Serial Number --</option>
                                                    <?php
                                                    $selected_value = $current_sale_serials[$i] ?? null;

                                                    // Collect all serials that are either 'In Stock' or were previously selected for this item (for pre-fill)
                                                    $display_options = [];
                                                    foreach ($available_instances_for_product as $instance) {
                                                        $display_options[$instance['serial_number']] = $instance;
                                                    }
                                                    // Add any previously selected but perhaps now unavailable serials for sticky form/completed transaction display
                                                    if ($selected_value && !isset($display_options[$selected_value])) {
                                                        $temp_instance = new ProductInstance(); // Create a dummy instance to represent it
                                                        $temp_instance->serial_number = $selected_value;
                                                        $temp_instance->status = 'Unavailable/Previously Sold'; // Indicate its status
                                                        $display_options[$selected_value] = $temp_instance;
                                                    }

                                                    // Sort display options by serial number
                                                    ksort($display_options);

                                                    foreach ($display_options as $serial_num => $instance):
                                                        $option_text = htmlspecialchars($serial_num);
                                                        // Check if $instance is an array (from DB) or an object (dummy/eager loaded)
                                                        if (is_array($instance)) {
                                                            if (isset($instance['status']) && $instance['status'] !== 'In Stock') {
                                                                $option_text .= " ({$instance['status']})";
                                                            }
                                                        } else { // It's a ProductInstance object (e.g., from eager load or dummy)
                                                            if (isset($instance->status) && $instance->status !== 'In Stock') {
                                                                $option_text .= " ({$instance->status})";
                                                            }
                                                        }
                                                        // This specific condition for 'Previously Selected' applies to the dummy object
                                                        if (is_object($instance) && $instance->status === 'Unavailable/Previously Sold' && $selected_value === $serial_num) {
                                                            $option_text .= " (Previously Selected)";
                                                        }
                                                    ?>
                                                        <option value="<?= htmlspecialchars($serial_num); ?>"
                                                            <?= ($serial_num === $selected_value) ? 'selected' : ''; ?>>
                                                            <?= $option_text; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endfor; ?>
                                        <?php if (empty($available_instances_for_product)): ?>
                                            <p class="text-warning mt-2">No serialized units of this product are currently 'In Stock' for sale.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_serialized_product && $transaction->transaction_type === 'Customer Return'): ?>
                                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="customer-return" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                        <?php if ($transaction->customer_id): ?>
                                            <div class="serial-numbers-subsection" data-return-type="customer">
                                                <h6 class="text-white">Serial Numbers (Customer Return - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                                                <p class="text-muted">Select the specific serial numbers being returned by the customer (should be previously sold).</p>

                                                <?php
                                                // Get potential instances for customer return (typically 'Sold' items)
                                                $potential_return_serials = $potential_customer_return_serials_by_product[$item->product->id] ?? [];
                                                usort($potential_return_serials, function($a, $b) {
                                                    return strcmp($a['serial_number'], $b['serial_number']);
                                                });

                                                // Prepare pre-filled selected serial numbers for customer returns
                                                $current_return_serials = [];
                                                if (isset($error_data['returned_serial_numbers'][$item->id]) && is_array($error_data['returned_serial_numbers'][$item->id])) {
                                                    $current_return_serials = $error_data['returned_serial_numbers'][$item->id];
                                                } elseif ($item->relationLoaded('returnedFromCustomerInstances') && !empty($item->returnedFromCustomerInstances)) {
                                                    foreach ($item->returnedFromCustomerInstances as $instance) {
                                                        $current_return_serials[] = $instance->serial_number; // This is a real Eloquent object
                                                    }
                                                }
                                                ?>

                                                <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                                    <div class="form-group mb-2">
                                                        <label for="return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
                                                        <select class="form-select form-control-sm dark-txt light-bg serial-number-input"
                                                                id="return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                                name="returned_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                                data-product-id="<?= htmlspecialchars($item->product->id); ?>"
                                                                data-item-id="<?= htmlspecialchars($item->id); ?>"
                                                                required>
                                                            <option value="">-- Select a Serial Number --</option>
                                                            <?php
                                                            $selected_value = $current_return_serials[$i] ?? null;

                                                            $display_options_return = [];
                                                            foreach ($potential_return_serials as $instance) {
                                                                $display_options_return[$instance['serial_number']] = $instance;
                                                            }
                                                            if ($selected_value && !isset($display_options_return[$selected_value])) {
                                                                $temp_instance = new ProductInstance();
                                                                $temp_instance->serial_number = $selected_value;
                                                                $temp_instance->status = 'Previously Returned';
                                                                $display_options_return[$selected_value] = $temp_instance;
                                                            }
                                                            ksort($display_options_return);

                                                            foreach ($display_options_return as $serial_num => $instance):
                                                                $option_text = htmlspecialchars($serial_num);
                                                                // Check if $instance is an array (from DB) or an object (dummy/eager loaded)
                                                                if (is_array($instance)) {
                                                                    if (isset($instance['status']) && $instance['status'] !== 'Sold') {
                                                                        $option_text .= " ({$instance['status']})";
                                                                    }
                                                                } else { // It's a ProductInstance object (e.g., from eager load or dummy)
                                                                    if (isset($instance->status) && $instance->status !== 'Sold') {
                                                                        $option_text .= " ({$instance->status})";
                                                                    }
                                                                }
                                                                // This specific condition for 'Previously Returned' applies to the dummy object
                                                                if (is_object($instance) && $instance->status === 'Previously Returned' && $selected_value === $serial_num) {
                                                                    $option_text .= " (Previously Returned)";
                                                                }
                                                            ?>
                                                                <option value="<?= htmlspecialchars($serial_num); ?>"
                                                                    <?= ($serial_num === $selected_value) ? 'selected' : ''; ?>>
                                                                    <?= $option_text; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endfor; ?>
                                                <?php if (empty($potential_return_serials)): ?>
                                                    <p class="text-warning mt-2">No serialized units of this product are currently 'Sold' for return.</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-warning">Please select a Customer for Customer Return to manage serial numbers.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_serialized_product && $transaction->transaction_type === 'Supplier Return'): ?>
                                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="supplier-return" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                        <?php if ($transaction->supplier_id): ?>
                                            <div class="serial-numbers-subsection" data-return-type="supplier">
                                                <h6 class="text-white">Serial Numbers (Supplier Return - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                                                <p class="text-muted">Select the specific serial numbers being returned to the supplier (should be in stock).</p>

                                                <?php
                                                // Get potential instances for supplier return (typically 'In Stock' items)
                                                $potential_supplier_return_serials = $potential_supplier_return_serials_by_product[$item->product->id] ?? [];
                                                usort($potential_supplier_return_serials, function($a, $b) {
                                                    return strcmp($a['serial_number'], $b['serial_number']);
                                                });

                                                // Prepare pre-filled selected serial numbers for supplier returns
                                                $current_supplier_return_serials = [];
                                                if (isset($error_data['supplier_returned_serial_numbers'][$item->id]) && is_array($error_data['supplier_returned_serial_numbers'][$item->id])) {
                                                    $current_supplier_return_serials = $error_data['supplier_returned_serial_numbers'][$item->id];
                                                } elseif ($item->relationLoaded('returnedToSupplierInstances') && !empty($item->returnedToSupplierInstances)) {
                                                    foreach ($item->returnedToSupplierInstances as $instance) {
                                                        $current_supplier_return_serials[] = $instance->serial_number; // This is a real Eloquent object
                                                    }
                                                }
                                                ?>

                                                <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                                    <div class="form-group mb-2">
                                                        <label for="supplier_return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
                                                        <select class="form-select form-control-sm dark-txt light-bg serial-number-input"
                                                                id="supplier_return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                                name="supplier_returned_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                                data-product-id="<?= htmlspecialchars($item->product->id); ?>"
                                                                data-item-id="<?= htmlspecialchars($item->id); ?>"
                                                                required>
                                                            <option value="">-- Select a Serial Number --</option>
                                                            <?php
                                                            $selected_value = $current_supplier_return_serials[$i] ?? null;

                                                            $display_options_supplier_return = [];
                                                            foreach ($potential_supplier_return_serials as $instance) {
                                                                $display_options_supplier_return[$instance['serial_number']] = $instance;
                                                            }
                                                            if ($selected_value && !isset($display_options_supplier_return[$selected_value])) {
                                                                $temp_instance = new ProductInstance();
                                                                $temp_instance->serial_number = $selected_value;
                                                                $temp_instance->status = 'Previously Returned to Supplier';
                                                                $display_options_supplier_return[$selected_value] = $temp_instance;
                                                            }
                                                            ksort($display_options_supplier_return);

                                                            foreach ($display_options_supplier_return as $serial_num => $instance):
                                                                $option_text = htmlspecialchars($serial_num);
                                                                // Check if $instance is an array (from DB) or an object (dummy/eager loaded)
                                                                if (is_array($instance)) {
                                                                    if (isset($instance['status']) && $instance['status'] !== 'In Stock') {
                                                                        $option_text .= " ({$instance['status']})";
                                                                    }
                                                                } else { // It's a ProductInstance object (e.g., from eager load or dummy)
                                                                    if (isset($instance->status) && $instance->status !== 'In Stock') {
                                                                        $option_text .= " ({$instance->status})";
                                                                    }
                                                                }
                                                                // This specific condition for 'Previously Returned to Supplier' applies to the dummy object
                                                                if (is_object($instance) && $instance->status === 'Previously Returned to Supplier' && $selected_value === $serial_num) {
                                                                    $option_text .= " (Previously Returned to Supplier)";
                                                                }
                                                            ?>
                                                                <option value="<?= htmlspecialchars($serial_num); ?>"
                                                                    <?= ($serial_num === $selected_value) ? 'selected' : ''; ?>>
                                                                    <?= $option_text; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                <?php endfor; ?>
                                                <?php if (empty($potential_supplier_return_serials)): ?>
                                                    <p class="text-warning mt-2">No serialized units of this product are currently 'In Stock' for supplier return.</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-warning">Please select a Supplier for Supplier Return to manage serial numbers.</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($is_serialized_product && $transaction->transaction_type === 'Stock Adjustment'): ?>
                                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="adjustment" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                        <h6 class="text-white">Serial Numbers (Adjustment - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                                        <p class="text-muted">Specify the direction of the adjustment (inflow/outflow) and manage serial numbers.</p>

                                        <?php
                                        // Determine the current adjustment direction for this item from old input or previous transaction
                                        $current_adjustment_direction = $submitted_adjustment_directions[$item->id] ?? null;

                                        if (!$current_adjustment_direction) {
                                            // Attempt to get from existing instances if transaction is completed/pending
                                            if ($item->relationLoaded('adjustedInInstances') && count($item->adjustedInInstances) > 0) {
                                                $current_adjustment_direction = 'inflow';
                                            } elseif ($item->relationLoaded('adjustedOutInstances') && count($item->adjustedOutInstances) > 0) {
                                                $current_adjustment_direction = 'outflow';
                                            }
                                        }
                                        ?>

                                        <div class="form-group mb-3">
                                            <label class="form-label light-txt">Adjustment Direction:</label>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input adjustment-direction-radio" type="radio" name="item_adjustment_direction[<?= htmlspecialchars($item->id); ?>]"
                                                       id="adjustment_inflow_<?= htmlspecialchars($item->id); ?>" value="inflow"
                                                       <?= ($current_adjustment_direction === 'inflow') ? 'checked' : ''; ?>>
                                                <label class="form-check-label light-txt" for="adjustment_inflow_<?= htmlspecialchars($item->id); ?>">Inflow (Add to Stock)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input adjustment-direction-radio" type="radio" name="item_adjustment_direction[<?= htmlspecialchars($item->id); ?>]"
                                                       id="adjustment_outflow_<?= htmlspecialchars($item->id); ?>" value="outflow"
                                                       <?= ($current_adjustment_direction === 'outflow') ? 'checked' : ''; ?>>
                                                <label class="form-check-label light-txt" for="adjustment_outflow_<?= htmlspecialchars($item->id); ?>">Outflow (Remove from Stock)</label>
                                            </div>
                                        </div>

                                        <div class="serial-number-fields" data-item-id="<?= htmlspecialchars($item->id); ?>">
                                            <?php if ($current_adjustment_direction === 'inflow'): ?>
                                                <div class="inflow-section">
                                                    <h6 class="text-white">Inflow Serial Numbers:</h6>
                                                    <p class="text-muted">Enter new serial numbers to add to stock.</p>
                                                    <?php
                                                    $current_adjusted_in_serials = [];
                                                    if (isset($error_data['adjusted_in_serial_numbers'][$item->id]) && is_array($error_data['adjusted_in_serial_numbers'][$item->id])) {
                                                        $current_adjusted_in_serials = $error_data['adjusted_in_serial_numbers'][$item->id];
                                                    } elseif ($item->relationLoaded('adjustedInInstances') && !empty($item->adjustedInInstances)) {
                                                        // Ensure this is accessing object properties since adjustedInInstances is a collection of objects
                                                        $current_adjusted_in_serials = array_map(fn($instance) => $instance->serial_number, $item->adjustedInInstances->toArray());
                                                    }
                                                    ?>
                                                    <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                                        <div class="form-group mb-2">
                                                            <label for="adjusted_in_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Serial #<?= ($i + 1); ?>:</label>
                                                            <input type="text"
                                                                   class="form-control form-control-sm dark-txt light-bg serial-number-input"
                                                                   id="adjusted_in_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                                   name="adjusted_in_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                                   value="<?= htmlspecialchars($current_adjusted_in_serials[$i] ?? ''); ?>"
                                                                   required>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php elseif ($current_adjustment_direction === 'outflow'): ?>
                                                <div class="outflow-section">
                                                    <h6 class="text-white">Outflow Serial Numbers:</h6>
                                                    <p class="text-muted">Select serial numbers to remove from stock.</p>
                                                    <?php
                                                    // Get available in-stock instances for this product
                                                    $available_in_stock_for_adjustment_out = $potential_adjusted_out_serials_by_product[$item->product->id] ?? [];
                                                    usort($available_in_stock_for_adjustment_out, function($a, $b) {
                                                        return strcmp($a['serial_number'], $b['serial_number']);
                                                    });

                                                    $current_adjusted_out_serials = [];
                                                    if (isset($error_data['adjusted_out_serial_numbers'][$item->id]) && is_array($error_data['adjusted_out_serial_numbers'][$item->id])) {
                                                        $current_adjusted_out_serials = $error_data['adjusted_out_serial_numbers'][$item->id];
                                                    } elseif ($item->relationLoaded('adjustedOutInstances') && !empty($item->adjustedOutInstances)) {
                                                        foreach ($item->adjustedOutInstances as $instance) {
                                                            $current_adjusted_out_serials[] = $instance->serial_number; // This is a real Eloquent object
                                                        }
                                                    }
                                                    ?>
                                                    <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                                        <div class="form-group mb-2">
                                                            <label for="adjusted_out_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
                                                            <select class="form-select form-control-sm dark-txt light-bg serial-number-input"
                                                                    id="adjusted_out_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                                                                    name="adjusted_out_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                                                                    required>
                                                                <option value="">-- Select a Serial Number --</option>
                                                                <?php
                                                                $selected_value = $current_adjusted_out_serials[$i] ?? null;

                                                                $display_options_outflow = [];
                                                                foreach ($available_in_stock_for_adjustment_out as $instance) {
                                                                    $display_options_outflow[$instance['serial_number']] = $instance;
                                                                }
                                                                if ($selected_value && !isset($display_options_outflow[$selected_value])) {
                                                                    $temp_instance = new ProductInstance();
                                                                    $temp_instance->serial_number = $selected_value;
                                                                    $temp_instance->status = 'Previously Adjusted Out';
                                                                    $display_options_outflow[$selected_value] = $temp_instance;
                                                                }
                                                                ksort($display_options_outflow);

                                                                foreach ($display_options_outflow as $serial_num => $instance):
                                                                    $option_text = htmlspecialchars($serial_num);
                                                                    // Check if $instance is an array (from DB) or an object (dummy/eager loaded)
                                                                    if (is_array($instance)) {
                                                                        if (isset($instance['status']) && $instance['status'] !== 'In Stock') {
                                                                            $option_text .= " ({$instance['status']})";
                                                                        }
                                                                    } else { // It's a ProductInstance object (e.g., from eager load or dummy)
                                                                        if (isset($instance->status) && $instance->status !== 'In Stock') {
                                                                            $option_text .= " ({$instance->status})";
                                                                        }
                                                                    }
                                                                    // This specific condition for 'Previously Adjusted Out' applies to the dummy object
                                                                    if (is_object($instance) && $instance->status === 'Previously Adjusted Out' && $selected_value === $serial_num) {
                                                                        $option_text .= " (Previously Adjusted Out)";
                                                                    }
                                                                ?>
                                                                    <option value="<?= htmlspecialchars($serial_num); ?>"
                                                                        <?= ($serial_num === $selected_value) ? 'selected' : ''; ?>>
                                                                        <?= $option_text; ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                    <?php endfor; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-white-50">No items in this transaction.</p>
                <?php endif; ?>
            </div>

            <div class="mb-3">
              <label for="notes" class="form-label light-txt">Notes</label>
              <textarea class="form-control dark-txt light-bg" id="notes" name="notes" rows="3" <?= $initial_is_form_readonly ? 'disabled' : '' ?>><?= htmlspecialchars($transaction->notes ?? '') ?></textarea>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg" id="updateButton">Update Transaction</button>
              <a href="/staff/transactions_list" class="btn btn-secondary btn-lg">Back to List</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const transactionForm = document.getElementById('transactionForm');
    const transactionTypeSelect = document.getElementById('transaction_type');
    const statusSelect = document.getElementById('status');
    const initialStatusInput = document.getElementById('initialStatus');
    const initialTransactionTypeInput = document.getElementById('initialTransactionType');
    const customerField = document.getElementById('customer_field');
    const supplierField = document.getElementById('supplier_field');
    const customerSelect = document.getElementById('customer_id');
    const supplierSelect = document.getElementById('supplier_id');
    const initialCustomerValue = customerSelect.dataset.initialValue;
    const initialSupplierValue = supplierSelect.dataset.initialValue;
    const updateButton = document.getElementById('updateButton');

    // Get all form controls that should be disabled when the form is read-only (excluding serial numbers and the update button)
    const formControls = document.querySelectorAll(
        '#transactionForm input:not(#invoice_bill_number):not(.serial-number-input), ' + // Exclude invoice_bill_number (always readonly) and serial-number-input
        '#transactionForm select:not(#status):not(.serial-number-input), ' + // Exclude status dropdown itself and serial-number-input
        '#transactionForm textarea' // Exclude adjustment-direction-radio
    );

    // Get all serial number sections and their product types
    const serialNumberSections = document.querySelectorAll('.serial-numbers-section');

    // Store the initial status of the transaction from the PHP rendered value
    const initialTransactionStatus = initialStatusInput.value;

    function setFormReadonly(isReadonly) {
        formControls.forEach(control => {
            if (isReadonly) {
                control.setAttribute('disabled', 'disabled');
            } else {
                control.removeAttribute('disabled');
            }
        });

        // The status dropdown itself
        const statusOptions = statusSelect.options;
        for (let i = 0; i < statusOptions.length; i++) {
            if (isReadonly && statusOptions[i].value !== statusSelect.value) {
                // If form is read-only, disable all status options except the currently selected one
                statusOptions[i].setAttribute('disabled', 'disabled');
            } else {
                // If form is not read-only, ensure all original options are enabled
                // (except 'Completed' and 'Cancelled' if they are the current status, which is handled by PHP initial_is_form_readonly)
                if (statusOptions[i].value !== 'Completed' && statusOptions[i].value !== 'Cancelled') {
                     statusOptions[i].removeAttribute('disabled');
                }
            }
        }
        // Special case for 'Completed' and 'Cancelled' options if the form is already read-only
        if (initialTransactionStatus === 'Completed' || initialTransactionStatus === 'Cancelled') {
            for (let i = 0; i < statusOptions.length; i++) {
                if (statusOptions[i].value !== initialTransactionStatus) {
                    statusOptions[i].setAttribute('disabled', 'disabled');
                }
            }
        }

        // --- REMOVED THIS BLOCK TO KEEP THE BUTTON ALWAYS ENABLED ---
        // if (isReadonly) {
        //     updateButton.setAttribute('disabled', 'disabled');
        // } else {
        //     updateButton.removeAttribute('disabled');
        // }
        // --- END REMOVED BLOCK ---


        // Now, trigger updateVisibility to handle serial number specific states (visibility and required)
        updateVisibility();
    }

    function updateVisibility() {
        const selectedType = transactionTypeSelect.value;
        const selectedStatus = statusSelect.value; // Get the current selected status

        // Reset display and required attributes for customer/supplier fields
        customerField.style.display = 'none';
        supplierField.style.display = 'none';
        customerSelect.removeAttribute('required');
        supplierSelect.removeAttribute('required');

        // Note: Customer/supplier selects are now also generally disabled by setFormReadonly() if isReadonly.
        // This specific logic primarily controls their visibility and 'required' attribute when the form IS editable.

        // Reset selected values for customer/supplier dropdowns based on transaction type logic
        if (selectedType === 'Sale' || selectedType === 'Customer Return') {
            customerField.style.display = 'block';
            if (!(selectedStatus === 'Completed' || selectedStatus === 'Cancelled')) { // Only set required if not completed/cancelled
                customerSelect.setAttribute('required', 'required');
            }
            customerSelect.value = initialCustomerValue;
            supplierSelect.value = '';
        } else if (selectedType === 'Purchase' || selectedType === 'Supplier Return') {
            supplierField.style.display = 'block';
            if (!(selectedStatus === 'Completed' || selectedStatus === 'Cancelled')) { // Only set required if not completed/cancelled
                supplierSelect.setAttribute('required', 'required');
            }
            supplierSelect.value = initialSupplierValue;
            customerSelect.value = '';
        } else if (selectedType === 'Stock Adjustment') {
            customerSelect.value = '';
            supplierSelect.value = '';
        }

        // Handle visibility and required state of serial number sections and their inputs
        serialNumberSections.forEach(section => {
            const sectionType = section.dataset.type;
            const quantity = parseInt(section.closest('.card').dataset.quantity);

            section.style.display = 'none'; // Hide all sections by default

            const serialInputs = section.querySelectorAll('.serial-number-input');
            const adjustmentDirectionRadios = section.querySelectorAll('.adjustment-direction-radio');

            // Serial number inputs and adjustment radios should always be enabled, so no `disabled` logic here.
            serialInputs.forEach(input => {
                input.removeAttribute('disabled'); // Ensure serial inputs are never disabled by this JS
                input.removeAttribute('required'); // Remove required initially, will be set conditionally below
            });
            adjustmentDirectionRadios.forEach(radio => {
                radio.removeAttribute('disabled'); // Ensure radios are never disabled by this JS
            });


            // Show relevant serial number section based on transaction type
            if (selectedType === 'Purchase' && sectionType === 'purchase') {
                section.style.display = 'block';
                if (quantity > 0) serialInputs.forEach(input => input.setAttribute('required', 'required'));
            } else if (selectedType === 'Sale' && sectionType === 'sale') {
                section.style.display = 'block';
                if (quantity > 0) serialInputs.forEach(input => input.setAttribute('required', 'required'));
            } else if (selectedType === 'Customer Return' && sectionType === 'customer-return') {
                section.style.display = 'block';
                if (quantity > 0) serialInputs.forEach(input => input.setAttribute('required', 'required'));
            } else if (selectedType === 'Supplier Return' && sectionType === 'supplier-return') {
                section.style.display = 'block';
                if (quantity > 0) serialInputs.forEach(input => input.setAttribute('required', 'required'));
            } else if (selectedType === 'Stock Adjustment' && sectionType === 'adjustment') {
                section.style.display = 'block';
                updateAdjustmentSerialFields(); // This function will handle setting 'required' for adjustment serials
            }
        });
    }

    function updateAdjustmentSerialFields() {
        document.querySelectorAll('.serial-numbers-section[data-type="adjustment"]').forEach(section => {
            const itemId = section.dataset.itemId;
            const quantity = parseInt(section.closest('.card').dataset.quantity);
            const inflowRadio = document.getElementById(`adjustment_inflow_${itemId}`);
            const outflowRadio = document.getElementById(`adjustment_outflow_${itemId}`);
            const inflowSection = section.querySelector('.inflow-section');
            const outflowSection = section.querySelector('.outflow-section');

            if (inflowSection) inflowSection.style.display = 'none';
            if (outflowSection) outflowSection.style.display = 'none';

            const inflowInputs = inflowSection ? inflowSection.querySelectorAll('.serial-number-input') : [];
            const outflowInputs = outflowSection ? outflowSection.querySelectorAll('.serial-number-input') : [];

            // First, remove required from all adjustment serial inputs
            inflowInputs.forEach(input => input.removeAttribute('required'));
            outflowInputs.forEach(input => input.removeAttribute('required'));


            if (inflowRadio && inflowRadio.checked) {
                if (inflowSection) inflowSection.style.display = 'block';
                if (quantity > 0) inflowInputs.forEach(input => input.setAttribute('required', 'required'));
            } else if (outflowRadio && outflowRadio.checked) {
                if (outflowSection) outflowSection.style.display = 'block';
                if (quantity > 0) outflowInputs.forEach(input => input.setAttribute('required', 'required'));
            }
            // If neither is checked, inputs remain not required and visible (default state for adjustment)
        });
    }

    // --- Custom Validation Logic on Submit ---
    transactionForm.addEventListener('submit', function(event) {
        const currentSelectedStatus = statusSelect.value;
        const previousStatus = initialStatusInput.value;
        const transactionType = initialTransactionTypeInput.value;

        // Only perform custom serial validation if transitioning TO 'Completed' from a non-'Completed' state
        if (currentSelectedStatus === 'Completed' && previousStatus !== 'Completed') {
            let allSerialsFilled = true;
            let missingSerialsMessage = "Please fill in all required serial numbers before marking the transaction as 'Completed'.\n\nMissing serial numbers for:";

            // Iterate through each transaction item that has serial numbers
            document.querySelectorAll('.card[data-is-serialized="1"]').forEach(itemCard => {
                const isSerialized = itemCard.dataset.isSerialized === '1';
                const quantity = parseInt(itemCard.dataset.quantity);
                let currentItemHasMissingSerials = false;

                if (isSerialized) {
                    const itemName = itemCard.querySelector('.card-title').textContent.trim();
                    let serialInputs = [];

                    if (transactionType === 'Purchase') {
                        serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="purchase"] input.serial-number-input');
                    } else if (transactionType === 'Sale') {
                        serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="sale"] select.serial-number-input');
                    } else if (transactionType === 'Customer Return') {
                        serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="customer-return"] select.serial-number-input');
                    } else if (transactionType === 'Supplier Return') {
                        serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="supplier-return"] select.serial-number-input');
                    } else if (transactionType === 'Stock Adjustment') {
                        const itemId = itemCard.querySelector('.serial-numbers-section[data-type="adjustment"]').dataset.itemId;
                        const inflowRadio = document.getElementById(`adjustment_inflow_${itemId}`);
                        const outflowRadio = document.getElementById(`adjustment_outflow_${itemId}`);

                        if (inflowRadio && inflowRadio.checked) {
                            serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="adjustment"] .inflow-section input.serial-number-input');
                        } else if (outflowRadio && outflowRadio.checked) {
                            serialInputs = itemCard.querySelectorAll('.serial-numbers-section[data-type="adjustment"] .outflow-section select.serial-number-input');
                        } else {
                            // If it's an adjustment and no direction is selected, it's an error
                            allSerialsFilled = false;
                            missingSerialsMessage += `\n- ${itemName} (Stock Adjustment: Direction not selected)`;
                            currentItemHasMissingSerials = true;
                        }
                    }

                    if (!currentItemHasMissingSerials) { // Only check if direction was selected for adjustment or not adjustment
                        let filledCount = 0;
                        serialInputs.forEach(input => {
                            if (input.value.trim() !== '') {
                                filledCount++;
                            }
                        });

                        if (filledCount < quantity) {
                            allSerialsFilled = false;
                            missingSerialsMessage += `\n- ${itemName} (${filledCount} / ${quantity} filled)`;
                        }
                    }
                }
            });

            if (!allSerialsFilled) {
                event.preventDefault(); // Stop the form submission
                alert(missingSerialsMessage);
                return; // Exit the function
            }

            // If all serials are filled, then ask for final confirmation
            const confirmCompletion = confirm("Are you sure you want to mark this transaction as 'Completed'? This action will update stock and make the transaction read-only.");
            if (!confirmCompletion) {
                event.preventDefault(); // Stop the form submission
            }
        }
    });


    // Initial calls on page load
    // The initial call to setFormReadonly will correctly disable main fields if needed
    // but the serial number inputs are intentionally left enabled by the JS logic.
    setFormReadonly(Boolean(<?php echo json_encode($initial_is_form_readonly); ?>));
    updateVisibility(); // Ensure initial visibility of serial number sections based on initial transaction type


    transactionTypeSelect.addEventListener('change', updateVisibility);
    statusSelect.addEventListener('change', function() {
        // When status changes, re-evaluate readonly state for main form controls
        const newStatus = statusSelect.value;
        const isReadonlyBasedOnNewStatus = (newStatus === 'Completed' || newStatus === 'Cancelled');
        setFormReadonly(isReadonlyBasedOnNewStatus);
        // updateVisibility is called by setFormReadonly, so it handles serial numbers' required state
    });

    // Event listeners for adjustment direction radios
    document.querySelectorAll('.adjustment-direction-radio').forEach(radio => {
        radio.addEventListener('change', updateAdjustmentSerialFields);
    });
});
</script>