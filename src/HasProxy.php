<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin;

use GuzzleHttp\RequestOptions;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use SplQueue;
use Saloon\Traits\Conditionable;

trait HasProxy
{
    use Conditionable;

    /**
     * 代理管理器实例
     *
     * @var SplQueue|null
     */
    protected ?SplQueue $splQueue = null;

    /**
     * 轮换模式
     *
     * @var bool
     */
    protected bool $roundRobin = false;

    /**
     * 当前使用的代理
     *
     * @var ProxyInterface|null
     */
    protected ?ProxyInterface $currentProxy = null;

    /**
     * 是否强制使用代理
     *
     * @var bool
     */
    protected bool $forceProxy = false;

    /**
     * 代理启用状态
     *
     * @var bool
     */
    protected bool $proxyEnabled = true;

    /**
     * 代理切换条件
     *
     * @var callable|null
     */
    protected mixed $proxySwitchCondition = null;

    /**
     * 设置代理切换条件
     *
     * @param callable $condition 一个接收 (Response|null $response, \Throwable|null $exception) 并返回 bool 的回调函数
     * @return static
     */
    public function switchProxyWhen(callable $condition): static
    {
        $this->proxySwitchCondition = $condition;
        return $this;
    }


    /**
     * 设置轮换模式
     *
     * @param bool $roundRobin
     * @return static
     */
    public function roundRobin(bool $roundRobin = true): static
    {
        $this->roundRobin = $roundRobin;
        return $this;
    }

    /**
     * 获取轮换模式
     *
     * @return bool
     */
    public function getRoundRobin(): bool
    {
        return $this->roundRobin;
    }

    /**
     * 获取当前使用的代理
     *
     * @return ProxyInterface|null
     */
    public function getCurrentProxy(): ?ProxyInterface
    {
        return $this->currentProxy;
    }

    public function setCurrentProxy(?ProxyInterface $proxy): void
    {
        $this->currentProxy = $proxy;
    }

    /**
     * 获取代理队列
     *
     * @return SplQueue
     */
    public function getSplQueue(): SplQueue
    {
        return $this->splQueue ??= new ProxySplQueue();
    }

    /** 
     * 设置代理队列
     *
     * @param SplQueue $splQueue
     * @return static
     */
    public function withSplQueue(SplQueue $splQueue): static
    {
        $this->splQueue = $splQueue;
        return $this;
    }

    /**
     * 获取代理切换条件
     *
     * @return callable
     */
    public function getProxySwitchCondition(): callable
    {
        return $this->proxySwitchCondition ?? static fn(?Response $response, ?\Throwable $exception, PendingRequest $pendingRequest) => $exception instanceof FatalRequestException;
    }

    /**
     * 获取代理启用状态
     *
     * @return bool
     */
    public function getProxyEnabled(): bool
    {
        return $this->proxyEnabled;
    }

    /**
     * 设置代理启用状态
     *
     * @param bool $proxyEnabled
     * @return static
     */
    public function withProxyEnabled(bool $proxyEnabled): static
    {
        $this->proxyEnabled = $proxyEnabled;
        return $this;
    }

    /**
     * 获取是否强制使用代理
     *
     * @return bool
     */
    public function getForceProxy(): bool
    {
        return $this->forceProxy;
    }

    /**
     * 设置是否强制使用代理
     *
     * @param bool $forceProxy
     * @return static
     */
    public function withForceProxy(bool $forceProxy): static
    {   
        $this->forceProxy = $forceProxy;
        return $this;
    }

    /**
     * 引导代理到请求中
     */
    public function bootHasProxy(PendingRequest $pendingRequest): void
    {
       
        // 如果代理未启用，移除代理设置
        if (!$this->getProxyEnabled()) {
            $pendingRequest->config()->add(RequestOptions::PROXY, null);
            return;
        }

        // 如果当前没有选中的代理，从队列获取一个
        if ($this->getCurrentProxy() === null) {
            $this->setCurrentProxy($this->dequeue());
        }

        // 检查是否有可用代理
        if ($this->getCurrentProxy() === null && $this->getForceProxy()) {
            throw new \RuntimeException('No available proxy');
        }

        // 将当前代理应用到请求
        $pendingRequest->config()->add(RequestOptions::PROXY, $this->getCurrentProxy()?->getUrl());

        // 设置响应中间件，用于处理代理切换逻辑
        $pendingRequest->middleware()->onResponse(function(Response $response) use ($pendingRequest) {
            $this->handleProxyResponse(response: $response, exception: null, pendingRequest: $pendingRequest);
            return $response;
        },name: 'proxy-response');

        // 设置异常中间件，用于处理代理失败
        $pendingRequest->middleware()->onFatalException(function(FatalRequestException $exception) use ($pendingRequest) {
            $this->handleProxyResponse(response: null, exception: $exception, pendingRequest: $pendingRequest);
            return $exception;
        }, name: 'proxy-exception');
    }


    protected function dequeue():?ProxyInterface
    {
        if($this->getSplQueue()->isEmpty()){
            return null;
        }

        $proxy = $this->getSplQueue()->dequeue();
        if($proxy && $this->getRoundRobin() && $proxy->isAvailable()){
            $this->getSplQueue()->enqueue($proxy);
        }
        return $proxy;
    }

    /**
     * 处理响应或异常，决定是否切换代理
     */
    protected function handleProxyResponse(?Response $response, ?\Throwable $exception, PendingRequest $pendingRequest): void
    {
        // 根据条件判断是否需要切换代理
        $shouldSwitch = call_user_func($this->getProxySwitchCondition(), $response, $exception,$pendingRequest);

        $this->when($shouldSwitch, function ($self) use ($exception) {

            // 切换到下一个代理（如果有）
            $self->setCurrentProxy($self->dequeue());
        });
    }
}