<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;
use Models\Transaction; // To get transaction details if needed
use Models\Product;    // To populate the product dropdown

// Ensure variables are set for the view
$transaction_id = $transaction_id ?? null;
$transaction_item = $transaction_item ?? (object)[
    'product_id' => null,
    'quantity' => 1, // Default quantity
    'unit_price' => 0.00, // Default price
];
$products = $products ?? []; // Products for the dropdown

// If a transaction ID is provided, fetch its details to display
$transaction = null;
if ($transaction_id) {
    $transaction = Transaction::find($transaction_id);
}

?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8">
        <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Add Item to Transaction #
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

          <form action="/staff/transaction_items/store" method="POST" id="transactionItemForm">
            <input type="hidden" name="transaction_id" value="<?= htmlspecialchars((string)$transaction_id) ?>">

            <div class="mb-3">
              <label for="product_id" class="form-label light-txt">Product</label>
              <select data-live-search="true" class="form-select form-select-lg dark-txt light-bg selectpicker" id="product_id" name="product_id" required maxlength="50">
                  <option value="">Select Product</option>
                  <?php foreach ($products as $product): ?>
                      <option value="<?= htmlspecialchars($product->id) ?>"
                          <?= ($transaction_item->product_id == $product->id) ? 'selected' : '' ?>
                          data-unit-price="<?= htmlspecialchars((string)$product->unit_price) ?>">
                          <?= htmlspecialchars($product->name) ?> (<?= htmlspecialchars($product->sku) ?>)
                      </option>
                  <?php endforeach; ?>
              </select>
          </div>

            <div class="mb-3">
              <label for="quantity" class="form-label light-txt">Quantity</label>
              <input type="number" class="form-control form-control-lg dark-txt light-bg" id="quantity" name="quantity"
                     value="<?= htmlspecialchars((string)$transaction_item->quantity) ?>" min="1" required
                     maxlength="4"
                     pattern="[0-9]*\.?[0-9]*"
                     oninput="this.value=this.value.slice(0,this.maxLength)"
                     >
            </div>

            <div class="mb-3">
              <label for="unit_price" class="form-label light-txt">Unit Price</label>
              <input type="number" step="0.01" class="form-control form-control-lg dark-txt light-bg" id="unit_price" name="unit_price"
                     value="<?= htmlspecialchars(number_format($transaction_item->unit_price, 2, '.', '')) ?>" min="0" required readonly>
            </div>

            <div class="mb-3">
              <label for="item_total" class="form-label light-txt">Item Total</label>
              <input type="text" class="form-control form-control-lg dark-txt light-bg" id="item_total" name="item_total"
                     value="<?= htmlspecialchars(number_format($transaction_item->quantity * $transaction_item->unit_price, 2, '.', '')) ?>" readonly>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg lightgreen-bg">Add Item</button>
              <a href="/staff/transactions/show/<?= htmlspecialchars((string)$transaction_id) ?>" class="btn btn-secondary btn-lg">Back to Transaction</a>
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
    $('#product_id').selectpicker();

    // After initialization, find the dynamically created search input
    // and apply the maxlength attribute to it.
    // The search input usually has a class like 'bs-searchbox' and contains an 'input' tag.
    $('#product_id').on('loaded.bs.select', function (e) {
        // Find the search input within the dropdown's container
        const searchInput = $(this).closest('.bootstrap-select').find('.bs-searchbox input');
        if (searchInput.length) {
            // Set the maxlength attribute
            searchInput.attr('maxlength', '50'); // You can change '50' to your desired length
            console.log('Applied maxlength to product_id selectpicker search input.');
        }
    });
    // Event listener to update unit price when product is selected
    productSelect.addEventListener('change', function() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        // Ensure data-unit-price attribute exists before trying to get its value
        const unitPrice = selectedOption.hasAttribute('data-unit-price') ? selectedOption.getAttribute('data-unit-price') : null;
        if (unitPrice !== null) { // Check for null specifically
            unitPriceInput.value = parseFloat(unitPrice).toFixed(2);
        } else {
            unitPriceInput.value = '0.00'; // Default if no price is set or attribute missing
        }
        calculateItemTotal();
    });

    // Event listeners for quantity and unit price changes
    quantityInput.addEventListener('input', calculateItemTotal);
    unitPriceInput.addEventListener('input', calculateItemTotal);

    // Initial calculation in case values are pre-filled (e.g., from an error)
    calculateItemTotal();
});
</script>

<?php
Logger::log('UI: On staff/transaction_items/add.php');
?>