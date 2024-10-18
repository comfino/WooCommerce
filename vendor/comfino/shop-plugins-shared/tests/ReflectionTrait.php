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
        return (new \ReflectionObject($object))->getConstant($name);
    }

    /**
     * @throws \ReflectionException
     * @param string $class
     * @param string $name
     * @return mixed
     */
    public function getConstantFromClass($class, $name)
    {
        return (new \ReflectionClass($class))->getConstant($name);
    }
}
