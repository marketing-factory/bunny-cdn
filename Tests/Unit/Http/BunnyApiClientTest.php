<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Tests\Unit\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Mfd\BunnyCdn\Http\BunnyApiClient;
use Psr\Http\Message\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BunnyApiClientTest extends UnitTestCase
{
    /**
     * @var array<int, array{request: RequestInterface}>
     */
    private array $history = [];

    private function createSubject(int $responses = 1): BunnyApiClient
    {
        $mockHandler = new MockHandler(array_fill(0, $responses, new Response(204)));
        $stack = HandlerStack::create($mockHandler);
        $this->history = [];
        $stack->push(Middleware::history($this->history));

        return new BunnyApiClient(new Client(['handler' => $stack]));
    }

    public function testPurgeUrlSendsExpectedRequest(): void
    {
        $subject = $this->createSubject();
        $subject->purgeUrl('secret-key', 42, 'https://example.com/foo');

        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.bunny.net/pullzone/42/purgeCache', (string)$request->getUri());
        self::assertSame('secret-key', $request->getHeaderLine('AccessKey'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"Url":"https:\/\/example.com\/foo"}',
            (string)$request->getBody()
        );
    }

    public function testPurgeTagSendsExpectedRequest(): void
    {
        $subject = $this->createSubject();
        $subject->purgeTag('secret-key', 7, 'pageId_5');

        self::assertCount(1, $this->history);
        $request = $this->history[0]['request'];

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.bunny.net/pullzone/7/purgeCache', (string)$request->getUri());
        self::assertJsonStringEqualsJsonString(
            '{"CacheTag":"pageId_5"}',
            (string)$request->getBody()
        );
    }
}
