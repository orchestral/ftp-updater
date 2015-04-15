<?php namespace Orchestra\FtpUpdater\Client;

use Orchestra\Support\Morph as Base;

class Morph extends Base
{
    /**
     * Define Morph prefix.
     *
     * @var string
     */
    public static $prefix = 'ftp_';

    /**
     * Magic method to ftp methods.
     *
     * @param  string  $method
     * @param  array   $parameters
     *
     * @return mixed
     *
     * @throws \Orchestra\FtpUpdater\Client\RuntimeException
     */
    public static function fire($method, $parameters)
    {
        $result = null;

        if (! static::isCallable($method)
            || ! $result = call_user_func_array(static::$prefix.$method, $parameters)) {
            throw new RuntimeException("Failed to use {$method}.", $parameters);
        }

        return $result;
    }
}
