<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection; // Assuming this is used for Eloquent setup
use Models\User;
use Models\Customer;
use Models\Supplier;
// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure Composer autoloader is included
require_once 'vendor/autoload.php';


class LoginController extends Controller {
    public function input($key, $default = null) {
        // Already trims by default, which is good for most text inputs
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    public function login() {
        Logger::log("DEBUG: Entered LoginController@login method.");
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('login/login', $data, 'default');
    }

    /**
     * Displays the initial "Forgot Password" form to collect the user's email.
     * Accessible via /login/forgotpass
     * @return void
     */
    public function forgotpass(){
        Logger::log("FORGOTPASS: Reached method (display form).");
        $this->view('login/forgotpass', [],'default');
    }

    /**
     * Handles the POST request to send a password reset code to the user's email.
     * Accessible via /login/send_reset_code
     * @return void
     */
    public function send_reset_code() {
        Logger::log("SEND_RESET_CODE: Method called.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("SEND_RESET_CODE_ERROR: Invalid request method.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /login/forgotpass');
            exit();
        }

        $email = $this->input('email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Logger::log("SEND_RESET_CODE_FAILED: Invalid or empty email provided: " . $email);
            $_SESSION['error_message'] = 'Please enter a valid email address.';
            header('Location: /login/forgotpass');
            exit();
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            // Log this for debugging but provide a generic message to the user
            // to prevent email enumeration attacks.
            Logger::log("SEND_RESET_CODE_INFO: Password reset requested for non-existent email: " . $email);
            $_SESSION['error_message'] = 'Email does not exist in the system.';
            header('Location: /login/forgotpass');
            exit();
        }

        // Generate a random 6-digit code
        $reset_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // Set expiry time (e.g., 15 minutes from now)
        $expiry_time = time() + (15 * 60); // 15 minutes

        // Store the code and expiry in session
        $_SESSION['password_reset'] = [
            'user_id' => $user->id,
            'code' => $reset_code,
            'expiry' => $expiry_time
        ];

        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; // Gmail SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'recon21342@gmail.com'; // !!! REPLACE WITH YOUR GMAIL EMAIL !!!
            $mail->Password   = 'xtdsnpxtjgekndlu';   // !!! REPLACE WITH YOUR GMAIL APP PASSWORD !!!
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Use SMTPS (465)
            $mail->Port       = 465;

            // Recipients
            $mail->setFrom('no-reply@compims.com', 'COMP IMS Password Reset');
            $mail->addAddress($user->email, $user->first_name . ' ' . $user->last_name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'COMPUTER PARTS IMS Password Reset Code';
            $mail->Body    = "Hello {$user->first_name},<br><br>"
                           . "Your password reset code is: <strong>{$reset_code}</strong><br><br>"
                           . "This code is valid for 15 minutes. If you did not request a password reset, please ignore this email.<br><br>"
                           . "Thank you,<br>COMP IMS Team";
            $mail->AltBody = "Hello {$user->first_name},\n\nYour password reset code is: {$reset_code}\n\nThis code is valid for 15 minutes. If you did not request a password reset, please ignore this email.\n\nThank you,\nCOMP IMS Team";

            $mail->send();
            Logger::log("SEND_RESET_CODE_SUCCESS: Reset code sent to {$user->email}.");
            $_SESSION['success_message'] = 'A password reset code has been sent to your email address.';
            header('Location: /login/verify_code'); // Redirect to code verification page
            exit();
        } catch (Exception $e) {
            Logger::log("SEND_RESET_CODE_ERROR: Failed to send email to {$user->email}. Mailer Error: {$mail->ErrorInfo}. Exception: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to send reset code. Please try again later. Mailer Error: ' . $mail->ErrorInfo;
            header('Location: /login/forgotpass');
            exit();
        }
    }

    /**
     * Displays the form to verify the reset code.
     * Accessible via /login/verify_code
     * @return void
     */
    public function verify_code() {
        Logger::log("VERIFY_CODE: Method called (display form).");

        // Ensure a reset process is initiated
        if (!isset($_SESSION['password_reset']['user_id'])) {
            $_SESSION['error_message'] = 'No password reset process initiated. Please request a new code.';
            header('Location: /login/forgotpass');
            exit();
        }

        $this->view('login/verify_code', [], 'default');
    }

    /**
     * Handles the POST request to verify the submitted reset code.
     * Accessible via /login/process_verify_code
     * @return void
     */
    public function process_verify_code() {
        Logger::log("PROCESS_VERIFY_CODE: Method called.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("PROCESS_VERIFY_CODE_ERROR: Invalid request method.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /login/verify_code');
            exit();
        }

        // Ensure a reset process is initiated
        if (!isset($_SESSION['password_reset']['user_id'], $_SESSION['password_reset']['code'], $_SESSION['password_reset']['expiry'])) {
            Logger::log("PROCESS_VERIFY_CODE_FAILED: No active password reset session.");
            $_SESSION['error_message'] = 'No password reset process initiated or session expired. Please request a new code.';
            header('Location: /login/forgotpass');
            exit();
        }

        $submitted_code = $this->input('reset_code');
        $stored_code = $_SESSION['password_reset']['code'];
        $expiry_time = $_SESSION['password_reset']['expiry'];

        if (empty($submitted_code)) {
            $_SESSION['error_message'] = 'Please enter the verification code.';
            header('Location: /login/verify_code');
            exit();
        }

        if (time() > $expiry_time) {
            Logger::log("PROCESS_VERIFY_CODE_FAILED: Reset code expired for user ID: " . $_SESSION['password_reset']['user_id']);
            unset($_SESSION['password_reset']); // Clear expired session data
            $_SESSION['error_message'] = 'The verification code has expired. Please request a new code.';
            header('Location: /login/forgotpass');
            exit();
        }

        if ($submitted_code === $stored_code) {
            Logger::log("PROCESS_VERIFY_CODE_SUCCESS: Code verified for user ID: " . $_SESSION['password_reset']['user_id']);
            // Mark as verified in session to allow access to change password form
            $_SESSION['password_reset']['verified'] = true;
            $_SESSION['success_message'] = 'Code verified successfully. You can now set your new password.';
            header('Location: /login/change_password'); // Redirect to change password page
            exit();
        } else {
            Logger::log("PROCESS_VERIFY_CODE_FAILED: Incorrect code submitted for user ID: " . $_SESSION['password_reset']['user_id']);
            $_SESSION['error_message'] = 'Invalid verification code. Please try again.';
            header('Location: /login/verify_code');
            exit();
        }
    }

    /**
     * Displays the form to change the password after successful code verification.
     * Accessible via /login/change_password
     * @return void
     */
    public function change_password() {
        Logger::log("CHANGE_PASSWORD: Method called (display form).");

        // Ensure code was verified
        if (!isset($_SESSION['password_reset']['verified']) || !$_SESSION['password_reset']['verified']) {
            Logger::log("CHANGE_PASSWORD_FAILED: Direct access without verification.");
            $_SESSION['error_message'] = 'Please verify your reset code first.';
            header('Location: /login/forgotpass');
            exit();
        }

        $this->view('login/changepass', [], 'default');
    }

    /**
     * Handles the POST request to update the user's password in the database.
     * Accessible via /login/process_change_password
     * @return void
     */
    public function process_change_password() {
        Logger::log("PROCESS_CHANGE_PASSWORD: Method called.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::log("PROCESS_CHANGE_PASSWORD_ERROR: Invalid request method.");
            $_SESSION['error_message'] = 'Invalid request method.';
            header('Location: /login/change_password');
            exit();
        }

        // Ensure code was verified and user ID is available
        if (!isset($_SESSION['password_reset']['verified']) || !$_SESSION['password_reset']['verified'] || !isset($_SESSION['password_reset']['user_id'])) {
            Logger::log("PROCESS_CHANGE_PASSWORD_FAILED: Session not verified or user ID missing.");
            $_SESSION['error_message'] = 'Password reset session invalid. Please restart the process.';
            header('Location: /login/forgotpass');
            exit();
        }

        $user_id = $_SESSION['password_reset']['user_id'];
        $new_password = $this->input('new_password');
        $confirm_new_password = $this->input('confirm_new_password');

        $errors = [];
        if (empty($new_password)) $errors[] = 'New password is required.';
        if (strlen($new_password) < 8) $errors[] = 'New password must be at least 8 characters long.';
        if ($new_password !== $confirm_new_password) $errors[] = 'New password and confirm password do not match.';

        if (!empty($errors)) {
            Logger::log("PROCESS_CHANGE_PASSWORD_FAILED: Validation errors for user ID {$user_id}: " . implode(', ', $errors));
            $_SESSION['error_message'] = 'Please correct the following issues: ' . implode('<br>', $errors);
            header('Location: /login/change_password');
            exit();
        }

        $user = User::find($user_id);

        if (!$user) {
            Logger::log("PROCESS_CHANGE_PASSWORD_ERROR: User ID {$user_id} not found in DB during password change.");
            $_SESSION['error_message'] = 'An error occurred. User not found.';
            header('Location: /login/forgotpass');
            exit();
        }

        try {
            $user->password = $new_password; // The User model's setPasswordAttribute mutator will hash this
            $user->save();

            // Clear all password reset related session data after successful change
            unset($_SESSION['password_reset']);
            Logger::log("PROCESS_CHANGE_PASSWORD_SUCCESS: Password updated for user ID: {$user_id}.");
            $_SESSION['success_message'] = 'Your password has been successfully changed. Please log in with your new password.';
            header('Location: /login/login');
            exit(); // Always call exit() after a header redirect
        } catch (Exception $e) {
            Logger::log("PROCESS_CHANGE_PASSWORD_DB_ERROR: Failed to update password for user ID {$user_id}. Exception: " . $e->getMessage());
            $_SESSION['error_message'] = 'An error occurred while changing your password. Please try again.';
            header('Location: /login/change_password');
            exit();
        }
    }


    public function login_acc() {
        Logger::log("LOGIN_ACC: Method called");

        $user_username = $this->input('username');
        $user_password = $this->input('password');

        if (!$user_username || !$user_password) {

            Logger::log("LOGIN FAILED: Username and password are required.");
            $_SESSION['error_message'] = "Username and password are required";
            header('Location: /login/login');
            exit();
        }

        $user = User::where('username', $user_username)->first();
        Logger::log("INPUT: Username = $user_username");
        if (!$user) {
            Logger::log("DB: User not found for $user_username");
        }

        if (!$user || !password_verify($user_password, $user->password)) {
            Logger::log("LOGIN FAILED: Invalid credentials for $user_username");
            $_SESSION['error_message'] = "Invalid Credentials for ". $user_username;
            header('Location: /login/login');
            exit();
        }

        // Store user in session - FIX IS HERE
        // REMOVED strtolower() to match capitalization in layout checks
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['user_type'] = $user->type; // Store original capitalized type

        $_SESSION['user'] = [
            'id' => $user->id,
            'username' => $user->username,
            'type' => $user->type // Store original capitalized type
        ];


        // Assuming these methods exist in StaffController or are accessible
        // You might need to instantiate StaffController or move these to a shared service/trait
        // For now, assuming they are available in LoginController for simplicity as per previous context
        $staffController = new \Controllers\StaffController(); // Instantiate StaffController
        $first_data = $staffController->getUserInfoAndCount();
        $second_data = $staffController->getProductAndTransactionCount();

        $data = array_merge($first_data, $second_data);

        Logger::log("LOGIN SUCCESS: Redirecting to staff/dashboard");
        $_SESSION['success_message']="Successfully logged in as ".$user->username;
        header('Location: /staff/dashboard');
        exit();
    }


    public function logout()
    {
        Logger::log('LOGOUT: User attempting to log out.');

        // Check if a user session exists, primarily for logging
        if (isset($_SESSION['user']['id'])) {
            $userId = $_SESSION['user']['id'];
            Logger::log("LOGOUT_SUCCESS: User ID {$userId} logged out.");
        } else {
            Logger::log("LOGOUT_INFO: Attempted logout without an active user session.");
            $_SESSION['error_message']="Attempted to Login without an active session. Please login again.";
            header('Location: login/login');
            exit();
        }

        // 1. Unset all of the session variables.
        $_SESSION = [];

        // 2. Delete the session cookie.
        // Note: This will destroy the session, and not just the session data!
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // 3. Destroy the session.
        session_destroy();

        // 4. Redirect to the login page
        $_SESSION['success_message']="Successfully logged out.";
        header('Location: /login/login'); // Redirect to your login route
        exit(); // Important to exit after a header redirect
    }

    // Helper methods for dashboard data - moved from StaffController for direct use here
    // If these methods are truly only for the dashboard, consider keeping them in StaffController
    // and passing data via a redirect or a shared service.
    // For this example, I'm placing them here to make login_acc self-contained for dashboard data.


}
