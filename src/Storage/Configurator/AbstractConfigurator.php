<?php

namespace PicoAuth\Storage\Configurator;

/**
 * Abstract configurator for validation of configuration arrays
 * and setting default values.
 */
abstract class AbstractConfigurator
{

    /**
     * Validates the configuration
     *
     * Performs assertations on all fields of the configuration array,
     * adds default values for the fields that were not specified.
     *
     * @throws ConfigurationException On validation failure
     * @param mixed $config Value read from the configuration file
     * @return array Resulting configuration with default values
     */
    abstract public function validate($config);

    /**
     * Assert the property is present
     * @param array $config Array being validated
     * @param string $key Inspected array key
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertRequired($config, $key)
    {
        if (!isset($config[$key])) {
            throw new ConfigurationException("Property $key is required.");
        }
        return $this;
    }

    /**
     * Assert the property is array or is not set
     * @param array $config Array being validated
     * @param string $key Inspected array key
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertArray($config, $key)
    {
        if (array_key_exists($key, $config) && !is_array($config[$key])) {
            throw new ConfigurationException($key." section must be an array.");
        }
        return $this;
    }
    
    /**
     * Assert the property is boolean or is not set
     * @param array $config Array being validated
     * @param string|int $key Inspected array key
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertBool($config, $key)
    {
        if (array_key_exists($key, $config) && !is_bool($config[$key])) {
            throw new ConfigurationException($key." must be a boolean value.");
        }
        return $this;
    }
    
    /**
     * Assert the property is integer or is not set
     * @param array $config Array being validated
     * @param string|int $key Inspected array key
     * @param int $lowest The lowest accepted values
     * @param int $highest The highest accepted value
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertInteger($config, $key, $lowest = null, $highest = null)
    {
        if (array_key_exists($key, $config)) {
            if (!is_int($config[$key])) {
                throw new ConfigurationException($key." must be an integer.");
            }
            if ($lowest !== null && $config[$key] < $lowest) {
                throw new ConfigurationException($key." cannot be lower than ".$lowest);
            }
            if ($highest !== null && $config[$key] > $highest) {
                throw new ConfigurationException($key." cannot be higher than ".$highest);
            }
        }
        return $this;
    }
    
    /**
     * Assert both properties are set and 1st is greater than second
     * @param array $config Array being validated
     * @param string|int $keyGreater Array key with greater value
     * @param string|int $keyLower Array key with lower value
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertGreaterThan($config, $keyGreater, $keyLower)
    {
        if (!isset($config[$keyLower])
            || !isset($config[$keyGreater])
            || $config[$keyLower] >= $config[$keyGreater]) {
            throw new ConfigurationException($keyGreater." must be greater than ".$keyLower);
        }
        return $this;
    }
    
    /**
     * Assert the property is string or is not set
     * @param array $config Array being validated
     * @param string|int $key Inspected array key
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertString($config, $key)
    {
        if (array_key_exists($key, $config) && !is_string($config[$key])) {
            throw new ConfigurationException($key." must be a string.");
        }
        return $this;
    }
    
    /**
     * Assert a string contains given substring or is not set
     * @param array $config Array being validated
     * @param string|int $key Inspected array key
     * @param string $searchedPart The string being searched
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertStringContaining($config, $key, $searchedPart)
    {
        $this->assertString($config, $key);
        if (array_key_exists($key, $config) && strpos($config[$key], $searchedPart) === false) {
            throw new ConfigurationException($key." must contain ".$searchedPart);
        }
        return $this;
    }
    
    /**
     * Assert the value is array of strings or is not set
     * @param array $config Array being validated
     * @param string|int $key Inspected array key
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertArrayOfStrings($config, $key)
    {
        if (!array_key_exists($key, $config)) {
            return $this;
        }
        if (!is_array($config[$key])) {
            throw new ConfigurationException($key." section must be an array.");
        }
        foreach ($config[$key] as $value) {
            if (!is_string($value)) {
                throw new ConfigurationException("Values in the `{$key}` must be strings"
                    . gettype($value) . " found.");
            } elseif ($value==="") {
                throw new ConfigurationException("Empty string not allowed in `{$key}` array.");
            }
        }
        return $this;
    }
    
    /**
     * Assert the value is either integer within bounds or false
     *
     * Some configuration values can be either false (disabled)
     * or have an integer value set if enabled
     *
     * @param array $config
     * @param string|int $key Array being validated
     * @param int $lowest The lowest accepted value
     * @param int $highest The highest accepted value
     * @return $this
     * @throws ConfigurationException On a failed assertation
     */
    public function assertIntOrFalse($config, $key, $lowest = null, $highest = null)
    {
        try {
            $this->assertInteger($config, $key, $lowest, $highest);
        } catch (ConfigurationException $e) {
            if ($config[$key]!==false) {
                throw new ConfigurationException(
                    "Key `{$key}` can be either false or a non-negative integer."
                );
            }
        }
        return $this;
    }
    
    /**
     * Corrects URL index if it does not have a correct format
     *
     * Add a leading slash to the page URL if not present.
     * And remove trailing slash from the end if present.
     * If the change is made, the array key is moved inside the
     * {$rules} array.
     *
     * @param array $rules Reference to $rules array
     * @param string $pageUrl URL to be checked and corrected
     */
    public function standardizeUrlFormat(&$rules, $pageUrl)
    {
        if (!is_string($pageUrl) || $pageUrl==="" || !is_array($rules) ||
                !array_key_exists($pageUrl, $rules) ) {
            return;
        }
        
        $oldIndex=$pageUrl;
        if ($pageUrl[0] !== '/') {
            $pageUrl='/'.$pageUrl;
        }
        $len=strlen($pageUrl);
        if ($len>1 && $pageUrl[$len-1]==='/') {
            $pageUrl= rtrim($pageUrl, '/');
        }
        if ($oldIndex!==$pageUrl) {
            $rules[$pageUrl]=$rules[$oldIndex];
            unset($rules[$oldIndex]);
        }
    }
    
    /**
     * Returns configuration merged with the default values
     *
     * Performs a union operation up to {$depth} level of sub-arrays.
     *
     * @param array|null $config The configuration array supplied by the user
     * @param array $defaults The default configuration array
     * @return array The resulting configuration array
     */
    public function applyDefaults($config, array $defaults, $depth = 1)
    {
        if (!is_int($depth) || $depth < 0) {
            throw new \InvalidArgumentException("Depth must be non-negative integer.");
        }
        
        if (!is_array($config)) {
            return $defaults;
        }
        
        if ($depth === 0) {
            $config += $defaults;
            return $config;
        }

        foreach ($defaults as $key => $defaultValue) {
            // Use the default value, if user's array is missing this key
            if (!isset($config[$key])) {
                $config[$key] = $defaultValue;
                continue;
            }

            if (is_array($defaultValue)) {
                if (is_array($config[$key])) {
                    $config[$key] = $this->applyDefaults($config[$key], $defaultValue, $depth-1);
                } else {
                    throw new ConfigurationException("Configuration key "
                        .$key." expects an array, a scalar value found.");
                }
            } else {
                if (is_array($config[$key])) {
                    throw new ConfigurationException("Configuration key "
                        .$key." expects scalar, an array found.");
                }
            }
        }

        return $config;
    }
}
