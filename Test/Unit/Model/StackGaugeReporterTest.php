<?php
/**
 * Copyright © StackNuts. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace StackNuts\StackGaugeCloudflareCache\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use StackNuts\CloudflareCache\Model\Config;
use StackNuts\CloudflareCache\Model\ResourceModel\PurgeQueue;
use StackNuts\StackGaugeCloudflareCache\Model\StackGaugeReporter;
use StackNuts\StackGauge\Api\Field\Field;
use StackNuts\StackGauge\Api\MetricCatalogInterface;
use StackNuts\StackGauge\Api\MetricDefinition;
use StackNuts\StackGauge\Api\ReporterInterface;
use StackNuts\StackGauge\Api\Section\Section;

class StackGaugeReporterTest extends TestCase
{
    public function testImplementsTheStackGaugeReporterContracts(): void
    {
        $config = $this->createStub(Config::class);
        $purgeQueue = $this->createStub(PurgeQueue::class);

        $reporter = new StackGaugeReporter($config, $purgeQueue);

        $this->assertInstanceOf(ReporterInterface::class, $reporter);
        $this->assertInstanceOf(MetricCatalogInterface::class, $reporter);
    }

    public function testReportsActiveAndBacklogWhenCloudflareIsEnabled(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isActive')->willReturn(true);

        $purgeQueue = $this->createStub(PurgeQueue::class);
        $purgeQueue->method('getPendingCount')->willReturn(7);
        $purgeQueue->method('getOldestPendingAgeInSeconds')->willReturn(120);

        $reporter = new StackGaugeReporter($config, $purgeQueue);

        $this->assertSame('cloudflare', $reporter->getName());
        $this->assertEquals(
            ['general' => Section::facts('general', 'General', $reporter->getDescription(), [
                'enabled' => Field::bool('Enabled', true),
                'purge_queue_backlog' => Field::trackableNumber(
                    'Purge Queue Backlog',
                    7,
                    'cloudflare.purge_queue_backlog',
                    MetricDefinition::AGGREGATION_LATEST
                ),
                'queue_oldest_pending_seconds' => Field::trackableNumber(
                    'Queue Oldest Pending (seconds)',
                    120,
                    'cloudflare.queue_oldest_pending_seconds',
                    MetricDefinition::AGGREGATION_LATEST
                ),
            ])],
            $reporter->getStatus()
        );
    }

    public function testReportsDisabledWithZeroBacklogWhenCloudflareIsNotTheActiveCacheType(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isActive')->willReturn(false);

        $purgeQueue = $this->createStub(PurgeQueue::class);
        $purgeQueue->method('getPendingCount')->willReturn(0);
        $purgeQueue->method('getOldestPendingAgeInSeconds')->willReturn(null);

        $reporter = new StackGaugeReporter($config, $purgeQueue);

        $this->assertEquals(
            ['general' => Section::facts('general', 'General', $reporter->getDescription(), [
                'enabled' => Field::bool('Enabled', false),
                'purge_queue_backlog' => Field::trackableNumber(
                    'Purge Queue Backlog',
                    0,
                    'cloudflare.purge_queue_backlog',
                    MetricDefinition::AGGREGATION_LATEST
                ),
                'queue_oldest_pending_seconds' => Field::trackableNumber(
                    'Queue Oldest Pending (seconds)',
                    0,
                    'cloudflare.queue_oldest_pending_seconds',
                    MetricDefinition::AGGREGATION_LATEST
                ),
            ])],
            $reporter->getStatus()
        );
    }

    public function testDeclaresTrackableMetricsForBothQueueGauges(): void
    {
        $config = $this->createStub(Config::class);
        $purgeQueue = $this->createStub(PurgeQueue::class);

        $reporter = new StackGaugeReporter($config, $purgeQueue);
        $metrics = $reporter->getTrackableMetrics();

        $this->assertCount(2, $metrics);
        $this->assertSame('cloudflare.purge_queue_backlog', $metrics[0]->getMetricKey());
        $this->assertSame('cloudflare.queue_oldest_pending_seconds', $metrics[1]->getMetricKey());
    }
}
