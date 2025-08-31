<?php
use App\Core\Logger;

        ?>
        <div class="d-flex justify-content-end mb-3">
          <a href="/staff/brands/add" class="btn btn-primary">Add New Brand</a>
        </div>

<h1 class="text-white mb-4">Brands List</h1> <div class="table-responsive">
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
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
    <div class="row">
      <div class="col-md-4">
        <label for="search_query" class="form-label light-txt">Search</label>
        <input type="text" 
              class="form-control dark-txt light-bg" 
              id="search_query" 
              name="search_query" 
              placeholder="Brand name or email" 
              value="<?= htmlspecialchars($search_query ?? '') ?>" 
              maxlength="30"
              style="margin-bottom: 10px;"
              >
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100" style="margin-bottom: 10px;">Search</button>
      </div>
    </div>
  </form>
    <table class="table table-dark table-striped table-hover">
      <thead>
        <tr>
          <th class="hidden-header">ID</th>
          <th>NAME</th>
          <th>WEBSITE</th>
          <th>EMAIL</th>
          <th>CREATION DATE</th>
          <th>UPDATED DATE</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$brand_info->isEmpty()): // CASE 1: Check if the collection is NOT empty (i.e., has brands) ?>
            <?php foreach ($brand_info as $brand): ?>
                <tr>
                    <td class="hidden-column"><?= htmlspecialchars($brand->id) ?></td> <td><?= htmlspecialchars($brand->name ?? 'N/A') ?></td>
                    <td ><?= htmlspecialchars($brand->website ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($brand->contact_email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($brand->created_at) ?? 'N/A')) ?></td>
                    <td ><?= htmlspecialchars(date('Y-m-d', strtotime($brand->updated_at)) ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/brands/edit/<?= htmlspecialchars($brand->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/brands/delete/<?= htmlspecialchars($brand->id) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this brand? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php elseif ($brand_info->isEmpty()): // CASE 2: Check if the collection IS empty ?>
          <?php Logger::log('DEBUG: Brands list ELSEIF block was executed (empty case - using isEmpty())!'); ?>
            <tr>
              <td colspan="7" class="text-center">No brands found.</td>
            </tr>
        <?php else: // CASE 3: Fallback for truly unexpected scenarios (unlikely for a Collection) ?>
          <?php Logger::log('ERROR: Brands list ELSE block reached - unexpected $brand_info state (using isEmpty())!'); ?>
            <tr>
              <td colspan="7" class="text-center text-danger">An unexpected error occurred while processing brand data.</td>
            </tr>
        <?php endif; ?>
      </tbody> 
    </table>
  </div>
<?php
Logger::log('UI: On brands_list.php');
?>