<?php
/* database */

$this['db'] = $this->share( function($c){
    return new \Cangit\Beatrix\DBAL($c['cache'], $c['logger']);
});