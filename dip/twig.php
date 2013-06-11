<?php
/* Twig template engine */
// http://twig.sensiolabs.org/doc/api.html

$this['twig'] = $this->share( function($c){

    $twigLoader = new \Twig_Loader_Filesystem(APP_ROOT.'/src/view/');
    $twigLoader->addPath(APP_ROOT.'/', 'root');
    $attributes = [];

    if ($c->setting('env') == 'dev'){
        $attributes = [
            'auto_reload' => true,
            'debug' => true
        ];
    }

    $attributes = array_merge($attributes, [
        'cache' => APP_ROOT.'/app/cache/twig',
        'strict_variables' => true
    ]);

    return new \Twig_Environment($twigLoader, $attributes);

});
