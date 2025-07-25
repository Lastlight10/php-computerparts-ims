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
  <h1 class="text-white mb-4">User List</h1>

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
        <?php if (!$user_info->isEmpty()): // Changed !empty($user_info) to !$user_info->isEmpty() ?>
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
                        <a href="/staff/users/delete/<?= htmlspecialchars($user->id) ?>" class="btn btn-sm btn-danger"  onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">Delete</a>
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