.. _architecture:

==============
Architecture
==============

Reuses the cache tags TYPO3 already computes for every page render (page ID,
content elements, custom TypoScript ``cache.tags``) for two things:

*  A middleware sets them as the ``CDN-Tag`` response header, so Bunny can
   purge by tag.
*  A decorator around the ``pages`` cache backend forwards the same tags to
   Bunny whenever TYPO3 flushes them, plus the affected page's URL.

See :ref:`decision 0001 <decision-0001>` for why invalidation is triggered
from the cache backend rather than a DataHandler hook.

Rate-limit retry
==================

``Mfd\BunnyCdn\Http\BunnyApiClient`` disables Guzzle's ``http_errors``, so a
non-2xx response comes back as a normal ``ResponseInterface`` instead of an
exception — ``BunnyCdnService`` branches on the status code itself rather
than sniffing Guzzle exception types.

On a ``429`` (rate limited), and only when the ``asyncRetryEnabled``
Extension Configuration switch is on, ``BunnyCdnService::scheduleRetry()``
dispatches a ``Mfd\BunnyCdn\Message\RetryPurgeTagMessage`` or
``RetryPurgeUrlMessage`` via the injected ``MessageBusInterface``. Both
carry only what's needed to redo the call (pull zone ID + tag, or just the
URL) — never the API key, which the handler re-reads from Extension
Configuration itself, the same way ``BunnyCdnService`` does.

``ext_localconf.php`` routes both message classes to the ``doctrine``
transport TYPO3 core already wires up (a ``sys_messenger_messages``
database table) — routing is registered unconditionally; whether a message
ever gets dispatched onto it is what the config switch actually gates. A
``messenger:consume doctrine`` worker eventually picks up the message and
``Mfd\BunnyCdn\MessageHandler\RetryPurgeMessageHandler`` (registered via the
``#[AsMessageHandler]`` attribute) calls back into
``BunnyCdnService::retryPurgeTag()`` / ``retryPurgeUrl()`` for the actual
retry. Those don't reschedule on a repeated 429 — just log it — since
nothing in this extension implements Symfony's exponential-backoff retry
strategy (TYPO3 core doesn't wire up
``SendFailedMessageForRetryListener`` either), so an unbounded requeue loop
is exactly what letting the handler reschedule itself would risk.

Activation state
==================

Separately from invalidation, ``Mfd\BunnyCdn\Middleware\BunnyCdnAspectMiddleware``
runs right after site resolution and sets a ``bunnyCdn`` Context aspect
(``Mfd\BunnyCdn\Context\BunnyCdnAspect``) with:

*  ``active`` — from ``BunnyCdnService::isActiveForSite()``, the same
   enabled/API-key/Pull-Zone-ID check ``purgeByTags()`` uses, just made
   callable outside that class.
*  ``viaBunny`` and a handful of other properties (``serverId``,
   ``requestId``, ``requestCountryCode``, ``requestStateCode``,
   ``mobileDevice``, ``connectionId``, ``host``, ``serverZone``) — read
   directly off the inbound request's ``CDN-*`` headers, which Bunny adds
   to every request it proxies to origin.

A Context aspect (rather than a Fluid-only ViewHelper) makes all of this
reachable from TypoScript's ``context`` data type and plain PHP as well as
Fluid, without three parallel implementations. Two ViewHelpers
(``Mfd\BunnyCdn\ViewHelpers\IsActiveViewHelper``,
``...\IsViaBunnyViewHelper``) wrap the aspect for the common yes/no cases;
everything else is reachable via the TypoScript bridge. See
:ref:`administrators` for usage examples.
