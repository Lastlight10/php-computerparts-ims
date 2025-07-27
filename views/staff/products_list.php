<?php
// Display success message if available
use App\Core\Logger; // Ensure Logger is used if needed here

if (isset($success_message) && !empty($success_message)) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($success_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

// Display error message if available
if (isset($error_message) && !empty($error_message)) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="text-white mb-0">Product List</h1>
  <div>
    <a href="/staff/products/add" class="btn btn-primary me-2">Add New Product</a>
    <!-- New Print List Button -->
    <a href="#" id="printProductsListBtn" class="btn btn-info" target="_blank">Print List</a>
  </div>
</div>

<form method="GET" action="/staff/products_list" class="mb-4 p-3 rounded shadow-sm light-bg-card" id="productsFilterForm">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="search_query" class="form-label light-txt">Search</label>
            <input type="text" class="form-control dark-txt light-bg" id="search_query" name="search_query" placeholder="SKU, name, description, category, brand" value="<?= htmlspecialchars($search_query ?? '') ?>" maxlength="50">
        </div>
        <div class="col-md-3">
            <label for="filter_category_id" class="form-label light-txt">Filter by Category</label>
            <select class="form-select dark-txt light-bg" id="filter_category_id" name="filter_category_id">
                <option value="">All Categories</option>
                <?php foreach ($categories ?? [] as $category): ?>
                    <option value="<?= htmlspecialchars($category->id) ?>" <?= ((string)($filter_category_id ?? '') === (string)$category->id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($category->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_brand_id" class="form-label light-txt">Filter by Brand</label>
            <select class="form-select dark-txt light-bg" id="filter_brand_id" name="filter_brand_id">
                <option value="">All Brands</option>
                <?php foreach ($brands ?? [] as $brand): ?>
                    <option value="<?= htmlspecialchars($brand->id) ?>" <?= ((string)($filter_brand_id ?? '') === (string)$brand->id) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($brand->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info w-100">Apply Filters</button>
        </div>
    </div>
    <div class="row g-3 align-items-end mt-2">
        <div class="col-md-3">
            <label for="filter_is_serialized" class="form-label light-txt">Serialized?</label>
            <select class="form-select dark-txt light-bg" id="filter_is_serialized" name="filter_is_serialized">
                <option value="">All</option>
                <option value="Yes" <?= (($filter_is_serialized ?? '') === 'Yes') ? 'selected' : '' ?>>Yes</option>
                <option value="No" <?= (($filter_is_serialized ?? '') === 'No') ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_is_active" class="form-label light-txt">Active?</label>
            <select class="form-select dark-txt light-bg" id="filter_is_active" name="filter_is_active">
                <option value="">All</option>
                <option value="Yes" <?= (($filter_is_active ?? '') === 'Yes') ? 'selected' : '' ?>>Yes</option>
                <option value="No" <?= (($filter_is_active ?? '') === 'No') ? 'selected' : '' ?>>No</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="sort_by" class="form-label light-txt">Sort By</label>
            <select class="form-select dark-txt light-bg" id="sort_by" name="sort_by">
                <option value="name" <?= (($sort_by ?? '') === 'name') ? 'selected' : '' ?>>Product Name</option>
                <option value="sku" <?= (($sort_by ?? '') === 'sku') ? 'selected' : '' ?>>SKU</option>
                <option value="unit_price" <?= (($sort_by ?? '') === 'unit_price') ? 'selected' : '' ?>>Unit Price</option>
                <option value="cost_price" <?= (($sort_by ?? '') === 'cost_price') ? 'selected' : '' ?>>Cost Price</option>
                <option value="current_stock" <?= (($sort_by ?? '') === 'current_stock') ? 'selected' : '' ?>>Current Stock</option>
                <option value="created_at" <?= (($sort_by ?? '') === 'created_at') ? 'selected' : '' ?>>Creation Date</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="sort_order" class="form-label light-txt">Sort Order</label>
            <select class="form-select dark-txt light-bg" id="sort_order" name="sort_order">
                <option value="asc" <?= (($sort_order ?? '') === 'asc') ? 'selected' : '' ?>>Ascending</option>
                <option value="desc" <?= (($sort_order ?? '') === 'desc') ? 'selected' : '' ?>>Descending</option>
            </select>
        </div>
    </div>
    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <button type="submit" class="btn btn-info w-100">Apply Sort</button>
        </div>
        <div class="col-md-6">
            <a href="/staff/products_list" class="btn btn-secondary w-100">Clear Filters & Sort</a>
        </div>
    </div>
</form>

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
        <?php if (!$products_info->isEmpty()): ?>
            <?php foreach ($products_info as $product): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($product->id) ?></td>
                    <td class="wrap-long"><?= htmlspecialchars($product->sku ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->description ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->category->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($product->brand->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(number_format($product->unit_price ?? 0.00, 2)) ?></td>
                    <td><?= htmlspecialchars(number_format($product->cost_price ?? 0.00, 2)) ?></td>
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
                    <td><?= htmlspecialchars($product->created_at ? date('Y-m-d H:i', strtotime($product->created_at)) : 'N/A') ?></td>
                    <td>
                        <a href="/staff/products/edit/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/products/show/<?= htmlspecialchars($product->id) ?>" class="btn btn-sm btn-info me-1">Show</a>
                        <form action="/staff/products/delete/<?= htmlspecialchars($product->id) ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Product list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="16" class="text-center">No products found matching your criteria.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<script>
document.getElementById('printProductsListBtn').addEventListener('click', function(e) {
    e.preventDefault(); // Prevent default link behavior

    const form = document.getElementById('productsFilterForm');
    const formData = new URLSearchParams(new FormData(form));
    
    // Construct the URL for the print list function
    const printUrl = '/staff/products/print_list?' + formData.toString();
    
    // Open the PDF in a new tab
    window.open(printUrl, '_blank');
});
</script>

<?php
Logger::log('UI: On products_list.php'); // Your existing logger call
?>
