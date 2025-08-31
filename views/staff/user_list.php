<?php
// Place all 'use' statements here, at the very top of the PHP file
use App\Core\Logger;

?>

<div class="d-flex justify-content-end mb-3">
  <a href="/staff/users/add" class="btn btn-primary">Add New User</a>
</div>
  <h1 class="text-white mb-4">User List</h1>
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
  <div class="row" style="margin-bottom: 10px;">
    <div class="col-md-4">
      <label for="search_query" class="form-label light-txt">Search</label>
      <input type="text" 
             class="form-control dark-txt light-bg" 
             id="search_query" 
             name="search_query" 
             placeholder="Username, email, or name" 
             value="<?= htmlspecialchars(trim($search_query ?? '')) ?>" 
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
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Last Name</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Birth Date</th>
          <th>Type</th>
          <th>Creation Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$user_info->isEmpty()): ?>
            <?php foreach ($user_info as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user->id) ?></td>
                    <td class="pxtable" style="max-width: 150px;"><?= htmlspecialchars($user->username) ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($user->email ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($user->last_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($user->first_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars($user->middle_name ?? 'N/A') ?></td>
                    <td class="pxtable" style="max-width: 100px;"><?= htmlspecialchars(date('Y-m-d', strtotime($user->birthdate)) ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d',strtotime($user->created_at)) ?? 'N/A') ?></td>
                    <td>
                        <a href="/staff/users/edit/<?= htmlspecialchars($user->id) ?>" class="btn btn-sm btn-info me-1">Edit</a>
                        <a href="/staff/users/delete/<?= htmlspecialchars($user->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <?php Logger::log('DEBUG: User list ELSE block was executed (empty case)!'); ?>
            <tr>
                <td colspan="10" class="text-center">No users found.</td> </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
<?php
Logger::log('UI: On user_list.php')
?>
