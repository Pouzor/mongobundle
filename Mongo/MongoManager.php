<?php

namespace Pouzor\MongoBundle\Mongo;

use Doctrine\MongoDB\Collection;
use Doctrine\MongoDB\Configuration;
use Doctrine\MongoDB\Connection;
use Doctrine\MongoDB\LoggableCollection;
use Pouzor\MongoBundle\Mongo\Logger\LoggerInterface;

class MongoManager
{

    /**
     * @var Connection[]
     */
    protected $connections = array();

    /**
     * @var array
     */
    protected $databases = array();


    /**
     * @var string
     */
    protected $defaultConnection = null;

    /**
     * @var string $currentConnection
     */
    protected $currentConnection = null;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param string $name The name used for identify the connection
     * @param array $configuration
     * @throws \InvalidArgumentException
     */
    public function addConnection($name, array $configuration = array())
    {
        if ($configuration['db'] == 'local') {
            throw new \InvalidArgumentException('You must not use MongoDB system local database.');
        }

        $this->databases[$name] = $configuration;

        $dsnFormat = 'mongodb://%host$s:%port$s';

        if (isset($configuration['password']) && !empty($configuration['password'])) {
            $dsnFormat = 'mongodb://%username$s:%password$s@%host$s:%port$s';
        }

        $dsn = self::sprintf($dsnFormat, $configuration);
        $mongoConfig = new Configuration();

        if (!empty($this->logger)) {
            $mongoConfig->setLoggerCallable([$this->logger, 'logQuery']);
        }

        $mongoClient = new \MongoClient($dsn);

        $mongoClient->setReadPreference(\MongoClient::RP_PRIMARY_PREFERRED);

        $this->connections[$name] = new Connection($mongoClient, $configuration['options'], $mongoConfig);

    }

    /**
     * @param $name
     * @return array
     * @throws \MongoException
     * @throws \Exception
     */
    public function drop($name)
    {
        $this->validateConnection($name);

        if (!$this->checkDbExists($name))
            throw new \Exception(sprintf("The database %s doesn't exist and can not be dropped out as it doesn't exist.", $name));

        return $this->connections[$name]->dropDatabase($this->databases[$name]['db']);
    }


    /**
     * @param $name
     * @return \Doctrine\MongoDB\Database
     */
    public function create($name)
    {
        $this->validateConnection($name);

        return $this->connections[$name]->selectDatabase($this->databases[$name]['db']);
    }

    /**
     *
     * @param  string $collection
     * @param null $connection
     * @return LoggableCollection
     */
    public function getCollection($collection, $connection = null)
    {
        $db = empty($connection) ? $this->defaultConnection : $connection;

        $this->validateConnection($db);

        return $this->get($db)->selectCollection($this->databases[$db]['db'], $collection);
    }

