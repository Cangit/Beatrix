<?php

namespace Cangit\Beatrix;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
// use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class Session extends \Symfony\Component\HttpFoundation\Session\Session
{
    public static function load($options = [])
    {

        $bag = new \Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag();

        $storage = new NativeSessionStorage($options);
        $Session = new Session($storage, $bag);
        
        $Session->start();
        return $Session;
    }
}
