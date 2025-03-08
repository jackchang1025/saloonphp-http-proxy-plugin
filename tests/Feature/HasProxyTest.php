<?php

use Saloon\Exceptions\DuplicatePipeNameException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Enums\Method;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Saloon\Http\Faking\MockClient;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxy;
use Weijiajia\SaloonphpHttpProxyPlugin\Proxy;
use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;
use Saloon\Http\Faking\MockResponse;
use Weijiajia\SaloonphpHttpProxyPlugin\HasProxyInterface;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;
class TestConnector extends Connector
{
    use HasProxy;
    use AlwaysThrowOnErrors;

    public function resolveBaseUrl(): string
    {
        return 'https://api.example.com';
    }
}

// 创建测试用的Request类
class TestRequest extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return 'test-endpoint';
    }
}

// 测试代理队列设置和获取
it('sets and gets proxy queue', function () {
    $connector = new TestConnector();
    $proxyQueue = new ProxySplQueue();
    
    // 设置前应该为null
    expect($connector->getSplQueue())->toBeInstanceOf(ProxySplQueue::class);
    
    // 设置代理队列
    $connector->withSplQueue($proxyQueue);
    expect($connector->getSplQueue())->toBe($proxyQueue);
});

// 测试当代理禁用时，请求中不应包含代理设置
it('does not apply proxy when disabled', function () {
    $connector = new TestConnector();
    $proxyQueue = new ProxySplQueue();
    
    // 添加测试代理
    $proxyQueue->enqueue(new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    ));
    
    $connector->withSplQueue($proxyQueue);
    
    // 禁用代理
    $connector->withProxyEnabled(false);
    
    // 配置模拟客户端
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);
    $connector->withMockClient($mockClient);

    // 发送请求
    $response = $connector->send(new TestRequest());
    
    // 验证请求中没有代理设置
    expect($response->getPendingRequest()->config()->get(RequestOptions::PROXY))->toBeNull();
});

// 测试代理URL应用到请求
it('applies proxy URL to request', function () {
    $connector = new TestConnector();
    $request = new TestRequest();
    $proxyQueue = new ProxySplQueue();
    
    // 添加一个测试代理
    $proxyQueue->enqueue(new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    ));
    
    $connector->withSplQueue($proxyQueue);

    // 配置模拟客户端
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);
    $connector->withMockClient($mockClient);

    // 发送请求
    $response = $connector->send($request);
    
    // 验证代理URL已应用到请求
    expect($response->getPendingRequest()->config()->get(RequestOptions::PROXY))->toBe('http://192.168.1.1:8080');
});

// 测试当没有可用代理时的行为
it('handles case when no proxy available', function () {
    $connector = new TestConnector();
    $proxyQueue = new ProxySplQueue();
    
    // 不添加任何代理
    $connector->withSplQueue($proxyQueue);
    $connector->withForceProxy(true);
    
   // 配置模拟客户端
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);
    $connector->withMockClient($mockClient);

    // 发送请求
    $response = $connector->send(new TestRequest());

})->throws(\RuntimeException::class, 'No available proxy');

// 测试非强制模式下没有代理的行为
it('does not throw exception in non-force mode when no proxy available', function () {
    $connector = new TestConnector();
    $proxyQueue = new ProxySplQueue();
    $request = new TestRequest();
    // 不添加任何代理
    $connector->withSplQueue($proxyQueue);
    
    // 设置为非强制模式
    $connector->withForceProxy(false);

    // 配置模拟客户端
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);
    $connector->withMockClient($mockClient);

    // 发送请求
    $response = $connector->send($request);

    // 应该没有设置代理
    expect($connector->getCurrentProxy())->toBeNull();
});

// 测试代理切换条件
it('switches proxy based on condition', function () {
    // 创建两个测试代理
    $proxy1 = new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    );
    
    $proxy2 = new Proxy(
        host: '192.168.1.2',
        port: 8080,
        url: 'http://192.168.1.2:8080',
        type: 'http'
    );
    
    // 创建代理队列
    $proxyQueue = new ProxySplQueue([$proxy1, $proxy2]);
    
    // 创建连接器并配置代理
    $connector = new TestConnector();
    $connector->withSplQueue($proxyQueue);
    
    // 设置自定义切换条件（总是切换）
    $connector->switchProxyWhen(function(?Response $response, ?\Throwable $exception, PendingRequest $pendingRequest) {
        return true;
    });
    
   
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);

    $connector->withMockClient($mockClient);

    $response = $connector->send(new TestRequest());
    
    // 由于切换条件返回 true，第一个代理应该被移出队列，当前代理应该是第二个代理

    expect($connector->getCurrentProxy())->toBe($proxy2);
});

