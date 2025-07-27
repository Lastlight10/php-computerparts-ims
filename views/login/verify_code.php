<?php
// Display success or error message if available
$display_success_message = $_SESSION['success_message'] ?? null;
$display_error_message = $_SESSION['error_message'] ?? null;

unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card p-4 shadow-lg light-bg-card" style="width: 100%; max-width: 400px; border-radius: 15px;">
        <h2 class="text-center mb-4 dark-txt">Verify Reset Code</h2>

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

        <form action="/login/process_verify_code" method="POST">
            <div class="mb-3">
                <label for="reset_code" class="form-label dark-txt">Enter the 6-digit code sent to your email:</label>
                <input type="text" class="form-control light-bg dark-txt" id="reset_code" name="reset_code" required maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/(\..*?)\..*/g, '$1').replace(/^0[^.]/, '0');" title="Please enter the 6-digit code.">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">Verify Code</button>
                <a href="/login/forgotpass" class="btn btn-secondary btn-lg">Resend Code / Go Back</a>
            </div>
        </form>
    </div>
</div>
<?
use App\Core\Logger;
Logger::log("UI: On login/verify_code.php");
?>