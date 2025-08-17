<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;
use Models\Transaction;
use Models\TransactionItem; // Assuming you'll have this model

// Ensure $transaction is passed. If not, this page can't function.
if (!isset($transaction) || !$transaction) {
    echo '<div class="alert alert-danger text-center mt-5">Transaction data not available.</div>';
    Logger::log('ERROR: Transaction object not available in staff/transactions/show.php');
    return; // Stop rendering the page if no transaction
}

// Format dates for display
$transaction_date_formatted = date('F j, Y', strtotime($transaction->transaction_date));
$created_at_formatted = $transaction->created_at ? date('F j, Y, h:i A', strtotime($transaction->created_at)) : 'N/A';
$updated_at_formatted = $transaction->updated_at ? date('F j, Y, h:i A', strtotime($transaction->updated_at)) : 'N/A';

// Prepare customer/supplier display
$party_type = 'N/A';
$party_name = 'N/A';

if ($transaction->transaction_type === 'Sale' && $transaction->customer) {
    $party_type = 'Customer';
    $party_name = htmlspecialchars($transaction->customer->company_name ?? $transaction->customer->contact_first_name . ' ' . $transaction->customer->contact_last_name);
} elseif ($transaction->transaction_type === 'Purchase' && $transaction->supplier) {
    $party_type = 'Supplier';
    $party_name = htmlspecialchars($transaction->supplier->company_name ?? $transaction->supplier->contact_first_name . ' ' . $transaction->supplier->contact_last_name);
} elseif ($transaction->transaction_type === 'Return') {
    if ($transaction->customer) {
        $party_type = 'Customer (Return From)';
        $party_name = htmlspecialchars($transaction->customer->company_name ?? $transaction->customer->contact_first_name . ' ' . $transaction->customer->contact_last_name);
    } elseif ($transaction->supplier) {
        $party_type = 'Supplier (Return To)';
        $party_name = htmlspecialchars($transaction->supplier->company_name ?? $transaction->supplier->contact_first_name . ' ' . $transaction->supplier->contact_last_name);
    } else {
        $party_type = 'N/A (Return)';
        $party_name = 'N/A';
    }
} elseif ($transaction->transaction_type === 'Adjustment') {
    $party_type = 'N/A';
    $party_name = 'No specific party';
}

// Determine if "Add Item" button should be shown (e.g., not for Completed/Cancelled transactions)
$can_add_items = ($transaction->status !== 'Completed' && $transaction->status !== 'Cancelled');
?>