// 测试轮换模式
it('respects round robin mode', function () {
    // 创建测试代理
    $proxy1 = new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    );
    
    // 创建代理队列只有一个代理
    $proxyQueue = new ProxySplQueue([$proxy1]);
    
    // 创建连接器并配置代理
    $connector = new TestConnector();
    $connector->withSplQueue($proxyQueue);
    
    // 启用轮换模式
    $connector->roundRobin(true);
    
    // 设置总是切换的条件
    $connector->switchProxyWhen(fn() => true);
    
    $mockClient = new MockClient([
        TestRequest::class => MockResponse::make(body: '', status: 200),
    ]);

    $connector->withMockClient($mockClient);

    $response = $connector->send(new TestRequest());
    
    // 在轮换模式下，代理应该被重新入队，队列不应为空
    expect($connector->getSplQueue()->isEmpty())->toBeFalse()
        ->and($connector->getSplQueue()->count())->toBe(1)
        ->and($connector->getCurrentProxy())->toBe($proxy1);

});

// 测试异常处理
it('handles exceptions correctly', function () {
    // 创建测试代理
    $proxy1 = new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    );

    $proxy2 = new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    );
    
    // 创建代理队列
    $proxyQueue = new ProxySplQueue([$proxy1, $proxy2]);
    
    // 创建连接器并配置代理
    $connector = new TestConnector();
    $connector->withSplQueue($proxyQueue);

    try {

        $connector->send(new TestRequest());

        $this->fail('Expected an exception to be thrown');
    }catch (FatalRequestException $e){
        // 验证代理被移出队列
        expect($connector->getSplQueue()->isEmpty())->toBeTrue()->and($connector->getCurrentProxy())->toBe($proxy2);
    }
});

// 测试默认代理切换条件
it('uses default proxy switch condition when none specified', function () {
    $connector = new TestConnector();
    
    // 默认条件应该检查是否是FatalRequestException
    $condition = $connector->getProxySwitchCondition();
    
    // 创建模拟对象
    $pendingRequest = $this->createMock(PendingRequest::class);
    
    // 测试异常情况
    $exception = new FatalRequestException(
        new ConnectException('Connection failed', new GuzzleRequest('GET', 'https://example.com')),
        $pendingRequest
    );
    
    // 应该返回true表示需要切换代理
    expect($condition(null, $exception, $pendingRequest))->toBeTrue();
    
    // 测试非异常情况
    $responseMock = $this->createMock(Response::class);
    expect($condition($responseMock, null, $pendingRequest))->toBeFalse();
});

// 测试同时在Connector和Request中使用HasProxy插件
it('handles case when HasProxy used in both connector and request with duplicate pipe name', function () {

    $testConnector = new class extends Connector
    {
        use HasProxy;

        public function resolveBaseUrl(): string
        {
            return 'https://api.example.com';
        }
    };

    // 创建测试用的Request类
    $testRequest = new class extends Request
    {
        use HasProxy;
        protected Method $method = Method::GET;

        public function resolveEndpoint(): string
        {
            return 'test-endpoint';
        }
    };

    // 创建测试代理
    $proxy1 = new Proxy(
        host: '192.168.1.1',
        port: 8080,
        url: 'http://192.168.1.1:8080',
        type: 'http'
    );
    
    $proxy2 = new Proxy(
        host: '192.168.1.2',
        port: 8080,
        url: 'http://192.168.1.2:8080',
        type: 'http'
    );
    
    // 为Connector创建代理队列
    $connectorProxyQueue = new ProxySplQueue([$proxy1]);
    
    // 为Request创建代理队列
    $requestProxyQueue = new ProxySplQueue([$proxy2]);
    
    // 创建连接器并配置代理
    $testConnector->withSplQueue($connectorProxyQueue);
    $testConnector->withProxyEnabled(true);
    
    // 创建带有HasProxy的Request并配置代理
    $testRequest->withSplQueue($requestProxyQueue);
    $testRequest->withProxyEnabled(true);
    
    // 配置模拟客户端
    $mockClient = new MockClient([
        $testRequest::class => MockResponse::make(body: '', status: 200),
    ]);
    $testConnector->withMockClient($mockClient);
    
    // 发送请求
    $response = $testConnector->send($testRequest);
    
    // 获取代理配置
    $proxyConfig = $response->getPendingRequest()->config()->get(RequestOptions::PROXY);
    
    // 验证最终使用的代理是Request中的代理，而不是Connector中的
    expect($proxyConfig)->toBe('http://192.168.1.2:8080');
    
    // 方法2：修改HasProxy trait，添加记录最终使用的代理URL的功能
    // 这需要修改HasProxy代码，添加一个lastUsedProxyUrl属性
    
    // 以下假设你已经添加了这个功能
    // expect($request->getLastUsedProxyUrl())->toBe('http://192.168.1.2:8080');
})->throws(DuplicatePipeNameException::class, '"proxy-response" pipe already exists on the pipeline');