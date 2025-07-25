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
  <a href="/staff/customers/add" class="btn btn-primary">Add New Customer</a>
</div>
<h1 class="text-white mb-4">Customers List</h1>
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
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$customers_info->isEmpty()): // Changed !empty($customers) to !$customers->isEmpty() ?>
            <?php foreach ($customers_info as $customer): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($customer->id) ?></td>
                    <td><?= htmlspecialchars($customer->customer_type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->company_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->contact_person_first_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->contact_person_last_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->phone_number ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->address ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->created_at ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->updated_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/customers/edit/<?= htmlspecialchars($customer->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/customers/delete/<?= htmlspecialchars($customer->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Customers list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="11" class="text-center">No customers found.</td> </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
Logger::log('UI: On customers_list.php'); // Your existing logger call
?>