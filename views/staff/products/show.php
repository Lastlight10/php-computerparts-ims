<?php ob_start(); ?>

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
                        <td><?= htmlspecialchars($instance->cost_at_receipt ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($instance->warranty_expires_at ?? 'N/A') ?></td>
                        <td>
                            <?php 
                            // Display purchase transaction date if available
                            echo htmlspecialchars($instance->purchaseItem->transaction->transaction_date ?? 'N/A');
                            ?>
                        </td>
                        <td>
                            <?php 
                            // Display sale transaction date if available
                            echo htmlspecialchars($instance->saleItem->transaction->transaction_date ?? 'N/A');
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

<?php
$content = ob_get_clean();
require_once 'staff_layout.php';

require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on show.php");
?>