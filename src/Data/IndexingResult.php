<?php

// src/Data/IndexingResult.php

namespace DancingJanissary\SeoIndexing\Data;

class IndexingResult
{
    public function __construct(
        public readonly string  $engine,
        public readonly string  $url,
        public readonly string  $action,
        public readonly bool    $success,
        public readonly int     $httpStatus,
        public readonly ?string $message   = null,
        public readonly ?array  $payload   = null,
    ) {}

    public static function success(
        string $engine,
        string $url,
        string $action,
        int    $httpStatus = 200,
        ?array $payload    = null,
    ): self {
        return new self(
            engine:     $engine,
            url:        $url,
            action:     $action,
            success:    true,
            httpStatus: $httpStatus,
            payload:    $payload,
        );
    }

    public static function failure(
        string $engine,
        string $url,
        string $action,
        int    $httpStatus,
        string $message,
        ?array $payload = null,
    ): self {
        return new self(
            engine:     $engine,
            url:        $url,
            action:     $action,
            success:    false,
            httpStatus: $httpStatus,
            message:    $message,
            payload:    $payload,
        );
    }

    public function toArray(): array
    {
        return [
            'engine'      => $this->engine,
            'url'         => $this->url,
            'action'      => $this->action,
            'success'     => $this->success,
            'http_status' => $this->httpStatus,
            'message'     => $this->message,
            'payload'     => $this->payload,
        ];
    }
}