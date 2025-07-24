<?php
use Models\User;
class Controller {
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    protected function view($view, $data = []) {
        if (empty($view)) {
            Logger::log("VIEW ERROR: View name not provided.");
            die("View name not provided.");
            
        }

        extract($data);

        $viewPath = "views/$view.php";
        if (!file_exists($viewPath)) {
            die("View file not found: $viewPath");
        }
        Logger::log("RENDERING ($viewPath): $viewPath ");

        include $viewPath;
    }

    protected function getUserInfoAndCount() {
        $user = $_SESSION['user'] ?? null;
        return [
            'username' => $user['username'] ?? 'Unknown',
            'count' => User::count('id')
        ];
    }
}
