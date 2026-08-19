# bunny_cdn

Active [Bunny CDN](https://bunny.net/) cache invalidation for TYPO3 13. Purges
Bunny's edge cache whenever an editor changes content, by page URL and by
cache tag, and tags outgoing responses with Bunny's `CDN-Tag` header so those
tag-based purges actually match something.

## How it works

Purges Bunny's edge cache by reusing the cache tags TYPO3 already computes
for every page render, both to tag outgoing responses (`CDN-Tag` header) and
to trigger purges when TYPO3 flushes its own page cache. See
[`Documentation/`](Documentation/Index.rst) for architecture, editor/admin
usage, and design decisions.

## Installation

```
composer require mfd/typo3-bunny-cdn
```

## Configuration

### Enable/disable (global switch)

Extension Configuration `enabled` (Admin Tools > Settings > Extension
Configuration > bunny_cdn), on by default. Turning it off skips all purge
requests — `CdnTagHeader` still sets the `CDN-Tag` response header regardless,
since that's harmless without anything ever purging by it.

In this project, `config/system/additional.php` forces it off outside the
`Production` application context, so purge requests are never sent from
Development/Testing:

```php
if ((string)Environment::getContext() !== 'Production') {
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['bunny_cdn']['enabled'] = false;
}
```

### API Access Key (shared across all sites)

Set via the regular TYPO3 Extension Configuration: Admin Tools > Settings >
Extension Configuration > bunny_cdn, or directly in `LocalConfiguration.php` /
`AdditionalConfiguration.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['bunny_cdn']['apiKey'] = 'your-access-key';
```

To source it from an environment variable instead (e.g. so it never touches
`LocalConfiguration.php`), set it in `config/system/additional.php` — it's
read after the backend-configured value and overrides it:

```php
if (isset($_ENV['BUNNY_API_KEY'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['bunny_cdn']['apiKey'] = $_ENV['BUNNY_API_KEY'];
}
```

If empty, the extension no-ops — no purge requests are sent.

### Pull Zone ID (per site)

Each TYPO3 site needs its Bunny Pull Zone ID configured, since different
sites are typically served by different Pull Zones. Set it on the "Bunny
CDN" tab of the site configuration (`bunny_pull_zone_id`), or directly in
`config/sites/<site>/config.yaml`:

```yaml
bunny_pull_zone_id: 12345
```

A site without a Pull Zone ID configured is skipped for purging.

## Testing

Unit tests use [typo3/testing-framework](https://github.com/TYPO3/testing-framework).

```
composer test:unit
```

or directly:

```
phpunit -c Build/UnitTests.xml
```
