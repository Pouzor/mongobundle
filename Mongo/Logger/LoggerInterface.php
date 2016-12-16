<?php


namespace Pouzor\MongoBundle\Mongo\Logger;


interface LoggerInterface {

    /**
     * Logs a mongo query
     *
     * @param array $query
     * @return mixed
     */
    public function logQuery(array $query);
} 