<?php

declare(strict_types=1);

namespace Mfd\BunnyCdn\Middleware;

use Mfd\BunnyCdn\Context\BunnyCdnAspect;
use Mfd\BunnyCdn\Service\BunnyCdnService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Makes Bunny CDN's activation state and per-request headers available as
 * the "bunnyCdn" Context aspect, so it's reachable from TypoScript
 * (`data = context:bunnyCdn:...`), PHP, and Fluid alike.
 */
class BunnyCdnAspectMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly BunnyCdnService $cdnService,
        private readonly Context $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $active = $site instanceof Site && $this->cdnService->isActiveForSite($site);
        $serverId = $this->header($request, 'CDN-ServerId');

        $mobileDeviceHeader = $this->header($request, 'CDN-MobileDevice');

        $this->context->setAspect('bunnyCdn', new BunnyCdnAspect(
            active: $active,
            viaBunny: $serverId !== null,
            serverId: $serverId,
            requestId: $this->header($request, 'CDN-RequestId'),
            requestCountryCode: $this->header($request, 'CDN-RequestCountryCode'),
            requestStateCode: $this->header($request, 'CDN-RequestStateCode'),
            mobileDevice: $mobileDeviceHeader !== null ? filter_var($mobileDeviceHeader, FILTER_VALIDATE_BOOLEAN) : null,
            connectionId: $this->header($request, 'CDN-ConnectionId'),
            host: $this->header($request, 'CDN-Host'),
            serverZone: $this->header($request, 'CDN-ServerZone'),
        ));

        return $handler->handle($request);
    }

    private function header(ServerRequestInterface $request, string $name): ?string
    {
        $value = $request->getHeaderLine($name);

        return $value !== '' ? $value : null;
    }
}
