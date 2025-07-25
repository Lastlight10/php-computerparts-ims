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

<h1 class="text-white mb-4">Transactions List</h1>
<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th>CUSTOMER</th>
          <th>SUPPLIER</th>
          <th>TRANSACTION DATE</th>
          <th>INVOICE</th>
          <th>TOTAL AMOUNT</th>
          <th>REMARKS</th>
          <th>CREATED BY</th>
          <th>UPDATED BY</th>
          <th>CREATED AT</th>
          <th>UPDATED AT</th>
          <th>ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$transactions_info->isEmpty()): // Changed !empty($transactions) to !$transactions->isEmpty() ?>
            <?php foreach ($transactions_info as $transaction): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($transaction->id) ?></td>
                    <td><?= htmlspecialchars($transaction->transaction_type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->customer->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->supplier->name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->transaction_date ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->invoice_bill_number ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->total_amount ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->status ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->notes ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->created_by_user_id ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->updated_by_user_id ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->created_at ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->updated_at ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/transactions/edit/<?= htmlspecialchars($transaction->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/transactions/delete/<?= htmlspecialchars($transaction->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Transactions list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="13" class="text-center">No transactions found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php
Logger::log('UI: On transactions_list.php')
?>