<?php

namespace PicoAuth\Storage\File;

/**
 * File writer
 *
 * Opens the file with an exclusive access
 */
class FileWriter extends File
{

    /**
     * Mode the file will be opened with
     */
    const OPEN_MODE = 'c+';

    /**
     * Will be set to true if write error/s occurred
     * @var bool
     */
    protected $writeErrors = false;

    /**
     * File name of the write backup file, if used
     * @var string|null
     */
    protected $bkFilePath = null;
    
    /**
     * Opens the file for writing
     *
     * Obtains an exclusive lock
     * Creates a backup file if the corresponding class option is set
     *
     * @return void
     * @throws \RuntimeException On open/lock error
     */
    public function open()
    {
        if ($this->isOpened()) {
            return;
        }

        $this->handle = @fopen($this->filePath, self::OPEN_MODE);
        if ($this->handle === false) {
            throw new \RuntimeException("Could not open file for writing: " . $this->filePath);
        }

        if (!$this->lock(LOCK_EX)) {
            $this->close();
            throw new \RuntimeException("Could not aquire an exclusive lock for " . $this->filePath);
        }

        if ($this->options["backup"]) {
            $this->createBkFile();
        }
        
        $this->writeErrors = false;
    }

    /**
     * Writes contents of the file
     *
     * @param string $data Data to write
     * @throws \InvalidArgumentException data not a string
     * @throws \RuntimeException Write error
     */
    public function write($data)
    {
        if (!is_string($data)) {
            throw new \InvalidArgumentException("The data is not a string.");
        }

        $this->open();

        if (!ftruncate($this->handle, 0)) {
            $this->writeErrors = true;
            throw new \RuntimeException("Could not truncate file " . $this->filePath);
        }
        
        fseek($this->handle, 0);
        
        $res = fwrite($this->handle, $data);
        if (strlen($data) !== $res) {
            $this->writeErrors = true;
            throw new \RuntimeException("Could not write to file " . $this->filePath);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function close()
    {
        // Unlock and close the main file
        parent::close();
        
        // Remove the backup file if there were no write errors or errors
        // when closing the file (above call would throw an exception)
        $this->removeBkFile();
    }

    /**
     * Creates a backup file before writing
     *
     * If there is no write permission for the directory the original file is in,
     * the backup file is not created. That way this option can be disabled,
     * if not considered as needed.
     *
     * @throws \RuntimeException On write error
     */
    protected function createBkFile()
    {
        if (!is_writable(dirname($this->filePath))) {
            return;
        }
        
        $this->bkFilePath = $this->filePath . '.' . date("y-m-d-H-i-s") . '.bak';
        $bkHandle = @fopen($this->bkFilePath, 'x+');
        if ($bkHandle === false) {
            $this->close();
            throw new \RuntimeException("Could not create a temporary file " . $this->bkFilePath);
        }
        $stat = fstat($this->handle);
        if (stream_copy_to_stream($this->handle, $bkHandle) !== $stat['size']) {
            $this->close();
            throw new \RuntimeException("Could not create a copy of " . $this->filePath);
        }
        
        if (!fclose($bkHandle)) {
            throw new \RuntimeException("Could not close a backup file " . $this->bkFilePath);
        }
        
        fseek($this->handle, 0);
    }

    /**
     * Closes the backup file
     *
     * Removes it if the write operation to the original file ended successfully
     *
     * @return void
     */
    protected function removeBkFile()
    {
        if (!$this->options["backup"]) {
            return;
        }

        // Remove backup file if the write was successful
        if (!$this->writeErrors && $this->bkFilePath) {
            unlink($this->bkFilePath);
        }
    }
}
