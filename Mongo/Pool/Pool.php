<?php

namespace Pouzor\MongoBundle\Mongo\Pool;

use Pouzor\MongoBundle\Mongo\Repository\RepositoryInterface;

/**
 * Class Pool
 * @package Pouzor\MongoBundle\Mongo\Pool
 */
class Pool implements PoolInterface
{

    /**
     * @var array
     */
    protected $persistedObjects = array();

    /**
     * @var RepositoryInterface[]
     */
    protected $repositories;

    /**
     * @param $type
     * @param $object
     */
    public function persist($type, $object)
    {
        $this->persistedObjects[$type][] = $object;
    }


    /**
     *  Flush all persisted objects into database
     */
    public function flush($options = [])
    {
        $responses = [];

        foreach ($this->persistedObjects as $type => $objects) {
            $repository = $this->get($type);

            foreach ($objects as $object) {
                $responses[] = $repository->save($object, $options);
            }
        }

        $this->persistedObjects = array();

        return $responses;
    }

    /**
     *  Get the repository for a collection
     *
     * @param $collection
     * @return RepositoryInterface
     * @throws \Exception
     */
    public function get($collection)
    {
        if (!$this->has($collection))
            throw new \Exception(sprintf("The repository for %s collection is not set or it's not an instance of RepositoryInterface", $collection));

        return $this->repositories[$collection];
    }

    /**
     * @return \Pouzor\MongoBundle\Mongo\Repository\RepositoryInterface[]
     */
    public function all()
    {
        return $this->repositories;
    }

    /**
     * @param RepositoryInterface $repositoryInterface
     */
    public function add(RepositoryInterface $repositoryInterface)
    {
        if(!$this->has($repositoryInterface->getCollection())){
            $this->repositories[$repositoryInterface->getCollection()] = $repositoryInterface;
            $this->repositories[$repositoryInterface->getCollection()]->setPool($this);
        }



    }

    public function has($repositoryName)
    {
        return (isset($this->repositories[$repositoryName])) and ($this->repositories[$repositoryName] instanceof RepositoryInterface);
    }
} 
