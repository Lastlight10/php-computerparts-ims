<?php
// Place all 'use' statements here, at the very top of the PHP file
// (Already present from your combined snippet, good!)
use App\Core\Logger;

// Check for success message in URL query parameters (keeping your existing code)
if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
    echo '<div class="alert alert-success text-center mb-3" role="alert">' . $success_message . '</div>';
}

// Check for error message in URL query parameters (keeping your existing code)
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
    echo '<div class="alert alert-danger text-center mb-3" role="alert">' . $error_message . '</div>';
}
?>
<div class="d-flex justify-content-end mb-3">
  <a href="/staff/products/add" class="btn btn-primary">Add New Product</a>
</div>

  <h1 class="text-white mb-4">Product List</h1>
  <div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>SKU</th>
          <th>NAME</th>
          <th>DESCRIPTION</th>
          <th>CATEGORY</th>
          <th>BRAND</th>
          <th>UNIT PRICE (₱)</th>
          <th>COST PRICE (₱)</th>
          <th>CURRENT STOCK</th>
          <th>REORDER LEVEL</th>
          <th>SERIALIZED?</th>
          <th class="hidden-header">ACTIVE?</th>
          <th class="hidden-header">AISLE</th>
          <th class="hidden-header">BIN</th>
          <th>CREATION DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$products_info->isEmpty()): // Changed !empty($products) to !$products->isEmpty() ?>
            <?php foreach ($products_info as $product): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($product->id) ?></td>
                    <td class="wrap-long"><?= htmlspecialchars($product->sku ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->description ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->category->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->brand->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->unit_price ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->cost_price ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->current_stock ?? 'N/A') ?></td>
                    <td>
                        <?= htmlspecialchars(($product->reorder_level ?? 0)) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(($product->is_serialized ? 'Yes' : 'No')) ?>
                    </td>
                    <td class="hidden-column">
                        <?= htmlspecialchars(($product->is_active ? 'Yes' : 'No')) ?>
                    </td>
                    <td class="hidden-column"><?= htmlspecialchars($product->location_aisle ?? 'N/A') ?></td>
                    <td class="hidden-column"><?= htmlspecialchars($product->location_bin ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->created_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/products/edit/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/products/show/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-info me-1">Show</a>
                        <a href="/staff/products/delete/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Product list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="16" class="text-center">No products found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
Logger::log('UI: On products_list.php'); // Your existing logger call
?>