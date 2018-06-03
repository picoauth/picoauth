<?php
namespace PicoAuth;

/**
 * The User entity.
 */
class User
{

    /**
     * Unique identifier of the user.
     * @var null|string
     */
    protected $id = null;

    /**
     * Optional displayable name.
     * @var null|string
     */
    protected $displayName = null;

    /**
     * Authentication flag.
     * @var bool
     */
    protected $authenticated = false;

    /**
     * Module name that was used to authenticate the user.
     * @see PicoAuth\Module\AuthModule::getName()
     * @var string|null Authenticator name
     */
    protected $authenticator = null;

    /**
     * Groups the user is member of.
     * @var string[]
     */
    protected $groups = [];

    /**
     * User attributes.
     * @var array
     */
    protected $attributes = [];

    /**
     * Gets user's authenticated state.
     * @return bool
     */
    public function getAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * Sets user's authenticated state.
     * @param bool $v
     * @return $this
     */
    public function setAuthenticated($v)
    {
        if (!$v) {
            $this->authenticator = null;
        }
        $this->authenticated = $v;
        return $this;
    }

    /**
     * Gets user's authenticator.
     * Will return null if the user is not authenticated.
     * @see User::authenticator
     * @return null|string
     */
    public function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * Sets user's authenticator.
     * @param string $name
     * @return $this
     */
    public function setAuthenticator($name)
    {
        $this->authenticator = $name;
        return $this;
    }

    /**
     * Gets user's id.
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets user's id.
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Returns user's display name if available.
     * @return string|null
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Sets user's display name.
     * @param string $displayName
     * @return $this
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * Gets user's groups.
     * @return string[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * Sets user's groups.
     * @param string[] $groups
     * @return $this
     */
    public function setGroups($groups)
    {
        if (is_array($groups)) {
            $this->groups = $groups;
        }
        return $this;
    }

    /**
     * Adds group.
     * @param string $group
     */
    public function addGroup($group)
    {
        if (is_string($group)) {
            $this->groups[] = $group;
        }
    }

    /**
     * Gets user's attribute if available.
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        return (isset($this->attributes[$key])) ? $this->attributes[$key] : null;
    }

    /**
     * Sets user's attribute.
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }
}
