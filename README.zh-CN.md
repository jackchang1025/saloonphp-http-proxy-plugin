# Saloon PHP HTTP 代理插件

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)
[![Total Downloads](https://img.shields.io/packagist/dt/weijiajia/saloonphp-http-proxy-plugin.svg)](https://packagist.org/packages/weijiajia/saloonphp-http-proxy-plugin)

为 [Saloon PHP](https://github.com/saloonphp/saloon) 提供的强大 HTTP 代理管理插件，让您可以在 API 请求中轻松使用和轮换代理。

## 功能特性

- 🔄 支持轮换代理（round-robin）
- 🔌 连接失败时自动切换备用代理
- 🔀 可自定义代理切换条件
- 🔐 支持需要认证的代理
- 🧩 与 Saloon 请求和连接器简单集成
- 🔍 智能处理代理失败和重试

## 安装

您可以通过 composer 安装此包：

```bash
composer require weijiajia/saloonphp-http-proxy-plugin
```

## 使用方法

### 基础用法

在您的 Saloon 连接器或请求类中添加 `HasProxy` trait：

```php
use Saloon\Http\Connector;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;

class MyApiConnector extends Connector
{
    use HasProxy;
    
    // ... 您的连接器代码
}
```

然后向连接器或请求添加代理：

```php
// 创建带有代理的连接器
$connector = new MyApiConnector();

// 通过 URL 添加代理
$connector->getSplQueue()->enqueue('http://proxy1.example.com:8080');
$connector->getSplQueue()->enqueue('http://user:pass@proxy2.example.com:8080');

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

$connector->getSplQueue()->enqueue($proxy);
```

### 启用轮换代理（Round-Robin）

```php
$connector->roundRobin(true);
```

### 自定义代理切换条件

您可以定义何时切换到下一个代理的自定义条件：

```php
use Weijiajia\SaloonphpHttpProxyPlugin\Condition\DefaultProxySwitchCondition;

// 使用带有自定义状态码的默认条件
$condition = new DefaultProxySwitchCondition();
$condition->withRetryStatusCodes([403, 429, 500, 502, 503, 504]);

$connector->switchProxyWhen($condition);

// 或使用自定义回调函数
$connector->switchProxyWhen(function ($response, $exception, $pendingRequest) {
    // 切换代理的逻辑
    return $response?->status() === 429 || $exception !== null;
});
```

### 强制使用代理

您可以强制请求仅在代理可用时才继续：

```php
$connector->withForceProxy(true);
```

### 临时禁用代理

```php
$connector->withProxyEnabled(false);
```

## 高级用法

### 自定义代理队列

您可以通过扩展 `ProxySplQueue` 类来实现自己的代理队列：

```php
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

class MyProxyQueue extends ProxySplQueue
{
    // 您的自定义实现
}

$connector->withSplQueue(new MyProxyQueue());
```

### 在特定请求中使用代理

`HasProxy` trait 也可以用于单独的请求：

```php
use Saloon\Http\Request;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;

class MyApiRequest extends Request
{
    use HasProxy;
    
    // ... 您的请求代码
}
```

## 许可证

该软件包是根据 MIT 许可证发布的开源软件。 