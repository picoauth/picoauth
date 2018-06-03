<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * Base File Storage
 */
class FileStorage
{

    /**
     * Configuration file location, redefined in subclasses
     */
    const CONFIG_FILE = '';
    
    /**
     * The configuration array
     * @var array
     */
    protected $config;

    /**
     * The Configurator instance
     * @var null|Configurator\AbstractConfigurator
     */
    protected $configurator;
    
    /**
     * Cache implementation
     * @var \Psr\SimpleCache\CacheInterface
     */
    protected $cache;
    
    /**
     * Base directory
     * @var string
     */
    protected $dir;

    /**
     * The last error message
     * @var string
     */
    protected static $lastError;

    /**
     * Reads contents of a file to a string
     *
     * Acquires blocking shared lock for the file.
     *
     * @param string $fileName Name of the file to read.
     * @param array $options Options for the Reader instance
     * @return string|bool String with the file contents on success, FALSE otherwise.
     */
    public static function readFile($fileName, $options = [])
    {
        $reader = new File\FileReader($fileName, $options);
        $success = true;
        $contents = null;

        try {
            $reader->open();
            $contents = $reader->read();
        } catch (\RuntimeException $e) {
            self::$lastError = $e->getMessage();
            $success = false;
        }

        try {
            $reader->close();
        } catch (\RuntimeException $e) {
            self::$lastError = $e->getMessage();
            $success = false;
        }

        return ($success) ? $contents : false;
    }

    /**
     * Writes data to a file
     *
     * @param string $fileName Name of the file
     * @param string $data File contents to write
     * @param array $options Options for the Writer instance
     * @return bool Was the write operation successful
     */
    public static function writeFile($fileName, $data, $options = [])
    {
        $writer = new File\FileWriter($fileName, $options);
        $isSuccess = true;
        $written = 0;

        try {
            $writer->open();
            $written = $writer->write($data);
        } catch (\RuntimeException $e) {
            self::$lastError = $e->getMessage();
            $isSuccess = false;
        }

        try {
            $writer->close();
        } catch (\RuntimeException $e) {
            self::$lastError = $e->getMessage();
            $isSuccess = false;
        }

        return $isSuccess;
    }

    /**
     * Prepares path
     *
     * Check if $path relative to $basePath exists and is writable.
     * Attempts to create it if it does not exist. Warning is supressed
     * in case of mkdir failure.
     *
     * @param string $basePath
     * @param string $path
     */
    public static function preparePath($basePath, $path)
    {
        $basePath = rtrim($basePath, '/');
        $path = ltrim($path, '/');
        $fullPath = $basePath . '/' . $path;

        if (file_exists($fullPath)) {
            if (!is_dir($fullPath)) {
                throw new \RuntimeException("Cannot create a directory, regular file already exists: {$path}.");
            }
            if (!is_writable($fullPath)) {
                throw new \RuntimeException("Directory is not writable: {$path}.");
            }
        } else {
            $res=@mkdir($fullPath, 0770, true);
            if (!$res) {
                throw new \RuntimeException("Unable to create a directory: {$path}.");
            }
        }
    }

    /**
     * Get item by URL
     *
     * Find an item that applies to a given url
     * Url keys must begin with a /
     *
     * @param array|null $items
     * @param string $url
     * @return array|null
     */
    public static function getItemByUrl($items, $url)
    {
        if (!isset($items)) {
            return null;
        }

        // Check for the exact rule
        if (array_key_exists("/" . $url, $items)) {
            return $items["/" . $url];
        }

        $urlParts = explode("/", trim($url, "/"));
        $urlPartsLen = count($urlParts);

        while ($urlPartsLen > 0) {
            unset($urlParts[--$urlPartsLen]);

            $subUrl = "/" . join("/", $urlParts);

            // Use the higher level rule, if it doesn't have deactivated recursive application
            if (array_key_exists($subUrl, $items)
                && (!isset($items[$subUrl]["recursive"])
                        || $items[$subUrl]["recursive"]===true)) {
                return $items[$subUrl];
            }
        }

        return null;
    }

    /**
     * Returns the configuration
     *
     * Reads the configuration if it hasn't been already read.
     *
     * @return Array
     */
    public function getConfiguration()
    {
        $this->readConfiguration();
        return $this->config;
    }

    /**
     * Configuration validation
     *
     * Calls the specific configurator instance, which validates the user
     * provided configuration and adds default values.
     *
     * @param mixed $userConfig Anything read from the configuration file
     * @return array Modified configuration (added defaults)
     * @throws \RuntimeException Missing configurator
     * @throws ConfigurationException Validation error
     */
    protected function validateConfiguration($userConfig = array())
    {
        if (!$this->configurator) {
            throw new \RuntimeException("Configurator class is not set.");
        }
        
        try {
            return $this->configurator->validate($userConfig);
        } catch (ConfigurationException $e) {
            $e->addBeforeMessage("Configuration error in ".static::CONFIG_FILE.":");
            throw $e;
        }
    }
    
    /**
     * Initializes the configuration array
     *
     * If the file exists, tries to read from the cache first, if the cache
     * has the data, but the file is newer, reads the file and renews the cache.
     *
     * md5 is used as a cache key, because some cache providers may not accept
     * characters present in the file path.
     * Collisions or reversibility do not present security concerns.
     *
     * @throws \RuntimeException On file read error
     */
    protected function readConfiguration()
    {
        // Return if already loaded
        if (is_array($this->config)) {
            return;
        }
        
        $fileName = $this->dir . static::CONFIG_FILE;
        
        // Abort if the file doesn't exist
        if (!file_exists($fileName)) {
            $this->config = $this->validateConfiguration();
            return;
        }
        
        $modifyTime = filemtime($fileName);
        if (false === $modifyTime) {
            throw new \RuntimeException("Unable to get mtime of the configuration file.");
        }
        
        // Check if the configuration is in cache
        $cacheKey = md5($fileName);
        if ($this->cache->has($cacheKey)) {
            $this->config=$this->cache->get($cacheKey);
            
            // Cached file is up to date
            if ($this->config["_mtime"] === $modifyTime) {
                return;
            }
        }

        if (($yaml = self::readFile($fileName)) !== false) {
            $config = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $this->config = $this->validateConfiguration($config);
        } else {
            throw new \RuntimeException("Unable to read configuration file.");
        }
        
        // Save to cache with updated modify-time
        $storedConfig = $this->config;
        $storedConfig["_mtime"]= $modifyTime;
        $this->cache->set($cacheKey, $storedConfig);
    }
}
