<?php
namespace Controllers;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Connection;
use Models\User;
require_once 'vendor/autoload.php';


class LoginController extends Controller {
    public function input($key, $default = null) {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    public function login() {
        Logger::log("DEBUG: Entered LoginController@login method."); // Keep this for log
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('login/login', $data, 'default'); 
    }

    public function forgotpass(){
        Logger::log("FORGOTPASS: Reached method.");
        $this->view('login/forgotpass', [],'default');
    }

    public function login_acc() {
        Logger::log("LOGIN_ACC: Method called");


        $user_username = $this->input('username');
        $user_password = $this->input('password');

        if (!$user_username || !$user_password) {
            $this->view('login/login', ['error' => 'Username and password are required.']);
            Logger::log("LOGIN FAILED: Username and password are required.");
            return;
        }

        $user = User::where('username', $user_username)->first(); 
        Logger::log("INPUT: Username = $user_username");
        if (!$user) {
            Logger::log("DB: User not found for $user_username");
        }

        if (!$user || !password_verify($user_password, $user->password)) {
            $this->view('login/login', ['error' => 'Invalid credentials.'],'default');
            Logger::log("LOGIN FAILED: Invalid credentials for $user_username");
            return;   
        }
        
        // Store user in session
        $_SESSION['user'] = [
            'id' => $user->id,
            'username' => $user->username,
            'type' => strtolower($user->type)
        ];
        $first_data = $this->getUserInfoAndCount();
        $second_data = $this->getProductAndTransactionCount();

        $data = array_merge($first_data, $second_data);

        Logger::log("LOGIN SUCCESS: Redirecting to staff/dashboard");
        $this->view('staff/dashboard', $data,'staff');
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
        // Since you have a view method, you might redirect differently.
        // For a full redirect, you would use header('Location: /login') and exit.
        // If your 'view' method can handle redirects, use it. Otherwise,
        // a direct header redirect is more common for logout.

        // Option A: Using header redirect (most common for logout)
        header('Location: /login/login'); // Redirect to your login route
        exit(); // Important to exit after a header redirect

        // Option B: If your view() method can handle a special redirect template
        // $this->view('login/login', ['success_message' => 'You have been logged out successfully.']);
        // The above would typically be less common for logout as it implies rendering a view,
        // rather than a full HTTP redirect. Sticking with Option A is usually best.
    }


}
