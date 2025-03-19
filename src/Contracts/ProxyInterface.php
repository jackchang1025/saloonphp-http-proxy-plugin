<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin\Contracts;

interface ProxyInterface
{
    /**
     * 获取代理主机地址 (IP或域名)
     *
     * @return string
     */
    public function getHost(): string;

    /**
     * 获取代理端口
     *
     * @return int
     */
    public function getPort(): int;

    /**
     * 获取代理类型 (http, https, socks5等)
     *
     * @return string
     */
    public function getType(): string;

    /**
     * 获取代理完整地址 (例如: http://ip:port)
     *
     * @return string
     */
    public function getUrl(): string;

    /**
     * 获取代理的用户名 (如果有)
     *
     * @return string|null
     */
    public function getUsername(): ?string;

    /**
     * 获取代理的密码 (如果有)
     *
     * @return string|null
     */
    public function getPassword(): ?string;

    /**
     * 获取代理的标识符 (用于日志和调试)
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * 检查代理是否可用
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * 标记代理的可用状态
     *
     * @param bool $available
     * @return void
     */
    public function setAvailable(bool $available): void;
}