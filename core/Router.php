<?php

class Router {
    public function route($url) {
        
        $parts = explode('/', trim($url, '/'));
        $controllerName = ucfirst($parts[0] ?? 'login') . 'Controller';
        $method = $parts[1] ?? 'login';

        if (class_exists($controllerName) && method_exists($controllerName, $method)) {
            $controller = new $controllerName;
            call_user_func_array([$controller, $method], array_slice($parts, 2));
            Logger::log("ROUTING (Router.php): $url => $controllerName@$method");
        } else {
            echo "404 Not Found";
            Logger::log("ROUTING ERROR: 404 Not Found");
        }
    }
}
