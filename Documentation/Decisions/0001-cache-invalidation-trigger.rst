.. _decision-0001:

============================================================
1. Trigger Bunny purges from the cache backend, not a hook
============================================================

Status
=======

Accepted

Context
========

``clearCachePostProc`` (a DataHandler hook) looks like the natural place to
trigger a Bunny purge. It isn't: normal editor saves flush the ``pages``
cache via ``DataHandler::processClearCacheQueue()``, which never calls that
hook. ``clearCachePostProc`` only fires for TSconfig ``clearCache_disable``
commands, the manual "clear cache" backend action, and ``cachetag:``
commands — not the main case. There's also no PSR-14 event around cache-tag
flushing.

Decision
=========

Decorate the ``pages`` cache's configured backend (``BunnyPurgingBackend``,
wired in ``ext_localconf.php``) instead. It wraps the real backend and
forwards every ``flushByTag()``/``flushByTags()`` call to
``BunnyPurgeService``. This is the one place that sees every tag-based flush
regardless of trigger — edits, workspace publish, CLI, TSconfig commands.

Consequences
=============

*  Correct by construction, but less discoverable than a documented hook.
*  Couples to TYPO3 core's Cache Backend interface, which is less stable
   across major versions than DataHandler hooks — TYPO3 14 already changed
   it, breaking ``BunnyPurgingBackend``'s signatures.