    /**
     *
     * @param $name
     * @internal param string $db
     *
     * @return boolean
     */
    public function checkDbExists($name)
    {
        $dbs = $this->get($name)->listDatabases();

        if (isset($dbs['databases'])) {
            foreach ($dbs['databases'] as $db) {
                if (isset($db['name']) && $db['name'] == $this->databases[$name]['db']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param $name
     * @return Connection
     */
    public function get($name)
    {
        return $this->connections[$name];
    }

    /**
     * Get a specific connection
     *
     * @param string $name
     * @throws \Exception
     * @return $this
     */
    public function using($name = null)
    {
        $this->currentConnection = null === $name ? $this->defaultConnection : $name;

        if (!in_array($name, array_keys($this->connections)))
            throw new \Exception(sprintf("The mongo connection %s doesn't exist", $name));

        return $this;
    }

    /**
     * @param Connection[] $connections
     */
    public function setConnections(array $connections)
    {
        $this->connections = $connections;
    }

    /**
     * @return Connection[]
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * @param string $defaultConnection
     */
    public function setDefaultConnection($defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->defaultConnection;
    }


    /**
     * @param LoggerInterface $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param null $name
     * @return \Doctrine\MongoDB\Database
     */
    public function getDatabase($name = null)
    {
        $name = null !== $name ? $name : $this->defaultConnection;

        $this->validateConnection($name);

        return $this->connections[$name]->selectDatabase($this->databases[$name]['db']);
    }

    /**
     * @return array
     */
    public function getDatabases()
    {
        return $this->databases;
    }

    private function validateConnection($name)
    {
        if (!in_array($name, array_keys($this->connections)))
            throw new \Exception(sprintf("%s connection doesn't exist", $name));

        return true;
    }

    /**
     * Return the list of collections created
     *
     * @param string $name
     * @return Collection[]
     */
    public function getCollections($name = null)
    {
        $name = null === $name ? $this->getDefaultConnection() : $name;

        $this->validateConnection($name);

        $db = $this->connections[$name]->selectDatabase($this->databases[$name]['db']);

        return $db->listCollections();
    }

    /**
     * Return the list of collections created
     *
     * @param $name
     * @param bool $erase
     * @return Collection[]
     */
    public function createCollectionsFor($name, $erase = false)
    {
        $this->validateConnection($name);

        $db = $this->connections[$name]->selectDatabase($this->databases[$name]['db']);

        $createdCollections = array();

        foreach ($this->databases[$name]['collections'] as $collectionName => $conf) {
            if ($erase)
                $db->dropCollection($collectionName);

            $collection = $db->createCollection($collectionName);
            $createdCollections[$collectionName] = $collection;
        }

        return $createdCollections;
    }


    public function ensureIndexesFor($collectionName, $connection = null)
    {
        $this->validateConnection($connection);

        $db = $this->connections[$connection]->selectDatabase($this->databases[$connection]['db']);

        $collection = $db->selectCollection($collectionName);

        if (!isset($this->databases[$connection]['collections'][$collectionName])) {
            return [];
        }

        $indexes = $this->databases[$connection]['collections'][$collectionName]['indexes'];

        $createdIndexes = array();

        foreach ($indexes as $name => $conf) {
            $fields = (is_array($conf) and isset($conf['fields'])) ? $conf['fields'] : array($name => $conf);
            $options = (is_array($conf) and isset($conf['options'])) ? $conf['options'] : array();

            $options['timeout'] = -1;
            $collection->ensureIndex($fields, $options);

            $createdIndexes[$name] = $fields;
        }

        return $createdIndexes;
    }

    /**
     * @param null $name
     */
    public function clean($name = null)
    {
        $this->validateConnection($name);

        foreach ($this->databases[$name]['collections'] as $collectionName => $scheme) {
            $this->getCollection($collectionName)->drop();
            $this->getDatabase($name)->createCollection($collectionName);
            $this->ensureIndexesFor($collectionName, $name);
        }
    }

    /**
     * Close connections on destroy
     */
    public function __destruct()
    {
        foreach($this->connections as $conn){
            if($conn->isConnected())
                $conn->close();
        }
    }


    /**
     * version of sprintf for cases where named arguments are desired (php syntax)
     *
     * with sprintf: sprintf('second: %2$s ; first: %1$s', '1st', '2nd');
     *
     * with sprintfn: sprintfn('second: %second$s ; first: %first$s', array(
     *  'first' => '1st',
     *  'second'=> '2nd'
     * ));
     *
     * @param string $format sprintf format string, with any number of named arguments
     * @param array $args array of [ 'arg_name' => 'arg value', ... ] replacements to be made
     * @return string|false result of sprintf call, or bool false on error
     */
    static function sprintf($format, array $args = array())
    {
        // map of argument names to their corresponding sprintf numeric argument value
        $arg_nums = array_slice(array_flip(array_keys(array(0 => 0) + $args)), 1);

        // find the next named argument. each search starts at the end of the previous replacement.
        for ($pos = 0; preg_match('/(?<=%)([a-zA-Z_]\w*)(?=\$)/', $format, $match, PREG_OFFSET_CAPTURE, $pos);) {
            $arg_pos = $match[0][1];
            $arg_len = strlen($match[0][0]);
            $arg_key = $match[1][0];

            // programmer did not supply a value for the named argument found in the format string
            if (!array_key_exists($arg_key, $arg_nums)) {
                user_error("sprintfn(): Missing argument '${arg_key}'", E_USER_WARNING);
                return false;
            }

            // replace the named argument with the corresponding numeric one
            $format = substr_replace($format, $replace = $arg_nums[$arg_key], $arg_pos, $arg_len);
            $pos = $arg_pos + strlen($replace); // skip to end of replacement for next iteration
        }

        return vsprintf($format, array_values($args));
    }
}
