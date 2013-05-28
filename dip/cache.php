<?php
/* Beatrix cache object */
// Cache interface: {beatrix}/Cache/CacheInterface.php

$this['cache'] = $this->share( function($c){
    
    try{
        $cache = $c->setting('cache');
    } catch (\Exception $e){
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
