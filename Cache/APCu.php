<?php

namespace Cangit\Beatrix\Cache;

class APCu implements CacheInterface
{

    private $settingCache;

    public function __construct($settingCache=false)
    {
        $this->settingCache = $settingCache;

        if (!function_exists('apcu_exists')) {
            exit ('APCu is not installed on this server. Remove APCu-configuration from settings file or install APCu.');
        }
    }

    public function clear($id = null)
    {
        if ($id === null){
            if (apcu_clear_cache()){
                return true;
            }
        } else {
            if (apcu_delete($id)){
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

        if (apcu_exists($id)){
            if ($readCache === true && $this->settingCache === true){
                return apcu_fetch($id);
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
            apcu_store($id, $data);
        }

        return $data;
    }
}
