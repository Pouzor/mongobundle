<?php

namespace Pouzor\MongoBundle\Mongo\Repository;

use Doctrine\MongoDB\LoggableCollection;
use Pouzor\MongoBundle\Mongo\MongoManager;
use Pouzor\MongoBundle\Mongo\Pool\PoolInterface;
use Pouzor\MongoBundle\Mongo\Query\QueryResult;

class Repository implements RepositoryInterface
{

    /**
     * @var MongoManager
     */
    protected $manager;

    /**
     * @var string
     */
    protected $defaultConnection;

    /**
     * @var PoolInterface
     */
    protected $pool;

    /**
     * @var array
     */
    protected $indexes = [];

    /**
     * Provides mapping configuration for binding data fields.
     *
     * @var array $bindingConfiguration
     */
    protected $bindingConfiguration = null;

    /**
     * @var string
     */
    protected $collection;

    /**
     * @var LoggableCollection
     */
    protected $mongoCollection;

    /**
     * @var bool
     */
    protected $initiated = false;

    /**
     * @var DispatcherInterface
     */
    protected $dispatcher;

    /**
     * initiates the repository
     */
    public function init()
    {
        if (!isset($this->manager)) {
            throw new \Exception(
                'Mongo manager cannot be null when initialising repository. Call setManager method before init()'
            );
        }

        if (isset($this->bindingConfiguration) and $this->bindingConfiguration !== null) {
            $order = function ($a, $b) {
                if (is_array($a) && is_array($b)) {
                    return 0;
                }

                if (is_array($a) && !is_array($b)) {
                    return 1;
                }

                if (is_array($b) && !is_array($a)) {
                    return -1;
                }
            };

            uasort($this->bindingConfiguration, $order);

        }

        if (!$this->defaultConnection) {
            $this->defaultConnection = $this->manager->getDefaultConnection();

        }

        if (isset($this->collection)) {
            $this->mongoCollection = $this->getManager()->getCollection($this->collection, $this->defaultConnection);

        }

        $this->initiated = $this->mongoCollection !== null && $this->mongoCollection instanceof LoggableCollection;

    }

    /**
     * Checks is repository is already initiated
     * @return bool
     */
    protected function isInitiated()
    {
        return $this->initiated;
    }

    /**
     * @param $field
     * @param array $query
     * @param array $options
     * @return \Doctrine\MongoDB\ArrayIterator
     */
    public function distinct($field, $query = [], $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $toBind = [$field => 0];
        $bindedfield = array_keys($this->bind($toBind))[0];

        return $this->mongoCollection->distinct($bindedfield, $query, $options);
    }

    // methods

    /**
     * @param \MongoCode|string $code
     * @param array $scope
     * @return array
     * @throws \Exception
     * @throws \MongoException
     */
    public function execute($code, $scope = [])
    {
        $code = $code instanceof \MongoCode ? $code : new \MongoCode($code, $scope);

        try {
            return $this->getManager()->getDatabase($this->manager->getDefaultConnection())
                ->execute($code, $scope);

        } catch (\MongoException $e) {
            throw $e;
        }
    }

    /**
     * @param string|\MongoId $id
     * @param array $fields
     * @return array|null
     */
    public function find($id, $fields = [])
    {

        $filter = ['_id' => $id instanceof \MongoId ? $id : new \MongoId($id)];

        if (!$this->isInitiated()) {
            $this->init();
        }

        $obj = $this->mongoCollection->findOne($filter, $fields);

        $this->unbind($obj);

        return $obj;
    }


    /**
     * @param array $params
     * @param array $options
     * @return QueryResult
     * @throws \Exception
     */
    public function findBy($params = [], $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $fields = (isset($options['fields']) and count($options['fields']) > 0) ? $options['fields'] : [];
        $sort = (isset($options['sort']) && count($options['sort'])) > 0 ? $options['sort'] : null;

        $this->bindQuery($params);
        $this->bindQuery($fields);
        $this->bindQuery($sort);

        $cursor = $this->mongoCollection
            ->find($params);

        $cursor->timeout(-1);


        $cursor->fields($fields);

        if ($sort) {
            $cursor->sort($sort);
        }


        if (isset($options['offset']) && $options['offset'] > 0) {
            $cursor->skip($options['offset']);
        }

        if (isset($options['limit']) && $options['limit'] > 0) {
            $cursor->limit($options['limit']);
        }

        if (isset($options['batchSize']) && is_int($options['batchSize']) && $options['batchSize'] > 0) {
            $cursor->batchSize($options['batchSize']);
        } else {
            $cursor->batchSize(10000);
        }

        if (isset($options['hint']) && $options['hint'] > 0) {
            $cursor->hint($options['hint']);
        }

        if (isset($options['snapshot']) && $options['snapshot'] == true) {
            $cursor->snapshot();
        }


        return new QueryResult($cursor);
    }