<section class="page-wrapper dark-bg">
    <div class="container-fluid page-content">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">

                <div class="card lighterdark-bg p-4 shadow-sm mb-4">
                    <h3 class="text-white text-center mb-4">Transaction Details: #<?= htmlspecialchars($transaction->invoice_bill_number) ?></h3>

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

                    <div class="row text-white mb-3">
                        <div class="col-md-6 mb-2"><strong>Transaction Type:</strong> <?= htmlspecialchars($transaction->transaction_type) ?></div>
                        <div class="col-md-6 mb-2"><strong>Transaction Date:</strong> <?= $transaction_date_formatted ?></div>

                        <div class="col-md-6 mb-2"><strong>Party:</strong>
                            <?php 
                            
                            if ($transaction->customer): ?>
                                <?= htmlspecialchars(
                                    $transaction->customer->company_name
                                    ?: trim(($transaction->customer->contact_first_name ?? '') . ' ' . ($transaction->customer->contact_last_name ?? ''))
                                ) ?>
                            <?php elseif ($transaction->supplier): ?>
                                <?= htmlspecialchars(
                                    $transaction->supplier->company_name
                                    ?: trim(($transaction->supplier->contact_first_name ?? '') . ' ' . ($transaction->supplier->contact_last_name ?? ''))
                                    
                                ) ?>
                            <?php else: ?>
                                No Supplier/Customer
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-2"><strong>Status:</strong> <?= htmlspecialchars($transaction->status) ?></div>
                        <div class="col-md-6 mb-2"><strong>Total Amount:</strong> ₱<?= number_format($transaction->total_amount, 2) ?></div>
                        <?php
                        $display_amount_received = false;
                        $amount_label = '';
                        if ($transaction->transaction_type === 'Sale') {
                            $display_amount_received = true;
                            $amount_label = 'Amount Received:';
                        } elseif ($transaction->transaction_type === 'Purchase') {
                            $display_amount_received = true;
                            $amount_label = 'Amount Paid:';
                        } elseif ($transaction->transaction_type === 'Customer Return') {
                            $display_amount_received = true;
                            $amount_label = 'Amount Refunded:';
                        } elseif ($transaction->transaction_type === 'Supplier Return') {
                            $display_amount_received = true;
                            $amount_label = 'Amount Received (Refund):';
                        }
                        ?>
                        <?php if ($display_amount_received): ?>
                            <div class="col-md-6 mb-2">
                                <strong><?= $amount_label ?></strong> 
                                ₱<?= number_format($transaction->amount_received !== null ? (float)$transaction->amount_received : 0.00, 2) ?>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6 mb-2"><strong>Created By:</strong> <?= htmlspecialchars($transaction->createdBy->username ?? 'N/A') ?></div>
                        <div class="col-md-6 mb-2"><strong>Created At:</strong> <?= date('Y-m-d', strtotime($created_at_formatted)) ?></div>
                        <div class="col-md-6 mb-2"><strong>Updated By:</strong> <?= htmlspecialchars($transaction->updatedBy->username ?? 'N/A') ?></div>
                        <div class="col-md-6 mb-2"><strong>Updated At:</strong> <?= date('Y-m-d', strtotime($updated_at_formatted)) ?></div>
                        <div class="col-12 mb-2"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($transaction->notes ?? '')) ?></div>
                    </div>

                    <div class="d-flex justify-content-center flex-wrap gap-2 mt-3">
                        <a href="/staff/transactions/edit/<?= htmlspecialchars($transaction->id) ?>" class="btn btn-warning">Edit Transaction Details</a>
                        <a href="/staff/transactions/print/<?= htmlspecialchars($transaction->id) ?>" class="btn btn-info" target="_blank">Print Transaction</a>
                        <a href="/staff/transactions_list" class="btn btn-secondary">Back to List</a>
                    </div>
                </div>

                <div class="card lighterdark-bg p-4 shadow-sm">
                    <h4 class="text-white text-center mb-4">Transaction Items</h4>

                    <?php if ($can_add_items): ?>
                        <div class="d-flex justify-content-end mb-3">
                            <a href="/staff/transaction_items/add/<?= htmlspecialchars($transaction->id) ?>" class="btn btn-primary lightgreen-bg">
                                <i class="fas fa-plus-circle"></i> Add New Item
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ((new \Illuminate\Support\Collection($transaction->items))->isEmpty()): ?>
                        <p class="text-white-50 text-center">No items have been added to this transaction yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-striped table-hover rounded-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                        <?php if ($can_add_items): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $item_counter = 1; ?>
                                    <?php foreach ($transaction->items as $item): ?>
                                        <tr>
                                            <td><?= $item_counter++ ?></td>
                                            <td><?= htmlspecialchars($item->product->name ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($item->quantity !== null ? $item->quantity : 'N/A') ?></td>
                                            <td>₱<?= number_format($item->unit_price_at_transaction !== null ? (float)$item->unit_price_at_transaction : 0.00, 2) ?></td>
                                            <td>₱<?= number_format($item->line_total !== null ? (float)$item->line_total : 0.00, 2) ?></td> <?php if ($can_add_items): ?>
                                                <td>
                                                    <a href="/staff/transaction_items/edit/<?= htmlspecialchars($item->id) ?>" class="btn btn-sm btn-warning mb-1">Edit</a>
                                                    <form action="/staff/transaction_items/delete/<?= htmlspecialchars($item->id) ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item? This action cannot be undone.');">
                                                        <button type="submit" class="btn btn-sm btn-danger mb-1">Delete</button>
                                                    </form>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
Logger::log('UI: On staff/transactions/show.php for Transaction ID: ' . $transaction->id);
?>
