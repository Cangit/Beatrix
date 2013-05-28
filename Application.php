<?php

namespace Cangit\Beatrix;

use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;

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
            \Symfony\Component\HttpFoundation\Request::enableHttpMethodParameterOverride();
            return \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        });

        require APP_ROOT.'/app/config/beatrixSettingsPreload.php';

        if (isset($this->settings['DIC'])){
            foreach ($this->settings['DIC'] as $part){
                require APP_ROOT.'/'.$part['path'];
            }
        }

        if (!isset($this->settings['cache.settings'])){
            $this->settings['cache.settings'] = false;
        }

        $this->settings = array_merge_recursive($this->settings, $this['cache']->file('beatrixSettings', APP_ROOT.'/app/config/beatrixSettings.yml', 'yml', $this->settings['cache.settings']));
        
        try {
            $timezone = $this->setting('timezone');
            date_default_timezone_set($timezone);
        } catch(\Exception $e) {}

        if ($this->setting('env') === 'dev'){
            $run = new \Whoops\Run();
            $handler = new \Whoops\Handler\PrettyPageHandler();
            $handler->setEditor('sublime');
            $cache = var_export($this->setting('cache'), true);
            $settings = var_export($this->setting('cache.settings'), true);
            $routes = var_export($this->setting('cache.routes'), true);
            $handler->addDataTable('Beatrix Settings', [
                'Name' => $this->setting('name'),
                'Environment' => $this->setting('env'),
                'Cache' => $cache,
                'Cache.interface' => $this->setting('cache.interface'),
                'Cache.settings' => $settings,
                'Cache.routes' => $routes
            ]);
            $run->pushHandler($handler);
            $run->pushHandler(function($exception, $inspector, $run) {
                $frames = $inspector->getFrames();
                foreach($frames as $i => $frame) {
                    if($function = $frame->getFunction()) {
                        $frame->addComment("'$function'", 'method/function');
                    }
                }
            });
            $run->pushHandler( function($exception){
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

                if (method_exists($this['logger'], $type)){
                    $this['logger']->$type('Exception', $error);
                } else {
                    $this['logger']->error('Exception', $error);
                }

            } );
            $run->register();
        } else {
            ErrorHandling::construct($this['logger']);
            set_error_handler( [ 'Cangit\\Beatrix\\ErrorHandling', 'errorHandler' ] );
            set_exception_handler( ['Cangit\\Beatrix\\ErrorHandling', 'exceptionHandler'] );
        }

        error_reporting(E_ALL);
    }

    /* Loads object from path configuration in settings */
    public function loadIntoDIC($id)
    {
        $ic = $this->setting('DIC');
        
        if (isset($ic[$id]['path'])){
            require APP_ROOT.'/'.$ic[$id]['path'];
        } else {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }
    }

    /* Overrides Pimple->offsetGet() */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            $this->loadIntoDIC($id);
        }

        $isFactory = is_object($this->values[$id]) && method_exists($this->values[$id], '__invoke');

        return $isFactory ? $this->values[$id]($this) : $this->values[$id];
    }

    public function run()
    {

        if (!is_readable(APP_ROOT.'/app/config/routes.yml')){
            $this['logger']->warning('Could not locate/read routes file. Looked for app/config/routes.yml');
            $loadFail = new LoadFail('500', $this);
            $loadFail->run();
        } else {

            $collection = new RouteCollection();
            $Locator = new \Symfony\Component\Config\FileLocator([APP_ROOT.'/app/config']);
            $loader = new YamlFileLoader($Locator);
            $collection->addCollection($loader->beatrixLoad('routes.yml', $this['cache'], $this->setting('cache.routes')));

            try{
                if (is_array($routes = $this->setting('routes'))){
                    foreach($routes as $route){
                        if (is_file(APP_ROOT.'/vendor/'.$route.'routes.yml')){
                            $Locator = new \Symfony\Component\Config\FileLocator([APP_ROOT.'/vendor/'.$route]);
                            $loader = new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator);
                            $collection->addCollection($loader->load('routes.yml'));
                        } else {
                            $this['logger']->warning('Could not find routing file: '.APP_ROOT.'/vendor/'.$route.'routes.yml');
                        }
                    }
                }
            } catch (\Exception $e) {}

            try{

                $Context = new RequestContext($this['request']);
                $matcher = new UrlMatcher($collection, $Context);

                $attributes = $matcher->match($this['request']->getPathInfo());
                $this['request']->attributes->add($attributes);
                if (substr($attributes['_controller'], 0, 1) === '@'){
                    $controller = substr($attributes['_controller'], 1);
                } else {
                    $controller = "controller\\".$attributes['_controller'];
                }

            } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
                $this['logger']->info(sprintf('Did not find a route matching input "%s", using app/config/routes.yml', $this['request']->getPathInfo()));
                $loadFail = new LoadFail('404', $this);
                $loadFail->debug(sprintf('Did not find a route matching input "%s", using app/config/routes.yml', $this['request']->getPathInfo()));
                $loadFail->debug("We dumped the contents of 'app/config/routes.yml' to make the debugging easier.\n\n==========");
                $loadFail->debug(htmlentities(file_get_contents(APP_ROOT.'/app/config/routes.yml')));
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

        if (method_exists($controller, '__construct')){
            $this->Controller = new $controller($this);
        } else {
            $this->Controller = new $controller();
        }
        
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

    public function setting($var, $default=null)
    {
        if (isset($this->settings[$var])){
            return $this->settings[$var];
        } elseif (!is_string($var)) {
            throw new \InvalidArgumentException('Expected string, '.gettype($var).' given.', E_ERROR);
        } elseif ($default !== null) {
            return $default;
        } else {
            throw new \Exception(sprintf('Setting "%s" is not defined.', $var), E_ERROR);
        }
    }
}
