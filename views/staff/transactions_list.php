<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Check for success message in URL query parameters
$success_message = $_GET['success_message'] ?? null;
if ($success_message) {
    echo '<div class="alert alert-success text-center mb-3" role="alert">' . htmlspecialchars($success_message) . '</div>';
}

// Check for error message in URL query parameters
$error_message = $_GET['error'] ?? null;
if ($error_message) {
    echo '<div class="alert alert-danger text-center mb-3" role="alert">' . htmlspecialchars($error_message) . '</div>';
}

// Ensure $transactions_info is treated as an Eloquent Collection, even if it's empty
// The controller should already pass a Collection, but this handles edge cases.
// If it's an array for some reason, collect([]) will convert it.
if (!isset($transactions_info) || is_array($transactions_info)) {
    $transactions_info = collect($transactions_info ?? []);
}

?>
<div class="d-flex justify-content-end mb-3">
  <a href="/staff/transactions/add" class="btn btn-primary">Add New Transaction</a>
</div>
<h1 class="text-white mb-4">Transactions List</h1>
<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th class="hidden-header">CUSTOMER</th>
          <th class="hidden-header">SUPPLIER</th>
          <th>TRANSACTION DATE</th>
          <th>INVOICE</th>
          <th>TOTAL AMOUNT</th>
          <th>STATUS</th>
          <th>REMARKS</th>
          <th>CREATED BY</th>
          <th>UPDATED BY</th>
          <th>CREATED AT</th>
          <th>UPDATED AT</th>
          <th>ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // The crucial check: If the collection has items, proceed.
        if ($transactions_info->isNotEmpty()):
        ?>
            <?php foreach ($transactions_info as $transaction): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($transaction->id ?? '') ?></td>
                    <td><?= htmlspecialchars($transaction->transaction_type ?? 'N/A') ?></td>
                    <td class="hidden-column">
                        <?php
                        // Accessing nested properties with null coalescing for safety
                        echo htmlspecialchars($transaction->customer->first_name ?? '') . ' ' . htmlspecialchars($transaction->customer->last_name ?? '') ?: 'N/A';
                        ?>
                    </td>
                    <td class="hidden-column">
                        <?php
                        // Accessing nested properties with null coalescing for safety
                        echo htmlspecialchars($transaction->supplier->name ?? 'N/A');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($transaction->transaction_date ? date('Y-m-d H:i', strtotime($transaction->transaction_date)) : 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->invoice_bill_number ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(number_format($transaction->total_amount ?? 0.00, 2)) ?></td>
                    <td><?= htmlspecialchars($transaction->status ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->notes ?? 'N/A') ?></td>
                    <td>
                        <?php
                        // Access created_by relationship
                        echo htmlspecialchars($transaction->createdBy->username ?? 'N/A');
                        ?>
                    </td>
                    <td>
                        <?php
                        // Access updated_by relationship
                        echo htmlspecialchars($transaction->updatedBy->username ?? 'N/A');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($transaction->created_at ? date('Y-m-d H:i', strtotime($transaction->created_at)) : 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->updated_at ? date('Y-m-d H:i', strtotime($transaction->updated_at)) : 'N/A') ?></td>
                    <td>
                        <a href="/staff/transactions/show/<?= htmlspecialchars($transaction->id ?? '') ?>" class="btn btn-sm btn-info me-1">Show</a>
                        <a href="/staff/transactions/edit/<?= htmlspecialchars($transaction->id ?? '') ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <form action="/staff/transactions/delete/<?= htmlspecialchars($transaction->id ?? '') ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Transactions list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="15" class="text-center">No transactions found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php
Logger::log('UI: On transactions_list.php');
?>