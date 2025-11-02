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
          <h3 class="text-white text-center mb-4">Edit Transaction: #<?= htmlspecialchars($transaction->invoice_bill_number ?? 'N/A') ?></h3>

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
          <form action="/staff/transactions/update" method="POST" id="transactionForm">
            <input type="hidden" name="id" value="<?= htmlspecialchars($transaction->id ?? 'N/A') ?>">
            <input type="hidden" id="initialStatus" value="<?= htmlspecialchars($transaction->status) ?>">
            <input type="hidden" id="initialTransactionType" value="<?= htmlspecialchars($transaction->transaction_type) ?>">
            <input type="hidden" name="items[]" value="" style="display: none;">


            <div class="row mb-3">
                <label for="invoice_bill_number" class="form-label light-txt">Invoice/Bill Number</label>
                <div class="col-sm-10">
                    <input readonly type="text" class="form-control form-select-lg dark-txt light-bg" id="invoice_bill_number" name="invoice_bill_number"
                        value="<?= htmlspecialchars($transaction->invoice_bill_number ?? '') ?>">
                </div>
            </div>
            
            <div class="mb-3">
              <label for="transaction_type" class="form-label light-txt">Transaction Type</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="transaction_type" name="transaction_type" required disabled>
                <option value="Sale" <?= ($transaction->transaction_type == 'Sale') ? 'selected' : '' ?>>Sale</option>
                <option value="Purchase" <?= ($transaction->transaction_type == 'Purchase') ? 'selected' : '' ?>>Purchase</option>
                <option value="Customer Return" <?= ($transaction->transaction_type == 'Customer Return') ? 'selected' : '' ?>>Customer Return</option>
                <option value="Supplier Return" <?= ($transaction->transaction_type == 'Supplier Return') ? 'selected' : '' ?>>Supplier Return</option>
                <option value="Stock Adjustment" <?= ($transaction->transaction_type == 'Stock Adjustment') ? 'selected' : '' ?>>Stock Adjustment</option>
              </select>
            </div>
            
          <?php if (in_array($transaction->transaction_type, ['Sale', 'Customer Return'])): ?>
            <div class="mb-3" id="customer_field">
                <label for="customer_id" class="form-label light-txt">Customer</label>
                
                <!-- Disabled dropdown just for display -->
                <select class="form-select form-select-lg dark-txt light-bg" disabled>
                    <?php if ($transaction->customer): ?>
                        <option selected>
                            <?= htmlspecialchars(
                                $transaction->customer->company_name
                                ?: trim(($transaction->customer->contact_first_name ?? '') . ' ' . ($transaction->customer->contact_last_name ?? ''))
                            ) ?>
                        </option>
                    <?php else: ?>
                        <option>No Customer Assigned</option>
                    <?php endif; ?>
                </select>
                
                <!-- Hidden input to keep value submitted -->
                <input type="hidden" name="customer_id" value="<?= htmlspecialchars((string) $transaction->customer_id) ?>">
            </div>
          <?php endif; ?>

          <?php if (in_array($transaction->transaction_type, ['Purchase', 'Supplier Return'])): ?>
            <div class="mb-3" id="supplier_field">
                <label for="supplier_id" class="form-label light-txt">Supplier</label>
                <select class="form-select form-select-lg dark-txt light-bg" disabled>
                  <option>
                    <?= htmlspecialchars(
                        $transaction->supplier['company_name'] 
                        ?? trim(($transaction->supplier['contact_first_name'] ?? '') . ' ' . ($transaction->supplier['contact_last_name'] ?? ''))
                    ) ?>
                  </option>
                </select>
                <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($transaction->supplier_id ?? '') ?>">
              </div>
            <?php endif; ?>
            
            <div class="row mb-3">
              <label for="total_amount" class="form-label light-txt">Total Amount (₱)</label>
              <div class="col-sm-10">
                  <div class="input-group">
                      <span class="input-group-text">₱</span>
                      <input type="text"
                            class="form-control form-control-lg dark-txt light-bg"
                            id="total_amount"
                            name="total_amount"
                            value="<?= htmlspecialchars(number_format($calculated_total_amount ?? $transaction->total_amount ?? 0, 2)) ?>"
                            readonly>
                  </div>


              </div>
          </div>

          <?php
            // Determine if the amount_received should be displayed
            $show_amount_received = in_array($transaction->transaction_type, ['Sale', 'Purchase', 'Customer Return', 'Supplier Return']);
            if ($show_amount_received):

                $amount_label = match($transaction->transaction_type) {
                    'Sale' => 'Amount Received from Customer (₱):',
                    'Purchase' => 'Amount Paid to Supplier (₱):',
                    'Customer Return' => 'Amount Refunded to Customer (₱):',
                    'Supplier Return' => 'Amount Received from Supplier (Refund in ₱):',
                    default => 'Amount (₱):',
                };

                // Use stored or pre-calculated amount
                $amount_value = number_format($transaction->amount_received ?? $calculated_total_amount ?? 0, 2);
            ?>
                <div class="row mb-3" hidden>
                    <label class="form-label light-txt"><strong><?= $amount_label ?></strong></label>
                    <div class="col-sm-10">
                      <div class="input-group">
                          <span class="input-group-text">₱</span>
                          <input type="text"
                                class="form-control form-control-lg dark-txt light-bg"
                                value="<?= htmlspecialchars($amount_value) ?>"

                                readonly>
                      </div>
                  </div>
                </div>
            <?php endif; ?>




            <div class="mb-3">
              <label for="transaction_date" class="form-label light-txt">Transaction Date</label>
              <input type="date" class="form-control form-control-lg dark-txt light-bg" id="transaction_date" name="transaction_date"
                     value="<?= $transaction_date_value ?>" required <?= $initial_is_form_readonly ? 'disabled' : '' ?> readonly>
            </div>
            

            <div class="mb-3">
              <label for="status" class="form-label light-txt">Status</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="status" name="status" required>
                <!-- <option value="Draft" <?= ($transaction->status == 'Draft') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Draft</option> -->
                <option value="Pending" <?= ($transaction->status == 'Pending') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Pending</option>
                <!-- <option value="Confirmed" <?= ($transaction->status == 'Confirmed') ? 'selected' : '' ?> <?= $initial_is_form_readonly ? 'disabled' : '' ?>>Confirmed</option> -->
                <option value="Completed" <?= ($transaction->status == 'Completed') ? 'selected' : '' ?>>Completed</option>
                <option value="Cancelled" <?= ($transaction->status == 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
              </select>
            </div>

            <h3 class="text-white mt-4 mb-3">Transaction Items</h3>
            <div class="transaction-items-list">
                <?php if (!empty($transaction->items) && count($transaction->items) > 0): ?>
                  <?php 
                    $error_data = $_SESSION['error_data'] ?? [];
                    unset($_SESSION['error_data']); // Clear it after use
                    foreach ($transaction->items as $index => $item): ?>
                  <?php
                        
                  $is_serialized_product = ($item->product->is_serialized ?? false);
                  // Add this debug line:
                  Logger::log("DEBUG: Item Product ID: " . $item->product->id . ", Transaction Type: " . $transaction->transaction_type);
                  ?>
                            
                  <div class="card lighterdark-bg mb-3 p-3 shadow-sm" data-product-id="<?= htmlspecialchars($item->product->id); ?>" data-is-serialized="<?= htmlspecialchars((int)$item->product->is_serialized); ?>" data-quantity="<?= htmlspecialchars($item->quantity); ?>">
                    <div class="card-body">
                      <h5 class="card-title text-white"><?= htmlspecialchars(string: $item->product->name ?? 'N/A') ?> (CODE: <?= htmlspecialchars($item->product->sku ?? 'N/A') ?>)</h5>
                      <p class="text-light">
                        Quantity: <?= htmlspecialchars($item->quantity ?? 'N/A') ?>
                    </p>
                      
                      <input type="hidden" name="items[<?= $index ?>][id]" value="<?= htmlspecialchars($item->id) ?>">
                      <input type="hidden" name="items[<?= $index ?>][product_id]" value="<?= htmlspecialchars($item->product->id) ?>">
                       <input type="hidden" name="items[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item->quantity) ?>">
                  <?php
                                // Only show "Cost at Receipt" for Purchase transactions
                  if ($transaction->transaction_type === 'Purchase'):
                    $default_purchase_cost = '';
                    if (isset($item->purchase_cost) && $item->purchase_cost !== null) {
                      $default_purchase_cost = $item->purchase_cost; // Use saved value if exists
                    } else {
                      // For new purchase items, default to product's cost
                      $default_purchase_cost = $item->product->unit_price ?? ''; // Assuming product has a default cost
                    }
                   ?>
                                
                    <div class="mb-3">
                      <label for="item_cost_<?= $index ?>" class="form-label light-txt">Cost at Receipt (price for customer in ₱):</label>
                      <div class="input-group input-group-sm mb-2">
                          <span class="input-group-text">₱</span>
                          <input type="number" step="0.01" 
                                class="form-control form-control-sm dark-txt light-bg"
                                id="item_cost_<?= $index ?>"
                                name="items[<?= $index ?>][purchase_cost]"
                                value="<?= htmlspecialchars(number_format((float)$default_purchase_cost, 2, '.', '')) ?>"
                                placeholder="Enter cost for this unit" 
                                required 
                                readonly>
                      </div>

                    </div>
                    <?php
                    endif;
                    ?>
                    <?php
                                // Determine if unit_price should be editable.
                                // It's editable for Sales, and potentially Customer Returns (for refund value).
                                // For Purchase, it's typically fixed by the purchase_cost, or perhaps auto-calculated.
                                // For Stock Adjustment/Supplier Return, it's usually not relevant as an editable input here.
                      $is_unit_price_editable = in_array($transaction->transaction_type, ['Sale', 'Customer Return']);

                      // Determine the default value for the unit_price input
                      $default_unit_price = '';
                      if (isset($item->unit_price) && $item->unit_price !== null) {
                          // Always use saved value if exists
                          $default_unit_price = $item->unit_price;
                      } else {
                          switch ($transaction->transaction_type) {
                              case 'Sale':
                                  // For new sale items, default to product's selling price
                                  $default_unit_price = $item->product->unit_price ?? '';
                                  break;

                              case 'Purchase':
                                  // For new purchase items, default to product's cost
                                  $default_unit_price = $item->product->cost_price ?? '';
                                  break;

                              case 'Customer Return':
                                  // Default to the unit price at which the customer bought the product
                                  $default_unit_price = $item->unit_price_at_transaction ?? $item->product->unit_price ?? '';
                                  break;

                              case 'Supplier Return':
                                  // Default to the product's cost (assuming refund is based on cost)
                                  $default_unit_price = $item->purchase_cost_at_transaction ?? $item->product->cost_price ?? '';
                                  break;

                              case 'Stock Adjustment':
                                  // Default to product cost (or 0 if not applicable)
                                  $default_unit_price = $item->product->cost_price ?? 0;
                                  break;

                              default:
                                  $default_unit_price = '';
                          }
                      }

                      ?>
                      <div class="mb-3">
                        <label for="item_unit_price_<?= $index ?>" class="form-label light-txt">
                        <?php
                          if ($transaction->transaction_type === 'Sale') {
                            echo 'Selling Price (price for Customer in ₱):';
                          } elseif ($transaction->transaction_type === 'Customer Return') {
                            echo 'Return Value (per unit in ₱):';
                          } else {
                            echo 'Unit Price (from supplier in ₱):'; // Default label if not Sale/Return
                           }
                        ?>
                        </label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text">₱</span>
                            <input type="text"
                                  class="form-control form-control-sm dark-txt light-bg"
                                  id="item_unit_price_<?= $index ?>"
                                  name="items[<?= $index ?>][unit_price]"
                                  <?= $is_unit_price_editable ? 'required' : 'readonly' ?>
                                  value="<?= htmlspecialchars(number_format((float)$default_unit_price, 2, '.', '')) ?>"
                                  placeholder="No Price">
                        </div>


                      </div>

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
                        // Priority 1: From temporary session data (e.g., successful update of PENDING transaction)
                        if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
                          $current_purchase_serials = $temp_submitted_serials[$item->id];
                        }
                        // Priority 2: From submitted error data (sticky form repopulation on *failed* attempt)
                        elseif (isset($error_data['submitted_serial_numbers'][$item->id]) && is_array($error_data['submitted_serial_numbers'][$item->id])) {
                          $current_purchase_serials = $error_data['submitted_serial_numbers'][$item->id];
                        }
                        // Priority 3: From existing ProductInstances if the transaction is already completed (or has existing links)
                        elseif ($item->relationLoaded('purchasedInstances') && !empty($item->purchasedInstances)) {
                          foreach ($item->purchasedInstances as $instance) {
                            $current_purchase_serials[] = $instance->serial_number;
                          }
                        }
                        // Ensure array has as many elements as quantity, padding with empty strings
                        $displayed_purchase_serials = []; // Use a separate variable for display
                        for ($i = 0; $i < $item->quantity; $i++) {
                          $displayed_purchase_serials[] = $current_purchase_serials[$i] ?? '';
                        }
                                        // Now use $displayed_purchase_serials in the loop below
                        ?>

                        <?php
                          $current_purchase_serials = [];
                          // Priority 1: From temporary session data (e.g., successful update of PENDING transaction)
                          if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
                            $current_purchase_serials = $temp_submitted_serials[$item->id];
                          }
                          // Priority 2: From submitted error data (sticky form repopulation on *failed* attempt)
                          elseif (isset($error_data['submitted_serial_numbers'][$item->id]) && is_array($error_data['submitted_serial_numbers'][$item->id])) {
                            $current_purchase_serials = $error_data['submitted_serial_numbers'][$item->id];
                          }
                          // Priority 3: From existing ProductInstances if the transaction is already completed (or has existing links)
                          elseif ($item->relationLoaded('purchasedInstances') && !empty($item->purchasedInstances)) {
                            foreach ($item->purchasedInstances as $instance) {
                              $current_purchase_serials[] = $instance->serial_number;
                            }
                          }
                          // Ensure array has as many elements as quantity, padding with empty strings
                          $displayed_purchase_serials = []; // Use a separate variable for display
                          for ($i = 0; $i < $item->quantity; $i++) {
                            $displayed_purchase_serials[] = $current_purchase_serials[$i] ?? '';
                          }
                        ?>

                        <?php foreach ($displayed_purchase_serials as $i => $serial_value): ?>
                          <div class="form-group mb-2">
                            <label for="purchase_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Serial #<?= ($i + 1); ?>:</label>
                            <input type="text"
                              class="form-control form-control-sm dark-txt light-bg serial-number-input"
                              id="purchase_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>"
                              name="serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
                              value="<?= htmlspecialchars($serial_value); ?>"
                              data-product-id="<?= htmlspecialchars($item->product->id); ?>"
                              data-item-id="<?= htmlspecialchars($item->id); ?>"
                              required
                              data-maxlength="20">

                          </div>
                        <?php endforeach; ?>
                      </div>
                  <?php endif; ?>

                  <?php if ($is_serialized_product && $transaction->transaction_type === 'Sale'): ?>
                    <div class="serial-numbers-section mt-3 border p-3 rounded" data-type="sale" data-item-id="<?= htmlspecialchars($item->id); ?>">
                    <h6 class="text-white">Serial Numbers (Sale - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
                    <p class="text-muted">Select the specific serial numbers being sold from available stock.</p>

                    <?php
                      $available_instances_for_product = $available_serial_numbers_by_product[$item->product->id] ?? [];
                                        // Sort available instances by serial number for readability
                      usort($available_instances_for_product, function($a, $b) {
                      return strcmp($a['serial_number'], $b['serial_number']);
                                        });

                                        // Prepare pre-filled selected serial numbers for sales
                      $current_sale_serials = [];
                                        // Priority 1: From temporary session data (e.g., successful update of PENDING transaction)
                      if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
                        $current_sale_serials = $temp_submitted_serials[$item->id];
                      }
                                        // Priority 2: From submitted error data (sticky form repopulation)
                      elseif (isset($error_data['selected_serial_numbers'][$item->id]) && is_array($error_data['selected_serial_numbers'][$item->id])) {
                        $current_sale_serials = $error_data['selected_serial_numbers'][$item->id];
                      }
                                        // Priority 3: From existing ProductInstances if the transaction is already completed
                        elseif ($item->relationLoaded('soldInstances') && !empty($item->soldInstances)) {
                          foreach ($item->soldInstances as $instance) {
                            $current_sale_serials[] = $instance->serial_number;
                          }
                        }
                        ?>

  <?php for ($i = 0; $i < $item->quantity; $i++): ?>
    <div class="mb-3">
    <label for="serial_number_<?= $i ?>" class="form-label light-txt">Serial Number</label>
    <select data-live-search="true"
            class="form-select form-select-lg dark-txt light-bg selectpicker serial-number-select w-100"
            id="serial_number_<?= $i ?>"
            name="sale[<?= $item->id ?>][]"
            data-maxlength="20"
            required>
        <option value="">-- Select a Serial Number --</option>
        <?php
        $selected_value = $current_sale_serials[$i] ?? null;

        $serial_options = $available_serial_numbers_by_product[$item->product->id] ?? [];
        // Include previously selected serial if it's no longer available
        if ($selected_value && !in_array($selected_value, array_column($serial_options, 'serial_number'))) {
            $serial_options[] = ['serial_number' => $selected_value, 'status' => 'Sold'];
        }

        // Sort by serial number
        usort($serial_options, fn($a, $b) => strcmp($a['serial_number'], $b['serial_number']));

        foreach ($serial_options as $instance):
            $option_text = htmlspecialchars($instance['serial_number']);
            if (isset($instance['status']) && $instance['status'] !== 'In Stock') {
                $option_text .= " ({$instance['status']})";
            }
        ?>
            <option value="<?= htmlspecialchars($instance['serial_number']) ?>"
                <?= ($instance['serial_number'] === $selected_value) ? 'selected' : '' ?>>
                <?= $option_text ?>
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
                    $potential_return_serials = $potential_customer_return_serials_by_product[$item->product->id] ?? [];
                    usort($potential_return_serials, function($a, $b) {
                      return strcmp($a['serial_number'], $b['serial_number']);
                    });
                    $current_return_serials = [];
                    if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
                      $current_return_serials = $temp_submitted_serials[$item->id];
                    }
                                                // Priority 2: From submitted error data (sticky form repopulation)
                    elseif (isset($error_data['returned_serial_numbers'][$item->id]) && is_array($error_data['returned_serial_numbers'][$item->id])) {
                      $current_return_serials = $error_data['returned_serial_numbers'][$item->id];
                                                }
                                                // Priority 3: From existing ProductInstances if the transaction is already completed
                    elseif ($item->relationLoaded('returnedFromCustomerInstances') && !empty($item->returnedFromCustomerInstances)) {
                    foreach ($item->returnedFromCustomerInstances as $instance) {
                      $current_return_serials[] = $instance->serial_number;
                      }
                    }
                  ?>

                  <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                    <div class="mb-3">
                      <label for="return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
                      <select data-live-search="true"
        data-width="100%"
        data-maxlength="20"
        class="form-select form-control-sm dark-txt light-bg selectpicker serial-number-select"
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
        if (is_array($instance)) {
          if (isset($instance['status']) && $instance['status'] !== 'Sold') {
            $option_text .= " ({$instance['status']})";
          }
        } else {
          if (isset($instance->status) && $instance->status !== 'Sold') {
            $option_text .= " ({$instance->status})";
          }
        }
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
                                                // Priority 1: From temporary session data (e.g., successful update of PENDING transaction)
                                                if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
                                                    $current_supplier_return_serials = $temp_submitted_serials[$item->id];
                                                }
                                                // Priority 2: From submitted error data (sticky form repopulation)
                                                elseif (isset($error_data['supplier_returned_serial_numbers'][$item->id]) && is_array($error_data['supplier_returned_serial_numbers'][$item->id])) {
                                                    $current_supplier_return_serials = $error_data['supplier_returned_serial_numbers'][$item->id];
                                                }
                                                // Priority 3: From existing ProductInstances if the transaction is already completed
                                                elseif ($item->relationLoaded('returnedToSupplierInstances') && !empty($item->returnedToSupplierInstances)) {
                                                    foreach ($item->returnedToSupplierInstances as $instance) {
                                                        $current_supplier_return_serials[] = $instance->serial_number;
                                                    }
                                                }
                                                ?>

                                                <?php for ($i = 0; $i < $item->quantity; $i++): ?>
                                                    <div class="mb-3">
                                                        <label for="supplier_return_serial_<?= htmlspecialchars($item->id); ?>_<?= $i; ?>" class="form-label light-txt">Select Serial #<?= ($i + 1); ?>:</label>
  <select data-live-search="true"
        data-width="100%"
        data-maxlength="20"
        class="form-select form-control-sm dark-txt light-bg selectpicker serial-number-select"
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

        if (is_array($instance)) {
            if (isset($instance['status']) && $instance['status'] !== 'In Stock') {
                $option_text .= " ({$instance['status']})";
            }
        } else {
            if (isset($instance->status) && $instance->status !== 'In Stock') {
                $option_text .= " ({$instance->status})";
            }
        }

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
                                    
      <?php if ($is_serialized_product && $transaction->transaction_type === 'Stock Adjustment'): ?>
                                                                     
