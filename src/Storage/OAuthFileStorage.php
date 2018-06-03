<?php

namespace PicoAuth\Storage;

use PicoAuth\Storage\Interfaces\OAuthStorageInterface;
use PicoAuth\Cache\NullCache;
use Psr\SimpleCache\CacheInterface;
use PicoAuth\Storage\Configurator\OAuthConfigurator;

/**
 * File storage for OAuth
 */
class OAuthFileStorage extends FileStorage implements OAuthStorageInterface
{

    /**
     * Configuration file name
     */
    const CONFIG_FILE = 'PicoAuth/OAuth.yml';

    public function __construct($dir, CacheInterface $cache = null)
    {
        $this->dir = $dir;
        $this->cache = ($cache!==null) ? $cache : new NullCache;
        $this->configurator = new OAuthConfigurator;
    }

    /**
     * @inheritdoc
     */
    public function getProviderByName($name)
    {
        $this->readConfiguration();
        return $this->config['providers'][$name];
    }

    /**
     * @inheritdoc
     */
    public function getProviderNames()
    {
        $this->readConfiguration();
        $providers = $this->config['providers'];
        if (is_array($providers)) {
            return array_keys($providers);
        } else {
            return null;
        }
    }
}
