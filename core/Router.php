<?php
// Router.php
namespace App\Core;

use App\Core\Logger;

class Router {
    public function route($url) {
        try {
            $parts = explode('/', trim($url, '/'));
            $count = count($parts);

            $controllerName = '';
            $method = '';
            $params = [];

            if ($count === 0 || $parts[0] === '') {
                $controllerName = 'LoginController';
                $method = 'login';
            } elseif ($count === 1) {
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = 'index';
            } elseif ($count === 2) {
                // Example: /staff/index (assuming 'staff' is a resource, 'index' is the method)
                // or /users/profile
                $controllerName = ucfirst($parts[0]) . 'Controller';
                $method = $parts[1];
            } else { // This block handles /staff/brands/add, /staff/products/edit/123, /staff/transaction_items/add/1 etc.
                $area = ucfirst($parts[0]);         // e.g., 'Staff'
                $resourcePlural = $parts[1];        // e.g., 'transactions'
                $action = $parts[2] ?? 'index';     // e.g., 'print'
                $params = array_slice($parts, 3);   // e.g., ['21']

                $singularResource = $resourcePlural;

                // Simple plural to singular for 's' and 'ies'
                if (substr($resourcePlural, -3) === 'ies') {
                    $singularResource = substr($resourcePlural, 0, -3) . 'y';
                } elseif (substr($resourcePlural, -1) === 's') {
                    // This handles most cases but be careful with words ending in 's' naturally
                    // For 'transaction_items', it correctly becomes 'transaction_item'
                    $singularResource = rtrim($resourcePlural, 's');
                }
                
                // Convert snake_case to PascalCase for the resource name
                // 'transaction_item' -> 'TransactionItem'
                $pascalCaseResource = str_replace('_', '', ucwords($singularResource, '_'));
                
                // Construct the full controller name
                $controllerName = $area . $pascalCaseResource . 'Controller';
                
                // --- START OF NEW LOGIC FOR SPECIFIC ACTION MAPPING ---
                // If the resource is 'transactions' and the action is 'print',
                // map it to the 'printTransaction' method in the controller.
                if ($resourcePlural === 'transactions') {
                    if ($action === 'print') {
                        $method = 'printTransaction'; // For single transaction PDF
                    } elseif ($action === 'print_list') {
                        $method = 'printTransactionsList'; // For list of transactions PDF
                    } elseif ($action === 'sales_report') {
                        $method = 'printSalesReport'; // For list of sales report PDF
                    } else {
                        $method = $action; // Default mapping for other actions
                    }
                } elseif ($resourcePlural === 'products') {
                    if ($action === 'print') { // ADD THIS NEW CONDITION FOR SINGLE PRODUCT PRINT
                        $method = 'printProductDetails'; 
                    } elseif ($action === 'print_list') {
                        $method = 'printProductsList'; // For list of products PDF
                    } else {
                        $method = $action; // Default mapping for other actions
                    }
                }
                else {
                    $method = $action; // Default mapping for other resources
                }
                // --- END OF NEW LOGIC ---
            }

            Logger::log("ROUTING: Attempting to call $controllerName@$method for URL: $url");

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
