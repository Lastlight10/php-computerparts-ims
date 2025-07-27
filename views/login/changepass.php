<?php
// Display success or error message if available
$display_success_message = $_SESSION['success_message'] ?? null;
$display_error_message = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4 shadow-lg light-bg-card" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <h2 class="text-center mb-4 dark-txt">Set New Password</h2>

        <?php if ($display_success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($display_success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($display_error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($display_error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="/login/process_change_password" method="POST">
            <div class="mb-3">
                <label for="new_password" class="form-label dark-txt">New Password</label>
                <input type="password" class="form-control light-bg dark-txt" id="new_password" name="new_password" required minlength="8">
            </div>
            <div class="mb-3">
                <label for="confirm_new_password" class="form-label dark-txt">Confirm New Password</label>
                <input type="password" class="form-control light-bg dark-txt" id="confirm_new_password" name="confirm_new_password" required minlength="8">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Change Password</button>
            </div>
        </form>
    </div>
</div>
<?php
use App\Core\Logger;

Logger::log("UI: On login/changepass.php");
?>