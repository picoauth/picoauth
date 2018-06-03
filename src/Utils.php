<?php
namespace PicoAuth;

/**
 * PicoAuth helper methods.
 */
final class Utils
{

    /**
     * Searches the url for a query parameter of a given key.
     * @param string $url
     * @param string $key
     * @return mixed
     */
    public static function getRefererQueryParam($url, $key)
    {
        if (!$url) {
            return null;
        }

        $query = [];

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query[$key])) {
            return $query[$key];
        }

        return null;
    }

    /**
     * Validates a non-empty Pico url param.
     * @param string $url
     * @return bool
     */
    public static function isValidPageId($url)
    {
        return 1 === preg_match("/^(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+$/", $url);
    }
}
