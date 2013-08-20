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
            $crc32id = crc32id($id);
            if ($this->memcachedObj->delete($crc32id)){
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

        $crc32id = crc32($id);

        $memcachedValue = $this->memcachedObj->get($crc32id);

        if ($this->memcachedObj->getResultCode() != \Memcached::RES_NOTFOUND) {
            if ($readCache === true && $this->settingCache === true){
                return $memcachedValue;
            }
        }

        if (!file_exists($file)) {
            throw new \Exception('Requested file could not be found. '.$file);
        }

        switch ($format){
            case 'yml':
                $data = \Symfony\Component\Yaml\Yaml::parse($file);
            break;
            default:
                throw new \Exception(sprintf('Requested format "%s" is not supported, refer to manual for supported formats.', $format), E_ERROR);
        }

        if ($writeCache === true && $this->settingCache === true){
            $this->memcachedObj->set($crc32id, $data);
        }

        return $data;
    }
}
