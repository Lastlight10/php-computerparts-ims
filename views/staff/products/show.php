<?php
use App\Core\Logger;

// Ensure $product is defined
$product = $product ?? null;

if (!$product) {
    // This case should ideally be handled by the controller redirecting to a 404
    // but as a fallback for the view, we can display a message
    echo '<div class="alert alert-danger text-center mt-5">Product data not available.</div>';
    exit; // Stop further execution of the view
}
?>

<section class="page-wrapper dark-bg">
    <div class="container-fluid page-content">
        <div class="card lighterdark-bg p-4 shadow-sm mb-4">
            <h3 class="text-white text-center mb-4">Product Details: <?= htmlspecialchars($product->name ?? 'N/A') ?></h3>
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


            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="light-txt mb-1"><strong>Code:</strong> <?= htmlspecialchars($product->sku ?? 'N/A') ?></p>
                    <p class="light-txt mb-1"><strong>Category:</strong> <?= htmlspecialchars($product->category->name ?? 'N/A') ?></p>
                    <p class="light-txt mb-1"><strong>Brand:</strong> <?= htmlspecialchars($product->brand->name ?? 'N/A') ?></p>
                    <p class="light-txt mb-1"><strong>Unit Price:</strong> ₱<?= htmlspecialchars(number_format($product->unit_price ?? 0, 2)) ?></p>
                    <p class="light-txt mb-1"><strong>Cost Price:</strong> ₱<?= htmlspecialchars(number_format($product->cost_price ?? 0, 2)) ?></p>
                </div>
                <div class="col-md-6">
                    <p class="light-txt mb-1"><strong>Current Stock:</strong> <?= htmlspecialchars($product->current_stock ?? 'N/A') ?></p>
                    <p class="light-txt mb-1"><strong>Reorder Level:</strong> <?= htmlspecialchars($product->reorder_level ?? 'N/A') ?></p>
                    <p class="light-txt mb-1"><strong>Serialized:</strong> <?= ($product->is_serialized ?? false) ? 'Yes' : 'No' ?></p>
                    <p class="light-txt mb-1"><strong>Active:</strong> <?= ($product->is_active ?? false) ? 'Yes' : 'No' ?></p>
                    <p class="light-txt mb-1"><strong>Location:</strong> <?= htmlspecialchars($product->location_aisle ?? 'N/A') ?> / <?= htmlspecialchars($product->location_bin ?? 'N/A') ?></p>
                </div>
            </div>

            <div class="mb-4">
                <p class="light-txt mb-1"><strong>Description:</strong></p>
                <p class="text-white ps-3" style="max-width:1100px; white-space:normal; overflow-wrap:break-word;"><?= htmlspecialchars($product->description ?? 'No description provided.') ?></p>
            </div>

            <div class="text-center mt-3">
                <a href="/staff/products/edit/<?= htmlspecialchars($product->id) ?>" class="btn btn-primary lightgreen-bg me-2">Edit Product</a>
                <!-- New Print Details Button -->
                <a href="/staff/products/print/<?= htmlspecialchars($product->id) ?>" class="btn btn-info me-2" target="_blank">Print Details</a>
                <a href="/staff/products_list" class="btn btn-secondary">Back to List</a>
            </div>
        </div>

        <?php if ($product->is_serialized): // Only show instances table if product is serialized ?>
            <h2 class="text-white mb-3 mt-5">Individual Units (Serialized)</h2>

            <form method="GET" action="/staff/products/show/<?= htmlspecialchars($product->id) ?>" class="mb-4 p-3 rounded shadow-sm light-bg-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="instance_search_query" class="form-label light-txt">Search Serial Number</label>
                        <input type="text" class="form-control dark-txt light-bg" id="instance_search_query" name="instance_search_query" placeholder="Enter serial number" value="<?= htmlspecialchars($instance_search_query ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="instance_filter_status" class="form-label light-txt">Filter by Status</label>
                        <select class="form-select dark-txt light-bg" id="instance_filter_status" name="instance_filter_status">
                            <option value="">All Statuses</option>
                            <?php foreach ($product_instance_statuses ?? [] as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= (($instance_filter_status ?? '') === $status) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-info w-100">Apply Filters</button>
                    </div>
                    <div class="col-md-3">
                        <a href="/staff/products/show/<?= htmlspecialchars($product->id) ?>" class="btn btn-secondary w-100">Clear Filters & Sort</a>
                    </div>
                </div>
                <div class="row g-3 align-items-end mt-2">
                    <div class="col-md-4">
                        <label for="instance_sort_by" class="form-label light-txt">Sort By</label>
                        <select class="form-select dark-txt light-bg" id="instance_sort_by" name="instance_sort_by">
                            <option value="serial_number" <?= (($instance_sort_by ?? '') === 'serial_number') ? 'selected' : '' ?>>Serial Number</option>
                            <option value="status" <?= (($instance_sort_by ?? '') === 'status') ? 'selected' : '' ?>>Status</option>
                            <!-- <option value="cost_at_receipt" <?= (($instance_sort_by ?? '') === 'cost_at_receipt') ? 'selected' : '' ?>>Cost at Receipt</option> -->
                            <option value="warranty_expires_at" <?= (($instance_sort_by ?? '') === 'warranty_expires_at') ? 'selected' : '' ?>>Warranty Expiration</option>
                            <option value="created_at" <?= (($instance_sort_by ?? '') === 'created_at') ? 'selected' : '' ?>>Purchase Date</option>
                            <!-- Note: Sold Date is derived from sale_transaction_item, direct sorting might be complex without a dedicated column -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="instance_sort_order" class="form-label light-txt">Sort Order</label>
                        <select class="form-select dark-txt light-bg" id="instance_sort_order" name="instance_sort_order">
                            <option value="asc" <?= (($instance_sort_order ?? '') === 'asc') ? 'selected' : '' ?>>Ascending</option>
                            <option value="desc" <?= (($instance_sort_order ?? '') === 'desc') ? 'selected' : '' ?>>Descending</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-info w-100">Apply Sort</button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>Cost at Receipt</th>
                            <th>Warranty Expiration</th>
                            <th>Purchase Date</th>
                            <th>Sold Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($product->productInstances) && $product->productInstances->isNotEmpty()): ?>
                            <?php foreach ($product->productInstances as $instance): ?>
                                <tr>
                                    <td><?= htmlspecialchars($instance->id) ?></td>
                                    <td><?= htmlspecialchars($instance->serial_number ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($instance->status ?? 'N/A') ?></td>
                                    <td>₱<?= htmlspecialchars(number_format((float)$instance->cost_at_receipt ?? 0, 2)) ?></td>
                                    <td><?= htmlspecialchars($instance->warranty_expires_at ? date('Y-m-d', strtotime($instance->warranty_expires_at)) : 'N/A') ?></td>
                                    <td>
                                        <?php
                                        // Display purchase transaction date if available
                                        if (isset($instance->purchaseTransactionItem->transaction->transaction_date)) {
                                            echo htmlspecialchars(date('Y-m-d', strtotime($instance->purchaseTransactionItem->transaction->transaction_date)));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Display sale transaction date if available
                                        if (isset($instance->saleTransactionItem->transaction->transaction_date)) {
                                            echo htmlspecialchars(date('Y-m-d', strtotime($instance->saleTransactionItem->transaction->transaction_date)));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="/staff/product_instances/edit/<?= htmlspecialchars($instance->id) ?>" class="btn btn-sm btn-outline-info me-1">Manage Unit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No individual units found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center mt-5">
                This product is not marked as serialized, so individual units are not tracked.
            </div>
        <?php endif; ?>

    </div>
</section>

<?php

// Log memory usage after rendering content, before sending to browser via layout
$memory = memory_get_usage();
Logger::log("Used: $memory on show.php");
?>
