<?php

namespace Cangit\Beatrix\Cache;

class APCu implements CacheInterface
{

    private $settings;

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    public function clear($identifier = null)
    {
        if ($identifier === null){
            if(apcu_clear_cache()){
                return true;
            }
        } else {
            if (apcu_delete($identifier)){
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

        if (apcu_exists($identifier)){
            if ($this->settings['cache.settings'] === true){
                return apcu_fetch($identifier);
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
            apcu_store($identifier, $data);
        }

        return $data;
    }
}
