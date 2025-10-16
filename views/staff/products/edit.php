<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Ensure $product, $categories, and $brands are defined for form use
// These should always be passed by the controller for edit view
$product = $product ?? new Models\Product();
$categories = $categories ?? [];
$brands = $brands ?? [];
$selected_supplier_ids = $product->supplier_ids ?? [];
?>

<section class="page-wrapper dark-bg">
  <div class="container-fluid page-content">
    <div class="row justify-content-center">
      <div class="col-12 col-md-10 col-lg-8"> <div class="card lighterdark-bg p-4 shadow-sm">
          <h3 class="text-white text-center mb-4">Edit Product: <?= htmlspecialchars($product->name ?? 'N/A') ?></h3>

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

          <form action="/staff/products/update" method="POST">
            <input type="hidden" name="id" value="<?= htmlspecialchars($product->id ?? '') ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="sku" class="form-label light-txt">Product Code</label>
                    <input type="text" class="form-control form-control-lg dark-txt light-bg" id="sku" name="sku"
                           value="<?= htmlspecialchars($product->sku ?? ''); ?>" required maxlength="50" readonly>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label light-txt">Product Name</label>
                    <input type="text" class="form-control form-control-lg dark-txt light-bg" id="name" name="name"
                           value="<?= htmlspecialchars($product->name ?? ''); ?>" required maxlength="50">
                </div>
            </div>

            <div class="mb-3">
              <label for="description" class="form-label light-txt">Description (Optional)</label>
              <textarea class="form-control form-control-lg dark-txt light-bg" id="description" name="description"
                        rows="3" maxlength="100"><?= htmlspecialchars($product->description ?? ''); ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="category_id" class="form-label light-txt">Category</label>
                    <select data-live-search="true" class="form-select form-select-lg dark-txt light-bg" id="category_id" name="category_id" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category->id) ?>"
                                <?= (isset($product->category_id) && $product->category_id == $category->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="brand_id" class="form-label light-txt">Brand</label>
                    <select data-live-search="true" class="form-select form-select-lg dark-txt light-bg" id="brand_id" name="brand_id" required>
                        <option value="">Select Brand</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?= htmlspecialchars($brand->id) ?>"
                                <?= (isset($product->brand_id) && $product->brand_id == $brand->id) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($brand->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-12 mb-3">
                    <label for="supplier_ids" class="form-label light-txt">Suppliers</label>
                    <select data-live-search="true" class="form-select form-select-lg dark-txt light-bg selectpicker"
                        id="supplier_ids" name="supplier_ids[]" multiple data-selected-text-format="count > 2" data-width="400px" required>
                        <?php
                        // 1. Get the array of IDs currently associated with the product (either from DB or submitted data)
                        // The controller assigns this array to $product->supplier_ids
                        $selected_supplier_ids = $product->supplier_ids ?? [];
                        if (!is_array($selected_supplier_ids)) {
                            // Ensure it's an array if the relationship wasn't loaded correctly (for safety)
                            $selected_supplier_ids = []; 
                        }
                        ?>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php
                            // Determine the display name: Company Name if available, otherwise contact person
                            $display_name = $supplier->company_name ?: trim("{$supplier->contact_first_name} {$supplier->contact_last_name}");
                            if (empty($display_name)) {
                                $display_name = "Supplier #{$supplier->id}"; // Fallback if no name fields are set
                            }
                            ?>
                            <option value="<?= htmlspecialchars($supplier->id) ?>"
                                <?= in_array($supplier->id, $selected_supplier_ids) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($display_name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="unit_price" class="form-label light-txt">Unit Price (₱)</label>
                    <input type="text" step="0.01" class="form-control form-control-lg dark-txt light-bg" id="unit_price" name="unit_price"
                        value="<?= htmlspecialchars($product->unit_price ?? ''); ?>" required min="0" data-maxlength="5">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="cost_price" class="form-label light-txt">Cost Price (₱)</label>
                    <input type="text" step="0.01" class="form-control form-control-lg dark-txt light-bg" id="cost_price" name="cost_price"
                        value="<?= htmlspecialchars($product->cost_price ?? ''); ?>" min="0" data-maxlength="5">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="current_stock" class="form-label light-txt">Current Stock</label>
                    <input type="number" class="form-control form-control-lg dark-txt light-bg" id="current_stock" name="current_stock"
                           value="<?= htmlspecialchars($product->current_stock ?? '0'); ?>" required min="0" readonly>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="reorder_level" class="form-label light-txt">Reorder Level</label>
                    <input type="number" class="form-control form-control-lg dark-txt light-bg" id="reorder_level" name="reorder_level"
                           value="<?= htmlspecialchars($product->reorder_level ?? '0'); ?>" required min="0">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="is_serialized" class="form-label light-txt">Serialized?</label>
                    <div class="form-check form-switch form-control-lg">
                        <input class="form-check-input" type="checkbox" id="is_serialized" name="is_serialized"
                                <?= (isset($product->is_serialized) && $product->is_serialized) ? 'checked' : '' ?>>
                        <label class="form-check-label light-txt" for="is_serialized">Yes</label>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="is_active" class="form-label light-txt">Active?</label>
                    <div class="form-check form-switch form-control-lg">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                                <?= (!isset($product->is_active) || $product->is_active) ? 'checked' : '' ?>>
                        <label class="form-check-label light-txt" for="is_active">Yes</label>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="location_aisle" class="form-label light-txt">Building (Optional)</label>
                    <input type="text" class="form-control form-control-lg dark-txt light-bg" id="location_aisle" name="location_aisle"
                           value="<?= htmlspecialchars($product->location_aisle ?? ''); ?>" maxlength="12">
                </div>
                <div class="col-md-4 mb-3">
                    <label for="location_bin" class="form-label light-txt">Shelf and Row(Optional)</label>
                    <input type="text" class="form-control form-control-lg dark-txt light-bg" id="location_bin" name="location_bin"
                           value="<?= htmlspecialchars($product->location_bin ?? ''); ?>" maxlength="15">
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
              <button type="submit" class="btn btn-primary btn-lg lightgreen-bg"
                onclick="return confirm('Are you sure you want to update the supplier details?');"
              >Update Product</button>
              <a href="/staff/products_list" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("JavaScript for input validation is running!");

        function enforceNumericInputRules(event) {
            const input = event.target;
            let value = input.value;
            const maxLength = parseInt(input.getAttribute('data-maxlength'), 10); // This line dynamically reads the data-maxlength

            // 1. Strip non-numeric characters
            if (input.id === 'reorder_level') {
                value = value.replace(/[^0-9]/g, ''); // Only digits for reorder_level
            } else {
                // For prices, allow one decimal point
                value = value.replace(/[^0-9.]/g, '');
                const parts = value.split('.');
                if (parts.length > 2) {
                    value = parts.shift() + '.' + parts.join('');
                }
                if (value.indexOf('.') !== value.lastIndexOf('.')) {
                    value = value.substring(0, value.lastIndexOf('.'));
                }
            }

            // 2. Enforce maxlength
            if (!isNaN(maxLength) && value.length > maxLength) {
                value = value.slice(0, maxLength);
            }

            // Update the input value
            input.value = value;
        }

        const unitPriceInput = document.getElementById('unit_price');
        const costPriceInput = document.getElementById('cost_price');
        const reorderLevelInput = document.getElementById('reorder_level');

        if (unitPriceInput) {
            unitPriceInput.addEventListener('input', enforceNumericInputRules);
        }
        if (costPriceInput) {
            costPriceInput.addEventListener('input', enforceNumericInputRules);
        }
        if (reorderLevelInput) {
            reorderLevelInput.addEventListener('input', enforceNumericInputRules);
        }
    });
    $('#category_id').on('shown.bs.select', function () {
    // Target the live search input inside the dropdown
    $('.bs-searchbox input').attr('maxlength', 30);
    });
    $('#brand_id').on('shown.bs.select', function () {
        // Target the live search input inside the dropdown
        $('.bs-searchbox input').attr('maxlength', 30);
    });
    $('#supplier_ids').on('shown.bs.select', function () {
        // Target the live search input inside the dropdown
        $('.bs-searchbox input').attr('maxlength', 30);
    });
</script>
<?php
Logger::log('UI: On staff/products/edit.php');
?>