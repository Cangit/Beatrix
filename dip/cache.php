<?php
/* Beatrix cache object */
// Cache interface: {beatrix}/Cache/CacheInterface.php

$this['cache'] = $this->share( function($c){
    switch($this->setting('cache.interface')) {
        case 'apcu':
            return new \Cangit\Beatrix\Cache\APCu();
        break;
        case 'memcached':
            return new \Cangit\Beatrix\Cache\Memcached($c['memcached'], $this->settings);
        break;
        case 'none':
        default:
            return new \Cangit\Beatrix\Cache\None();
        break;
    }
});