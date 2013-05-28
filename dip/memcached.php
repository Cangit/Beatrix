<?php
/* Memcached, a memcache client interface */
// http://php.net/memcached

$this['memcached'] = $this->share( function($c){
    $m = new \Memcached();

    $settings = $c->setting('DIC')['memcached'];

    if (isset($settings['servers'])){
        foreach ($settings['servers'] as $server){
           $m->addServer($server['host'], $server['port'], $server['weight']);
        }
    }
    
    return $m;
});
