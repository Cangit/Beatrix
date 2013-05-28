<?php
/* Beatrix cache object */
// Cache interface: {beatrix}/Cache/CacheInterface.php

$this['cache'] = $this->share( function($c){

    if (isset($c->setting('cache'))){
        $cache = $c->setting('cache');
    } else {
        $cache = false;
    }

    switch($c->setting('cache.interface')) {
        case 'apcu':
            return new \Cangit\Beatrix\Cache\APCu($cache);
        break;
        case 'memcached':
            return new \Cangit\Beatrix\Cache\Memcached($c['memcached'], $cache);
        break;
        case 'none':
        default:
            return new \Cangit\Beatrix\Cache\None();
        break;
    }
});