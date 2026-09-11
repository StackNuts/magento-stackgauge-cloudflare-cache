# StackNuts StackGauge CloudflareCache

[![Latest Version](https://img.shields.io/packagist/v/stacknuts/magento-stackgauge-cloudflare-cache.svg)](https://packagist.org/packages/stacknuts/magento-stackgauge-cloudflare-cache) [![License](https://img.shields.io/packagist/l/stacknuts/magento-stackgauge-cloudflare-cache.svg)](https://github.com/StackNuts/magento-stackgauge-cloudflare-cache/blob/main/LICENSE) [![PHP Version](https://img.shields.io/packagist/php-v/stacknuts/magento-stackgauge-cloudflare-cache.svg)](https://packagist.org/packages/stacknuts/magento-stackgauge-cloudflare-cache)

A compatibility module: reports [`stacknuts/magento-cloudflare-cache`](https://github.com/StackNuts/magento-cloudflare-cache)'s
purge-queue status into a [`stacknuts/magento-stackgauge`](https://github.com/StackNuts/magento-stackgauge)
fleet report, via StackGauge's pluggable `ReporterInterface`.

## Why a separate module

Both parent modules are independently useful, independently published, and shouldn't have to
know about each other. This module - not either of them - is the only place that depends on
both, following the same "compatibility module" pattern Magento core itself uses for optional
cross-module integrations (e.g. `Magento_CatalogInventoryGraphQl`, `Magento_PaypalGraphQl`):
a small module named after the pair it bridges, containing only glue code.

Install it and both are wired together automatically. Don't install it, and both modules
work exactly as if it didn't exist - no partial functionality, no missing classes, no
`setup:di:compile` risk on either parent module installed alone.

## Installation

Requires both parent modules to already be installed.

```bash
composer require stacknuts/magento-stackgauge-cloudflare-cache
bin/magento module:enable StackNuts_StackGaugeCloudflareCache
bin/magento setup:upgrade
```

## What it does

Registers `Model/StackGaugeReporter.php` against StackGauge's `ReporterPool`, contributing a
`cloudflare` block to every StackGauge full report:

```json
{
  "cloudflare": {
    "schema_version": "1.0",
    "label": "Cloudflare",
    "description": "Whether Cloudflare is the active cache type, and the current delayed-purge-queue backlog.",
    "sections": [
      {
        "kind": "facts",
        "key": "general",
        "label": "General",
        "description": "Whether Cloudflare is the active cache type, and the current delayed-purge-queue backlog.",
        "fields": {
          "enabled": {"type": "bool", "label": "Enabled", "value": true},
          "purge_queue_backlog": {"type": "number", "label": "Purge Queue Backlog", "value": 0, "metric_key": "cloudflare.purge_queue_backlog"},
          "queue_oldest_pending_seconds": {"type": "number", "label": "Queue Oldest Pending (seconds)", "value": 0, "metric_key": "cloudflare.queue_oldest_pending_seconds"}
        }
      }
    ]
  }
}
```

- `enabled`: whether Cloudflare is currently the active Full Page Cache type
  (`StackNuts\CloudflareCache\Model\Config::isActive()`).
- `purge_queue_backlog`: pending tag count in the delayed purge queue
  (`StackNuts\CloudflareCache\Model\ResourceModel\PurgeQueue::getPendingCount()`) - naturally
  `0` whenever the delayed queue is disabled, since nothing is ever enqueued in that mode.
- `queue_oldest_pending_seconds`: age in seconds of the oldest tag still waiting in the queue
  (`PurgeQueue::getOldestPendingAgeInSeconds()`), `0` when the queue is empty. A sustained
  high value is a proxy for the drain cron having stopped running.

Both queue fields are declared as **trackable metrics** (`MetricCatalogInterface`), so a
StackGauge dashboard auto-creates an editable alert rule for each the first time it syncs this
module's config - "Purge Queue Backlog > 50" and "Oldest Pending Purge > 1800s", both over a
15-minute window by default. No configuration of its own beyond that - no admin UI, nothing to
disable independently - remove the module (or uninstall either parent) to turn the integration
off.

## License

[PolyForm Shield 1.0.0](https://polyformproject.org/licenses/shield/1.0.0), same as
`stacknuts/magento-stackgauge`. See [LICENSE](LICENSE).
