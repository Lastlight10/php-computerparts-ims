<?php
use App\Core\Logger;
// Display success or error message if available
$display_success_message = $_SESSION['success_message'] ?? null;
$display_error_message = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4 shadow-lg light-bg-card" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <h2 class="text-center mb-4 dark-txt">Forgot Password</h2>

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

        <form action="/login/send_reset_code" method="POST">
            <div class="mb-3">
                <label for="email" class="form-label dark-txt">Enter your email address:</label>
                <input type="email" class="form-control light-bg dark-txt" id="email" name="email" required placeholder="your.email@example.com" maxlength="30">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Send Reset Code</button>
                <a href="/login/login" class="btn btn-secondary btn-lg">Back to Login</a>
            </div>
        </form>
    </div>
</div>
<? 
Logger::log("UI: On login/forgotpass.php");
?>