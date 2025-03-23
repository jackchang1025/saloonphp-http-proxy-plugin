# Saloon PHP HTTP Proxy Plugin

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)

A robust HTTP proxy management plugin for [Saloon PHP](https://github.com/saloonphp/saloon), allowing you to easily use and rotate proxies in your API requests.

[中文文档](README.zh-CN.md) | [English](README.md)

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)
  - [Basic Setup](#basic-setup)
  - [Adding Proxies](#adding-proxies)
  - [Proxy Rotation](#proxy-rotation)
  - [Custom Proxy Switching](#custom-proxy-switching)
  - [Force Using Proxies](#force-using-proxies)
  - [Temporarily Disable Proxies](#temporarily-disable-proxies)
  - [Proxy Availability Management](#proxy-availability-management)
- [Advanced Usage](#advanced-usage)
  - [Custom Proxy Queue](#custom-proxy-queue)
  - [Request-Specific Proxies](#request-specific-proxies)
  - [Concurrent Requests](#concurrent-requests)
  - [Error Handling](#error-handling)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)

## Features

- 🔄 Stateless proxy management aligned with Saloon design principles
- 🔌 Automatic proxy fallback on connection failures
- 🔀 Customizable proxy rotation strategies (FIFO queue with round-robin support)
- 🔐 Support for authenticated proxies (username/password)
- 🧩 Simple integration with Saloon requests and connectors
- 🚦 Dynamic proxy availability management
- 🔍 Smart handling of proxy failures with custom exceptions

## Requirements

- PHP 8.0 or higher
- Saloon PHP v2.0 or higher

## Installation

You can install the package via composer:

```bash
composer require weijiajia/saloonphp-http-proxy-plugin
```

## Usage

### Basic Setup

1. Implement the `ProxyManagerInterface` in your connector or request class
2. Add the `HasProxy` trait to your Saloon connector or request class:

```php
use Saloon\Http\Connector;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyManagerInterface;

class MyApiConnector extends Connector implements ProxyManagerInterface
{
    use HasProxy;
    
    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }
}
```

### Adding Proxies

Add proxies to your connector or request object:

```php
// Create a connector
$connector = new MyApiConnector();

// Add proxies from URLs
$connector->getProxyQueue()->enqueue('http://proxy1.example.com:8080');
$connector->getProxyQueue()->enqueue('http://user:pass@proxy2.example.com:8080');

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

$connector->getProxyQueue()->enqueue($proxy);

// Set a custom proxy queue
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

$proxyQueue = new ProxySplQueue(roundRobinEnabled: true, proxies: ['http://proxy1.example.com:8080']);
$connector->withProxyQueue($proxyQueue); // Use new method name
```

### Proxy Rotation

Enable round-robin proxy rotation to cycle through available proxies:

```php
// Enable round-robin mode directly on the proxy queue
$connector->getProxyQueue()->setRoundRobinEnabled(true);

// Or use the convenience method
$connector->roundRobin(true);
```

### Force Using Proxies

You can force your requests to only proceed if a proxy is available:

```php
$connector->withForceProxy(true); // Default is true
```

### Temporarily Disable Proxies

```php
$connector->withProxyEnabled(false);
```

### Proxy Availability Management

You can mark proxies as available or unavailable:

```php
// Get all proxies in the queue
$proxies = $connector->getProxyQueue()->getAllProxies();

// Mark a proxy as unavailable
$proxies[0]->setAvailable(false);

// Check if a proxy is available
$isAvailable = $proxies[0]->isAvailable(); // returns false
```

## Advanced Usage

### Custom Proxy Queue

You can implement your own proxy queue by extending the `ProxySplQueue` class:

```php
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyInterface;

class PrioritizedProxyQueue extends ProxySplQueue
{
    // Your custom implementation with prioritization
    
    public function dequeue(): mixed
    {
        // Custom dequeue logic that considers priority
        // ...
    }
    
    // You can also implement additional methods
    public function addWithPriority(ProxyInterface $proxy, int $priority): void
    {
        // Custom logic to add proxy with priority
    }
}

$connector->withProxyQueue(new PrioritizedProxyQueue());
```

### Request-Specific Proxies

The `HasProxy` trait can be used on individual requests as well:

```php
use Saloon\Http\Request;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyManagerInterface;

class MyApiRequest extends Request implements ProxyManagerInterface
{
    use HasProxy;
    
    protected ?string $method = 'GET';
    
    public function resolveEndpoint(): string
    {
        return '/api/endpoint';
    }
    
    public function __construct()
    {
        // Add request-specific proxies
        $this->getProxyQueue()->enqueue('http://request-specific-proxy.example.com:8080');
    }
}
```



### Error Handling

```php
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\NoAvailableProxyException;
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\HasProxyException;

try {
    $connector->withForceProxy(true);
    $response = $connector->send(new MyRequest());
} catch (NoAvailableProxyException $e) {
    // Handle the case when no proxies are available
    echo "No proxies available: " . $e->getMessage();
} catch (HasProxyException $e) {
    // Handle interface implementation issues
    echo "Proxy configuration error: " . $e->getMessage();
} catch (\Exception $e) {
    // Handle other exceptions
    echo "Request error: " . $e->getMessage();
}
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the MIT license.

## Credits

- [weijiajia](https://github.com/weijiajia)
- [All Contributors](../../contributors)