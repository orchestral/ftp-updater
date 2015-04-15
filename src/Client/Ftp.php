<?php namespace Orchestra\FtpUpdater\Client;

use Illuminate\Support\Arr;

class Ftp 
{
    /**
     * FTP stream connection.
     *
     * @var Stream
     */
    protected $connection = null;

    /**
     * FTP configuration.
     *
     * @var array
     */
    protected $config = [
        'host'     => null,
        'port'     => 21,
        'user'     => null,
        'password' => null,
        'timeout'  => 90,
        'passive'  => false,
        'ssl'      => false,
    ];

    /**
     * System type of FTP server.
     *
     * @var string
     */
    protected $systemType;

    /**
     * Make a new FTP instance.
     *
     * @param  array  $config
     *
     * @return self
     */
    public static function make(array $config = [])
    {
        return new static($config);
    }

    /**
     * Make a new FTP instance.
     *
     * @param  array  $config
     */
    public function __construct(array $config = [])
    {
        if (! empty($config)) {
            $this->setUp($config);
        }
    }

    /**
     * Configure FTP.
     *
     * @param  array  $config
     *
     * @return void
     */
    public function setUp(array $config = [])
    {
        $this->connection = Arr::pull($config, 'connection', $this->connection);

        if (preg_match('/^(ftp|sftp):\/\/([a-zA-Z0-9\.\-_]*):?(\d{1,4})$/', Arr::get($config, 'host'), $matches)) {
            $config['host'] = $matches[2];
            $config['ssl']  = ($matches[1] === 'sftp');

            isset($matches[3]) && $config['port'] = $matches[3];
        }

        $this->config = array_merge($this->config, $config);
    }

    /**
     * Change current directory on FTP server.
     *
     * @param  string  $directory
     *
     * @return bool
     */
    public function changeDirectory($directory)
    {
        return @Morph::fire('chdir', [$this->connection, $directory]);
    }

    /**
     * Get current directory path.
     *
     * @return string
     */
    public function currentDirectory()
    {
        return @Morph::pwd($this->connection);
    }

    /**
     * Download file from FTP server.
     *
     * @param  string  $remoteFile
     * @param  string  $localFile
     * @param  int  $mode
     *
     * @return bool
     */
    public function get($remoteFile, $localFile, $mode = FTP_ASCII)
    {
        return @Morph::fire('get', [$this->connection, $localFile, $remoteFile, $mode]);
    }

    /**
     * Upload file to FTP server.
     *
     * @param  string  $localFile
     * @param  string  $remoteFile
     * @param  int  $mode
     *
     * @return bool
     */
    public function put($localFile, $remoteFile, $mode = FTP_ASCII)
    {
        return @Morph::fire('put', [$this->connection, $remoteFile, $localFile, $mode]);
    }

    /**
     * Rename file on FTP server.
     *
     * @param  string  $oldName
     * @param  string  $newName
     *
     * @return bool
     */
    public function rename($oldName, $newName)
    {
        return @Morph::fire('rename', [$this->connection, $oldName, $newName]);
    }

    /**
     * Delete file on FTP server.
     *
     * @param  string  $remoteFile
     *
     * @return bool
     */
    public function delete($remoteFile)
    {
        return @Morph::fire('delete', [$this->connection, $remoteFile]);
    }

    /**
     * Set file permissions.
     *
     * @param  string  $remoteFile
     * @param  int  $permission
     *
     * @return bool
     */
    public function permission($remoteFile, $permission = 0644)
    {
        return @Morph::fire('chmod', [$this->connection, $permission, $remoteFile]);
    }

    /**
     * Get list of files/directories on FTP server.
     *
     * @param  string  $directory
     *
     * @return array
     */
    public function allFiles($directory)
    {
        $list = @Morph::fire('nlist', [$this->connection, $directory]);

        return is_array($list) ? $list : [];
    }

    /**
     * Create directory on FTP server.
     *
     * @param  string  $directory
     *
     * @return bool
     */
    public function makeDirectory($directory)
    {
        return @Morph::fire('mkdir', [$this->connection, $directory]);
    }

    /**
     * Remove directory on FTP server.
     *
     * @param  string  $directory
     *
     * @return bool
     */
    public function removeDirectory($directory)
    {
        return @Morph::fire('rmdir', [$this->connection, $directory]);
    }

    /**
     * Connect to FTP server.
     *
     * @return bool|null
     *
     * @throws \Orchestra\FtpUpdater\Client\ServerException
     */
    public function connect()
    {
        $config = $this->config;

        if (is_null($config['host'])) {
            return;
        }

        $this->createConnection($config['host'], $config['port'], $config['timeout']);

        if (! (@Morph::login($this->connection, $config['user'], $config['password']))) {
            throw new ServerException("Failed FTP login to [{$config['host']}].");
        }

        // Set passive mode.
        @Morph::pasv($this->connection, (bool) $config['passive']);

        // Set system type.
        $this->systemType = @Morph::systype($this->connection);

        return true;
    }

    /**
     * Create a FTP connection.
     *
     * @param  string  $host
     * @param  int  $port
     * @param  int  $timeout
     *
     * @return void
     *
     * @throws \Orchestra\FtpUpdater\Client\ServerException
     */
    protected function createConnection($host, $port = 21, $timeout = 90)
    {
        if ($this->config['ssl'] && @Morph::isCallable('sslConnect')) {
            $this->createSecureConnection($host, $port, $timeout);
        } elseif (! ($this->connection = @Morph::connect($host, $port, $timeout))) {
            throw new ServerException("Failed to connect to [{$host}].");
        }
    }

    /**
     * Create a secure (SSL) FTP connection.
     *
     * @param  string  $host
     * @param  int  $port
     * @param  int  $timeout
     *
     * @return void
     *
     * @throws \Orchestra\FtpUpdater\Client\ServerException
     */
    protected function createSecureConnection($host, $port = 21, $timeout = 90)
    {
        if (! ($this->connection = @Morph::sslConnect($host, $port, $timeout))) {
            throw new ServerException(
                "Failed to connect to [{$host}] (SSL Connection)."
            );
        }
    }

    /**
     * Close FTP connection.
     *
     * @return void
     */
    public function close()
    {
        if (! is_null($this->connection)) {
            @Morph::close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Check FTP connection status.
     *
     * @return bool
     */
    public function connected()
    {
        return (! is_null($this->connection));
    }
}
