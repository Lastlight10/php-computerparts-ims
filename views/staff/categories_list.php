<?php
// Place all 'use' statements here, at the very top of the PHP file
// (Already present from your combined snippet, good!)
use App\Core\Logger;

?>

<div class="d-flex justify-content-end mb-3">
  <a href="/staff/categories/add" class="btn btn-primary">Add New Category</a>
</div>
<h1 class="text-white mb-4">Categories List</h1>

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
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
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

<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>NAME</th>
          <th>Description</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$category_info->isEmpty()): // Changed !empty($category_info) to !$category_info->isEmpty() ?>
            <?php foreach ($category_info as $category): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($category->id) ?></td>
                    <td><?= htmlspecialchars($category->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($category->description ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($category->created_at)) ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($category->updated_at)) ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/categories/edit/<?= htmlspecialchars($category->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/categories/delete/<?= htmlspecialchars($category->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Categories list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="6" class="text-center">No categories found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
Logger::log('UI: On categories_list.php'); // Your existing logger call
?>