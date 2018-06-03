<?php

namespace PicoAuth\Security\Password\Policy;

use PicoAuth\Security\Password\Password;

/**
 * Class for enforcing password constraints
 *
 * Reference:
 * Unicode graphemes regex: https://www.regular-expressions.info/unicode.html
 */
class PasswordPolicy implements PasswordPolicyInterface
{

    protected $constraints;
    protected $errors;

    /**
     * The minimum length in characters
     *
     * Unicode code point is counted as a single character (NIST recommendation)
     *
     * @param int $n
     * @return $this
     */
    public function minLength($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (mb_strlen($str) < $n) {
                return sprintf("Minimum password length is %d characters.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }
    
    /**
     * The maximum length in characters
     *
     * Note: This option is provided only to allow prevention from DoS attacks
     * using computationally intensive password hashing algorithm on very long inputs.
     * It is not advised to limit the maximum length without a specific reason.
     *
     * @param int $n
     * @return $this
     */
    public function maxLength($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (mb_strlen($str) > $n) {
                return sprintf("Maximum password length is %d characters.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }

    /**
     * The minimum amount of numeric characters
     * @param int $n
     * @return $this
     */
    public function minNumbers($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (preg_match_all("/\\p{N}/", $str) < $n) {
                return sprintf("Password must contain at least %d numbers.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }

    /**
     * The minimum amount of uppercase letters (an uppercase letter that
     * has a lowercase variant, from any language)
     * @param int $n
     * @return $this
     */
    public function minUppercase($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (preg_match_all("/\\p{Lu}/", $str) < $n) {
                return sprintf("Password must contain at least %d uppercase letters.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }

    /**
     * The minimum amount of lowercase letters (a lowercase letter that has
     * an uppercase variant, from any language)
     * @param int $n
     * @return $this
     */
    public function minLowercase($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (preg_match_all("/\\p{Ll}/", $str) < $n) {
                return sprintf("Password must contain at least %d lowercase letters.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }
    
    /**
     * Minimum number of special characters (any character other
     * than letter or number)
     *
     * Matches also language specific letters
     * Possible replacement: "/[\\p{M}\\p{Z}\\p{S}\\p{P}]/"
     * But does not match e.g. "ˇ"
     *
     * @param int $n
     * @return $this
     */
    public function minSpecial($n)
    {
        $this->constraints[] = (function (Password $str) use ($n) {
            if (preg_match_all("/[^A-Za-z0-9]/", $str) < $n) {
                return sprintf("Password must contain at least %d special characters.", $n);
            } else {
                return true;
            }
        });
        return $this;
    }

    /**
     * Matches provided regular expression
     *
     * @param string $regexp Regular expression that must be matched
     * @param string $message Error message on failure
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function matches($regexp, $message)
    {
        if (!is_string($regexp) || !is_string($message)) {
            throw new \InvalidArgumentException("Both arguments must be string.");
        }
        $this->constraints[] = (function (Password $str) use ($regexp, $message) {
            if (!preg_match($regexp, $str)) {
                return $message;
            } else {
                return true;
            }
        });
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function check(Password $str)
    {
        $this->errors = array();

        foreach ($this->constraints as $constraint) {
            $res = $constraint($str);
            if ($res !== true) {
                $this->errors[] = $res;
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
