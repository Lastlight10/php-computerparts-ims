<?php
// Router.php
namespace App\Core;

use App\Core\Logger; // Make sure Logger is used

class Router {
    public function route($url) {
        try {
            $parts = explode('/', trim($url, '/'));
            $count = count($parts);

            $controllerName = ''; // Initialize
            $method = '';         // Initialize
            $params = [];         // Initialize

            if ($count === 0 || $parts[0] === '') {
                $controllerName = 'LoginController';
                $method = 'login';
                // Params remain empty
            } elseif ($count === 1) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = 'index';
            } elseif ($count === 2) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = $parts[1];
            } else { // This block handles /staff/brands/add, /staff/products/edit/123, etc.
                $area = ucfirst($parts[0]);
                $resourcePlural = $parts[1];
                $action = $parts[2] ?? 'index';
                $params = array_slice($parts, 3);

                $singularResource = $resourcePlural;
                if (substr($resourcePlural, -3) === 'ies') {
                    $singularResource = substr($resourcePlural, 0, -3) . 'y';
                } elseif (substr($resourcePlural, -1) === 's') {
                    $singularResource = rtrim($resourcePlural, 's');
                }
                $controllerName = $area . ucfirst($singularResource) . 'Controller';
                $method = $action;
            }

            // --- THIS IS THE MISSING LOGIC THAT SHOULD BE OUTSIDE THE IF/ELSE CHAIN ---
            // It applies to ALL successful route matches, not just the 'else' block
            Logger::log("ROUTING: Attempting to call $controllerName@$method for URL: $url");

            // Construct the fully qualified controller name
            $controllerFQN = 'Controllers\\' . $controllerName;

            if (!class_exists($controllerFQN)) {
                Logger::log("ROUTING ERROR: Controller class '$controllerFQN' not found.");
                throw new \Exception("Controller $controllerName not found.");
            }

            $controller = new $controllerFQN();

            if (!method_exists($controller, $method)) {
                Logger::log("ROUTING ERROR: Method '$method' not found in controller '$controllerName'.");
                throw new \Exception("Method $method not found in $controllerName.");
            }

            // Call the controller method with parameters
            return call_user_func_array([$controller, $method], $params);

        } catch (\Exception $e) {
            Logger::log("ROUTING EXCEPTION: " . $e->getMessage());
            $this->handle404($url);
        }
    }

    private function handle404($url) {
        http_response_code(404);
        echo "<h1>404 Not Found</h1>";
        echo "<p>The page you requested ($url) could not be found.</p>";
        Logger::log("ROUTING ERROR: 404 Not Found for $url");
    }
}