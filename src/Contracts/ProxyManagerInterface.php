<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin\Contracts;

use Weijiajia\SaloonphpHttpProxyPlugin\ProxySplQueue;

interface ProxyManagerInterface
{
    /**
     * 获取代理队列
     */
    public function getProxyQueue(): ProxySplQueue;
    
    /**
     * 获取代理启用状态
     */
    public function isProxyEnabled(): bool;
    
    /**
     * 获取是否强制使用代理
     */
    public function isForceProxyEnabled(): bool;
    
    // /**
    //  * 获取代理切换条件
    //  */
    // public function getProxySwitchCondition(): callable;
}