<?php

namespace Cangit\Beatrix;

class Application extends Pimple
{

    private $settings;
    private $Controller;

    public function __construct()
    {
        parent::__construct();

        /* Symfony UniversalClassLoader */
        // http://symfony.com/doc/current/components/class_loader.html#usage
        $this['classLoader'] = $this->share( function(){
            return new \Symfony\Component\ClassLoader\UniversalClassLoader('beatrixClassLoader'); 
        });

        /* Symfony HttpFoundation Request object */
        // http://symfony.com/doc/current/components/http_foundation/introduction.html#accessing-request-data
        $this['request'] = $this->share( function(){
            return \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        });

        /* Monolog logger */
        // https://github.com/Seldaek/monolog
        $this['logger'] = $this->share(function(){
            
            $monolog = new \Monolog\Logger( $this->setting('name') );
            $loggerSettings = $this->setting('logger');
            
            if (isset($loggerSettings['streamHandler']['level'])){
                $streamHandlerLevel = $loggerSettings['streamHandler']['level'];
            } else {
                $streamHandlerLevel = \Monolog\Logger::WARNING;
            }

            switch ($this->setting('env')){
                case 'prod':
                case 'test':
                    if (isset($loggerSettings['pushoverHandler'])){
                        foreach ( $loggerSettings['pushoverHandler'] as $handler ) {
                            $pushoverHandler = new \Monolog\Handler\PushoverHandler($handler['token'], $handler['user'], $handler['title'], $handler['level']);
                            $monolog->pushHandler($pushoverHandler);
                        }
                    }
                
                    $streamHandler = new \Monolog\Handler\StreamHandler(WEB_ROOT.'/app/log/debug/'.date("ymd").'.log');
                    $formatter = new \Monolog\Formatter\JsonFormatter();
                    $streamHandler->setFormatter($formatter);
                    
                    $streamHandler = new \Monolog\Handler\FingersCrossedHandler($streamHandler, $streamHandlerLevel);
                    $monolog->pushHandler($streamHandler);

                break;
                case 'dev':
                    $streamHandler = new \Monolog\Handler\StreamHandler(WEB_ROOT.'/app/log/debug/'.date("ymd").'.log');
                    $formatter = new \Monolog\Formatter\JsonFormatter();
                    $streamHandler->setFormatter($formatter);
                    $streamHandler = new \Monolog\Handler\FingersCrossedHandler($streamHandler, $streamHandlerLevel);
                    $monolog->pushHandler($streamHandler);

                    $fireHandler = new \Monolog\Handler\FirePHPHandler();
                    $monolog->pushHandler($fireHandler);

                    $chromeHandler = new \Monolog\Handler\ChromePHPHandler();
                    $monolog->pushHandler($chromeHandler);
                break;
                default:
                    throw new \Exception('"env" setting is invalid in beatrixSettings.yml', E_ERROR);
                break;
            }

            return $monolog;
        });

        /* Twig template engine */
        // http://twig.sensiolabs.org/doc/api.html
        $this['twig'] = $this->share( function(){
            $twigLoader = new \Twig_Loader_Filesystem(WEB_ROOT.'/src/lib/');
            $twigLoader->addPath(WEB_ROOT.'/', 'root');
            $attributes = [];

            if ($this->setting('env') == 'dev'){
                $attributes = [
                    'auto_reload' => true,
                    'debug' => true
                ];
            }

            $attributes = array_merge($attributes, [
                'cache' => WEB_ROOT.'/app/cache/twig',
                'strict_variables' => true
            ]);

            return new \Twig_Environment($twigLoader, $attributes);
        });

        /* Memcached, a memcache client interface */
        // http://php.net/memcached
        $this['memcached'] = $this->share( function(){
            $m = new \Memcached();
            $m->addServer('localhost', 11211);
            return $m;
        });

        $this['db'] = $this->share( function($c){
            return new \Cangit\Beatrix\DBAL($c['cache'], $c['logger']);
        });

        $this['session'] = $this->share( function(){
            return \Cangit\Beatrix\Session::load();
        }); 

        /* Beatrix variable cache interface */
        $this['cache'] = $this->share( function($c){
            switch($this->setting('cache.interface')) {
                case 'apcu':
                    // https://github.com/krakjoe/apcu
                    return new Cache\ApcU();
                break;
                case 'memcached':
                    return new Cache\Memcached($c['memcached'], $this->settings);
                break;
                case 'none':
                default:
                    return new Cache\None();
                break;
            }
        });

        require(WEB_ROOT.'/app/config/beatrixCache.php');
        $this->settings = array_merge($this->settings, $this['cache']->file('beatrixSettings', WEB_ROOT.'/app/config/beatrixSettings.yml', 'yml'));
        
        try {
            $timezone = $this->setting('timezone');
            date_default_timezone_set($timezone);
        } catch(\Exception $e) {}

        ErrorHandling::construct($this->setting('env'), $this['logger']);
        set_error_handler( [ 'Cangit\\Beatrix\\ErrorHandling', 'errorHandler' ] );
        set_exception_handler( ['Cangit\\Beatrix\\ErrorHandling', 'exceptionHandler'] );
        error_reporting(E_ALL);
    }

