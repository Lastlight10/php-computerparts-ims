<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;
use Models\Transaction; // To get transaction details if needed
use Models\TransactionItem; // To fetch the item being edited
use Models\Product;    // To populate the product dropdown


// Ensure variables are set for the view
// $transaction_item should be passed from the controller when editing
$transaction_item = $transaction_item ?? null;
$transaction = $transaction ?? null; // The parent transaction
$products = $products ?? []; // All products for the dropdown

// If transaction_item is not set, this page likely shouldn't be loaded directly
if (!$transaction_item) {
    Logger::log("UI_ERROR: transaction_items/edit.php loaded without \$transaction_item.");

    $_SESSION['error_message']="Invalid Transaction Item for editing.";
    // Redirect or show an error
    header('Location: /staff/transactions_list');
    exit();
}

// Ensure transaction is loaded if not already passed (e.g., in case of validation error repopulation)
if (!$transaction && $transaction_item->transaction_id) {
    $transaction = Transaction::find($transaction_item->transaction_id);
}
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Item for Transaction #<?= htmlspecialchars($transaction->invoice_bill_number ?? $transaction_item->transaction_id) ?></h3>

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

          <form action="/staff/transaction_items/update" method="POST" id="transactionItemForm">
            <input type="hidden" name="id" value="<?= htmlspecialchars($transaction_item->id) ?>">
            <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($transaction_item->transaction_id) ?>">

            <div class="mb-3">
              <label for="product_id" class="form-label light-txt">Product</label>
              <select class="form-select form-select-lg dark-txt light-bg" id="product_id" name="product_id" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= htmlspecialchars($product->id) ?>"
                    <?= ($transaction_item->product_id == $product->id) ? 'selected' : '' ?>
                    data-unit-price="<?= htmlspecialchars($product->selling_price) ?>">
                    <?= htmlspecialchars($product->name) ?> (<?= htmlspecialchars($product->sku) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label for="quantity" class="form-label light-txt">Quantity</label>
              <input type="number" class="form-control form-control-lg dark-txt light-bg" id="quantity" name="quantity"
                     value="<?= htmlspecialchars($transaction_item->quantity) ?>" min="1" required>
            </div>

            <div class="mb-3">
              <label for="unit_price" class="form-label light-txt">Unit Price (₱)</label>
              <input type="number" step="0.01" class="form-control form-control-lg dark-txt light-bg" id="unit_price" name="unit_price"
                     value="<?= htmlspecialchars(number_format($transaction_item->line_total, 2, '.', '')) ?>" min="0" required>
            </div>

            <div class="mb-3">
              <label for="item_total" class="form-label light-txt">Item Total (₱)</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="item_total" name="item_total"
                     value="<?= htmlspecialchars(number_format($transaction_item->quantity * $transaction_item->unit_price, 2, '.', '')) ?>" readonly>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg lightgreen-bg">Update Item</button>
              <a href="/staff/transactions/show/<?= htmlspecialchars($transaction_item->transaction_id) ?>" class="btn btn-secondary btn-lg">Back to Transaction</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const unitPriceInput = document.getElementById('unit_price');
    const itemTotalInput = document.getElementById('item_total');

    function calculateItemTotal() {
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        const total = quantity * unitPrice;
        itemTotalInput.value = total.toFixed(2);
    }

    // Event listener to update unit price when product is selected
    productSelect.addEventListener('change', function() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const unitPrice = selectedOption.getAttribute('data-unit-price');
        if (unitPrice) {
            unitPriceInput.value = parseFloat(unitPrice).toFixed(2);
        } else {
            unitPriceInput.value = '0.00'; // Default if no price is set
        }
        calculateItemTotal();
    });

    // Event listeners for quantity and unit price changes
    quantityInput.addEventListener('input', calculateItemTotal);
    unitPriceInput.addEventListener('input', calculateItemTotal);

    // Initial calculation (important for edit view as values are pre-filled)
    calculateItemTotal();
});
</script>

<?php
Logger::log('UI: On staff/transaction_items/edit.php');
?>