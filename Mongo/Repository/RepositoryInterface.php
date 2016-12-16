<?php

namespace Pouzor\MongoBundle\Mongo\Repository;

use Pouzor\MongoBundle\Mongo\MongoManager;
use Pouzor\MongoBundle\Mongo\Pool\PoolInterface;

/**
 * Models the idea of a repository pool where you can add, get and ask for a collection repository
 *
 * Interface RepositoryInterface
 * @package Pouzor\MongoBundle\Mongo\Repository
 */
interface RepositoryInterface {


    /**
     * @param $id
     * @param array $fields Fields to return
     * @return array|null
     */
    public function find($id, $fields = []);

    /**
     * @param array $params
     * @param array $fields
     * @param array $sort
     * @return Cursor
     */
    public function findBy($params = [], $options = []);
    /**
     * @param $a
     * @param array $options
     * @return mixed
     */
    public function save(&$a, $options = []);


    /**
     * @param $id
     * @param array $options
     * @return mixed
     */
    public function remove($id, $options = []);


    /**
     * @param $query
     * @param $newObj
     * @param array $options
     * @return mixed
     */
    public function update($query, $newObj, $options = []);

    /**
     * @param $query
     * @param $newObj
     * @param $options
     * @return mixed
     */
    public function batchUpdate($query, $newObj, $options);

    /**
     * @return string
     */
    public function getCollection();


    /**
     * Execute a string javascript code|MongoCode
     *
     * @param \MongoCode|string $code
     * @return array
     * @throws \MongoCursorException
     */
    public function execute($code);


    /**
     * Set the pool for relate the others repositories
     *
     * @param PoolInterface $pool
     * @return mixed
     */
    public function setPool(PoolInterface $pool);

    /**
     * @return MongoManager
     */
    public function getManager();

    /**
     * Get the maximum value for a column
     *
     * @param $columnName
     * @param $query
     * @param array $options
     * @return mixed
     */
    public function max($columnName, $query, $options = array());
} 
