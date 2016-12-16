<?php

namespace Pouzor\MongoBundle\Model;

/**
 * Class MongoDocument
 * @package Pouzor\MongoBundle\Model
 */
abstract class MongoDocument extends \ArrayObject
{

    /**
     * gets the array representaiton of this object
     * @return array
     */
    public abstract function toArray();


    /**
     * Reload object from values
     *
     * @param array $values
     * @return $this
     */
    public function fromArray(array $values)
    {
        foreach ($values as $prop => $value) {
            $this->set($prop, $value);
        }

        return $this;
    }


    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @param $name
     * @return mixed
     */
    public function &get($name)
    {
        return $this->{$name};
    }

    /*
     *
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    public function set($name, $value)
    {
        $this->{$name} = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->set($offset, null);
    }
}