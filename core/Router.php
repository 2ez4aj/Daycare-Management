<?php

class Router {
    protected $controller = 'HomeController';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        // This constructor can be used for setting up routes in the future
    }

    /**
     * Parses the URL from the 'url' GET parameter into a controller, method, and parameters.
     */
    protected function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }

    /**
     * Dispatches the request to the appropriate controller and method.
     */
    public function dispatch() {
        $url = $this->parseUrl();

        // Look for the controller file
        if (!empty($url[0])) {
            $controllerName = ucwords($url[0]) . 'Controller';
            if (file_exists(BASE_PATH . '/app/controllers/' . $controllerName . '.php')) {
                $this->controller = $controllerName;
                unset($url[0]);
            }
        }

        // Require and instantiate the controller
        require_once BASE_PATH . '/app/controllers/' . $this->controller . '.php';
        $this->controller = new $this->controller;

        // Look for the method in the controller
        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        // Get the remaining URL parts as parameters
        $this->params = $url ? array_values($url) : [];

        // Call the controller method with the parameters
        call_user_func_array([$this->controller, $this->method], $this->params);
    }
}
