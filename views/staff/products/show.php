<?php

use App\Core\Logger;

// Ensure $product is defined
$product = $product ?? null;

if (!$product) {
    // This case should ideally be handled by the controller redirecting to a 404
    // but as a fallback for the view, we can display a message
    echo '<div class="alert alert-danger text-center mt-5">Product data not available.</div>';
    $content = ob_get_clean();
    require_once 'staff_layout.php';
    exit;
}
?>

<section class="page-wrapper dark-bg">
    <div class="container-fluid page-content">
        <div class="card lighterdark-bg p-4 shadow-sm mb-4">
            <h3 class="text-white text-center mb-4">Product Details: <?= htmlspecialchars($product->name ?? 'N/A') ?></h3>

            <div class="row mb-3">
                <div class="col-md-6">
                    <p class="light-txt mb-1"><strong>SKU:</strong> <?= htmlspecialchars($product->sku ?? 'N/A') ?></p>
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
                <p class="text-white ps-3"><?= htmlspecialchars($product->description ?? 'No description provided.') ?></p>
            </div>

            <div class="text-center mt-3">
                <a href="/staff/products/edit/<?= htmlspecialchars($product->id) ?>" class="btn btn-primary lightgreen-bg me-2">Edit Product</a>
                <a href="/staff/products_list" class="btn btn-secondary">Back to List</a>
            </div>
        </div>

        <?php if ($product->is_serialized): // Only show instances table if product is serialized ?>
            <h2 class="text-white mb-3 mt-5">Individual Units (Serialized)</h2>
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
                        <?php if (!empty($product->instances)): ?>
                            <?php foreach ($product->instances as $instance): ?>
                                <tr>
                                    <td><?= htmlspecialchars($instance->id) ?></td>
                                    <td><?= htmlspecialchars($instance->serial_number ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($instance->status ?? 'N/A') ?></td>
                                    <td>₱<?= htmlspecialchars(number_format((float)$instance->cost_at_receipt ?? 0, 2)) ?></td>
                                    <td><?= htmlspecialchars($instance->warranty_expires_at ?? 'N/A') ?></td>
                                    <td>
                                        <?php
                                        // Display purchase transaction date if available
                                        // Check if purchaseTransactionItem exists, AND if its transaction exists
                                        if (isset($instance->purchaseTransactionItem->transaction->transaction_date)) {
                                            // Format the Carbon date object
                                            echo htmlspecialchars($instance->purchaseTransactionItem->transaction->transaction_date->format('Y-m-d'));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Display sale transaction date if available
                                        // Check if saleTransactionItem exists, AND if its transaction exists
                                        if (isset($instance->saleTransactionItem->transaction->transaction_date)) {
                                            // Format the Carbon date object
                                            echo htmlspecialchars($instance->saleTransactionItem->transaction->transaction_date->format('Y-m-d'));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="/staff/product-instances/edit/<?= htmlspecialchars($instance->id) ?>" class="btn btn-sm btn-outline-info me-1">Manage Unit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No individual units found for this product.</td>
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