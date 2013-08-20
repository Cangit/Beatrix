<?php
/* Session handling, using symfony component */

$this['session'] = $this->share( function($c){

    $settings = $c->setting('factory')['session'];

    if (isset($settings['options'])){
        $options = $settings['options'];
    } else {
        $options = [];
    }

    if (isset($settings['handler'])){
        $handler = [];
        $handler['settings'] = $settings['handler'];
        if (isset($settings['handler']['db_handler'])){
            $handler['db'] = $c['db']->getPdoHandle($settings['handler']['db_handler']);
        }
    } else {
        $handler = null;
    }

    return \Cangit\Beatrix\Session::load($options, $handler);
});
