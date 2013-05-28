<?php

namespace Cangit\Beatrix\Cache;

interface CacheInterface
{

    /*  file()
        Returnes the data of the file, no matter if caching is on or not.
        Throw exception if format is not supported.
    */
    public function file($id, $file, $format, $readCache, $writeCache);

    /*  clear() the cache, no questions asked.
        If identifier is provided, only clear that item.
        Returns true on succsess, false otherwise.
    */
    public function clear($id);

}
