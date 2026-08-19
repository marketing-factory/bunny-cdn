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
