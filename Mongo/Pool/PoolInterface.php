<?php

namespace Pouzor\MongoBundle\Mongo\Pool;


use Pouzor\MongoBundle\Mongo\Repository\RepositoryInterface;

interface PoolInterface {

    /**
     *  Get the repository for a collection
     *
     * @param $collection
     * @return RepositoryInterface
     * @throws \Exception
     */
    public function get($collection);

    /**
     * Add a repository to the registry. After this you should be able to call $pool->get($collection)
     * to return the repository associated to this collection.
     *
     * @param RepositoryInterface $repositoryInterface
     */
    public function add(RepositoryInterface $repositoryInterface);


    /**
     * Must return true if the pool has a repository for $name
     *
     * @param $name
     * @return bool
     */
    public function has($name);
} 