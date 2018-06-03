<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\LocalAuthStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\LocalAuthConfigurator;
use PicoAuth\Storage\File\FileWriter;

/**
 * File storage for LocalAuth
 */
class LocalAuthFileStorage extends FileStorage implements LocalAuthStorageInterface
{

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/LocalAuth.yml';

    /**
     * Directory with user files
     */
    const USERS_DIR = 'PicoAuth/users';
    
    /**
     * Extension of user files
     */
    const USERFILE_EXT = '.yml';

    /**
     * Reset tokens filename
     */
    const RESET_TOKENS = 'PicoAuth/data/reset_tokens.yml';

    /**
     * Already fetched users
     *
     * @var array
     */
    protected $fetchedUsers = array();
    
    /**
     * Number of users
     *
     * @var int
     */
    protected $usersCount;
    
    /**
     * The LocalAuthConfigurator instance
     * @var Configurator\LocalAuthConfigurator
     */
    protected $configurator;
    
    public function __construct($dir, CacheInterface $cache = null)
    {
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new LocalAuthConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function getUserByName($name)
    {

        // Check if the user wasn't already fetched during this request
        if (isset($this->fetchedUsers[$name])) {
            return $this->fetchedUsers[$name];
        }

        // Validate correct username format before search
        try {
            $this->checkValidName($name);
        } catch (\RuntimeException $e) {
            return null;
        }

        // Search in user files
        $userFileName = $this->dir . self::USERS_DIR . '/' . $name . self::USERFILE_EXT;

        if (($yaml = self::readFile($userFileName)) !== false) {
            $userData = \Symfony\Component\Yaml\Yaml::parse($yaml);
            $this->configurator->validateUserData($userData);
            $this->fetchedUsers[$name] = $userData;
            return $userData;
        }

        // Search in the main configuration file
        $this->readConfiguration();
        if (isset($this->config['users']) && isset($this->config['users'][$name])) {
            $this->fetchedUsers[$name] = $this->config['users'][$name];
            return $this->config['users'][$name];
        }
        
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getUserByEmail($email)
    {
        // Search in user files
        $searchDir = $this->dir . self::USERS_DIR;
        if (is_dir($searchDir)) {
            $userFiles = $this->getDirFiles($searchDir);
            foreach ($userFiles as $filename) {
                $username = substr($filename, 0, -strlen(self::USERFILE_EXT));
                $user = $this->getUserByName($username);
                if (isset($user['email']) && $email === $user['email']) {
                    $user['name'] = $username;
                    return $user;
                }
            }
        }

        // Search in the main configuration file
        if (isset($this->config['users'])) {
            foreach ($this->config['users'] as $name => $userData) {
                if (isset($userData['email']) && $email === $userData['email']) {
                    $userData['name'] = $name;
                    return $userData;
                }
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function saveUser($id, $userdata)
    {
        $dir = $this->dir . self::USERS_DIR;
        self::preparePath($this->dir, self::USERS_DIR);

        $yaml = \Symfony\Component\Yaml\Yaml::dump($userdata, 2, 2);

        $userFileName = $dir . '/' . $id . self::USERFILE_EXT;
        
        // Write the new user file
        if ((self::writeFile($userFileName, $yaml, ["backup" => true]) === false)) {
            throw new \RuntimeException("Unable to save the user data (". basename($userFileName).").");
        }

        // If exists, remove the user record from the main configuration file
        // Needs write permission for users.yml
        $this->readConfiguration();
        if (isset($this->config['users']) && isset($this->config['users'][$id])) {
            unset($this->config['users'][$id]);
            $yaml = \Symfony\Component\Yaml\Yaml::dump($this->config, 3, 2);
            $fileName = $this->dir . static::CONFIG_FILE;
            if ((self::writeFile($fileName, $yaml, ["backup" => true]) === false)) {
                throw new \RuntimeException("Unable to save new configuration (".static::CONFIG_FILE.").");
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * When adding a new token entry, the file must be read first, the token
     * added and then saved. An exclusive access to the token file must be held
     * since the initial read in order to avoid lost writes.
     */
    public function saveResetToken($id, $data)
    {
        $fileName = $this->dir . self::RESET_TOKENS;
        $tokens = array();
        $writer = new FileWriter($fileName);

        // Get exclusive access and read the current tokens
        try {
            $writer->open();
            $reader = new \PicoAuth\Storage\File\FileReader(
                $fileName,
                ["handle"=>$writer->getHandle()]
            );
            $yaml = $reader->read();
            $tokens = \Symfony\Component\Yaml\Yaml::parse($yaml);
        } catch (\RuntimeException $e) {
            // File doesn't exist, no write permission, read or parse error
            // If not writeable, will fail in saveResetTokens
            $tokens = array();
        }

        // Add the new token entry
        $tokens[$id] = $data;

        // Save the token file, while keeping the same file lock
        $this->saveResetTokens($tokens, $writer);
    }

    /**
     * Saves reset tokens
     *
     * @param array $tokens Tokens array
     * @param FileWriter $writer Optional file writer to use
     * @throws \RuntimeException On save error
     */
    protected function saveResetTokens($tokens, FileWriter $writer = null)
    {
        // Before saving, remove all expired tokens
        $time = time();
        foreach ($tokens as $id => $token) {
            if ($time > $token['valid']) {
                unset($tokens[$id]);
            }
        }

        $fileName = $this->dir . self::RESET_TOKENS;
        $yaml = \Symfony\Component\Yaml\Yaml::dump($tokens, 1, 2);
        
        if ($writer && $writer->isOpened()) {
            // An exclusive lock is already held, then use the given writer instance
            $writer->write($yaml); // Will throw on write error
        } else {
            self::preparePath($this->dir, dirname(self::RESET_TOKENS));
            if ((self::writeFile($fileName, $yaml) === false)) {
                throw new \RuntimeException("Unable to save token file (".self::RESET_TOKENS.").");
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getResetToken($id)
    {
        $fileName = $this->dir . self::RESET_TOKENS;
        $tokens = array();

        if (($yaml = self::readFile($fileName)) !== false) {
            $tokens = \Symfony\Component\Yaml\Yaml::parse($yaml);
        }

        if (isset($tokens[$id])) {
            $token = $tokens[$id];
            unset($tokens[$id]);                // Remove the token when retrieved
            $this->saveResetTokens($tokens);
            return $token;
        } else {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function checkValidName($name)
    {
        if (!$this->configurator->checkValidNameFormat($name)) {
            throw new \RuntimeException("A username can contain only alphanumeric characters.");
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getUsersCount()
    {
        if (!isset($this->usersCount)) {
            try {
                $files = $this->getDirFiles($this->dir . self::USERS_DIR);
                $this->usersCount=count($files);
            } catch (\RuntimeException $e) {
                // Return 0 if the users directory cannot be listed (no users yet)
                $this->usersCount=0;
            }
        }
        
        return $this->usersCount;
    }

    /**
     * Gets directory files
     *
     * @param string $searchDir Searched directory
     * @return array File names
     * @throws \RuntimeException On read error
     */
    protected function getDirFiles($searchDir)
    {
        // Error state is handled by the excpetion, warning disabled
        $files = @scandir($searchDir, SCANDIR_SORT_NONE);
        if ($files === false) {
            throw new \RuntimeException("Cannot list directory contents: {$searchDir}.");
        }

        return array_diff($files, array('..', '.'));
    }
}
