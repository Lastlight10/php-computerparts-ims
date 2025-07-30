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

  <!-- Message Display Area -->
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
  <!-- End Message Display Area -->

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
                    <td><?= htmlspecialchars($user->username) ?></td>
                    <td><?= htmlspecialchars($user->email ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->last_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->first_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->middle_name ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->birthdate ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->type ?? 'N/A') ?></td>
                    <td><?= htmlspecialchars($user->created_at ?? 'N/A') ?></td>
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
