<?php

namespace Cangit\Beatrix;

use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;

class Application extends \Pimple
{

    private $settings;
    private $Controller;

    public function __construct()
    {
        parent::__construct();

        /* Symfony HttpFoundation Request object */
        // http://symfony.com/doc/current/components/http_foundation/introduction.html#accessing-request-data
        $this['request'] = $this->share( function() {
            Request::enableHttpMethodParameterOverride();
            return Request::createFromGlobals();
        });
        
        if (file_exists(APP_ROOT.'/app/config/beatrix/settings.php') === false) {
            exit("Application is not installed correctly. Error: Could not locate setting.php file.");
        }
        
        $defaultSettings = [
            'name' => 'Beatrix',
            'cache.interface' => 'none',
            'cache.routes' => false,
            'cache' => false,
            'env' => 'prod'
        ];

        require APP_ROOT.'/app/config/beatrix/settings.php';

        $this->settings = array_merge($defaultSettings, $this->settings);

        $this->settings['factory'] = $this['cache']->file('BeatrixFactory', APP_ROOT.'/app/config/beatrix/factoryDefinitions.yml', 'yml', $this->settings['cache']);
        $this->settings['DIC'] = $this->settings['factory']; // BC, Old factory definitions used DIC.

        if (isset($this->settings['timezone'])) {
            date_default_timezone_set($this->settings['timezone']);
        }

        if ($this->setting('env') === 'prod') {
            if (file_exists(APP_ROOT.'/app/config/beatrix/prodAutoexecute.php')) {
                try {
                    require APP_ROOT.'/app/config/beatrix/prodAutoexecute.php';
                } catch (\Exception $e) {
                    $this['logger']->warning('Catchable error in /app/config/beatrix/prodAutoexecute.php');
                }
            }
        }

        if ($this->setting('env') === 'dev') {
            if (file_exists(APP_ROOT.'/app/config/beatrix/devAutoexecute.php')) {
                try {
                    require APP_ROOT.'/app/config/beatrix/devAutoexecute.php';
                } catch (\Exception $e) {
                    $this['logger']->warning('Catchable error in /app/config/beatrix/devAutoexecute.php');
                }
            }
        }

        error_reporting(E_ALL);

    }

    public function createFactory($id)
    {
        if (!is_string($id)) {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not string.', $id));
        }

        $factoryBlueprint = $this['cache']->file($id, $id, 'yml', $this->settings['cache']);
        $factory = new Factory();
        $factory->addBlueprints($factoryBlueprint);

        return $factory;
    }

    /* Loads object from path configuration in settings */
    public function loadIntoDIC($id)
    {
        if (isset($this->settings['factory'])) {
            $factoryBlueprints = $this->setting('factory');
        }
        
        if (isset($factoryBlueprints[$id]['path'])){
            require APP_ROOT.'/'.$factoryBlueprints[$id]['path'];
        } else {
            throw new \InvalidArgumentException(sprintf('Blueprint identifier "%s" does not have a "path" defined.', $id));
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
        if (isset($this->settings['maintenance'])) {

            if ($this->settings['maintenance'] === true) {

                $this['logger']->info('Site is in maintenance mode.');
                $maintenanceWorkers = $this->settings['maintenanceWorkers'];

                if (!in_array($_SERVER['REMOTE_ADDR'], $maintenanceWorkers)) {
                    $controller = '\\'.$this->settings['defaultPages']['maintenance'];

                    if (!class_exists($controller)) {
                        $this['logger']->warning('Could not locate/read maintenance controller defined in /app/config/beatrix/settings.php');
                        $loadFail = new LoadFail('500', $this);
                        $loadFail->run();
                        return;
                    }
                    
                    if (method_exists($controller, '__construct')) {
                        $this->Controller = new $controller($this);
                    } else {
                        $this->Controller = new $controller();
                    }

                    $requestMethod = $this['request']->getMethod();
                    $this->initiateControllerMethod($requestMethod);
                    return;
                }

            }
        
        }
        
        if (!is_readable(APP_ROOT.'/app/config/routes.yml')) {
            $this['logger']->warning('Could not locate/read routes file. Looked for app/config/routes.yml');
            $loadFail = new LoadFail('500', $this);
            $loadFail->run();
            return;
        } else {

            $collection = new RouteCollection();
            $Locator = new FileLocator([APP_ROOT.'/app/config']);
            $loader = new YamlFileLoader($Locator);
            $collection->addCollection($loader->beatrixLoad('routes.yml', $this['cache'], $this->setting('cache.routes')));

            if (isset($this->settings['routes']) && is_array($this->settings['routes']) ) {
                
                $routes = $this->settings['routes'];

                foreach ($routes as $route){
                    if (is_file(APP_ROOT.'/vendor/'.$route.'routes.yml')){
                        $Locator = new FileLocator([APP_ROOT.'/vendor/'.$route]);
                        $loader = new \Symfony\Component\Routing\Loader\YamlFileLoader($Locator);
                        $collection->addCollection($loader->load('routes.yml'));
                    } else {
                        $this['logger']->warning('Could not find routing file: '.APP_ROOT.'/vendor/'.$route.'routes.yml');
                    }
                }
            }

            try {

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
                $loadFail->run();
                return;
            } catch (\InvalidArgumentException $e){
                $this['logger']->error('InvalidArgumentException thrown when trying to load resource: '.$this['request']->getPathInfo());
                $loadFail = new LoadFail('500', $this);
                $loadFail->run();
                return;
            } catch (\Exception $e) {
                $this['logger']->error('Exception thrown when trying to load resource. Probably syntax error in routes.yml file.');
                $loadFail = new LoadFail('500', $this);
                $loadFail->run();
                return;
            }

            if (!class_exists($controller)) {
                $filePath = str_replace('\\', '/', $controller);
                $file = 'src/'.$filePath.'.php';
                $loadFail = new LoadFail('500', $this);

                if(!file_exists($file)){
                    $this['logger']->error(sprintf('The file "%s" could not be found when trying to run controller "%s()".', $file, $controller));
                } else {
                    $this['logger']->error(sprintf('Could not find controller "%s()" in file "%s", malformed namespace or classname.', $controller, $file));
                }

                $loadFail->run();
                return;
            }

            if (method_exists($controller, '__construct')) {
                $this->Controller = new $controller($this);
            } else {
                $this->Controller = new $controller();
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
                        $this['logger']->debug('Executed in '. number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's');
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

    public function debug($str='', $arr=[])
    {
        $report = [];
        $report['time'] = number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4);
        $report['memory'] = memory_get_usage();
        $report['debug'] = $arr;

        $this['logger']->debug('Profile '.$str, $report);
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
