# Saloon PHP HTTP Proxy Plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)

A robust HTTP proxy management plugin for [Saloon PHP](https://github.com/saloonphp/saloon), allowing you to easily use and rotate proxies in your API requests.

[中文文档](README.zh.md) | [English](README.md)
## Features

- 🔄 Proxy rotation with round-robin support
- 🔌 Automatic proxy fallback on connection failures
- 🔀 Customizable proxy switching conditions
- 🔐 Support for authenticated proxies
- 🧩 Simple integration with Saloon requests and connectors
- 🔍 Smart handling of proxy failures and retries

## Installation

You can install the package via composer:

```bash
composer require weijiajia/saloonphp-http-proxy-plugin
```

## Usage

### Basic Usage

Add the `HasProxy` trait to your Saloon connector or request class:

```php
use Saloon\Http\Connector;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;

class MyApiConnector extends Connector
{
    use HasProxy;
    
    // ... your connector code
}
```

Then add proxies to the connector or request:

```php
// Create a connector with proxies
$connector = new MyApiConnector();

// Add proxies from URLs
$connector->getSplQueue()->enqueue('http://proxy1.example.com:8080');
$connector->getSplQueue()->enqueue('http://user:pass@proxy2.example.com:8080');

// Or use Proxy objects
use Weijiajia\SaloonphpHttpProxyPlugin\Proxy;

$proxy = new Proxy(
    host: 'proxy3.example.com',
    port: 8080,
    url: 'http://proxy3.example.com:8080',
    type: 'http',
    username: 'user',
    password: 'pass'
);

$connector->getSplQueue()->enqueue($proxy);
```

### Enable Round-Robin Proxy Rotation

```php
$connector->roundRobin(true);
```

### Custom Proxy Switching Conditions

You can define custom conditions for when to switch to the next proxy:

```php
use Weijiajia\SaloonphpHttpProxyPlugin\Condition\DefaultProxySwitchCondition;

// Use the default condition with custom status codes
$condition = new DefaultProxySwitchCondition();
$condition->withRetryStatusCodes([403, 429, 500, 502, 503, 504]);

$connector->switchProxyWhen($condition);

// Or use a custom callback
$connector->switchProxyWhen(function ($response, $exception, $pendingRequest) {
    // Switch proxy logic here
    return $response?->status() === 429 || $exception !== null;
});
```

### Force Using Proxies

You can force your requests to only proceed if a proxy is available:

```php
$connector->withForceProxy(true);
```

### Disabling Proxy Usage Temporarily

```php
$connector->withProxyEnabled(false);
```

## Advanced Usage

### Custom Proxy Queue

You can implement your own proxy queue by extending the `ProxySplQueue` class:

```php
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

class MyProxyQueue extends ProxySplQueue
{
    // Your custom implementation
}

$connector->withSplQueue(new MyProxyQueue());
```

### Using Proxies with Specific Requests

The `HasProxy` trait can be used on individual requests as well:

```php
use Saloon\Http\Request;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;

class MyApiRequest extends Request
{
    use HasProxy;
    
    // ... your request code
}
```

## License

This package is open-sourced software licensed under the MIT license.

## Credits

- [Author Name](https://github.com/weijiajia)
- [All Contributors](../../contributors)