<div class="serial-numbers-section mt-3 border p-3 rounded" data-type="adjustment" data-item-id="<?= htmlspecialchars($item->id); ?>">
          <h6 class="text-white">Serial Numbers (Adjustment - Qty: <?= htmlspecialchars($item->quantity) ?>)</h6>
          <p class="text-muted">Specify the direction of the adjustment (inflow/outflow) and manage serial numbers.</p>

          <?php
            $current_adjustment_serials = [];
            $current_adjustment_direction = ''; // To be determined for display

                                        // Priority 1: From temporary session data (e.g., successful update of PENDING transaction)
            if (isset($temp_submitted_serials[$item->id]) && is_array($temp_submitted_serials[$item->id])) {
              $current_adjustment_serials = $temp_submitted_serials[$item->id];
              $current_adjustment_direction = $temp_submitted_adjustment_directions[$item->id] ?? '';
            }
                                        // Priority 2: From submitted error data (sticky form repopulation on *failed* attempt)
            elseif (isset($error_data['adjustment_serial_numbers'][$item->id]) && is_array($error_data['adjustment_serial_numbers'][$item->id])) {
              $current_adjustment_serials = $error_data['adjustment_serial_numbers'][$item->id];
              $current_adjustment_direction = $error_data['adjustment_direction'][$item->id] ?? '';
            }
                                       // Priority 3: From existing ProductInstances if the transaction is already completed/exists
            elseif ($item->relationLoaded('adjustedInInstances') && count($item->adjustedInInstances) > 0) {
              $current_adjustment_serials = $item->adjustedInInstances->pluck('serial_number')->toArray();
              $current_adjustment_direction = 'inflow';
            } elseif ($item->relationLoaded('adjustedOutInstances') && count($item->adjustedOutInstances) > 0) {
              $current_adjustment_serials = $item->adjustedOutInstances->pluck('serial_number')->toArray();
              $current_adjustment_direction = 'outflow';
            }

                                        // Ensure we have enough input fields for the quantity, pre-filling existing serials
            $displayed_adjustment_serials = [];
            for ($i = 0; $i < $item->quantity; $i++) {
              $displayed_adjustment_serials[] = $current_adjustment_serials[$i] ?? ''; // Use existing serial or empty string
            }
            ?>

            <div class="form-group mb-3">
              <label for="adjustment_direction_<?= htmlspecialchars($item->id); ?>" class="form-label light-txt">Adjustment Direction:</label>
              <select class="form-select form-control-sm dark-txt light-bg adjustment-direction-select"
                      id="adjustment_direction_<?= htmlspecialchars($item->id); ?>"
                      name="adjustment_direction_<?= htmlspecialchars($item->id); ?>"
                      data-item-id="<?= htmlspecialchars($item->id); ?>"
                      required>
                  <option value="">-- Select Direction --</option>
                  <option value="inflow" <?= ($current_adjustment_direction === 'inflow') ? 'selected' : ''; ?>>
                      Inflow (Adding to Stock)
                  </option>
                  <option value="outflow" <?= ($current_adjustment_direction === 'outflow') ? 'selected' : ''; ?>>
                      Outflow (Removing from Stock)
                  </option>
              </select>

            </div>
            <div class="serial-numbers-inputs-container" data-item-id="<?= htmlspecialchars($item->id); ?>">
  <?php foreach ($displayed_adjustment_serials as $serial_idx => $serial_value) : ?>


  <div class="mb-3">
  <label for="adjustment_serial_<?= htmlspecialchars($item->id); ?>_<?= $serial_idx; ?>"
         class="form-label light-txt">
    Serial #<?= ($serial_idx + 1); ?>:
  </label>

