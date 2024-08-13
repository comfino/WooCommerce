<?php

namespace Comfino;

trait ReflectionTrait
{
    /**
     * @param object $object
     * @param string $name
     * @return mixed
     */
    public function getConstantFromObject($object, $name)
    {
        return (new ComfinoExternal\\ReflectionObject($object))->getConstant($name);
    }

    /**
     * @throws ComfinoExternal\\ReflectionException
     * @param string $class
     * @param string $name
     * @return mixed
     */
    public function getConstantFromClass($class, $name)
    {
        return (new ComfinoExternal\\ReflectionClass($class))->getConstant($name);
    }
}
