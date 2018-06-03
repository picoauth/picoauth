<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\RateLimitStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\RateLimitConfigurator;

/**
 * Sqlite storage for rate limiting
 *
 * Still uses FileStorage for the configuration file,
 * the rate limiting data are stored in Sqlite 3 database.
 */
class RateLimitSqliteStorage extends FileStorage implements RateLimitStorageInterface
{

    /**
     * Database location
     */
    const DB_NAME = 'PicoAuth/data/ratelimit.db';

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/RateLimit.yml';

    /**
     * SQLite 3 Database instance
     *
     * @var \SQLite3|null
     */
    protected $db = null;
    
    /**
     * Storage options
     * @var array
     */
    protected $options;

    public function __construct($dir, CacheInterface $cache = null, array $options = [])
    {
        if (!extension_loaded("sqlite3")) {
            throw new \RuntimeException("Extension sqlite3 is required for RateLimitSqliteStorage.");
        }
        
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new RateLimitConfigurator;
        
        // Set options and apply defaults
        $this->options = $options;
        $this->options += array(
            "busyTimeout" => 10000
        );
        
        $bt = $this->options["busyTimeout"];
        if (!is_int($bt) || $bt<0) {
            throw new \InvalidArgumentException("Invalid busyTimeout value.");
        }
    }
    
    /**
     * @inheritdoc
     */
    public function getLimitFor($action, $type, $key)
    {
        $stmt = $this->getStatement("SELECT ts, cnt FROM limits WHERE
            action=:action AND type=:type AND entity=:key;");

        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        
        if (!($res = $stmt->execute())) {
            throw new \RuntimeException("Could not execute getLimitFor statement.");
        }
        
        $arr = $res->fetchArray(SQLITE3_ASSOC);
        $stmt->close();
            
        return $arr ? $arr : null;
    }
    
    /**
     * @inheritdoc
     */
    public function updateLimitFor($action, $type, $key, $limitData)
    {
        $stmt = $this->getStatement("INSERT OR REPLACE INTO limits (action, type, entity, ts, cnt)
            VALUES (:action, :type, :key, :ts, :cnt);");
        
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':key', $key, SQLITE3_TEXT);
        $stmt->bindValue(':ts', $limitData["ts"], SQLITE3_INTEGER);
        $stmt->bindValue(':cnt', $limitData["cnt"], SQLITE3_INTEGER);

        if (!($res = $stmt->execute())) {
            throw new \RuntimeException("Could not execute updateLimitFor statement.");
        }
        
        $stmt->close();
    }

    /**
     * @inheritdoc
     */
    public function cleanup($action, $type, $config)
    {
        $stmt = $this->getStatement("DELETE FROM limits WHERE ts < :time;");
        
        // Delete expired records
        $threshold = time() - $config["counterTimeout"];
        $stmt->bindValue(':time', $threshold, SQLITE3_INTEGER);
        
        if (!($res = $stmt->execute())) {
            throw new \RuntimeException("Could not execute cleanup statement.");
        }

        $stmt->close();
        
        // Number of deleted rows
        return $this->db->changes();
    }

    /**
     * {@inheritdoc}
     *
     * In file storage, save() is called after all changes.
     * In sqlite storage the changes are persisted directly, in the above
     * method calls, so no action is needed.
     */
    public function save($action, $type)
    {
        return;
    }
    
    /**
     * @inheritdoc
     */
    public function transaction($action, $type, $state)
    {
        switch ($state) {
            // Start transaction
            case self::TRANSACTION_BEGIN:
                $this->openDatabase();

                if (!$this->db->exec("BEGIN EXCLUSIVE TRANSACTION;")) {
                    throw new \RuntimeException("Could not begin transaction.");
                }
                
                break;

            // Commit
            case self::TRANSACTION_END:
                if (!$this->db) {
                    throw new \InvalidArgumentException("Cannot end transaction in closed DB.");
                }

                if (!$this->db->exec("END TRANSACTION;")) {
                    throw new \RuntimeException("Could not commit transaction.");
                }
                
                // Close the database
                if (!$this->db->close()) {
                    throw new \RuntimeException("Could not close the database.");
                }
                
                $this->db=null;
        
                break;

            default:
                throw new \InvalidArgumentException("Unexpected transaction argument.");
        }
    }
    
    /**
     * Prepares a statement
     *
     * @param string $query
     * @return \SQLite3Stmt
     * @throws \RuntimeException
     */
    protected function getStatement($query)
    {
        $this->openDatabase();
        
        if (!($stmt = $this->db->prepare($query))) {
            throw new \RuntimeException("Could not prepare statement.");
        }
        
        return $stmt;
    }
    
    /**
     * Opens the database if not opened
     *
     * Also creates the schema if not created
     *
     * @return void
     */
    protected function openDatabase()
    {
        // Return if already conntected
        if ($this->db) {
            return;
        }
        
        // Open or create the database file
        $dbPath = $this->dir . self::DB_NAME;
        if (!\file_exists($dbPath)) {
            self::preparePath($this->dir, dirname(self::DB_NAME));
        }
        
        // Opens the database file (will throw \Exception on failure)
        $this->db = new \SQLite3($dbPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        
        // Maximum waiting time
        if (!$this->db->busyTimeout($this->options["busyTimeout"])) {
            throw new \RuntimeException("Could not set busy timout.");
        }

        // Create schema if not exists
        $res = $this->db->exec("
        CREATE TABLE IF NOT EXISTS limits (
            id INTEGER PRIMARY KEY,
            action TEXT NOT NULL,
            type TEXT NOT NULL,
            entity TEXT NOT NULL,
            ts INTEGER NOT NULL,
            cnt INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS limitIndex ON limits (action,type,entity);
        CREATE INDEX IF NOT EXISTS tsIndex ON limits (ts);
        ");

        if (!$res) {
            throw new \RuntimeException("Could not create table schema.");
        }
    }
}