<input type="text"
       class="form-control form-control-sm dark-txt light-bg serial-number-input inflow-field"
       id="adjustment_serial_input_<?= htmlspecialchars($item->id); ?>_<?= $serial_idx; ?>"
       name="adjustment_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
       value="<?= htmlspecialchars($serial_value); ?>"
       data-product-id="<?= htmlspecialchars($item->product->id); ?>"
       data-item-id="<?= htmlspecialchars($item->id); ?>"
       data-maxlength="20"
       <?= ($current_adjustment_direction === 'outflow') ? 'style="display:none;" disabled' : 'required'; ?>
       <?= ($current_adjustment_direction !== 'inflow') ? 'style="display:none;" disabled' : 'required'; ?>>
       
<?php
$product_serials = $available_serial_numbers_by_product[$item->product->id] ?? [];

// Only keep in-stock serials
$product_serials = array_filter($product_serials, fn($s) => $s['status'] === 'In Stock');

// Ensure unique serial numbers
$unique_serial_numbers = [];
foreach ($product_serials as $instance) {
    $unique_serial_numbers[$instance['serial_number']] = $instance;
}

// Sort alphabetically by serial
usort($unique_serial_numbers, fn($a, $b) => strcmp($a['serial_number'], $b['serial_number']));

$product_serials = array_values($unique_serial_numbers);
$product_serials = array_unique($product_serials, SORT_REGULAR);

