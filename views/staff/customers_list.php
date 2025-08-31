<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

// Ensure $customers_info is an object with an isEmpty method to prevent errors
if (!isset($customers_info)) {
    $customers_info = new \Illuminate\Database\Eloquent\Collection();
}
?>
<div class="d-flex justify-content-end mb-3">
  <a href="/staff/customers/add" class="btn btn-primary">Add New Customer</a>
</div>
<h1 class="text-white mb-4">Customer List</h1>
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
<form method="get" action="">
  <div class="row" style="margin-bottom:10px;">
    <div class="col-md-4">
      <label for="search_query" class="form-label light-txt">Search</label>
      <input type="text" 
             class="form-control dark-txt light-bg" 
             id="search_query" 
             name="search_query" 
             placeholder="Company name, contact name, type, or email" 
             value="<?= htmlspecialchars($search_query ?? '') ?>" 
             maxlength="30">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">Search</button>
    </div>
  </div>
</form>

<div class="table-responsive">
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>TYPE</th>
          <th>COMPANY NAME</th>
          <th>CONTACT (First Name)</th>
          <th>CONTACT (Middle Name)</th> <th>CONTACT (Last Name)</th>
          <th>EMAIL</th>
          <th>PHONE</th>
          <th>ADDRESS</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$customers_info->isEmpty()): ?>
            <?php foreach ($customers_info as $customer): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($customer->id) ?></td>
                    <td ><?= htmlspecialchars($customer->customer_type ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 150px;"><?= htmlspecialchars($customer->company_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($customer->contact_first_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($customer->contact_middle_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($customer->contact_last_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 120px;"><?= htmlspecialchars($customer->email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($customer->phone_number ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 150px;"><?= htmlspecialchars($customer->address ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($customer->created_at)) ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d',strtotime($customer->updated_at)) ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/customers/edit/<?= htmlspecialchars($customer->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/customers/delete/<?= htmlspecialchars($customer->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: Customer list ELSE block was executed (empty case)!'); ?>
            <tr>
                <?php $colspan = 11; // 11 columns in the table: ID, TYPE, COMPANY, First, Middle, Last, Email, Phone, Address, Creation, Update, Actions ?>
                <td colspan="<?= $colspan ?>" class="text-center">No customers found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
  Logger::log('UI: On customers_list.php');
?>