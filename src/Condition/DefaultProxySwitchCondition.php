<?php

namespace Weijiajia\SaloonphpHttpProxyPlugin\Condition;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\PendingRequest;
use Throwable;
use Saloon\Http\Response;

class DefaultProxySwitchCondition
{
    /**
     * 重试的HTTP状态码
     *
     * @var array<int>
     */
    protected array $retryStatusCodes = [
        407, // 代理认证失败
        502, // 网关错误
        503, // 服务不可用
        504, // 网关超时
    ];

    /**
     * 设置重试状态码
     *
     * @param array $statusCodes
     * @return $this
     */
    public function withRetryStatusCodes(array $statusCodes): self
    {
        $this->retryStatusCodes = $statusCodes;
        return $this;
    }

    /**
     * 添加重试状态码
     *
     * @param int $statusCode
     * @return $this
     */
    public function addRetryStatusCode(int $statusCode): self
    {
        $this->retryStatusCodes[] = $statusCode;
        return $this;
    }


    /**
     * 使该类可以作为回调函数使用
     *
     * @param Response|null $response
     * @param Throwable|null $exception
     * @param PendingRequest $pendingRequest
     * @return bool
     */
    public function __invoke(?Response $response, ?\Throwable $exception, PendingRequest $pendingRequest): bool
    {
        // 处理连接异常 (通常是代理连接问题)
        if ($exception instanceof ConnectException) {
            return true;
        }

        // 处理致命请求异常
        if ($exception instanceof FatalRequestException) {
            $previous = $exception->getPrevious();

            if ($previous instanceof RequestException && $previous->hasResponse()) {
                $statusCode = $previous->getResponse()?->getStatusCode();
                return in_array($statusCode, $this->retryStatusCodes, true);
            }

            return true;
        }

        // 处理请求异常
        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $statusCode = $exception->getResponse()?->getStatusCode();
            return in_array($statusCode, $this->retryStatusCodes, true);
        }

        // 默认不切换代理
        return false;
    }
} 