    /**
     * @param array $params
     * @param array $fields
     * @return array|null
     */
    public function findOneBy($params = [], $fields = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $query = $this->bindQuery($params);

        $bdFields = $this->bindQuery($fields);

        $obj = $this->mongoCollection->findOne($query, $bdFields);

        $this->unbind($obj);

        return $obj;
    }

    /**
     * @param $a
     * @param array $options
     * @return array|bool
     * @throws \Exception
     */
    public function insert(&$a, $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bind($a);

        return $this->mongoCollection->insert($a, $options);
    }

    /**
     * @param $a
     * @param array $options
     * @return array|bool
     */
    public function save(&$a, $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bind($a);

        $obj = $this->mongoCollection->save($a, $options);

        return $this->unbind($obj);
    }

    /**
     * @param $id
     * @param array $options
     * @return array|bool
     */
    public function remove($id, $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        return $this->mongoCollection
            ->remove(['_id' => $id instanceof \MongoId ? $id : new \MongoId($id)], $options);
    }


    /**
     * @param array $params
     * @param array $options
     * @return array|bool
     */
    public function removeWhere($params = [], $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $query = $this->bindQuery($params);

        return $this->mongoCollection
            ->remove($query, $options);
    }


    /**
     * @param array $where
     * @return int
     * @throws \Exception
     */
    public function count($where = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bindQuery($where);


        return $this->mongoCollection->count($where);
    }

    /**
     * @param array $filters
     * @return int
     */
    public function getVolumetry($filters = [])
    {
        return $this->count($filters);
    }


    /**
     * @param $id
     * @param $newObj
     * @param array $options
     * @return array|bool|mixed
     */
    public function update($id, $newObj, $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bind($newObj);

        $obj = $this->mongoCollection
            ->findAndUpdate(
                ['_id' => $id instanceof \MongoId ? $id : new \MongoId($id)],
                $newObj,
                array_merge(['new' => true, "socketTimeoutMS" => -1], $options)
            );

        $this->unbind($obj);

        return $obj;
    }


    public function updateMultipleWhere($query, $newObj, $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }


        $obj = $this->mongoCollection->update(
            $query,
            $newObj,
            array_merge(['multiple' => true, "socketTimeoutMS" => -1], $options)
        );

