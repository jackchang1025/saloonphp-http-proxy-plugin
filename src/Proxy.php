<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin;

use Weijiajia\SaloonphpHttpProxyPlugin\Contracts\ProxyInterface;

class Proxy implements ProxyInterface
{

    /**
     * 构造函数
     *
     * @param string $type 代理类型 (http, https, socks5)
     * @param string $host 代理主机
     * @param int $port 代理端口
     * @param string|null $username 代理用户名 (可选)
     * @param string|null $password 代理密码 (可选)
     */
    public function __construct(
        protected string $host,
        protected int $port,
        protected string $url,
        protected string $type,
        protected ?string $username = null,
        protected ?string $password = null,
        protected bool $available = true
    ) {
       
    }

    /**
     * 从URL字符串创建代理实例
     *
     * @param string $url 代理URL (例如 http://user:pass@host:port)
     * @return static
     */
    public static function fromUrl(string $url): static
    {
        $parsedUrl = parse_url($url);
        
        if (!isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            throw new \InvalidArgumentException("Invalid proxy URL: $url");
        }

        $type = $parsedUrl['scheme'];
        $host = $parsedUrl['host'];
        $port = $parsedUrl['port'] ?? 80;
        $username = $parsedUrl['user'] ?? null;
        $password = $parsedUrl['pass'] ?? null;

        return new static(host: $host, port: $port, url: $url, type: $type, username: $username, password: $password);
    }

    /**
     * {@inheritdoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritdoc}
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl(): string
    {
        return $this->url;
        
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getIdentifier(): string
    {
        return md5($this->getUrl());
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * {@inheritdoc}
     */
    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }
} 