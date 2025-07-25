<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Check for success message in URL query parameters
if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
    echo '<div class="alert alert-success text-center mb-3" role="alert">' . $success_message . '</div>';
}

// Check for error message in URL query parameters
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
    echo '<div class="alert alert-danger text-center mb-3" role="alert">' . $error_message . '</div>';
}
?>
        <div class="d-flex justify-content-end mb-3">
          <a href="/staff/brands/add" class="btn btn-primary">Add New Brand</a>
        </div>

<h1 class="text-white mb-4">Brands List</h1> <div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>NAME</th>
          <th>WEBSITE</th>
          <th>EMAIL</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$brand_info->isEmpty()): // CASE 1: Check if the collection is NOT empty (i.e., has brands) ?>
            <?php foreach ($brand_info as $brand): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($brand->id) ?></td> <td><?= htmlspecialchars($brand->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($brand->website ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($brand->contact_email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($brand->created_at ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($brand->updated_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/brands/edit/<?= htmlspecialchars($brand->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/brands/delete/<?= htmlspecialchars($brand->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php elseif ($brand_info->isEmpty()): // CASE 2: Check if the collection IS empty ?>
          <?php Logger::log('DEBUG: Brands list ELSEIF block was executed (empty case - using isEmpty())!'); ?>
            <tr>
              <td colspan="7" class="text-center">No brands found.</td>
            </tr>
        <?php else: // CASE 3: Fallback for truly unexpected scenarios (unlikely for a Collection) ?>
          <?php Logger::log('ERROR: Brands list ELSE block reached - unexpected $brand_info state (using isEmpty())!'); ?>
            <tr>
              <td colspan="7" class="text-center text-danger">An unexpected error occurred while processing brand data.</td>
            </tr>
        <?php endif; ?>
      </tbody> 
    </table>
  </div>
<?php
Logger::log('UI: On brands_list.php');
?>