<?php
/* Monolog logger */
// https://github.com/Seldaek/monolog

$this['logger'] = $this->share(function(){
    
    $monolog = new \Monolog\Logger( $this->setting('name') );
    $settings = $this->setting('ICP')['logger'];

    foreach ($settings['handlers'] as $handel){
        switch ($handel['type']){
            case 'streamHandler':
                if (isset($handel['level'])){
                    $streamHandlerLevel = $handel['level'];
                } else {
                    $streamHandlerLevel = \Monolog\Logger::WARNING;
                }

                $streamHandler = new \Monolog\Handler\StreamHandler(APP_ROOT.'/'.$handel['location'].date("ymd").'.log', $streamHandlerLevel);
                
                if (isset($handel['formatter'])){
                    switch ($handel['formatter']){
                        case 'JsonFormatter':
                            $formatter = new \Monolog\Formatter\JsonFormatter();
                            $streamHandler->setFormatter($formatter);
                        break;
                        case 'LineFormatter':
                            $formatter = new \Monolog\Formatter\LineFormatter();
                            $streamHandler->setFormatter($formatter);
                        break;
                    }
                }
                
                if (isset($handel['handler'])){
                    switch ($handel['handler']){
                        case 'FingersCrossedHandler':
                            $streamHandler = new \Monolog\Handler\FingersCrossedHandler($streamHandler, $streamHandlerLevel);
                        break;
                    }
                }

                $monolog->pushHandler($streamHandler);
            break;
            case 'pushoverHandler':
                $pushoverHandler = new \Monolog\Handler\PushoverHandler($handel['token'], $handel['user'], $handel['title'], $handel['level']);
                $monolog->pushHandler($pushoverHandler);
            break;
            case 'FirePHPHandler':
                $fireHandler = new \Monolog\Handler\FirePHPHandler();
                $monolog->pushHandler($fireHandler);
            break;
            case 'ChromePHPHandler':
                $chromeHandler = new \Monolog\Handler\ChromePHPHandler();
                $formatter = new\Monolog\Formatter\ChromePHPFormatter();
                $chromeHandler->setFormatter($formatter);
                $monolog->pushHandler($chromeHandler);
            break;
        }
    }

    return $monolog;
});
