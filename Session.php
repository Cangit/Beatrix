<?php

namespace Cangit\Beatrix;

use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

class Session extends \Symfony\Component\HttpFoundation\Session\Session
{
    public static function load($options = [], $handler = null)
    {

        $bag = new \Symfony\Component\HttpFoundation\Session\Attribute\NamespacedAttributeBag();

        if (isset($handler['settings']['interface'])){
            switch ( $handler['settings']['interface'] ){
                case 'pdo':
                    $storage = new NativeSessionStorage($options, new PdoSessionHandler($handler['db'], $handler['settings']));
                break;
                default:
                    $storage = new NativeSessionStorage($options);
                break;
            }
        } else {
            $storage = new NativeSessionStorage($options);
        }

        $Session = new Session($storage, $bag);
        $Session->start();
        return $Session;
    }
}
