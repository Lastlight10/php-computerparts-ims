<?php
use App\Core\Logger;

// Ensure $instance and $product_instance_statuses are defined
$instance = $instance ?? null;
$product_instance_statuses = $product_instance_statuses ?? [];

// Error and success messages passed from the controller via session or GET
$session_error_message = $_SESSION['error_message'] ?? null;
$session_success_message = $_SESSION['success_message'] ?? null;

unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

$display_success_message = null;
$display_error_message = null;

if (isset($_GET['success_message']) && !empty($_GET['success_message'])) {
    $display_success_message = htmlspecialchars($_GET['success_message']);
} elseif (isset($success_message) && !empty($success_message)) {
    $display_success_message = htmlspecialchars($success_message);
} elseif (isset($session_success_message) && !empty($session_success_message)) {
    $display_success_message = htmlspecialchars($session_success_message);
}

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $display_error_message = htmlspecialchars($_GET['error']);
} elseif (isset($error) && !empty($error)) {
    $display_error_message = htmlspecialchars($error);
} elseif (isset($session_error_message) && !empty($session_error_message)) {
    $display_error_message = htmlspecialchars($session_error_message);
}

if (!$instance) {
    echo '<div class="alert alert-danger text-center mt-5">Product instance data not available for editing.</div>';
    Logger::log('ERROR: Product instance object not available in staff/product_instances/edit.php');
    exit;
}
?>

<section class="page-wrapper dark-bg">
    <div class="container-fluid page-content">
        <div class="card lightishdark-bg p-4 shadow-sm mb-4">
            <div class="card-header text-white text-center primary-bg-card">
                <h2 class="mb-0 light-txt">Manage Product Unit: <?= htmlspecialchars($instance->serial_number ?? 'N/A') ?></h2>
                <h5 class="mb-0 light-txt">Product: <?= htmlspecialchars($instance->product->name ?? 'N/A') ?> (SKU: <?= htmlspecialchars($instance->product->sku ?? 'N/A') ?>)</h5>
            </div>
            <div class="card-body light-bg-card">

                <?php if ($display_success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= $display_success_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($display_error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= $display_error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form action="/staff/product_instances/update" method="POST">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($instance->id) ?>">

                    <div class="mb-3">
                        <label for="serial_number" class="form-label light-txt">Serial Number</label>
                        <input type="text" class="form-control dark-txt light-bg" id="serial_number" value="<?= htmlspecialchars($instance->serial_number ?? 'N/A') ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label light-txt">Status</label>
                        <select class="form-select dark-txt light-bg" id="status" name="status" required>
                            <?php foreach ($product_instance_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>" <?= (($instance->status ?? '') === $status) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="cost_at_receipt" class="form-label light-txt">Cost at Receipt</label>
                        <input type="text" class="form-control dark-txt light-bg" id="cost_at_receipt" value="₱<?= htmlspecialchars(number_format((float)$instance->cost_at_receipt ?? 0, 2)) ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label for="warranty_expires_at" class="form-label light-txt">Warranty Expiration Date</label>
                        <input type="date" class="form-control dark-txt light-bg" id="warranty_expires_at" name="warranty_expires_at" value="<?= htmlspecialchars($instance->warranty_expires_at ?? '') ?>">
                    </div>

                    <h4 class="text-white mt-4 mb-3">Associated Transactions</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="light-txt mb-1"><strong>Purchased In:</strong>
                                <?php if (isset($instance->purchaseTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->purchaseTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->purchaseTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->purchaseTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                            <p class="light-txt mb-1"><strong>Sold In:</strong>
                                <?php if (isset($instance->saleTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->saleTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->saleTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->saleTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                            <p class="light-txt mb-1"><strong>Returned From Customer In:</strong>
                                <?php if (isset($instance->returnedFromCustomerTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->returnedFromCustomerTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->returnedFromCustomerTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->returnedFromCustomerTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="light-txt mb-1"><strong>Returned To Supplier In:</strong>
                                <?php if (isset($instance->returnedToSupplierTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->returnedToSupplierTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->returnedToSupplierTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->returnedToSupplierTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                            <p class="light-txt mb-1"><strong>Adjusted In:</strong>
                                <?php if (isset($instance->adjustedInTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->adjustedInTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->adjustedInTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->adjustedInTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                            <p class="light-txt mb-1"><strong>Adjusted Out:</strong>
                                <?php if (isset($instance->adjustedOutTransactionItem->transaction)): ?>
                                    <a href="/staff/transactions/show/<?= htmlspecialchars($instance->adjustedOutTransactionItem->transaction->id) ?>" class="text-info">
                                        <?= htmlspecialchars($instance->adjustedOutTransactionItem->transaction->invoice_bill_number ?? 'N/A') ?>
                                    </a>
                                    (<?= htmlspecialchars(date('Y-m-d', strtotime($instance->adjustedOutTransactionItem->transaction->transaction_date))) ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">Update Unit Details</button>
                        <a href="/staff/products/show/<?= htmlspecialchars($instance->product_id) ?>" class="btn btn-secondary btn-lg">Back to Product</a>
                        <form action="/staff/product_instances/delete/<?= htmlspecialchars($instance->id) ?>" method="POST" style="display:inline;" onsubmit="return confirm('WARNING: Deleting this product unit is permanent and cannot be undone. Are you absolutely sure?');">
                            <button type="submit" class="btn btn-danger btn-lg mt-2">Delete Unit</button>
                        </form>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php
Logger::log('UI: On staff/product_instances/edit.php');
?>
