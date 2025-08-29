
<div class="container">
        <h1>Edit My Account</h1>
        <?php
          if (isset($_SESSION['success_message'])) {
              echo '
              <div class="alert alert-success alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['success_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['success_message']); // fix: previously unsetting error instead
          }

          if (isset($_SESSION['error_message'])) {
              echo '
              <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['error_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['error_message']);
          }
          if (isset($_SESSION['warning_message'])) {
              echo '
              <div class="alert alert-warning alert-dismissible fade show" role="alert">
                  ' . htmlspecialchars($_SESSION['warning_message']) . '
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
              unset($_SESSION['warning_message']);
          }
          ?>

        <h2>General Information</h2>
        <form action="/staff/update_user_account" method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_account->id); ?>">

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user_account->username); ?>" readonly>
                <small>Username cannot be changed.</small>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_account->email); ?>" required maxlength="30">
            </div>

            <div class="form-group">
                <label for="first_name">First Name:</label>
                <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user_account->first_name); ?>" required maxlength="30">
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name (Optional):</label>
                <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user_account->middle_name ?? ''); ?>" maxlength="30">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user_account->last_name); ?>" required maxlength="30">
            </div>

            <div class="form-group">
                <label for="birthdate">Birthdate:</label>
                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($user_account->birthdate ? $user_account->birthdate->format('Y-m-d') : ''); ?>">
                <small>Format: YYYY-MM-DD</small>
            </div>

            <div class="form-group">
                <label for="type">User Type:</label>
                <input type="text" id="type" name="type" value="<?php echo htmlspecialchars(ucfirst($user_account->type)); ?>" readonly>
                <small>User type cannot be changed here (only by Admin).</small>
            </div>

            <div class="form-group">
                <label>Account Created:</label>
                <input type="text" value="<?php echo htmlspecialchars($user_account->created_at ? $user_account->created_at->format('Y-m-d H:i:s') : 'N/A'); ?>" readonly>
            </div>

            <div class="form-group">
                <button type="submit"
                    onclick="return confirm('Are you sure you want update your account details?');"
                >Save General Information</button>
            </div>
        </form>

        <hr style="margin: 40px 0;">

        <h2>Change Password</h2>
        <form action="/staff/update_user_password" method="POST">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_account->id); ?>" maxlength="30">

            <div class="form-group">
                <label for="current_password">Current Password:</label>
                <input type="password" id="current_password" name="current_password" required maxlength="30">
            </div>

            <div class="form-group">
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required maxlength="30">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required maxlength="30">
            </div>

            <div class="form-group">
                <button onclick= "return confirm('Are you sure you want to change your password?')" type="submit">Change Password</button>
            </div>
        </form>
    </div>
<?php 
use App\Core\Logger;
Logger::log('UI: On edit_user_account.php')
?>