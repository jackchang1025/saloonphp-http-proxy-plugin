# Saloon PHP HTTP 代理插件

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)

为 [Saloon PHP](https://github.com/saloonphp/saloon) 提供的强大 HTTP 代理管理插件，让您可以在 API 请求中轻松使用和轮换代理。

[中文文档](README.zh-CN.md) | [English](README.md)

## 目录

- [功能特性](#功能特性)
- [环境要求](#环境要求)
- [安装](#安装)
- [使用方法](#使用方法)
  - [基本设置](#基本设置)
  - [添加代理](#添加代理)
  - [代理轮换](#代理轮换)
  - [自定义代理切换](#自定义代理切换)
  - [强制使用代理](#强制使用代理)
  - [临时禁用代理](#临时禁用代理)
  - [代理可用性管理](#代理可用性管理)
- [高级用法](#高级用法)
  - [自定义代理队列](#自定义代理队列)
  - [请求特定代理](#请求特定代理)
  - [并发请求](#并发请求)
  - [错误处理](#错误处理)
- [贡献指南](#贡献指南)
- [许可证](#许可证)
- [致谢](#致谢)

## 功能特性

- 🔄 无状态代理管理，符合 Saloon 设计原则
- 🔌 连接失败时自动切换备用代理
- 🔀 可自定义代理轮换策略（FIFO队列支持轮换模式）
- 🔐 支持需要认证的代理（用户名/密码）
- 🧩 与 Saloon 请求和连接器简单集成
- 🚦 动态代理可用性管理
- 🔍 通过自定义异常智能处理代理失败

## 环境要求

- PHP 8.0 或更高版本
- Saloon PHP v2.0 或更高版本

## 安装

您可以通过 Composer 安装此包：

```bash
composer require weijiajia/saloonphp-http-proxy-plugin
```

## 使用方法

### 基本设置

1. 在您的连接器或请求类中实现 `ProxyManagerInterface` 接口
2. 添加 `HasProxy` trait 到您的 Saloon 连接器或请求类：

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

### 添加代理

向您的连接器或请求对象添加代理：

```php
// 创建连接器
$connector = new MyApiConnector();

// 通过 URL 添加代理
$connector->getProxyQueue()->enqueue('http://proxy1.example.com:8080');
$connector->getProxyQueue()->enqueue('http://user:pass@proxy2.example.com:8080');

// 或使用 Proxy 对象
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

use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

$proxyQueue = new ProxySplQueue(roundRobinEnabled:true,proxies:['http://proxy3.example.com:8080']);

$proxy = new Proxy(
    host: 'proxy3.example.com',
    port: 8080,
    url: 'http://proxy3.example.com:8080',
    type: 'http',
    username: 'user',
    password: 'pass'
);

$proxyQueue->enqueue($proxy);

$connector->withProxyQueue($proxyQueue);

```

### 代理轮换

启用轮换代理模式，循环使用可用代理：

```php
// 直接在代理队列上启用轮换模式
$connector->getProxyQueue()->setRoundRobinEnabled(true);

// 或使用便捷方法
$connector->roundRobin(true);
```

### 强制使用代理

您可以强制请求仅在代理可用时才继续：

```php
$connector->withForceProxy(true); // 默认为 true
```

### 临时禁用代理

```php
$connector->withProxyEnabled(false);
```

### 代理可用性管理

您可以标记代理为可用或不可用：

```php
// 获取队列中的所有代理
$proxies = $connector->getProxyQueue()->getAllProxies();

// 将代理标记为不可用
$proxies[0]->setAvailable(false);

// 检查代理是否可用
$isAvailable = $proxies[0]->isAvailable(); // 返回 false
```

## 高级用法

### 自定义代理队列

您可以通过扩展 `ProxySplQueue` 类来实现自己的代理队列：

```php
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyInterface;

class PrioritizedProxyQueue extends ProxySplQueue
{
    // 您的自定义实现，带有优先级排序
    
    public function dequeue(): mixed
    {
        // 考虑优先级的自定义出队逻辑
        // ...
    }
    
    // 您还可以实现额外的方法
    public function addWithPriority(ProxyInterface $proxy, int $priority): void
    {
        // 添加带优先级的代理的自定义逻辑
    }
}

$connector->withProxyQueue(new PrioritizedProxyQueue());
```

### 请求特定代理

`HasProxy` trait 也可以用于单独的请求：

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
        // 添加请求特定的代理
        $this->getProxyQueue()->enqueue('http://request-specific-proxy.example.com:8080');
    }
}
```

### 并发请求

在使用 Saloon 的请求池时使用代理：

```php
$connector = new MyApiConnector();
$connector->getProxyQueue()->enqueue('http://proxy1.example.com:8080');
$connector->getProxyQueue()->enqueue('http://proxy2.example.com:8080');
$connector->roundRobin(true);

// 创建请求池
$pool = $connector->pool();

// 添加多个请求
$pool->add(new MyFirstRequest());
$pool->add(new MySecondRequest());
$pool->add(new MyThirdRequest());

// 使用代理轮换并发发送所有请求
$responses = $pool->send();
```

### 错误处理

```php
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\NoAvailableProxyException;
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\HasProxyException;

try {
    $connector->withForceProxy(true);
    $response = $connector->send(new MyRequest());
} catch (NoAvailableProxyException $e) {
    // 处理没有可用代理的情况
    echo "没有可用代理: " . $e->getMessage();
} catch (HasProxyException $e) {
    // 处理接口实现问题
    echo "代理配置错误: " . $e->getMessage();
} catch (\Exception $e) {
    // 处理其他异常
    echo "请求错误: " . $e->getMessage();
}
```

## 贡献指南

欢迎贡献！请随时提交 Pull Request。

## 许可证

该软件包是根据 MIT 许可证发布的开源软件。

## 致谢

- [weijiajia](https://github.com/weijiajia)
- [所有贡献者](../../contributors)