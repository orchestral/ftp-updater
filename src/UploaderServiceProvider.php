<?php

namespace Orchestra\FtpUpdater;

use Orchestra\FtpUpdater\Client\Ftp;
use Illuminate\Support\ServiceProvider;

class UploaderServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('orchestra.publisher.ftp', function ($app) {
            return new Uploader($app, new Ftp());
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['orchestra.publisher.ftp'];
    }
}
