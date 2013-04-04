<?php

namespace Cangit\Beatrix\Cache;


class None implements CacheInterface
{

    public function clear($identifier = null)
    {
        return true;
    }

    public function file($identifier, $file, $format = '')
    {
        if(!is_string($identifier)){
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
