.. _administrators:

===================
For administrators
===================

Installation and configuration (enable switch, API key, Pull Zone ID) are
covered in the README in the repository root.

Checking activation state
==========================

See the README's "Checking activation state" section for full examples.
Short version: ``active`` (is Bunny configured for this site) and
``viaBunny`` (did *this* request actually come through Bunny's edge) are
both available as:

*  PHP: ``$context->getPropertyFromAspect('bunnyCdn', 'active')``
*  TypoScript: ``data = context:bunnyCdn:active``
*  Fluid: ``{bunny:isActive()}`` / ``{bunny:isViaBunny()}`` (namespace
   ``xmlns:bunny="http://typo3.org/ns/Mfd/BunnyCdn/ViewHelpers"``)

Custom cache tags
==================

To purge additional, custom-scoped content together (e.g. everything for one
product, one category), no extra setup is needed here — just add a TypoScript
``cache.tags`` on the relevant content, the way TYPO3 already supports it.
The tag is automatically included in the ``CDN-Tag`` header and purged the
same way as page/content-element tags.

Troubleshooting
================

Purge failures never block editors from saving — they're logged instead, via
TYPO3's regular logging (``Mfd\BunnyCdn\...`` classes). Check there first if
content doesn't seem to invalidate: most causes are a wrong/expired API key,
a missing or wrong Pull Zone ID on a site, or Bunny being unreachable.
