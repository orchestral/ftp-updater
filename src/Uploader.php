<?php

namespace Orchestra\FtpUpdater;

use Closure;
use RuntimeException;
use Orchestra\Support\Str;
use Orchestra\FtpUpdater\Client\Ftp as FtpClient;
use Orchestra\Contracts\Publisher\ServerException;
use Orchestra\Contracts\Publisher\Uploader as UploaderContract;

class Uploader implements UploaderContract
{
    /**
     * Application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * FTP Connection instance.
     *
     * @var \Orchestra\FtpUpdater\Client\Ftp
     */
    protected $connection;

    /**
     * Construct a new FTP instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Orchestra\FtpUpdater\Client\Ftp  $client
     *
     * @throws \Orchestra\FtpUpdater\Client\ServerException
     */
    public function __construct($app, FtpClient $client)
    {
        $this->app = $app;
        $this->setConnection($client);

        // If FTP credential is stored in the session, we should reuse it
        // and connect to FTP server straight away.
        $config = $this->app['session']->get('orchestra.ftp', []);

        try {
            $this->connect($config);
        } catch (ServerException $e) {
            // Connection might failed, but there nothing really to report.
            $this->app['session']->put('orchestra.ftp', []);
        }
    }

    /**
     * Get service connection instance.
     *
     * @return object
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set service connection instance.
     *
     * @param  object  $client
     *
     * @return void
     */
    public function setConnection($client)
    {
        $this->connection = $client;
    }

    /**
     * Connect to the service.
     *
     * @param  array  $config
     *
     * @return bool
     */
    public function connect($config = [])
    {
        $this->connection->setUp($config);

        return $this->connection->connect();
    }

    /**
     * Make a directory.
     *
     * @param  string  $path
     *
     * @return bool
     */
    public function makeDirectory($path)
    {
        return $this->connection->makeDirectory($path);
    }

    /**
     * CHMOD a directory/file.
     *
     * @param  string  $path
     * @param  int     $mode
     *
     * @return bool
     */
    public function permission($path, $mode = 0755)
    {
        return $this->connection->permission($path, $mode);
    }

    /**
     * CHMOD a file/directory recursively.
     *
     * @param  string  $path
     * @param  int     $mode
     *
     * @return bool
     */
    public function recursivePermission($path, $mode = 0755)
    {
        $this->permission($path, $mode);

        try {
            return $this->recursiveFilePermission($path, $mode);
        } catch (RuntimeException $e) {
            // Do nothing.
        }

        return true;
    }

    /**
     * CHMOD both file and directory recursively.
     *
     * @param  string  $path
     * @param  int     $mode
     *
     * @return bool
     */
    protected function recursiveFilePermission($path, $mode = 0755)
    {
        $lists = $this->connection->allFiles($path);

        $ignored_path = function ($dir) {
            return (substr($dir, -3) === '/..' || substr($dir, -2) === '/.');
        };

        // this is to check if return value is just a single file,
        // avoiding infinite loop when we reach a file.
        if ($lists !== [$path]) {
            foreach ($lists as $dir) {
                // Not a file or folder, ignore it.
                if (! $ignored_path($dir)) {
                    $this->recursivePermission($dir, $mode);
                }
            }
        }

        return true;
    }

    /**
     * Upload the file.
     *
     * @param  string  $name
     * @param  bool    $recursively
     *
     * @return bool
     *
     * @throws \RuntimeException
     */
    public function upload($name, $recursively = false)
    {
        list($path, $basePath, $recursively, $folderExist) = $this->checkDestination($name, $recursively);

        try {
            $self         = $this;
            $basePathName = "{$basePath}{$name}/";

            $callback = function () use ($self, $folderExist, $basePathName) {
                if (! $folderExist) {
                    $self->makeDirectory($basePathName);
                    $self->permission($basePathName, 0777);
                }
            };

            $this->changePermission($path, $recursively, 0777, $callback);
        } catch (RuntimeException $e) {
            // We found an exception with FTP, but it would be hard to say
            // extension can't be activated, let's try activating the
            // extension and if it failed, we should actually catching
            // those exception instead.
        }

        $this->app['orchestra.extension']->activate($name);

        $this->changePermission($path, $recursively, 0755);

        return true;
    }

    /**
     * Check upload path.
     *
     * @param  string  $name
     * @param  bool    $recursively
     *
     * @return array
     */
    protected function checkDestination($name, $recursively = false)
    {
        $folderExist = true;

        $public = $this->basePath($this->app['path.public']);

        // Start chmod from public/packages directory, if the extension folder
        // is yet to be created, it would be created and own by the web server
        // (Apache or Nginx). If otherwise, we would then emulate chmod -Rf
        $public = rtrim($public, '/').'/';
        $path   = $basePath = "{$public}packages/";

        // If the extension directory exist, we should start chmod from the
        // folder instead.
        if ($this->app['files']->isDirectory($folder = "{$basePath}{$name}/")) {
            $recursively = true;
            $path        = $folder;
        } else {
            $folderExist = false;
        }

        // Alternatively if vendor has been created before, we need to
        // change the permission on the vendor folder instead of
        // public/packages.
        if (! $recursively && Str::contains($name, '/')) {
            list($vendor, ) = explode('/', $name);

            if ($this->app['files']->isDirectory($folder = "{$basePath}{$vendor}/")) {
                $path = $folder;
            }
        }

        return [$path, $basePath, $recursively, $folderExist];
    }

    /**
     * Revert chmod back to original state.
     *
     * @param  string  $path
     * @param  bool  $recursively
     * @param  int  $mode
     * @param  \Closure  $callback
     *
     * @return void
     */
    protected function changePermission($path, $recursively, $mode = 0755, Closure $callback = null)
    {
        if ($recursively) {
            $this->recursivePermission($path, $mode);
        } else {
            $this->permission($path, $mode);

            if (is_callable($callback)) {
                $callback();
            }
        }
    }

    /**
     * Get base path for FTP.
     *
     * @param  string  $path
     *
     * @return string
     */
    public function basePath($path)
    {
        // This set of preg_match would filter ftp' user is not accessing
        // exact path as path('public'), in most shared hosting ftp' user
        // would only gain access to it's /home/username directory.
        if (preg_match('/^\/(home)\/([a-zA-Z0-9]+)\/(.*)$/', $path, $matches)) {
            $path = '/'.ltrim($matches[3], '/');
        }

        return $path;
    }

    /**
     * Verify that FTP driver is connected to a service.
     *
     * @return bool
     */
    public function connected()
    {
        return $this->connection->connected();
    }
}
