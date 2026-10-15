<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\StackGaugeCloudflareCache\Model;

use StackNuts\CloudflareCache\Model\Config;
use StackNuts\CloudflareCache\Model\ResourceModel\PurgeQueue;
use StackNuts\StackGauge\Api\DeclaresSectionInterface;
use StackNuts\StackGauge\Api\DeclaresSlotInterface;
use StackNuts\StackGauge\Api\Field\Field;
use StackNuts\StackGauge\Api\MetricCatalogInterface;
use StackNuts\StackGauge\Api\MetricDefinition;
use StackNuts\StackGauge\Api\ReporterInterface;
use StackNuts\StackGauge\Api\Section\Section;
use StackNuts\StackGauge\Api\Unit;
use StackNuts\StackGauge\Model\Reporter\Concern\PerformanceSectionTrait;

/**
 * Contributes StackNuts_CloudflareCache's status to the StackNuts_StackGauge fleet report via
 * ReporterInterface. Both queue fields use MetricCatalogInterface (not plain Field::number())
 * so they get an editable dashboard alert rule for free, with AGGREGATION_LATEST since each is
 * a point-in-time gauge, not something to sum or average.
 */
class StackGaugeReporter implements ReporterInterface, MetricCatalogInterface, DeclaresSectionInterface, DeclaresSlotInterface
{
    use PerformanceSectionTrait;

    private const SCHEMA_VERSION = '1.0';

    private const METRIC_BACKLOG = 'cloudflare.purge_queue_backlog';
    private const METRIC_OLDEST_PENDING_SECONDS = 'cloudflare.queue_oldest_pending_seconds';

    /**
     * @param Config $config
     * @param PurgeQueue $purgeQueue
     * @param Field $field
     * @param Section $section
     */
    public function __construct(
        private readonly Config $config,
        private readonly PurgeQueue $purgeQueue,
        private readonly Field $field,
        private readonly Section $section
    ) {
    }

    public function getName(): string
    {
        return 'cloudflare';
    }

    public function getLabel(): string
    {
        return 'Cloudflare';
    }

    public function getDescription(): string
    {
        return 'Whether Cloudflare is the active cache type, and the current delayed-purge-queue backlog.';
    }

    public function getSchemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    public function getStatus(): array
    {
        return [
            'general' => $this->section->facts('general', 'General', $this->getDescription(), [
                'enabled' => $this->field->bool('Enabled', $this->config->isActive()),
                'purge_queue_backlog' => $this->field->trackableNumber(
                    'Purge Queue Backlog',
                    $this->purgeQueue->getPendingCount(),
                    self::METRIC_BACKLOG,
                    MetricDefinition::AGGREGATION_LATEST
                ),
                'queue_oldest_pending_seconds' => $this->field->trackableNumber(
                    'Queue Oldest Pending',
                    // An empty queue has nothing to be stale, so 0 (not null) is "nothing wrong".
                    $this->purgeQueue->getOldestPendingAgeInSeconds() ?? 0,
                    self::METRIC_OLDEST_PENDING_SECONDS,
                    MetricDefinition::AGGREGATION_LATEST,
                    unit: Unit::SECONDS
                ),
            ]),
        ];
    }

    public function getTrackableMetrics(): array
    {
        return [
            // Matches this module's own default admin backlog-warning threshold, so an
            // agency's alert rule starts at the same number the Magento admin already flags.
            new MetricDefinition(
                self::METRIC_BACKLOG,
                'Cloudflare: Purge Queue Backlog',
                MetricDefinition::AGGREGATION_LATEST,
                MetricDefinition::OPERATOR_GT,
                50,
                15,
                null,
                description: '{value} cache purge requests are waiting to be sent to Cloudflare, above {threshold}.',
                impact: 'Customers may be served stale pages from Cloudflare.',
                criticalThreshold: 500
            ),
            // 30 minutes stale is a proxy for "the drain cron looks dead".
            new MetricDefinition(
                self::METRIC_OLDEST_PENDING_SECONDS,
                'Cloudflare: Oldest Pending Purge',
                MetricDefinition::AGGREGATION_LATEST,
                MetricDefinition::OPERATOR_GT,
                1800,
                15,
                null,
                description: 'The oldest pending Cloudflare purge has waited {value}, above {threshold}.',
                impact: 'The purge queue may have stopped draining, leaving stale pages cached.',
                criticalThreshold: 7200,
                unit: Unit::SECONDS
            ),
        ];
    }

    /**
     * Run 1 within this reporter's cadence tier.
     */
    public function getSlot(): int
    {
        return 1;
    }
}
