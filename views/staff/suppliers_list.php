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

<h1 class="text-white mb-4">Supplier List</h1>
<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th>COMPANY NAME</th>
          <th>CONTACT (First Name)</th>
          <th>CONTACT (Last Name)</th>
          <th>EMAIL</th>
          <th>PHONE</th>
          <th>ADDRESS</th>
          <th>NOTES</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$suppliers_info->isEmpty()): // Changed !empty($suppliers) to !$suppliers->isEmpty() ?>
            <?php foreach ($suppliers_info as $supplier): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($supplier->id) ?></td>
                    <td><?= htmlspecialchars($supplier->supplier_type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->company_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->contact_person_first_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->contact_person_last_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->phone_number ?? 'N/A') ?></td>
                    <td>
                        <?= htmlspecialchars($supplier->address_street ?? '') ?><br>
                        <?= htmlspecialchars($supplier->address_city ?? '') ?>,
                        <?= htmlspecialchars($supplier->address_state_province ?? '') ?>
                        <?= htmlspecialchars($supplier->address_zip_code ?? '') ?>
                    </td>
                    <td><?= htmlspecialchars($supplier->notes ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($supplier->created_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/suppliers/edit/<?= htmlspecialchars($supplier->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/suppliers/delete/<?= htmlspecialchars($supplier->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this supplier? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Supplier list ELSE block was executed (empty case)!'); ?>
            <tr>
                <?php $colspan = 12; // Already correctly calculated by you ?>
                <td colspan="<?= $colspan ?>" class="text-center">No suppliers found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
Logger::log('UI: On suppliers_list.php'); // Your existing logger call
?>