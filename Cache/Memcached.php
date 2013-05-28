<?php

namespace Cangit\Beatrix\Cache;

class Memcached implements CacheInterface
{

    private $memcachedObj;
    private $settingCache;

    public function __construct(\Memcached $memcached, $settingCache=false)
    {
        $this->memcachedObj = $memcached;
        $this->settingCache = $settingCache;
    }

    public function clear($id = null)
    {
        if ($id === null){
            if ($this->memcachedObj->flush()){
                return true;
            }
        } else {
            if ($this->memcachedObj->delete($id)){
                return true;
            }
        }

        return false;
    }

    public function file($id, $file, $format='', $readCache=true, $writeCache=true)
    {
        if(!is_string($id)){
            throw new \InvalidArgumentException('Expected string as first parameter.', E_ERROR);
        }

        $memcachedValue = $this->memcachedObj->get($id);

        if ($this->memcachedObj->getResultCode() != \Memcached::RES_NOTFOUND) {
            if ($readCache === true && $this->settingCache === true){
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

        if ($writeCache === true && $this->settingCache === true){
            $this->memcachedObj->set($id, $data);
        }

        return $data;
    }
}
