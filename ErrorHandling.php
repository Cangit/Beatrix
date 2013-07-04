<?php

namespace Cangit\Beatrix;

class ErrorHandling
{

    private static $logger;

    public static function construct($logger){
        self::$logger = $logger;
    }

    public static function errorHandler($num, $str, $file, $line)
    {
        throw new \ErrorException($str, $num, 0, $file, $line);
        return true; // Supresses PHP internal error handler
    }

    public static function exceptionHandler($exception)
    {
        $file = str_replace( APP_ROOT , "", $exception->getFile() );

        switch ($exception->getCode()){
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $type = 'error';
            break;
            case E_WARNING:
            case E_USER_WARNING:
                $type = 'warning';
            break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $type = 'notice';
            break;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $type = 'deprecated';
            break;
            default:
                $type = 'unknown';
        }

        $error = [
            'code' => $exception->getCode(),
            'type' => $type,
            'msg' => $exception->getMessage(),
            'file' => $file,
            'line' => $exception->getLine(),
            'trace' => $exception->getTrace()
        ];

        if (method_exists(self::$logger, $type)){
            self::$logger->$type('Exception', $error);
        } else {
            self::$logger->error('Exception', $error);
        }
        
        if (is_readable('app/static/exception.php')){
            require 'app/static/exception.php';
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            exit('Something broke, we are working to get it fixed. Please try to reload your browser.');
        }
    }

}
