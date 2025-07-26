<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Initialize variables to hold messages (for direct view rendering)
$success_message = ''; // Renamed from $display_success_message for consistency with query param check
$error_message = '';   // Renamed from $display_error_message for consistency with query param check

// Check for success message in URL query parameters
if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $success_message = htmlspecialchars($_GET['success_message']);
}
// Note: Removed the `elseif (isset($success_message))` part as controller redirects
// typically set these via GET params. If your controller also passes $success_message
// directly when rendering, you can re-add that 'elseif'.

// Check for error message in URL query parameters
if (isset($_GET['error']) && !empty($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}
// Note: Same as above for error messages.

// Ensure $suppliers_info is an object with an isEmpty method to prevent errors
if (!isset($suppliers_info)) {
    $suppliers_info = new \Illuminate\Database\Eloquent\Collection();
}
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/staff/suppliers/add" class="btn btn-primary">Add New Supplier</a>
</div>
<h1 class="text-white mb-4">Supplier List</h1>

<?php if (!empty($success_message)): ?>
  <div class="alert alert-success text-center mb-3" role="alert">
    <?= $success_message ?>
  </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
  <div class="alert alert-danger text-center mb-3" role="alert">
    <?= $error_message ?>
  </div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th>COMPANY NAME</th>
          <th>CONTACT (First Name)</th>
          <th>CONTACT (Middle Name)</th>
          <th>CONTACT (Last Name)</th>
          <th>EMAIL</th>
          <th>PHONE</th>
          <th>ADDRESS</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$suppliers_info->isEmpty()): ?>
            <?php foreach ($suppliers_info as $supplier): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($supplier->id) ?></td>
                    <td><?= htmlspecialchars($supplier->supplier_type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->company_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->contact_first_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->contact_middle_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->contact_last_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->phone_number ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->address ?? 'N/A') ?></td> <td><?= htmlspecialchars($supplier->created_at ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->updated_at ?? 'N/A') ?></td> <td>
                        <a href="/staff/suppliers/edit/<?= htmlspecialchars($supplier->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/suppliers/delete/<?= htmlspecialchars($supplier->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this supplier? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Supplier list ELSE block was executed (empty case)!'); ?>
            <tr>
                <?php $colspan = 11; // 11 columns in the table, adjust if needed: ID, TYPE, COMPANY, First, Middle, Last, Email, Phone, Address, Creation, Update, Actions ?>
                <td colspan="<?= $colspan ?>" class="text-center">No suppliers found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
Logger::log('UI: On suppliers_list.php');
?>