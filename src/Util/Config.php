<?php

namespace Yoti\Util;

/**
 * Provides key/value configuration.
 */
class Config
{
    /**
     * @var string[]
     */
    private static $config = [];

    /**
     * @param string $key
     * @param string $value
     */
    public static function set(string $key, string $value)
    {
        self::$config[$key] = $value;
    }

    /**
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    public static function get(string $key, $default = null): string
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * @param string $key
     */
    public static function unset($key)
    {
        unset(self::$config[$key]);
    }

    public static function reset()
    {
        self::$config = [];
    }
}
