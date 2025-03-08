<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin;

use InvalidArgumentException;
use SplQueue;


class ProxySplQueue extends SplQueue
{
    /**
     * 构造函数
     *
     * @param array $proxies 初始代理列表
     */
    public function __construct(array $proxies = []) 
    {
        
        // 初始化队列
        foreach ($proxies as $proxy) {
            $this->enqueue($proxy);
        }
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
        // 如果传入字符串URL，转换为代理对象
        if (is_string($value)) {
            $value = Proxy::fromUrl($value);
        }
        
        // 确保传入的是 ProxyInterface 类型
        if (!$value instanceof ProxyInterface) {
            throw new InvalidArgumentException('Only ProxyInterface objects or proxy URLs can be added to the queue');
        }
        
        // 调用父类实现添加到队列
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
        
        // 从队列前端获取代理，不再循环使用
        return parent::dequeue();
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