<?php

namespace PicoAuth\Storage\File;

/**
 * File
 *
 * Provides file locking methods and other generic methods over an opened file
 */
class File
{

    /**
     * Number of maximum repeat attempts to lock a file
     */
    const LOCK_MAX_TRIES = 100;

    /**
     * Microseconds to wait before next lock try
     */
    const LOCK_RETRY_WAIT = 20;

    /**
     * File resource
     * @var mixed
     */
    protected $handle;

    /**
     * File location
     * @var string
     */
    protected $filePath;
    
    /**
     * Options
     * @var array
     */
    protected $options;

    /**
     * Creates a file instance
     *
     * @param string $path File path
     * @param array $options Options array
     */
    public function __construct($path, $options = [])
    {
        $this->filePath = $path;
        $this->options = $options;
        
        // Apply default options
        $this->options += array(
            "blocking" => true,
            "backup" => false
        );
        
        // If an opened file is specified
        if (isset($this->options["handle"])) {
            $this->handle = $this->options["handle"];
            unset($this->options["handle"]);
            if (!$this->isOpened()) {
                throw new \InvalidArgumentException("The file must be opened.");
            }
        }
    }

    /**
     * Checks if a file is opened
     *
     * @return bool
     */
    public function isOpened()
    {
        return is_resource($this->handle);
    }

    /**
     * Obtains a file lock
     *
     * @param int $lockType Lock type PHP constant
     * @return boolean true on successful lock, false otherwise
     */
    public function lock($lockType)
    {
        if (!$this->isOpened()) {
            return false;
        }

        if ($this->options["blocking"]) {
            return flock($this->handle, $lockType);
        } else {
            $tries = 0;
            do {
                if (flock($this->handle, $lockType | LOCK_NB)) {
                    return true;
                } else {
                    ++$tries;
                    usleep(self::LOCK_RETRY_WAIT);
                }
            } while ($tries < self::LOCK_MAX_TRIES);

            return false;
        }
    }

    /**
     * Unlocks the file
     *
     * @throws \RuntimeException
     */
    public function unlock()
    {
        if (!flock($this->handle, LOCK_UN)) {
            throw new \RuntimeException("Could not unlock file");
        }
    }

    /**
     * Closes the file
     *
     * @return void
     * @throws \RuntimeException
     */
    public function close()
    {
        if (!$this->isOpened()) {
            return;
        }

        $this->unlock();

        if ($this->handle && !fclose($this->handle)) {
            throw new \RuntimeException("Could not close file " . $this->filePath);
        }
    }
    
    /**
     * Returns the file handle
     *
     * @return resource|null
     */
    public function getHandle()
    {
        return ($this->isOpened()) ? $this->handle : null;
    }
}
