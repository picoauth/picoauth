<?php

namespace PicoAuth;

use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase
{

    /**
     * Allows testing private/protected methods
     * @param instance $entity
     * @param string $name
     * @return ReflectionMethod
     */
    public static function getMethod($entity, $name)
    {
        $class = new \ReflectionClass($entity);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Sets property value
     * @param mixed $entity
     * @param mixed $value
     * @param string $propertyName
     */
    public static function set($entity, $value, $propertyName)
    {
        $class = new \ReflectionClass($entity);
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($entity, $value);
    }
    
    /**
     * Gets property value
     * @param mixed $entity
     * @param string $propertyName
     * @return mixed the value
     */
    public static function get($entity, $propertyName)
    {
        $class = new \ReflectionClass($entity);
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($entity);
    }
}
