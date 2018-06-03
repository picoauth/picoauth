<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\PageACLStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\PageACLConfigurator;

/**
 * File storage for PageACL
 */
class PageACLFileStorage extends FileStorage implements PageACLStorageInterface
{

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/PageACL.yml';

    /**
     * @inheritdoc
     */
    public function __construct($dir, CacheInterface $cache = null)
    {
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new PageACLConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function getRuleByURL($url)
    {
        $this->readConfiguration();

        return self::getItemByUrl($this->config['access'], $url);
    }
}
