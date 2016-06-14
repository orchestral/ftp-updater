<?php

namespace Orchestra\FtpUpdater\TestCase;

use Mockery as m;
use Orchestra\FtpUpdater\Uploader;
use Illuminate\Container\Container;

class UploaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app = null;

    /**
     * FTP Client instance.
     *
     * @var \Orchestra\FtpUpdater\Client\Ftp
     */
    private $client = null;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        $this->app    = new Container();
        $this->client = m::mock('\Orchestra\FtpUpdater\Client\Ftp');
    }

    /**
     * Teardown the test environment.
     */
    public function tearDown()
    {
        unset($this->app);
        unset($this->client);

        m::close();
    }

    /**
     * Get Session mock.
     *
     * @access private
     *
     * @return Mockery
     */
    private function getSessionMock()
    {
        $session = m::mock('\Illuminate\Session\SessionInterface');

        $session->shouldReceive('get')->once()
            ->with('orchestra.ftp', [])->andReturn(['ftpconfig']);

        return $session;
    }

    /**
     * Test constructing \Orchestra\FtpUpdater\Uploader.
     *
     * @test
     */
    public function testConstructMethod()
    {
        $app    = $this->app;
        $client = $this->client;

        $app['session'] = $this->getSessionMock();

        $client->shouldReceive('setUp')->once()->with(['ftpconfig'])->andReturnNull()
            ->shouldReceive('connect')->once()->andReturn(false)
            ->shouldReceive('connected')->once()->andReturn(true);

        $stub = new Uploader($app, $client);

        $this->assertEquals($client, $stub->getConnection());
        $this->assertTrue($stub->connected());

        $this->assertEquals('/domains/foo.bar/public', $stub->basePath('/home/foo/domains/foo.bar/public'));
        $this->assertEquals('/var/html/foo.bar/public', $stub->basePath('/var/html/foo.bar/public'));
        $this->assertInstanceOf('\Orchestra\Contracts\Publisher\Uploader', $stub);
    }

    /**
     * Test constructing \Orchestra\FtpUpdater\Uploader when connection
     * is not set.
     *
     * @test
     */
    public function testConstructMethodWhenConnectionIsNotSet()
    {
        $app    = $this->app;
        $client = $this->client;

        $app['session'] = $this->getSessionMock();

        $client->shouldReceive('setUp')->once()->with(['ftpconfig'])->andReturnNull()
            ->shouldReceive('connect')->once()->andReturn(false)
            ->shouldReceive('connected')->once()->andReturn(false);

        $stub = new Uploader($app, $client);

        $this->assertEquals($client, $stub->getConnection());
        $this->assertFalse($stub->connected());
    }

    /**
     * Test constructing \Orchestra\FtpUpdater\Uploader throws ServerException.
     *
     * @test
     */
    public function testConstructMethodThrowsServerException()
    {
        $app    = $this->app;
        $client = $this->client;

        $app['session'] = $session = $this->getSessionMock();

        $session->shouldReceive('put')->once()->with('orchestra.ftp', [])->andReturnNull();
        $client->shouldReceive('setUp')->once()->with(['ftpconfig'])->andReturnNull()
            ->shouldReceive('connect')->once()->andThrow('\Orchestra\Contracts\Publisher\ServerException');

        new Uploader($app, $client);
    }

    /**
     * Test \Orchestra\FtpUpdater\Uploader::upload() method.
     *
     * @test
     */
    public function testUploadMethod()
    {
        $app    = $this->app;
        $client = $this->client;

        $app['session'] = $this->getSessionMock();
        $app['path.public'] = $path = '/var/foo/public';
        $app['files'] = $file = m::mock('\Illuminate\Filesystem\Filesystem');
        $app['orchestra.extension'] = $extension = m::mock('\Orchestra\Contracts\Extension\Factory');

        $client->shouldReceive('setUp')->once()->with(['ftpconfig'])->andReturnNull()
            ->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('permission')->once()->with($path.'/packages/laravel/framework/', 0777)->andReturnNull()
            ->shouldReceive('permission')->once()->with($path.'/packages/laravel/framework/', 0755)->andReturnNull()
            ->shouldReceive('allFiles')->twice()->with($path.'/packages/laravel/framework/')->andReturn([
                '/..',
                '/.',
                'recursive-foobar',
            ])
            ->shouldReceive('permission')->once()->with('recursive-foobar', 0777)->andReturnNull()
            ->shouldReceive('allFiles')->once()->with('recursive-foobar')->andThrow('\RuntimeException');
        $file->shouldReceive('isDirectory')->once()->with($path.'/packages/laravel/framework/')->andReturn(true);
        $extension->shouldReceive('activate')->once()->with('laravel/framework')->andReturnNull();

        $stub = new Uploader($app, $client);
        $stub->upload('laravel/framework');
    }

    /**
     * Test \Orchestra\FtpUpdater\Uploader::upload() method chmod vendor
     * folder.
     *
     * @test
     */
    public function testUploadMethodChmodVendorFolder()
    {
        $app    = $this->app;
        $client = $this->client;

        $app['session'] = $this->getSessionMock();
        $app['path.public'] = $path = '/var/foo/public';
        $app['files'] = $file = m::mock('\Illuminate\Filesystem\Filesystem');
        $app['orchestra.extension'] = $extension = m::mock('\Orchestra\Contracts\Extension\Factory');

        $client->shouldReceive('setUp')->once()->with(['ftpconfig'])->andReturnNull()
            ->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('permission')->once()->with($path.'/packages/laravel/', 0777)->andReturnNull()
            ->shouldReceive('permission')->once()->with($path.'/packages/laravel/', 0755)->andReturnNull()
            ->shouldReceive('makeDirectory')->once()->with($path.'/packages/laravel/framework/')->andReturn(true)
            ->shouldReceive('permission')->once()->with($path.'/packages/laravel/framework/', 0777)->andReturnNull();
        $file->shouldReceive('isDirectory')->once()->with($path.'/packages/laravel/framework/')->andReturn(false)
            ->shouldReceive('isDirectory')->once()->with($path.'/packages/laravel/')->andReturn(true);
        $extension->shouldReceive('activate')->once()->with('laravel/framework')->andReturnNull();

        $stub = new Uploader($app, $client);
        $stub->upload('laravel/framework');
    }
}

