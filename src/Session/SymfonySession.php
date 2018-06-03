<?php

namespace PicoAuth\Session;

use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * Session manager implementation using HttpFoundation
 */
class SymfonySession implements SessionInterface
{

    /**
     * Symfony session instance
     * @var Session
     */
    protected $session;

    public function __construct(SessionStorageInterface $storage = null)
    {
        $storage = $storage ?: new NativeSessionStorage();
        $flashes = new AutoExpireFlashBag('_flash');
        $this->session = new Session($storage, null, $flashes);
    }

    /**
     * @inheritdoc
     */
    public function has($name)
    {
        return $this->session->has($name);
    }

    /**
     * @inheritdoc
     */
    public function get($name, $default = null)
    {
        return $this->session->get($name, $default);
    }

    /**
     * @inheritdoc
     */
    public function set($name, $value)
    {
        $this->session->set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function remove($name)
    {
        $this->session->remove($name);
    }

    /**
     * @inheritdoc
     */
    public function invalidate($lifetime = null)
    {
        return $this->session->invalidate($lifetime);
    }

    /**
     * @inheritdoc
     */
    public function migrate($destroy = false, $lifetime = null)
    {
        return $this->session->migrate($destroy, $lifetime);
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->session->clear();
    }

    /**
     * @inheritdoc
     */
    public function addFlash($type, $message)
    {
        $this->session->getFlashBag()->add($type, $message);
    }

    /**
     * @inheritdoc
     */
    public function getFlash($type, array $default = array())
    {
        return $this->session->getFlashBag()->get($type, $default);
    }
}
