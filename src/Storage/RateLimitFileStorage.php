<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\RateLimitStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\RateLimitConfigurator;

/**
 * File storage for RateLimit
 */
class RateLimitFileStorage extends FileStorage implements RateLimitStorageInterface
{

    /**
     * Data directory
     */
    const DATA_DIR = 'PicoAuth/data/';

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/RateLimit.yml';

    /**
     * Configuration of the limits
     * @var array
     */
    protected $limits = array();
    
    /**
     * Transaction files
     * @var \PicoAuth\Storage\File\FileReader[]
     */
    protected $tFiles = array();
    
    public function __construct($dir, CacheInterface $cache = null)
    {
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new RateLimitConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function getLimitFor($action, $type, $key)
    {
        $id = $this->getId($action, $type);
        $fileName = $this->dir . static::DATA_DIR . $id;

        if (isset($this->tFiles[$id])) {
            $file = $this->tFiles[$id]->read();
            if (!$file) {
                $this->limits[$id] = array();
            } else {
                $this->limits[$id] = unserialize($file);
            }
        } elseif (\file_exists($fileName)) {
            if (($file = self::readFile($fileName)) === false) {
                throw new \RuntimeException("Unable to read limit file.");
            }
            $this->limits[$id] = unserialize($file);
        } else {
            $this->limits[$id] = array();
        }
        
        return (isset($this->limits[$id][$key])) ? $this->limits[$id][$key] : null;
    }

    /**
     * @inheritdoc
     */
    public function updateLimitFor($action, $type, $key, $limitData)
    {
        $id = $this->getId($action, $type);
        $this->limits[$id][$key] = $limitData;
    }

    /**
     * @inheritdoc
     */
    public function cleanup($action, $type, $config)
    {
        $id = $this->getId($action, $type);
        $time = time();
        $removed = 0;
        foreach ($this->limits[$id] as $key => $limit) {
            if ($time > $limit["ts"] + $config["counterTimeout"]) {
                unset($this->limits[$id][$key]);
                ++$removed;
            }
        }
        
        return $removed;
    }

    /**
     * {@inheritdoc}
     *
     * Save must be part of an exclusive transaction.
     */
    public function save($action, $type)
    {
        $id = $this->getId($action, $type);
        $fileName = $this->dir . static::DATA_DIR . $id;
        $file = serialize($this->limits[$id]);

        // Write the updated records
        if (isset($this->tFiles[$id]) && $this->tFiles[$id]->isOpened()) {
            $writer = new \PicoAuth\Storage\File\FileWriter(
                $fileName,
                ["handle"=>$this->tFiles[$id]->getHandle()]
            );
            $writer->write($file);
        } else {
            throw new \RuntimeException("Transaction file not opened.");
        }
    }

    /**
     * @inheritdoc
     */
    public function transaction($action, $type, $state)
    {
        $id = $this->getId($action, $type);
        
        switch ($state) {
            // Start transaction
            case self::TRANSACTION_BEGIN:
                $this->openTransactionFile($id);
                if (!$this->tFiles[$id]->lock(LOCK_EX)) {
                    throw new \RuntimeException("Could not lock the file.");
                }
                break;

            // Commit
            case self::TRANSACTION_END:
                if (!isset($this->tFiles[$id])) {
                    throw new \RuntimeException("Transation is not openened, cannot close.");
                }
                $this->tFiles[$id]->unlock();
                $this->tFiles[$id]->close();
                break;

            default:
                throw new \InvalidArgumentException("Unexpected transaction argument.");
        }
    }

    /**
     * Opens the transaction file
     *
     * This file is opened for read and write operations and it's lock
     * will stay active during the transaction calls started and ended with
     * {@see RateLimitFileStorage::transaction()}
     *
     * @param string $id action-type file id
     * @throws \RuntimeException On file open error
     */
    private function openTransactionFile($id)
    {
        if (!isset($this->tFiles[$id])) {
            self::preparePath($this->dir, self::DATA_DIR);
            $fileName = $this->dir . static::DATA_DIR . $id;

            $handle = @fopen($fileName, 'c+');
            if ($handle === false) {
                throw new \RuntimeException("Could not open file: " . $fileName);
            }
            $this->tFiles[$id] = new \PicoAuth\Storage\File\FileReader(
                $fileName,
                ["handle"=>$handle]
            );
        }
    }
    
    /**
     * Get a single identifier for action-type pair
     *
     * @param string $action
     * @param string $type
     * @return string
     */
    protected function getId($action, $type)
    {
        return $action . "_" . $type;
    }
}