    public function run()
    {
        $Loader = $this['classLoader'];
        $Loader->registerNamespace( 'src' , WEB_ROOT);
        $Loader->registerNamespace( 'controller' , WEB_ROOT.'/src');
        $Loader->registerNamespace( 'model' , WEB_ROOT.'/src');
        $Loader->register();

        if (!is_readable(WEB_ROOT.'/src/routes/index.yml')){
            $this['logger']->warning('Could not locate/read routes file. Looked for src/routes/index.yml');
            new LoadFail('500', $this);
        } else {
            try{
                $Locator = new \Symfony\Component\Config\FileLocator([WEB_ROOT.'/src/routes/']);
                $Context = new \Symfony\Component\Routing\RequestContext($this['request']);

                if ($this->setting('cache')){
                    $Router = new \Symfony\Component\Routing\Router(
                        new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator),
                        'index.yml',
                        ['cache_dir' => WEB_ROOT.'/app/cache/beatrix'],
                        $Context
                    );
                } else {
                    $Router = new \Symfony\Component\Routing\Router(
                        new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator),
                        'index.yml',
                        [],
                        $Context
                    );
                }

                $attributes = $Router->match($this['request']->getPathInfo());
                $this['request']->attributes->add($Router->match($this['request']->getPathInfo()));
                $controller = "src\\controller\\".$attributes['_controller'];
                $this->createController($controller);

            } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
                $this['logger']->info(sprintf('Did not find a route matching input "%s", using src/routes/index.yml', $this['request']->getPathInfo()));
                new LoadFail('404', $this, 'routes');
            } catch (\InvalidArgumentException $e){
                $this['logger']->error('InvalidArgumentException thrown when trying to load resource: '.$this['request']->getPathInfo());
                new LoadFail('500', $this);
            } catch (\Exception $e) {
                $this['logger']->error('Exception thrown when trying to load resource: '.$this['request']->getPathInfo());
                new LoadFail('500', $this);
            }

        }

        $requestMethod = $this['request']->getMethod();
        $this->initiateControllerMethod($requestMethod);

    }

    public function response($content = '', $status = 200, $headers = [])
    {
        return new \Symfony\Component\HttpFoundation\Response($content, $status, $headers);
    }

    public function redirect($url, $status = 302)
    {
        return new \Symfony\Component\HttpFoundation\RedirectResponse($url, $status);
    }

    public function json($data = [], $status = 200, $headers = [])
    {
        return new \Symfony\Component\HttpFoundation\JsonResponse($data, $status, $headers);
    }

    public function stream($callback = null, $status = 200, $headers = [])
    {
        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, $status, $headers);
    }

    public function sendFile($file, $status = 200, $headers = [], $contentDisposition = null)
    {
        return new \Symfony\Component\HttpFoundation\BinaryFileResponse($file, $status, $headers, true, $contentDisposition);
    }

    public function createController($controller)
    {
        if (!class_exists($controller)){
            $this['logger']->notice('Could not find controller '.$controller);
            new LoadFail('500', $this);
        }

        $this->Controller = new $controller();
    }

    private function compileAllowHeaders()
    {
        $methods = get_class_methods($this->Controller);
        $options = '';
        
        foreach ($methods as $method) {
            $method = strtoupper($method);
            if (in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'TRACE', 'CONNECT'])){
                $options .= $method.', ';
            }
        }
        
        if (strpos($options, 'GET,') !== false){
            $options .= 'HEAD, ';
        }

        $options .= 'OPTIONS';
        return ["Allow" => $options];
    }

    public function initiateControllerMethod($requestMethod)
    {
        switch ($requestMethod){
            case 'HEAD':
                $requestMethod = 'GET';
            case 'GET':
            case 'POST':
            case 'PUT':
            case 'DELETE':
            case 'TRACE':
            case 'CONNECT':
                if (method_exists($this->Controller, $requestMethod)){
                    $reflection = new \ReflectionMethod($this->Controller, $requestMethod);
                    $params = $reflection->getParameters();
                    $attr = [];
                    foreach($params as $param){
                        switch($param->getName()){
                            case 'app':
                                $attr[] = $this;
                            break;
                            /*
                            case 'request':
                                $attr[] = $this['request'];
                            break;
                            */
                            default:
                                throw new \Exception('Argument list is invalid.', E_ERROR);
                        }
                    }
                    if ($params === []){
                        $responseObj = $this->Controller->$requestMethod();
                    } else {
                        $responseObj = $this->Controller->$requestMethod($attr[0]);
                    }
                    if (is_object($responseObj)){
                        $responseObj->setProtocolVersion('1.1');
                        $this['logger']->notice('Executed in '. number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's');
                        $responseObj->prepare( $this['request'] )->send();
                    }
                } else {
                    $headers = $this->compileAllowHeaders();
                    $responseObj = $this->response('', 405, $headers);
                    $this->prepareAndSend($responseObj);
                }
            break;
            case 'OPTIONS':                
                if (method_exists($this->Controller, 'OPTIONS')){
                    $headers = $this->compileAllowHeaders();
                    $options = $this->Controller->OPTIONS();
                    $responseObj = $this->json($options, 200, $headers);
                    $this->prepareAndSend($responseObj);
                }
            break;
            default:
                $headers = $this->compileAllowHeaders();
                $responseObj = $this->response('', 501, $headers);
                $this->prepareAndSend($responseObj);
        }
    }

    public function prepareAndSend($responseObj)
    {
        $responseObj->setProtocolVersion('1.1');
        $responseObj->prepare( $this['request'] )->send();
    }

    public function setting($var)
    {
        if (isset($this->settings[$var])){
            return $this->settings[$var];
        } elseif (!is_string($var)) {
            throw new \InvalidArgumentException('Expected string, '.gettype($var).' given.', E_ERROR);
        } else {
            throw new \Exception(sprintf('Setting "%s" is not defined.', $var), E_ERROR);
        }
    }
}
