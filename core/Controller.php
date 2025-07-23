<?php
class Controller {
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
        Logger::log("RENDERING ($viewPath): $viewPath with data: " . json_encode($data));

        include $viewPath;
    }
}
