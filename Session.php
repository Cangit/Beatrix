<?php

namespace Cangit\Beatrix;

class Session extends \Symfony\Component\HttpFoundation\Session\Session
{
    public static function load()
    {
        $bag = new \Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag();
        $Session = new Session();
        $Session->registerBag($bag);
        $Session->start();
        return $Session;
    }
}
