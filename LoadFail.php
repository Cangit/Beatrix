<?php

namespace Cangit\Beatrix;

class LoadFail
{

    function __construct($code, $app, $reason=null)
    {
        $this->app = $app;
        $this->resource($code, $this->app['request']);
        $this->reason = $reason;
    }

    public function resource($code, $request)
    {
        $acceptHeaders = $request->headers->get('accept');

        $hit = mb_strpos($acceptHeaders, 'text/html');

        if ($hit === false){
            if (false === mb_strpos($acceptHeaders, 'application/json')){
                $this->respondhtml($code);
            } else {
                $this->respondjson($code);
            }
        } else {
            $this->respondhtml($code);
        }
    }

    private function respondhtml($code)
    {
        switch ($code){
            case '404':
                header("HTTP/1.1 404 Page Not Found");
                if($this->app->setting('env') == 'dev'){
                    require WEB_ROOT."/app/static/404dev.php";
                } else {
                    require WEB_ROOT."/app/static/400.php";
                }
                exit ();
            break;
            case '500':
                header("HTTP/1.1 500 Internal Server Error");
                if($this->app->setting('env') == 'dev'){
                    require WEB_ROOT."/app/static/500dev.php";
                } else {
                    require WEB_ROOT."/app/static/500.php";
                }
                exit ();
            break;
        }
    }

    private function respondjson($code)
    {
        header('Content-Type: application/json; charset=UTF-8');
        switch ($code){
            case '404':
                header("HTTP/1.1 404 Page Not Found");
                exit (json_encode('Resource not found'));
            break;
            case '500':
                header("HTTP/1.1 500 Internal Server Error");
                exit (json_encode('Internal Server Error'));
            break;
        }
    }
}
