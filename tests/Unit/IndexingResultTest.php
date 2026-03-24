<?php

namespace DancingJanissary\SeoIndexing\Tests\Unit;

use DancingJanissary\SeoIndexing\Data\IndexingResult;
use PHPUnit\Framework\TestCase;

class IndexingResultTest extends TestCase
{
    public function test_success_factory_creates_successful_result(): void
    {
        $result = IndexingResult::success(
            engine: 'google',
            url: 'https://example.com/page',
            action: 'URL_UPDATED',
            httpStatus: 200,
            payload: ['url' => 'https://example.com/page'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('google', $result->engine);
        $this->assertSame('https://example.com/page', $result->url);
        $this->assertSame('URL_UPDATED', $result->action);
        $this->assertSame(200, $result->httpStatus);
        $this->assertSame(['url' => 'https://example.com/page'], $result->payload);
        $this->assertNull($result->message);
    }

    public function test_success_factory_defaults_http_status_to_200(): void
    {
        $result = IndexingResult::success(
            engine: 'google',
            url: 'https://example.com',
            action: 'URL_UPDATED',
        );

        $this->assertSame(200, $result->httpStatus);
    }

    public function test_failure_factory_creates_failed_result(): void
    {
        $result = IndexingResult::failure(
            engine: 'google',
            url: 'https://example.com/page',
            action: 'URL_DELETED',
            httpStatus: 403,
            message: 'Forbidden',
            payload: ['errors' => ['access denied']],
        );

        $this->assertFalse($result->success);
        $this->assertSame('google', $result->engine);
        $this->assertSame('https://example.com/page', $result->url);
        $this->assertSame('URL_DELETED', $result->action);
        $this->assertSame(403, $result->httpStatus);
        $this->assertSame('Forbidden', $result->message);
        $this->assertSame(['errors' => ['access denied']], $result->payload);
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $result = IndexingResult::success(
            engine: 'indexnow',
            url: 'https://example.com/page',
            action: 'URL_UPDATED',
            httpStatus: 202,
            payload: ['status' => 'accepted'],
        );

        $array = $result->toArray();

        $this->assertSame([
            'engine'      => 'indexnow',
            'url'         => 'https://example.com/page',
            'action'      => 'URL_UPDATED',
            'success'     => true,
            'http_status' => 202,
            'message'     => null,
            'payload'     => ['status' => 'accepted'],
        ], $array);
    }

    public function test_to_array_includes_message_on_failure(): void
    {
        $result = IndexingResult::failure(
            engine: 'google',
            url: 'https://example.com',
            action: 'URL_UPDATED',
            httpStatus: 500,
            message: 'Internal server error',
        );

        $array = $result->toArray();

        $this->assertSame('Internal server error', $array['message']);
        $this->assertFalse($array['success']);
        $this->assertSame(500, $array['http_status']);
    }

    public function test_properties_are_readonly(): void
    {
        $result = IndexingResult::success(
            engine: 'google',
            url: 'https://example.com',
            action: 'URL_UPDATED',
        );

        $reflection = new \ReflectionClass($result);

        foreach ($reflection->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
