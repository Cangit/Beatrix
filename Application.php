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

        if (!is_readable(WEB_ROOT.'/app/config/routes.yml')){
            $this['logger']->warning('Could not locate/read routes file. Looked for app/config/routes.yml');
            $loadFail = new LoadFail('500', $this);
            $loadFail->run();
        } else {
            try{
                $Locator = new \Symfony\Component\Config\FileLocator([WEB_ROOT.'/app/config/']);
                $Context = new \Symfony\Component\Routing\RequestContext($this['request']);

                if ($this->setting('cache') && $this->setting('cache.routes')){
                    $Router = new \Symfony\Component\Routing\Router(
                        new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator),
                        'routes.yml',
                        ['cache_dir' => WEB_ROOT.'/app/cache/beatrix'],
                        $Context
                    );
                } else {
                    $Router = new \Symfony\Component\Routing\Router(
                        new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator),
                        'routes.yml',
                        [],
                        $Context
                    );
                }

                $attributes = $Router->match($this['request']->getPathInfo());
                $this['request']->attributes->add($Router->match($this['request']->getPathInfo()));
                $controller = "controller\\".$attributes['_controller'];

            } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
                $this['logger']->info(sprintf('Did not find a route matching input "%s", using app/config/routes.yml', $this['request']->getPathInfo()));
                $loadFail = new LoadFail('404', $this);
                $loadFail->debug(sprintf('Did not find a route matching input "%s", using app/config/routes.yml', $this['request']->getPathInfo()));
                $loadFail->debug("We dumped the contents of 'app/config/routes.yml' to make the debugging easier.\n\n==========");
                $loadFail->debug(htmlentities(file_get_contents(WEB_ROOT.'/app/config/routes.yml')));
                $loadFail->run();
            } catch (\InvalidArgumentException $e){
                $this['logger']->error('InvalidArgumentException thrown when trying to load resource: '.$this['request']->getPathInfo());
                $loadFail = new LoadFail('500', $this);
                $loadFail->run();
            } catch (\Exception $e) {
                $this['logger']->error('Exception thrown when trying to load resource. Probably syntax error in routes.yml file.');
                $loadFail = new LoadFail('500', $this);
                $loadFail->run();
            }

            $this->createController($controller);

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

    private function createController($controller)
    {
        if (!class_exists($controller)){
            $filePath = str_replace('\\', '/', $controller);
            $file = 'src/'.$filePath.'.php';
            $loadFail = new LoadFail('500', $this);

            if(!file_exists($file)){
                $this['logger']->error(sprintf('The file "%s" could not be found when trying to run controller "%s()".', $file, $controller));
                $loadFail->debug(sprintf('The file "%s" could not be found when trying to run controller "%s()".', $file, $controller));
            } else {
                $this['logger']->error(sprintf('Could not find controller "%s()" in file "%s", malformed namespace or classname.', $controller, $file));
                $loadFail->debug(sprintf("Could not find controller '%s()' in file '%s'.\nMalformed namespace or classname.", $controller, $file));
                $loadFail->debug(sprintf("We dumped the contents of '%s' to make the debugging easier.\n\n==========", $file));
                $loadFail->debug(htmlentities(file_get_contents($file)));
            }

            $loadFail->run();
        }

        $this->Controller = new $controller();
    }

    public function compileAllowHeaders($controller=null)
    {
        if($controller === null){
            $controller = $this->Controller;
        }

        $methods = get_class_methods($controller);
        $options = '';
        
        if (is_array($methods)){
            foreach ($methods as $method) {
                $method = strtoupper($method);
                if (in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'TRACE', 'CONNECT'])){
                    $options .= $method.', ';
                }
            }
        } elseif($methods === null) {
            throw new \Exception(sprintf('Class "%s" could not be found.', $controller));
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
                        $this['logger']->notice('Executed in '. number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's');
                        $this->prepareAndSend($responseObj);
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
