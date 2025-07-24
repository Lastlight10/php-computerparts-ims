<?php
class Router {
    public function route($url) {
        try {
            $parts = explode('/', trim($url, '/'));
            $count = count($parts);

            if ($count === 0 || $parts[0] === '') {
                $controllerName = 'LoginController';
                $method = 'login';
                $params = [];
            } elseif ($count === 1) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = 'index';
                $params = [];
            } elseif ($count === 2) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = $parts[1];
                $params = [];
            } else {
                // For nested controllers like staff/manager/view
                $controllerName = ucfirst($parts[0]) . ucfirst($parts[1]) . 'Controller';
                $method = $parts[2] ?? 'index';
                $params = array_slice($parts, 3);
            }

            Logger::log("ROUTING: $url => $controllerName@$method");

            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                if (method_exists($controller, $method)) {
                    return call_user_func_array([$controller, $method], $params);
                } else {
                    throw new Exception("Method $method not found in $controllerName");
                }
            } else {
                throw new Exception("Controller $controllerName not found");
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
