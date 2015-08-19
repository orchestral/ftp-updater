Extension Updater (via FTP) for Orchestra Platform
==============

[![Latest Stable Version](https://img.shields.io/github/release/orchestral/ftp-updater.svg?style=flat-square)](https://packagist.org/packages/orchestra/ftp-updater)
[![Total Downloads](https://img.shields.io/packagist/dt/orchestra/ftp-updater.svg?style=flat-square)](https://packagist.org/packages/orchestra/ftp-updater)
[![MIT License](https://img.shields.io/packagist/l/orchestra/ftp-updater.svg?style=flat-square)](https://packagist.org/packages/orchestra/ftp-updater)
[![Build Status](https://img.shields.io/travis/orchestral/ftp-updater/3.1.svg?style=flat-square)](https://travis-ci.org/orchestral/ftp-updater)
[![Coverage Status](https://img.shields.io/coveralls/orchestral/ftp-updater/3.1.svg?style=flat-square)](https://coveralls.io/r/orchestral/ftp-updater?branch=3.1)
[![Scrutinizer Quality Score](https://img.shields.io/scrutinizer/g/orchestral/ftp-updater/3.1.svg?style=flat-square)](https://scrutinizer-ci.com/g/orchestral/ftp-updater/)

## Table of Content

* [Installation](#installation)
* [Configuration](#configuration)

## Installation

To install through composer, simply put the following in your `composer.json` file:

```json
{
    "require": {
        "orchestra/ftp-updater": "~3.1"
    }
}
```

And then run `composer install` from the terminal.

### Quick Installation

Above installation can also be simplify by using the following command:

    composer require "orchestra/ftp-updater=~3.1"

## Configuration

Add following service providers in `resources/config/app.php`.

```php
'providers' => [

    // ...

    'Orchestra\FtpUpdater\UploaderServiceProvider',
],
```
