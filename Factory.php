<?php

namespace Cangit\Beatrix;

class Factory extends \Pimple
{

    private $blueprints = [];

    public function __construct($blueprints='')
    {
        parent::__construct();

        if (!is_array($blueprints)){
            return;
        }

        $this->blueprints = array_merge_recursive($this->blueprints, $blueprints);
    }

    public function addBlueprints($blueprints)
    {
        if (!is_array($blueprints)){
            throw new \InvalidArgumentException('Argument passed to addBlueprint is not an array.');
        }

        $this->blueprints = array_merge_recursive($this->blueprints, $blueprints);
    }

    public function getBlueprints($id)
    {
        return $this->blueprints[$id];
    }

    /* Overrides Pimple->offsetGet() */
    public function offsetGet($id)
    {
        if (!array_key_exists($id, $this->values)) {
            $this->loadIntoDIC($id);
        }

        $isFactory = is_object($this->values[$id]) && method_exists($this->values[$id], '__invoke');

        return $isFactory ? $this->values[$id]($this) : $this->values[$id];
    }

    /* Loads object from path configuration in the factory blueprint */
    protected function loadIntoDIC($id)
    {
        if (isset($this->blueprints[$id]['path'])){
            require APP_ROOT.'/'.$this->blueprints[$id]['path'];
        } else {
            throw new \InvalidArgumentException(sprintf('Identifier "%s" is not defined.', $id));
        }
    }

}
