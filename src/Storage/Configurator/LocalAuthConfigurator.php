<?php

namespace PicoAuth\Storage\Configurator;

use PicoAuth\Storage\Configurator\AbstractConfigurator;
use PicoAuth\Storage\Configurator\ConfigurationException;

/**
 * The configurator for LocalAuth
 */
class LocalAuthConfigurator extends AbstractConfigurator
{

    /**
     * @inheritdoc
     */
    public function validate($rawConfig)
    {
        $config = $this->applyDefaults($rawConfig, $this->getDefault());
        $this->validateGlobalOptions($config);
        $this->validateLoginSection($config);
        $this->validateAccountEditSection($config);
        $this->validatePasswordResetSection($config);
        $this->validateRegistrationSection($config);
        $this->validateUsersSection($config);
        return $config;
    }
    
    /**
     * The default configuration must contain all keys that are required.
     * @return array
     */
    protected function getDefault()
    {
        return array(
            "encoder" => "bcrypt",
            "login" => array(
                "passwordRehash" => false
            ),
            "accountEdit" => array(
                "enabled" => false,
            ),
            "passwordReset" => array(
                "enabled" => false,
                "emailMessage" => "Hello,\n\nVisit the link to reset your password "
                                    . "to %site_title%.\n\nReset URL:\n%url%",
                "emailSubject" => "%site_title% - Password Reset",
                "tokenIdLen" => 10,
                "tokenLen" => 50,
                "tokenValidity" => 7200,
                "resetTimeout" => 1800,
            ),
            "registration" => array(
                "enabled" => false,
                "maxUsers" => 10000,
                "nameLenMin" => 3,
                "nameLenMax" => 20,
            ),
        );
    }
    
    public function validateGlobalOptions($config)
    {
        $this->assertString($config, "encoder");
    }
    
    public function validateLoginSection($config)
    {
        $section = $config["login"];
        $this->assertBool($section, "passwordRehash");
    }
    
    public function validateAccountEditSection($config)
    {
        $section = $config["accountEdit"];
        $this->assertBool($section, "enabled");
    }

    public function validatePasswordResetSection($config)
    {
        $section = $config["passwordReset"];
        $this->assertBool($section, "enabled")
            ->assertStringContaining($section, "emailMessage", "%url%")
            ->assertString($section, "emailSubject")
            ->assertInteger($section, "tokenIdLen", 4, 128)
            ->assertInteger($section, "tokenLen", 4, 1024)
            ->assertInteger($section, "tokenValidity", 60)
            ->assertInteger($section, "resetTimeout", 60);
    }
    
    public function validateRegistrationSection($config)
    {
        $section = $config["registration"];
        $this->assertBool($section, "enabled")
            ->assertInteger($section, "maxUsers", 0)
            ->assertInteger($section, "nameLenMin", 1)
            ->assertInteger($section, "nameLenMax", 1, 200)
            ->assertGreaterThan($section, "nameLenMax", "nameLenMin");
    }
    
    /**
     * Validates the users section of the configuration
     *
     * May have side effects in the configuration array,
     * if some usernames are not defined in lowercase
     *
     * @param array $config Configuration reference
     * @throws ConfigurationException On a failed assertation
     */
    public function validateUsersSection(&$config)
    {
        if (!isset($config["users"])) {
            // No users are specified in the configuration file
            return;
        }
        $this->assertArray($config, "users");
        
        foreach ($config["users"] as $username => $userData) {
            $this->assertUsername($username, $config);
            try {
                $this->validateUserData($userData);
            } catch (ConfigurationException $e) {
                $e->addBeforeMessage("Invalid userdata for $username:");
                throw $e;
            }
            
            // Assure case insensitivity of username indexing
            $lowercaseName = strtolower($username);
            if ($username !== $lowercaseName) {
                if (!isset($config["users"][$lowercaseName])) {
                    $config["users"][$lowercaseName] = $userData;
                    unset($config["users"][$username]);
                } else {
                    throw new ConfigurationException("User $username is defined multiple times.");
                }
            }
        }
    }
    
    /**
     * Validates all user parameters
     *
     * @param array $userData Userdata array
     * @throws ConfigurationException On a failed assertation
     */
    public function validateUserData($userData)
    {
        $this->assertRequired($userData, "pwhash");
        $this->assertString($userData, "pwhash");
        
        // All remaining options are optional
        $this->assertString($userData, "email");
        $this->assertArray($userData, "attributes");
        $this->assertString($userData, "encoder");
        $this->assertBool($userData, "pwreset");
        $this->assertArrayOfStrings($userData, "groups");
        $this->assertString($userData, "displayName");
    }
    
    /**
     * Asserts a valid username format
     *
     * @param string $username Username being checked
     * @param array $config The configuration array
     * @throws ConfigurationException On a failed assertation
     */
    public function assertUsername($username, $config)
    {
        if (!is_string($username)) {
            throw new ConfigurationException("Username $username must be a string.");
        }
        $len = strlen($username);
        $minLen=$config["registration"]["nameLenMin"];
        $maxLen=$config["registration"]["nameLenMax"];
        if ($len < $minLen || $len > $maxLen) {
            throw new ConfigurationException(
                sprintf("Length of a username $username must be between %d-%d characters.", $minLen, $maxLen)
            );
        }
        if (!$this->checkValidNameFormat($username)) {
            throw new ConfigurationException("Username $username contains invalid character/s.");
        }
    }
    
    /**
     * Checks a valid username format
     *
     * @param string $name file name to be checked
     * @return bool true if the name format is accepted
     */
    public function checkValidNameFormat($name)
    {
        return preg_match('/^[a-z0-9_\-]+$/i', $name) === 1;
    }
}
