<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Ensure $suppliers_info is an object with an isEmpty method to prevent errors
if (!isset($suppliers_info)) {
    $suppliers_info = new \Illuminate\Database\Eloquent\Collection();
}
?>

<div class="d-flex justify-content-end mb-3">
  <a href="/staff/suppliers/add" class="btn btn-primary">Add New Supplier</a>
</div>
<h1 class="text-white mb-4">Supplier List</h1>

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
                    <td><?= htmlspecialchars($supplier->address ?? 'N/A') ?></td> <td><?= htmlspecialchars(date('Y-m-d', strtotime($supplier->created_at)) ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($supplier->updated_at)) ?? 'N/A') ?></td> <td>
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