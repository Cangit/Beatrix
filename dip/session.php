<?php
/* Session handling, using symfony component */

$this['session'] = $this->share( function($c){

    $settings = $c->setting('ICP')['session'];

    if (isset($settings['options'])){
        $options = $settings['options'];
    } else {
        $options = [];
    }

    return \Cangit\Beatrix\Session::load($options);
});
