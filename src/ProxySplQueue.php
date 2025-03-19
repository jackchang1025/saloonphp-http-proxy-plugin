<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin;

use InvalidArgumentException;
use SplQueue;
use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyInterface;

class ProxySplQueue extends SplQueue
{
    
    public function __construct(protected bool $roundRobinEnabled = false,array $proxies = []) 
    {
        foreach ($proxies as $proxy) {
            $this->enqueue($proxy);
        }
    }

     /**
     * 设置轮换模式
     */
    public function setRoundRobinEnabled(bool $enabled): self
    {
        $this->roundRobinEnabled = $enabled;
        return $this;
    }

     /**
     * 获取轮换模式状态
     */
    public function isRoundRobinEnabled(): bool
    {
        return $this->roundRobinEnabled;
    }
    
    /**
     * 将代理添加到队列末尾
     * 重写父类方法以确保只有有效的代理可以入队
     *
     * @param ProxyInterface|string $value 代理对象或代理URL
     * @return void
     * @throws InvalidArgumentException 如果提供的不是有效的代理
     */
    public function enqueue(mixed $value): void
    {
        if (is_string($value)) {
            $value = Proxy::fromUrl($value);
        }
        
        if (!$value instanceof ProxyInterface) {
            throw new InvalidArgumentException('Only ProxyInterface objects or proxy URLs can be added to the queue');
        }
        
        parent::enqueue($value);
    }
    
    /**
     * 从队列前端获取代理（标准FIFO行为）
     *
     * @return ProxyInterface|null
     */
    public function dequeue(): mixed
    {
        if ($this->isEmpty()) {
            return null;
        }

       $proxy = parent::dequeue();
        
        if ($this->isRoundRobinEnabled() && $proxy instanceof ProxyInterface && $proxy->isAvailable()) {
            parent::enqueue($proxy);
        }
        
        return $proxy;
    }
    
    /**
     * 查看队列中下一个代理但不移除
     *
     * @return ProxyInterface|null
     */
    public function peek(): ?ProxyInterface
    {
        if ($this->isEmpty()) {
            return null;
        }
        
        return $this->bottom();
    }
    
    /**
     * 从队列中获取所有代理（不会改变队列）
     *
     * @return array<ProxyInterface>
     */
    public function getAllProxies(): array
    {
        $proxies = [];
        $tempQueue = clone $this;
        
        foreach ($tempQueue as $proxy) {
            $proxies[] = $proxy;
        }
        
        return $proxies;
    }
    
    /**
     * 移除所有代理
     *
     * @return self
     */
    public function clear(): self
    {
        while (!$this->isEmpty()) {
            parent::dequeue();
        }
        
        return $this;
    }
} 