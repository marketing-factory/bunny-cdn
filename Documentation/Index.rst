.. _start:

=========
bunny_cdn
=========

Active `Bunny CDN <https://bunny.net/>`__ cache invalidation for TYPO3 13.
Purges Bunny's edge cache whenever an editor changes content, by page URL and
by cache tag, and tags outgoing responses with Bunny's ``CDN-Tag`` header so
those tag-based purges actually match something.

See the README in the repository root for installation and configuration.

.. toctree::
   :maxdepth: 2
   :titlesonly:

   Editors
   Administrators
   Architecture
   Decisions/Index
