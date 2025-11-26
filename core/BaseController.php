<?php

class BaseController {

    /**
     * Loads a view file and passes data to it.
     *
     * @param string $view The name of the view file (without .php extension).
     * @param array $data Data to be extracted into variables for the view.
     */
    protected function view($view, $data = [], $layout = null) {
        // Extract the data array into individual variables for the view and layout
        extract($data);

        $viewPath = BASE_PATH . '/app/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            die('View "' . $view . '" not found!');
        }

        // Buffer the view content
        ob_start();
        require_once $viewPath;
        $content = ob_get_clean();

        // If a layout is specified, buffer the layout with the view's content
        if ($layout) {
            $layoutPath = BASE_PATH . '/app/views/layouts/' . $layout . '.php';
            if (!file_exists($layoutPath)) {
                die('Layout "' . $layout . '" not found!');
            }
            
            ob_start();
            require_once $layoutPath;
            $final_content = ob_get_clean();
            echo $final_content;
        } else {
            // Otherwise, just echo the view's content
            echo $content;
        }
    }
}
