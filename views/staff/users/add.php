<?php
use App\Core\Logger;

// Initialize display variables for messages
$display_success_message = $_SESSION['success_message'] ?? null;
$display_error_message = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Ensure $user and $user_types are defined
$user = $user ?? (object)[]; // Provide an empty object if not set
$user_types = $user_types ?? ['Staff', 'Manager', 'Admin']; // Default if not passed from controller
?>

<section class="page-wrapper dark-bg">
    <div class="container-fluid page-content">
        <div class="card lighterdark-bg p-4 shadow-sm mb-4">
            <h3 class="text-white text-center mb-4">Add New User</h3>

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

            <form action="/staff/users/store" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label light-txt">Username</label>
                    <input type="text" class="form-control dark-txt light-bg" id="username" name="username"
                           value="<?= htmlspecialchars($user->username ?? '') ?>" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label light-txt">Email</label>
                    <input type="email" class="form-control dark-txt light-bg" id="email" name="email"
                           value="<?= htmlspecialchars($user->email ?? '') ?>" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label light-txt">Password</label>
                    <input type="password" class="form-control dark-txt light-bg" id="password" name="password" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label light-txt">Confirm Password</label>
                    <input type="password" class="form-control dark-txt light-bg" id="confirm_password" name="confirm_password" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="first_name" class="form-label light-txt">First Name</label>
                    <input type="text" class="form-control dark-txt light-bg" id="first_name" name="first_name"
                           value="<?= htmlspecialchars($user->first_name ?? '') ?>" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="last_name" class="form-label light-txt">Last Name</label>
                    <input type="text" class="form-control dark-txt light-bg" id="last_name" name="last_name"
                           value="<?= htmlspecialchars($user->last_name ?? '') ?>" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="middle_name" class="form-label light-txt">Middle Name (Optional)</label>
                    <input type="text" class="form-control dark-txt light-bg" id="middle_name" name="middle_name"
                           value="<?= htmlspecialchars($user->middle_name ?? '') ?>" maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="birthdate" class="form-label light-txt">Birthdate</label>
                    <input type="date" class="form-control dark-txt light-bg" id="birthdate" name="birthdate"
                           value="<?= htmlspecialchars($user->birthdate ?? '') ?>" required maxlength="50">
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label light-txt">User Type</label>
                    <select class="form-select dark-txt light-bg" id="type" name="type" required>
                        <option value="">Select Type</option>
                        <?php foreach ($user_types as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>"
                                <?= (($user->type ?? '') === $type) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Add User</button>
                    <a href="/staff/user_list" class="btn btn-secondary btn-lg">Back to User List</a>
                </div>
            </form>
        </div>
    </div>
</section>

<?php
Logger::log('UI: On staff/users/add.php');
?>