?>
<select data-live-search="true"
  class="form-select form-select-sm dark-txt light-bg selectpicker serial-number-select outflow-field w-100"
  id="adjustment_serial_select_<?= htmlspecialchars($item->id); ?>_<?= $serial_idx; ?>"
  name="adjustment_serial_numbers[<?= htmlspecialchars($item->id); ?>][]"
  data-product-id="<?= htmlspecialchars($item->product->id); ?>"
  data-item-id="<?= htmlspecialchars($item->id); ?>"
  title="-- Select Serial --"
  
  <?= ($current_adjustment_direction !== 'outflow') ? 'style="display:none;" disabled' : 'required'; ?>>
  
  <?php foreach ($product_serials as $instance): 
    ?>
    
    <option value="<?= htmlspecialchars($instance['serial_number']); ?>">
      <?= htmlspecialchars($instance['serial_number']); ?>
    </option>
  <?php endforeach; ?>
</select>


</div>

<?php endforeach; ?>
</div>

      </div>
      <?php endif; ?>

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
    // This script should be placed in the HTML file where your numeric input field is located.
// For example, at the bottom of the <body> tag or in a separate JS file.

document.addEventListener('DOMContentLoaded', function() {
    // Select all input fields that have type="number" and a data-maxlength attribute
    const numberInputs = document.querySelectorAll('input[type="number"][data-maxlength]');

    numberInputs.forEach(input => {
        const maxLength = parseInt(input.getAttribute('data-maxlength'), 10);

        // Add an input event listener to restrict the length
        input.oninput = function() {
            if (this.value.length > maxLength) {
                this.value = this.value.slice(0, maxLength);
            }
        };

        // Add a paste event listener to restrict pasted content length
        input.onpaste = function(event) {
            const pastedData = event.clipboardData.getData('text/plain');
            if (pastedData.length > maxLength) {
                event.preventDefault(); // Prevent default paste behavior
                this.value = pastedData.slice(0, maxLength); // Manually set truncated value
            }
        };

        // Optional: Add a keypress/keydown listener for more immediate feedback
        // This can prevent the user from even typing beyond the limit
        input.onkeydown = function(event) {
            // Allow backspace, delete, tab, escape, enter, and arrow keys
            if ([8, 46, 9, 27, 13, 37, 38, 39, 40].indexOf(event.keyCode) !== -1 ||
                // Allow Ctrl/Cmd+A, Ctrl/Cmd+C, Ctrl/Cmd+V, Ctrl/Cmd+X
                ((event.ctrlKey || event.metaKey) && ['a', 'c', 'v', 'x'].includes(event.key.toLowerCase()))) {
                return; // Let it happen
            }
            // Restrict input if current value length is already at max and it's not a control key
            if (this.value.length >= maxLength && event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
                event.preventDefault();
            }
        };
    });
});

