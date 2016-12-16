<?php

namespace Pouzor\MongoBundle\Mongo\Query;


/**
 * Class QueryResult
 *
 *
 * @package Pouzor\MongoBundle\Mongo\Query
 */
class QueryResult implements \Iterator
{

    /**
     * @var \MongoCursor $realCursor
     */
    protected $realCursor = null;

    /**
     * @param \Iterator $cursor
     */
    public function __construct(\Iterator $cursor)
    {
        $this->realCursor = $cursor;

    }


    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $current = $this->realCursor->current();

        return $current;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        $this->realCursor->next();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->realCursor->key();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->realCursor->valid();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->realCursor->rewind();
    }

    /**
     * Get a single result for a query
     *
     * @return mixed
     */
    public function getSingleResult()
    {
        $result = $this->realCursor->getSingleResult();

        return $result;
    }

    /**
     * @param $function
     * @param $params
     * @return mixed
     */
    public function __call($function, $params)
    {
        return call_user_func_array([$this->realCursor, $function], $params);
    }


}
