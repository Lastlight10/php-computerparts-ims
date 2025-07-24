<?php ob_start(); ?>

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
        <?php if (!empty($user_info)): // Check if the collection is not empty ?>
            <?php foreach ($user_info as $user): // Loop through each user model ?>
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
                        <a href="/staff/users/delete/<?= htmlspecialchars($user->id) ?>" class="btn btn-sm btn-danger">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" class="text-center">No users found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

<?php
$content = ob_get_clean();
require_once 'staff_layout.php';

// These Logger lines should ideally be in your controller or main entry point,
// not directly within the view file. Views should only handle presentation.
require_once 'core/Logger.php';
$memory = memory_get_usage();
Logger::log("Used: $memory on user_list.php"); // Changed filename for clarity
?>