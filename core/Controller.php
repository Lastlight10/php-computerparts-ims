<?php
namespace App\Core;
use Models\User;
use Models\Product;
use Models\Transaction;
use Models\ProductInstance;
class Controller {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    // App/Core/Controller.php
    protected function view($name, $data = [], $layout = 'staff') // Default to 'staff' layout
    {
        extract($data);

        $viewPath =  "views/{$name}.php";

        if (!file_exists($viewPath)) {
            Logger::log("VIEW ERROR: View file not found: " . $viewPath);
            throw new \Exception("View not found: {$name}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        // Dynamically determine the layout file
        $layoutFileName = ($layout === 'staff') ? 'staff/staff_layout.php' : 'login/layout.php'; // Or add more conditions for other layouts
        $layoutPath = "views/{$layoutFileName}";

        if (!file_exists($layoutPath)) {
            Logger::log("VIEW ERROR: Layout file not found: " . $layoutPath);
            throw new \Exception("Layout not found: {$layoutFileName} at path: " . $layoutPath);
        }
        else{
            Logger::log(message: "VIEW LAYOUT: Layout file found: " . $layoutPath);
        }

        require_once $layoutPath;
    }

    protected function getUserInfoAndCount() {
        $user = $_SESSION['user'] ?? null;
        return [
            'username' => $user['username'] ?? 'Unknown',
            'count' => User::count('id')
        ];
    }
    protected function getProductAndTransactionCount() {
        $user = $_SESSION['user'] ?? null;
        return [
            'products_count'=>Product::count('id'),
            'items_count'=>ProductInstance::count('id'),
            'transaction_count' => Transaction::count('id')
        ];
    }
    protected function input(string $key, $default = null)
    {
        // Check POST first
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        // Then check GET
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        // Return default if not found in either
        return $default;
    }    public function update_user_password()
    {
        Logger::log('Attempting to update user password via self-edit form.');

        // 1. Basic Security & Session Check
        if (!isset($_SESSION['user']['id'])) {
            Logger::log('PASSWORD_UPDATE_FAILED: No user ID in session. Redirecting to login.');
            $this->view('login/login', ['error' => 'Your session has expired. Please log in again.']);
            return;
        }

        $currentUserId = $_SESSION['user']['id'];

        // 2. Retrieve Input Data
        $submittedUserId    = $this->input('user_id'); // Hidden field from form
        $current_password   = $this->input('current_password');
        $new_password       = $this->input('new_password');
        $confirm_password   = $this->input('confirm_password');

        // 3. Authorization Check: Ensure the user is changing their own password
        if ($currentUserId != $submittedUserId) {
            Logger::log("SECURITY_ALERT: User ID $currentUserId attempted to change password for account ID $submittedUserId.");
            $this->view('staff/edit_user_account', [
                'error' => 'You are not authorized to perform this action.',
                'user_account' => User::find($currentUserId) // Re-fetch current user data for display
            ]);
            return;
        }

        // 4. Retrieve the User Model instance
        $user = User::find($currentUserId);

        if (!$user) {
            Logger::log("PASSWORD_UPDATE_FAILED: User ID $currentUserId not found in database for password update.");
            session_destroy();
            $this->view('login/login', ['error' => 'User account not found. Please log in again.']);
            return;
        }

        // 5. Input Validation for Passwords
        $errors = [];

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $errors[] = 'All password fields are required.';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirm password do not match.';
        }

        // Verify the current password against the hashed password in the database
        // IMPORTANT: The $user->password contains the HASHED password from the DB.
        if (!password_verify($current_password, $user->password)) {
            $errors[] = 'Current password is incorrect.';
        }

        // Optional: Add new password complexity rules here
        if (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        // You might add checks for uppercase, lowercase, numbers, symbols, etc.
        // if (!preg_match('/[A-Z]/', $new_password)) { $errors[] = 'Password needs an uppercase letter.'; }

        if (!empty($errors)) {
            Logger::log("PASSWORD_UPDATE_FAILED: Validation errors for User ID $currentUserId: " . implode(', ', $errors));
            $this->view('staff/edit_user_account', [
                'error' => implode('<br>', $errors),
                'user_account' => $user // Pass the current user object back to re-populate the form
            ]);
            return;
        }

        // 6. Update Password
        // Assigning the new_password will trigger the setPasswordAttribute mutator in the User model,
        // which will automatically hash the password before saving.
        $user->password = $new_password;

        try {
            // 7. Save Changes
            $user->save();
            Logger::log("PASSWORD_UPDATE_SUCCESS: User ID $currentUserId password updated successfully.");
            $this->view('staff/edit_user_account', [
                'success_message' => 'Your password has been updated successfully!',
                'user_account' => $user // Pass the updated user object back
            ]);
        } catch (\Exception $e) {
            Logger::log("PASSWORD_UPDATE_DB_ERROR: User ID $currentUserId - " . $e->getMessage());
            $this->view('staff/edit_user_account', [
                'error' => 'An error occurred while updating your password. Please try again.',
                'user_account' => $user // Pass the user object back
            ]);
        }
    }

    public function flash($key){
        if (!empty($_SESSION[$key])){
            $msg = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $msg;
        }
        return null;
    }
}
