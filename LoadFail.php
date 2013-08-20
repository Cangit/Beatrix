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
                    $this->prepareAndSend($return);
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

    private function htmlheader()
    {
        ob_start();
?>
<!doctype html>
<head>
    <meta charset="UTF-8">
    <title><?=$this->code?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style type="text/css" media="screen">
        html{
            background: #f2f2f2;
        }
        body{
            margin: 0;
            background: #fff;
        }
        pre{
            background: #f2f2f2;
            margin: 0;
            padding: 20px 20px;
            overflow: none;
        }
        h1{
            display: inline-block;
            font-family: 'Arial';
            margin: 20px 20px;
            padding: 0 5px;
            background: #fff;
        }
        .envTagdev, .envTagtest, .envTagprod{
            position: absolute;
            top: 0;
            right: 20px;
            padding: 3px 10px;
            font-family: Arial;
            text-transform: uppercase;
            color: #fff;
        }
        .envTagdev{
            background: #61aa79;
        }
        .envTagtest{
            background: #2b93a7;
        }
        .envTagprod{
            background: #3c5b78;
        }
        .error{
            background: #c1392b;
            color: #fff;
        }
    </style>
</head>
<body>
<?php
        $content = ob_get_clean();
        return $content;

    }

    private function notfounderror()
    {
        ob_start();
        echo $this->htmlheader();
?>
    <div class="envTag<?=$this->app->setting('env')?>"><?=$this->app->setting('env')?> environment</div>
    <h1 class="error">Resource Not Found</h1>
    <pre>
<?php
    foreach($this->debug as $val){
        if (is_string($val)){
            echo $val."\n";
        } elseif(is_array($val)) {
            print_r($val);
        }
    }
?>

==========

Executed in <?=number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4)?>s</pre>
<?php
        $content = ob_get_clean();
        return $content;
    }

    private function internalError()
    {
        ob_start();
        echo $this->htmlheader();
?>
    <div class="envTag<?=$this->app->setting('env')?>"><?=$this->app->setting('env')?> environment</div>
    <h1 class="error">Internal Server Error</h1>
    <pre>
<?php
    foreach($this->debug as $val){
        if (is_string($val)){
            echo $val."\n";
        } elseif(is_array($val)) {
            print_r($val);
        }
    }
?>

==========

Executed in <?=number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4)?>s</pre>
<?php
        $content = ob_get_clean();
        return $content;
    }

}