        return $obj;

    }


    /**
     *  CANNOT UPDATE MANY DOCUMENT
     *
     * @param $query
     * @param $newObj
     * @param array $options
     * @return array|bool
     */
    public function updateWhere($query, $newObj, $options)
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $query = $this->bindQuery($query);

        $this->bind($newObj);

        $obj = $this->mongoCollection
            ->findAndUpdate(
                $query,
                $newObj,
                array_merge(["socketTimeoutMS" => -1], $options)
            );

        $this->unbind($obj);

        return $obj;
    }

    /**
     * @param $query
     * @param $newObj
     * @param $options
     * @return mixed
     */
    public function batchUpdate($query, $newObj, $options)
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bindQuery($query);

        $this->bind($newObj);

        $obj = $this->mongoCollection
            ->update(
                $query,
                $newObj,
                array_merge($options, ['multiple' => true, "socketTimeoutMS" => -1])
            );

        $this->unbind($obj);

        return $obj;
    }

    /**
     * @param $match
     * @param $field
     * @param null $default
     * @return null
     * @throws \Exception
     */
    public function findLast($match, $field, $default = null)
    {

        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bind($match);

        $datas = $this->mongoCollection->find($match)->sort([$field => -1])->limit(1);

        foreach ($datas as &$data) {

            $this->unbind($data);

            return $data[$field];
        }

        return $default;
    }

    /**
     * Get the maximum value for a column
     * 
     * @param string $columnName
     * @param array $query
     * @param array $options
     * @return mixed|null
     * @throws \Exception
     */
    public function max($columnName, $query, $options = array())
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $result = $this->findBy(
            $query,
            [
                'sort' => [$columnName => -1],
                'fields' => [$columnName => true]
            ],
            $options
        )->getSingleResult();

        return is_array($result) && isset($result[$columnName]) ? $result[$columnName] : null;
    }

    /**
     * @param array $match
     * @param $field
     * @param string $alias
     * @param mixed|null $default
     * @return bool|null|string
     */
    public function findMax($match = [], $field, $alias = null, $default = null)
    {

        if (count($match) > 0) {
            $query[] = [
                '$match' => $match
            ];
        }

        $query[] = [
            '$project' => [$field => 1]
        ];

        if ($pos = strpos($field, '.') !== false) {
            $query[] = ['$unwind' => sprintf("$%s", preg_split('/\./', $field)[0])];
        }


        if ($alias == null) {
            $alias = $field;
        }


        $query[] = [
            '$group' => [
                '_id' => null,
                $alias => ['$max' => sprintf('$%s', $field)]
            ]
        ];
        \MongoCursor::$timeout = -1;
        $options = [
            //           "maxTimeMS" => -1
        ];

        $last = @$this->aggregate($query, $options);

        if ($last->count() && isset($last->current()[$alias]) and $last->current()[$alias] !== null) {
            if (gettype($last->current()[$alias]) == 'object') {

                switch (get_class($last->current()[$alias])) {
                    case 'MongoDate':
                        return date('Y-m-d h:i:s', $last->current()[$alias]->sec);
                    case 'MongoId':
                        return $last->current()[$alias]->{'$id'};
                    default:
                        return $last->current()[$alias];
                }
            }

            return $last->current()[$alias];
        }

        return $default;
    }

    /**
     * @param $pipes
     * @return \Doctrine\MongoDB\ArrayIterator
     */
    public function aggregate(array $pipes = [], $options = [])
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $this->bindAggregation($pipes);

        $cursor = $this->mongoCollection->aggregate($pipes, $options);

        return new QueryResult($cursor);
    }


    /**
     * @param $groupBy
     * @param array $where
     * @param $fields
     * @param null $unwind
     * @param null $project
     * @param null $limit
     * @return \Doctrine\MongoDB\ArrayIterator
     */
    public function group($groupBy, $where = [], $fields, $unwind = null, $project = null, $limit = null)
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $pipes = [];

        if (null !== $project) {
            $pipes[] = ['$project' => $project];
        }

        if (null !== $unwind) {
            $pipes[] = ['$unwind' => $unwind];
        }

        if (null !== $where && is_array($where) && count($where) > 1) {
            $pipes[] = ['$match' => $where];
        }

        if ($fields !== null && is_array($fields)) {
            $pipes[] = ['$group' => array_merge(['_id' => sprintf('$%s', $groupBy)], $fields)];
        }

        if ($limit !== null) {
            $pipes[] = ['$limit' => $limit];
        }

        return $this->mongoCollection
            ->aggregate($pipes);
    }


    /**
     * @param array|string $fields
     * @param array $options
     * @param null $name
     * @return $this
     */
    public function addIndex($fields = [], $options = [], $name = null)
    {
        $index = [
            'fields' => $fields,
            'options' => $options
        ];

        $index_name = $name !== null ? $name : (is_array($fields) ? implode('__', array_keys($fields)) : $fields);

        $this->indexes[$index_name] = $index;

        return $this;
    }

    /**
     * (Re)Build an index
     *
     * @param string $name
     *
     * @return array
     */
    protected function buildIndex($name)
    {
        $conf = $this->indexes[$name];

        $db = $this->mongoCollection->getDatabase();

        $func = "function() {
            print(collection);
            print(JSON.stringify(options));
            return db[collection].createIndex(keys, options);
        }";

        $result = null;

        if (is_array($conf)) {
            $keys = isset($conf['fields']) ? $conf['fields'] : [$name => 1];
            $options = isset($conf['options']) ? $conf['fields'] : [];

            $scope = array('collection' => $this->collection, 'keys' => $keys, 'options' => $options);
            $code = new \MongoCode($func, $scope);


            $result = $db->execute(
                $code,
                [
                    'keys' => $keys,
                    'options' => $options
                ]
            );
        } else {
            $scope = array('collection' => $this->collection, 'keys' => [$name => $conf], 'options' => []);
            $code = new \MongoCode($func, $scope);
            $result = $db->execute(
                $code,
                [
                    'keys' => [$name => $conf],
                    'options' => []
                ]
            );
        }

        return $result;
    }

    /**
     * Rebuild indexes for collection
     *
     * @return array
     */
    public function ensureIndexes()
    {
        if (!$this->isInitiated()) {
            $this->init();
        }

        $mappedIndexes = [];

        $realIndexes = $this->indexes;

        if (count($realIndexes) > 0) {
            $this->bind($realIndexes);

            foreach ($realIndexes as $field => &$value) {
                if (is_array($value)) {
                    $this->bind($value['fields']);
                }
            }


            $structuredIndexes = [];

            foreach ($realIndexes as $name => $value) {
                if (is_array($value)) {
                    $structuredIndexes[$name] = $value['fields'];
                } else {
                    $structuredIndexes[$name] = [
                        $name => $value
                    ];
                }
            }

            $createdIndexes = [];
            $toBuild = [];
            $toErase = [];

            $actualIndexes = $this->mongoCollection->getIndexInfo();

            foreach ($actualIndexes as $conf) {
                // default index created by mongo
                if ($conf['name'] === '_id_') {
                    continue;
                }

                $found = false;
                foreach ($structuredIndexes as $name => $key) {
                    $found |= array_keys($key) === array_keys($conf['key']);

                    if ($found) {

                        foreach ($key as $k => $v) {
                            if ($v !== $conf['key'][$k]) {
                                $toErase[] = $conf ['key'];
                                $toBuild[] = $name;
                                break;
                            }
                        }

                        break;
                    }
                }

                if (!$found) {
                    $toErase[] = $conf['key'];
                }

            }

            foreach ($structuredIndexes as $name => $keys) {
                $isNew = true;

                foreach ($actualIndexes as $actualConf) {
                    if ($actualConf['key'] === $keys) {
                        $isNew = false;
                        break;
                    }

                }

                if ($isNew && !in_array($name, $toBuild)) {
                    $toBuild[] = $name;
                }
            }

            foreach ($toErase as $index) {
                $this->mongoCollection->deleteIndex($index);
            }

            /**
             * check indexes
             */
            foreach ($toBuild as $name) {
                $createdIndexes[] = $name;
                $this->buildIndex($name);
            }


            return $createdIndexes;
        }


        return $mappedIndexes;
    }

    public function getFieldIndex($field)
    {
        $indexes = $this->mongoCollection->getIndexInfo();

        if ($this->mongoCollection->isFieldIndexed($field)) {
            foreach ($indexes as $index) {
                if (in_array($field, array_keys($index['key']))) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * True if an object contains mongo update operators, which means it's an update query
     *
     * @param array $object
     * @return bool
     */
    protected function hasUpdateOperators(array $object = null)
    {
        if (null === $object || !is_array($object)) {
            return false;
        }

        $keys = array_keys($object);

        foreach ($keys as $key) {
            preg_match('/\$(?P<key>\w+)/', $key, $matches);

            if (count($matches) > 0) {
                unset($keys);

                return true;
            }
        }

        unset($keys);

        return false;
    }

    /**
     * Binds an object using mapper. If mapper is not set, return the object without modifications
     *
     * @param $object
     * @return array|null
     */
    public function bind(&$object)
    {

        return $object;
    }

    /**
     * Remap a query using the mapper
     * Inused now, waiting for mapper fct
     * @param $query
     * @return array
     */
    public function bindQuery(&$query)
    {

        return $query;
    }

    /**
     * Inused now, waiting mapper fct
     * @param array $pipe
     * @return array
     */
    public function bindAggregation(array &$pipe = [])
    {

        return $pipe;
    }

    /**
     * Unbind an object using the mapper
     * Inused now, waiting mapper fct
     * @param $object
     * @return array|null
     */
    public function unbind(&$object)
    {

        return $object;
    }

    /**
     * @param string $collection
     * @return $this
     */
    public function setCollection($collection)
    {
        $this->collection = $collection;

        return $this;
    }

    /**
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * @param \Pouzor\MongoBundle\Mongo\MongoManager $manager
     * @return $this
     */
    public function setManager($manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * @return \Pouzor\MongoBundle\Mongo\MongoManager
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * @param \Pouzor\MongoBundle\Mongo\Pool\PoolInterface $pool
     * @return mixed|void
     */
    public function setPool(PoolInterface $pool)
    {
        $this->pool = $pool;

        return $this;
    }

    /**
     * @return \Pouzor\MongoBundle\Mongo\Pool\PoolInterface
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param \Pouzor\MongoBundle\Common\Dispatcher\DispatcherInterface $dispatcher
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return \Pouzor\MongoBundle\Common\Dispatcher\DispatcherInterface
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }


    /**
     * @return array
     */
    public function getBindingConfiguration()
    {
        return $this->bindingConfiguration;
    }

    /**
     * @param $bindingConfiguration
     * @return $this
     * @throws \Exception
     */
    public function setBindingConfiguration($bindingConfiguration)
    {
        $this->bindingConfiguration = $bindingConfiguration;

        $this->init();

        return $this;
    }

    /**
     * @param array $indexes
     */
    public function setIndexes(array $indexes = [])
    {
        $this->indexes = $indexes;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->collection;
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
    public function __toString()
    {
        return $this->getName();
    }


}

