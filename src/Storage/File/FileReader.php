<?php

namespace PicoAuth\Storage\File;

/**
 * File reader
 *
 * Respects the locks while reading
 */
class FileReader extends File
{

    const OPEN_MODE = 'r';

    /**
     * Opens the file for reading
     *
     * Obtains a shared lock for the file
     * @return void
     * @throws \RuntimeException On read/lock error
     */
    public function open()
    {
        if ($this->isOpened()) {
            return;
        }

        if (!file_exists($this->filePath)) {
            throw new \RuntimeException($this->filePath . " does not exist");
        }

        $this->handle = @fopen($this->filePath, self::OPEN_MODE);
        if ($this->handle === false) {
            throw new \RuntimeException("Could not open file for reading: " . $this->filePath);
        }

        if (!$this->lock(LOCK_SH)) {
            $this->close();
            throw new \RuntimeException("Could not aquire a shared lock for " . $this->filePath);
        }
    }

    /**
     * Reads contents of the opened file
     *
     * @return string Read data
     * @throws \RuntimeException On read error
     */
    public function read()
    {
        $this->open();

        // Better performance than fread() from the existing handle
        // but it doesn't respect flock
        $data = file_get_contents($this->filePath);

        if ($data === false) {
            throw new \RuntimeException("Could not read from file " . $this->filePath);
        }
        return $data;
    }
}
