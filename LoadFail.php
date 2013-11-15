<?php

namespace Cangit\Beatrix;

class LoadFail
{
    private $code;
    private $app;
    private $debug = [];

    function __construct($code, $app)
    {
        $this->code = $code;
        $this->app = $app;
    }

    public function run()
    {
        $this->resource($this->code, $this->app['request']);
    }

    public function debug($debug)
    {
        if (is_string($debug) OR is_array($debug)){
            $this->debug[] = $debug;
        } elseif (is_object($debug)) {
            ob_start();
            var_dump($debug);
            $debug = ob_get_flush();
            $this->debug[] = $debug;
        }
    }

    public function resource($code, $request)
    {
        $acceptHeaders = $request->headers->get('accept');

        $hit = mb_strpos($acceptHeaders, 'text/html');

        if ($hit === false){
            if (false === mb_strpos($acceptHeaders, 'application/json')){
                $this->respondDefault($code);
            } else {
                $this->respondJson($code);
            }
        } else {
            $this->respondDefault($code);
        }
    }

    private function respondDefault($code)
    {
        switch ($code){
            case '404':
                $defaultPages = $this->app->setting('defaultPages', false);
            break;
            case '500':
                $defaultPages = $this->app->setting('defaultPages', false);
            break;
            default:
                $defaultPages = $this->app->setting('defaultPages', false);
                $code = 'default';
            break;
        }

        if (!is_array($defaultPages)) {
            
            if ($code == 'default') {
                $code = 500;
            }

            $response = $this->app->response($code, $code);
            $this->app->prepareAndSend($response);
        } else {

            if (isset($defaultPages[$code])) {

                $classToLoad = $defaultPages[$code];
                $obj = new $classToLoad();
                $return = $obj->get($this->app);

                if (is_object($return)) {
                    $this->app->prepareAndSend($return);
                }

            } else {

                if ($code == 'default') {
                    $code = 500;
                }

                $response = $this->app->response($code, $code);
                $this->app->prepareAndSend($response);
            }
        }
    }

    private function respondJson($code)
    {
        header('Content-Type: application/json; charset=UTF-8');
        switch ($code) {
            case '404':
                header("HTTP/1.1 404 Page Not Found");
                exit (json_encode('Resource not found'));
            break;
            case '500':
            default:
                header("HTTP/1.1 500 Internal Server Error");
                exit (json_encode('Internal Server Error'));
            break;
        }
    }

}
