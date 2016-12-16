<?php


namespace Pouzor\MongoBundle\Mongo\Logger;

use Psr\Log\LoggerInterface as PsrLoggerInterface;


/**
 * MongoDB Logger
 *
 * @author Charles Sanquer <charles.sanquer.ext@francetv.fr>
 */
class Logger implements LoggerInterface
{
    /**
     *
     * @var string
     */
    protected $prefix;

    /**
     * @var int
     */
    protected $nbQueries = 0;

    /**
     *
     * @var PsrLoggerInterface
     */
    protected $logger;

    /**
     * @param PsrLoggerInterface $logger
     * @param string $prefix
     */
    public function __construct(PsrLoggerInterface $logger = null, $prefix = 'MongoDB query')
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    /**
     * @param $query
     * @return mixed|void
     */
    public function logQuery(array $query)
    {
        ++$this->nbQueries;

        if (null !== $this->logger) {
            $this->logger->info($this->prefix . static::formatQuery($query));
        }
    }

    /**
     * @return array|bool
     */
    public function getQueries()
    {
        $logger = $this->logger->getDebugLogger();

        if (!$logger) {
            return false;
        }

        $offset = strlen($this->prefix);
        $mapper = function ($log) use ($offset) {
            if (0 === strpos($log['message'], $this->prefix)) {
                return substr($log['message'], $offset);
            }
        };

        // map queries from logs, remove empty entries and re-index the array
        return array_values(array_filter(array_map($mapper, $logger->getLogs())));
    }

    /**
     * Formats the supplied query array recursively.
     *
     * @param array $query All or part of a query array
     *
     * @return string A serialized object for the log
     */
    static protected function formatQuery(array $query)
    {
        $parts = array();

        $array = true;

        foreach ($query as $key => $value) {
            if (!is_numeric($key)) {
                $array = false;
            }

            if (is_bool($value)) {
                $formatted = $value ? 'true' : 'false';
            } elseif (is_scalar($value)) {
                $formatted = '"' . $value . '"';
            } elseif ($value instanceof \stdClass) {
                $formatted = static::formatQuery((array)$value);
            } elseif (is_array($value)) {
                $formatted = static::formatQuery($value);
            } elseif ($value instanceof \MongoId) {
                $formatted = 'ObjectId("' . $value . '")';
            } elseif ($value instanceof \MongoDate) {
                $formatted = 'new Date("' . date('r', $value->sec) . '")';
            } elseif ($value instanceof \DateTime) {
                $formatted = 'new Date("' . date('r', $value->getTimestamp()) . '")';
            } elseif ($value instanceof \MongoRegex) {
                $formatted = 'new RegExp("' . $value->regex . '", "' . $value->flags . '")';
            } elseif ($value instanceof \MongoMinKey) {
                $formatted = 'new MinKey()';
            } elseif ($value instanceof \MongoMaxKey) {
                $formatted = 'new MaxKey()';
            } elseif ($value instanceof \MongoBinData) {
                $formatted = 'new BinData("' . $value->bin . '", "' . $value->type . '")';
            } else {
                $formatted = (string)$value;
            }

            $parts['"' . $key . '"'] = $formatted;
        }

        if (0 == count($parts)) {
            return $array ? '[ ]' : '{ }';
        }

        if ($array) {
            return '[ ' . implode(', ', $parts) . ' ]';
        } else {
            $mapper = function ($key, $value) {
                return $key . ': ' . $value;
            };

            return '{ ' . implode(', ', array_map($mapper, array_keys($parts), array_values($parts))) . ' }';
        }
    }
} 