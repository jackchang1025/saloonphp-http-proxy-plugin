<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin;

use GuzzleHttp\RequestOptions;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Saloon\Traits\Conditionable;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyManagerInterface;
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\NoAvailableProxyException;
use Weijiajia\SaloonphpHttpProxyPlugin\Exceptions\HasProxyException;

trait HasProxy
{
    use Conditionable;

    /**
     * 代理队列实例
     */
    protected ?ProxySplQueue $proxyQueue = null;
    
    /**
     * 是否强制使用代理
     */
    protected bool $forceProxy = true;
    
    /**
     * 代理启用状态
     */
    protected bool $proxyEnabled = true;

    /**
     * 设置代理切换条件
     */
    public function switchProxyWhen(callable $condition): static
    {
        $this->proxySwitchCondition = $condition;
        return $this;
    }

    /**
     * 设置轮换模式 - 代理到队列的方法
     */
    public function roundRobin(bool $enabled = true): static
    {
        $this->getProxyQueue()->setRoundRobinEnabled($enabled);
        return $this;
    }

    /**
     * 获取代理队列
     */
    public function getProxyQueue(): ProxySplQueue
    {
        return $this->proxyQueue ??= new ProxySplQueue();
    }

    /**
     * 设置代理队列
     */
    public function withProxyQueue(ProxySplQueue $queue): static
    {
        $this->proxyQueue = $queue;
        return $this;
    }

    /**
     * 获取代理启用状态
     */
    public function isProxyEnabled(): bool
    {
        return $this->proxyEnabled;
    }

    /**
     * 设置代理启用状态
     */
    public function withProxyEnabled(bool $proxyEnabled): static
    {
        $this->proxyEnabled = $proxyEnabled;
        return $this;
    }

    /**
     * 获取是否强制使用代理
     */
    public function isForceProxyEnabled(): bool
    {
        return $this->forceProxy;
    }

    /**
     * 设置是否强制使用代理
     */
    public function withForceProxy(bool $forceProxy): static
    {   
        $this->forceProxy = $forceProxy;
        return $this;
    }

    /**
     * 引导代理到请求中
     * 无状态实现，不保存代理信息
     */
    public function bootHasProxy(PendingRequest $pendingRequest): void
    {
        $connector = $pendingRequest->getConnector();
        $request = $pendingRequest->getRequest();
        

        if (! $request instanceof ProxyManagerInterface && ! $connector instanceof ProxyManagerInterface) {
            throw new HasProxyException(sprintf('Your connector or request must implement %s to use the HasCaching plugin', ProxyManagerInterface::class));
        }

        /** @var ProxyManagerInterface $proxyManager */

        $proxyManager = $request instanceof ProxyManagerInterface
        ? $request
        : $connector;

        if (!$proxyManager->isProxyEnabled()) {
            $pendingRequest->config()->add(RequestOptions::PROXY, null);
            return;
        }
        
        $proxy = $proxyManager->getProxyQueue()->dequeue();
        
        if ($proxy === null && $proxyManager->isForceProxyEnabled()) {
            throw new NoAvailableProxyException('No available proxy');
        }
        
        $pendingRequest->config()->add(RequestOptions::PROXY, $proxy->getUrl());
        
    }
}