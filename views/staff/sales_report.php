<?php
// Display success message if available
use App\Core\Logger; // Ensure Logger is used if needed here
use Carbon\Carbon; // Required for date comparison

?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="text-white mb-0">Sales Report</h1>
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
  <div>
    <a href="#" id="printListBtn" class="btn btn-info" target="_blank">Print Report</a>
  </div>
</div>

<form method="GET" action="/staff/sales_report" class="mb-4 p-3 rounded shadow-sm light-bg-card" id="transactionsFilterForm">
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="search_query" class="form-label light-txt">Search</label>
            <input type="text" class="form-control dark-txt light-bg" id="search_query" name="search_query" placeholder="Invoice Number" value="<?= htmlspecialchars($search_query ?? '') ?>" maxlength="50">
        </div>
        
        <div class="col-md-3">
            <label for="filter_status" class="form-label light-txt">Filter by Status</label>
            <select class="form-select dark-txt light-bg" id="filter_status" name="filter_status">
                <option value="">All Statuses</option>
                <?php foreach ($transaction_statuses_list as $status): ?>
                    <option value="<?= htmlspecialchars($status) ?>" <?= (($filter_status ?? '') === $status) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_date_range" class="form-label light-txt">Date Range</label>
            <select class="form-select dark-txt light-bg" id="filter_date_range" name="filter_date_range">
                <option value="">All Time</option>
                <option value="today" <?= (($filter_date_range ?? '') === 'today') ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= (($filter_date_range ?? '') === 'yesterday') ? 'selected' : '' ?>>Yesterday</option>
                <option value="week" <?= (($filter_date_range ?? '') === 'week') ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= (($filter_date_range ?? '') === 'month') ? 'selected' : '' ?>>This Month</option>
                <option value="year" <?= (($filter_date_range ?? '') === 'year') ? 'selected' : '' ?>>This Year</option>
                <option value="custom" <?= (($filter_date_range ?? '') === 'custom') ? 'selected' : '' ?>>Custom Dates</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info w-100">Apply Filters</button>
        </div>
    </div> 
    
    <div class="row g-3 align-items-end mt-2" 
         id="customDateFieldsContainer" 
         style="display: <?= (($filter_date_range ?? '') === 'custom') ? 'flex' : 'none' ?>;">
        <div class="col-md-3">
            <label class="form-label light-txt">Start Date</label>
            <input type="date" class="form-control dark-txt light-bg" id="start_date" name="start_date" 
                value="<?= htmlspecialchars($start_date ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label light-txt">End Date</label>
            <input type="date" class="form-control dark-txt light-bg" id="end_date" name="end_date" 
                value="<?= htmlspecialchars($end_date ?? '') ?>">
        </div>
        
    </div>
    
    <div class="row g-3 align-items-end mt-2">
        <div class="col-md-4">
            <label for="sort_by" class="form-label light-txt">Sort By</label>
            <select class="form-select dark-txt light-bg" id="sort_by" name="sort_by">
                <option value="transaction_date" <?= (($sort_by ?? '') === 'transaction_date') ? 'selected' : '' ?>>Transaction Date</option>
                <option value="invoice_bill_number" <?= (($sort_by ?? '') === 'invoice_bill_number') ? 'selected' : '' ?>>Invoice Number</option>
                <option value="total_amount" <?= (($sort_by ?? '') === 'total_amount') ? 'selected' : '' ?>>Total Amount</option>
                <option value="status" <?= (($sort_by ?? '') === 'status') ? 'selected' : '' ?>>Status</option>
                <option value="created_at" <?= (($sort_by ?? '') === 'created_at') ? 'selected' : '' ?>>Created At</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="sort_order" class="form-label light-txt">Sort Order</label>
            <select class="form-select dark-txt light-bg" id="sort_order" name="sort_order">
                <option value="desc" <?= (($sort_order ?? '') === 'desc') ? 'selected' : '' ?>>Descending</option>
                <option value="asc" <?= (($sort_order ?? '') === 'asc') ? 'selected' : '' ?>>Ascending</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-info w-100">Apply Sort</button>
        </div>
        <div class="col-md-3">
            <a href="/staff/sales_report" class="btn btn-secondary w-100">Clear Filters & Sort</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th>CUSTOMER</th>
          <th class="hidden-header">SUPPLIER</th>
          <th>TRANSACTION DATE</th>
          <th>INVOICE</th>
          <th>TOTAL AMOUNT (₱)</th>
          <th>STATUS</th>
          <th>CREATED BY</th>
          <th>UPDATED BY</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // The crucial check: If the collection has items, proceed.
        if ($transactions_info->isNotEmpty()):
        ?>
            <?php foreach ($transactions_info as $transaction): ?>
              <?php if ($transaction->transaction_type == "Sale"): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($transaction->id ?? '') ?></td>
                    <td><?= htmlspecialchars($transaction->transaction_type ?? 'N/A') ?></td>
                    <td>
                      <?php
                        $partyName = $transaction->customer->company_name
                            ?? trim(($transaction->customer->contact_first_name ?? '') . ' ' . ($transaction->customer->contact_last_name ?? ''));

                        echo htmlspecialchars($partyName !== '' ? $partyName : 'N/A');
                      ?>
                    </td>

                    <td class="hidden-column">
                        <?php
                        // Accessing nested properties with null coalescing for safety
                        echo htmlspecialchars($transaction->supplier->supplier_name ?? 'N/A');
                        ?>
                    </td>
                    <td><?= htmlspecialchars($transaction->transaction_date ? date('Y-m-d', strtotime($transaction->transaction_date)) : 'N/A') ?></td>
                    <td><?= htmlspecialchars($transaction->invoice_bill_number ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(number_format($transaction->total_amount ?? 0.00, 2)) ?></td>
                    <td><?= htmlspecialchars($transaction->status ?? 'N/A') ?></td>
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
                </tr>
              <?php endif ?>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Transactions list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="15" class="text-center">No transactions found matching your criteria.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<script>
document.getElementById('printListBtn').addEventListener('click', function(e) {
    e.preventDefault(); // Prevent default link behavior

    const form = document.getElementById('transactionsFilterForm');
    const formData = new URLSearchParams(new FormData(form));
    
    // Construct the URL for the print list function
    const printUrl = '/staff/transactions/sales_report?' + formData.toString();
    
    // Open the PDF in a new tab
    window.open(printUrl, '_blank');


});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterDateRange = document.getElementById('filter_date_range');
    // NOTE: Targeting the new container div
    const customFieldsContainer = document.getElementById('customDateFieldsContainer');
    const printListBtn = document.getElementById('printListBtn');
    const transactionsFilterForm = document.getElementById('transactionsFilterForm');

    // Function to handle showing/hiding custom date fields
    function toggleCustomDateFields() {
        if (filterDateRange.value === 'custom') {
            // Use 'flex' to ensure the bootstrap row layout works correctly
            customFieldsContainer.style.display = 'flex'; 
        } else {
            customFieldsContainer.style.display = 'none';
        }
    }

    // Initial check when the page loads
    toggleCustomDateFields(); 

    // Event listener for date range change
    filterDateRange.addEventListener('change', toggleCustomDateFields);

    // Print Button Logic (Cleaned up and placed inside DOMContentLoaded)
    printListBtn.addEventListener('click', function(e) {
        e.preventDefault(); 
        const formData = new URLSearchParams(new FormData(transactionsFilterForm));
        
        // Use a dedicated print route (recommended practice)
        const printUrl = '/staff/sales_report/print?' + formData.toString();
        
        window.open(printUrl, '_blank');
    });
});
</script>
<?php
Logger::log('UI: On sales_report.php');
?>
