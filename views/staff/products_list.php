<?php ob_start(); ?>

  <h1 class="text-white mb-4">Product List</h1> <div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>SKU</th>
          <th>NAME</th>
          <th>DESCRIPTION</th>
          <th>CATEGORY</th>
          <th>BRAND</th>
          <th>UNIT PRICE</th>
          <th>COST PRICE</th>
          <th>CURRENT STOCK</th>
          <th>REORDER LEVEL</th>
          <th>SERIALIZED?</th>
          <th>ACTIVE?</th>
          <th>AISLE</th>
          <th>BIN</th>
          <th>CREATION DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($products)): // Assuming $products is the collection, not $user_info ?>
            <?php foreach ($products as $product): // Looping through each product model, not user ?>
                <tr>
                    <td><?= htmlspecialchars($product->id) ?></td>
                    <td><?= htmlspecialchars($product->sku ?? 'N/A') ?></td>
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
                    <td>
                        <?= htmlspecialchars(($product->is_active ? 'Yes' : 'No')) ?>
                    </td>
                    <td><?= htmlspecialchars($product->location_aisle ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->location_bin ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->created_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/products/edit/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/products/delete/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="16" class="text-center">No products found.</td> </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
$content = ob_get_clean();
require_once 'staff_layout.php';

require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on products_list.php");
?>