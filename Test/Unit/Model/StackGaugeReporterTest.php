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
    private function reporter(Config $config, PurgeQueue $purgeQueue): StackGaugeReporter
    {
        return new StackGaugeReporter($config, $purgeQueue, new Field(), new Section());
    }

    public function testImplementsTheStackGaugeReporterContracts(): void
    {
        $config = $this->createStub(Config::class);
        $purgeQueue = $this->createStub(PurgeQueue::class);

        $reporter = $this->reporter($config, $purgeQueue);

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

        $reporter = $this->reporter($config, $purgeQueue);
        $fields = $reporter->getStatus()['general']->getFields();

        $this->assertTrue($fields['enabled']->getValue());
        $this->assertSame(7, $fields['purge_queue_backlog']->getValue());
        $this->assertSame(120, $fields['queue_oldest_pending_seconds']->getValue());
    }

    public function testReportsDisabledWithZeroBacklogWhenCloudflareIsNotTheActiveCacheType(): void
    {
        $config = $this->createStub(Config::class);
        $config->method('isActive')->willReturn(false);

        $purgeQueue = $this->createStub(PurgeQueue::class);
        $purgeQueue->method('getPendingCount')->willReturn(0);
        $purgeQueue->method('getOldestPendingAgeInSeconds')->willReturn(null);

        $reporter = $this->reporter($config, $purgeQueue);
        $fields = $reporter->getStatus()['general']->getFields();

        $this->assertFalse($fields['enabled']->getValue());
        $this->assertSame(0, $fields['purge_queue_backlog']->getValue());
        $this->assertSame(0, $fields['queue_oldest_pending_seconds']->getValue());
    }

    public function testDeclaresTrackableMetricsForBothQueueGauges(): void
    {
        $config = $this->createStub(Config::class);
        $purgeQueue = $this->createStub(PurgeQueue::class);

        $reporter = $this->reporter($config, $purgeQueue);
        $metrics = $reporter->getTrackableMetrics();

        $this->assertCount(2, $metrics);
        $this->assertSame('cloudflare.purge_queue_backlog', $metrics[0]->getMetricKey());
        $this->assertSame('cloudflare.queue_oldest_pending_seconds', $metrics[1]->getMetricKey());
    }
}
