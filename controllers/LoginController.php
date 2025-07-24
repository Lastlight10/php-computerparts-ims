<?php
use Models\User;
require_once 'vendor/autoload.php';
require_once 'core/Logger.php';
require_once 'core/Connection.php';

class LoginController extends Controller {
    public function input($key, $default = null) {
        return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    public function login() {
        $data = ['title' => 'Welcome', 'message' => 'Hello from MVC!'];
        $this->view('login/login', $data);
    }

    public function forgotpass(){
        Logger::log("FORGOTPASS: Reached method.");
        $this->view('login/forgotpass');
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
            $this->view('login/login', ['error' => 'Invalid credentials.']);
            Logger::log("LOGIN FAILED: Invalid credentials for $user_username");
            return;   
        }
        
        // Store user in session
        $_SESSION['user'] = [
            'id' => $user->id,
            'username' => $user->username,
            'type' => strtolower($user->type)
        ];
        
        Logger::log("LOGIN SUCCESS: Redirecting to staff/dashboard");
        $this->view('staff/dashboard', $this->getUserInfoAndCount());
    }


}
