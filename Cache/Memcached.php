<?php

namespace Cangit\Beatrix\Cache;


class Memcached implements CacheInterface
{

    private $memcachedObj;
    private $settings;

    public function __construct(\Memcached $memcached, $settings)
    {
        $this->memcachedObj = $memcached;
        $this->settings = $settings; 
    }

    public function clear($identifier = null)
    {
        if ($identifier === null){
            if($this->memcachedObj->flush()){
                return true;
            }
        } else {
            if ($this->memcachedObj->delete($identifier)){
                return true;
            }
        }

        return false;
    }

    public function file($identifier, $file, $format = '')
    {
        if(!is_string($identifier)){
            throw new \InvalidArgumentException('Expected string as first parameter.', E_ERROR);
        }

        $memcachedValue = $this->memcachedObj->get($identifier);

        if ($this->memcachedObj->getResultCode() != \Memcached::RES_NOTFOUND) {
            if ($this->settings['cache.settings'] === true){
                return $memcachedValue;
            }
        }

        switch ($format){
            case 'yml':
                $data = \Symfony\Component\Yaml\Yaml::parse($file);
            break;
            default:
                throw new \Exception(sprintf('Requested format "%s" is not supported, refer to manual for supported formats.', $format), E_ERROR);
        }

        if ($this->settings['cache.settings'] === true){
            $this->memcachedObj->set($identifier, $data);
        }

        return $data;
    }
}