</script>
<script>
    const formControls = document.querySelectorAll(
    '#transactionForm input:not(#invoice_bill_number):not(.serial-number-input):not([name="id"]), ' + // <-- ADDED :not([name="id"])
    '#transactionForm select:not(#status):not(.serial-number-input), ' +
    '#transactionForm textarea'
);
</script>
<script>
$(document).ready(function () {
    // Initialize all selectpickers once
    $('.selectpicker').selectpicker();

    // Adjust inflow/outflow
    $(document).on('change', '.adjustment-direction-select', function() {
        const itemId = $(this).data('item-id');
        const direction = $(this).val();
        const container = $(`.serial-numbers-inputs-container[data-item-id="${itemId}"]`);

        if (direction === 'inflow') {
            container.find('.inflow-field').show().prop('disabled', false).prop('required', true);
            container.find('.outflow-field').hide().prop('disabled', true).prop('required', false);
        } else if (direction === 'outflow') {
            container.find('.outflow-field').show().prop('disabled', false).prop('required', true)
                     .selectpicker('refresh'); // refresh only
            container.find('.inflow-field').hide().prop('disabled', true).prop('required', false);
        } else {
            container.find('.inflow-field, .outflow-field').hide().prop('disabled', true).prop('required', false);
        }
    });
    $('.inflow-field').show().prop('disabled', false).prop('required', true);
$('.outflow-field').hide().prop('disabled', true).prop('required', false);

});


