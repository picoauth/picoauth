<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\PageLockStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\PageLockConfigurator;

/**
 * File storage for PageLock
 */
class PageLockFileStorage extends FileStorage implements PageLockStorageInterface
{

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/PageLock.yml';

    public function __construct($dir, CacheInterface $cache = null)
    {
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new PageLockConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function getLockById($lockId)
    {
        $this->readConfiguration();
        
        return isset($this->config['locks'][$lockId]) ? $this->config['locks'][$lockId] : null;
    }

    /**
     * @inheritdoc
     */
    public function getLockByURL($url)
    {
        $this->readConfiguration();

        $urlRecord = self::getItemByUrl($this->config['urls'], $url);

        if ($urlRecord && isset($urlRecord["lock"])) {
            $lockId = $urlRecord["lock"];
            return $lockId;
        } else {
            return null;
        }
    }
}
