<?php
class Router {
    public function route($url) {
        try {
            $parts = explode('/', trim($url, '/'));
            $controllerName = ucfirst($parts[0] ?? 'login') . 'Controller';
            $method = $parts[1] ?? 'login';
            
            // Validate controller and method names (security)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $parts[0] ?? 'login') ||
                !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $method)) {
                throw new Exception("Invalid controller or method name");
            }
            
            if (class_exists($controllerName) && method_exists($controllerName, $method)) {
                $controller = new $controllerName;
                call_user_func_array([$controller, $method], array_slice($parts, 2));
                Logger::log("ROUTING: $url => $controllerName@$method");
            } else {
                $this->handle404($url);
            }
        } catch (Exception $e) {
            Logger::log("ROUTING ERROR: " . $e->getMessage());
            $this->handle404($url);
        }
    }
    
    private function handle404($url) {
        http_response_code(404);
        echo "404 Not Found";
        Logger::log("ROUTING ERROR: 404 Not Found for $url");
    }
}