</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Watch all selectpickers with serial numbers
    document.querySelectorAll('.serial-number-select').forEach(select => {
        const maxLength = parseInt(select.dataset.maxlength, 10) || 20;

        // Bootstrap-select creates a search input inside the dropdown
        $(select).on('shown.bs.select', function() {
            const searchInput = document.querySelector('.bs-searchbox input');

            if (searchInput) {
                searchInput.addEventListener('input', function(event) {
                    let value = event.target.value;

                    // Allow only letters, numbers, dashes
                    value = value.replace(/[^a-zA-Z0-9]/g, '');

                    // Enforce max length
                    if (value.length > maxLength) {
                        value = value.substring(0, maxLength);
                    }

                    event.target.value = value;
                });
            }
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.serial-number-input').forEach(input => {
        const maxLength = parseInt(input.dataset.maxlength, 10) || 20;

        // Prevent invalid characters as user types
        input.addEventListener('input', function(e) {
            let value = e.target.value;

            // Allow only letters, numbers, and dashes
            value = value.replace(/[^A-Z0-9]/g, '');

            // Enforce max length
            if (value.length > maxLength) {
                value = value.substring(0, maxLength);
            }

            e.target.value = value;
        });

        // Optional: Prevent pasting invalid content
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            let paste = (e.clipboardData || window.clipboardData).getData('text');
            paste = paste.replace(/[^A-Z0-9-]/g, '').substring(0, maxLength);
            e.target.value = paste;
        });
    });
});
</script>
