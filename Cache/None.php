<?php

namespace Cangit\Beatrix\Cache;

class None implements CacheInterface
{

    public function clear($id = null)
    {
        return true;
    }

    public function file($id, $file, $format='', $readCache=true, $writeCache=true)
    {
        if(!is_string($id)){
            throw new \InvalidArgumentException('Expected string as first parameter.', E_ERROR);
        }

        switch ($format){
            case 'yml':
                $data = \Symfony\Component\Yaml\Yaml::parse($file);
            break;
            default:
                throw new \Exception(sprintf('Requested format "%s" is not supported, refer to manual for supported formats.', $format), E_ERROR);
        }

        return $data;
    }
}
