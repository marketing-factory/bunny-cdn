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
