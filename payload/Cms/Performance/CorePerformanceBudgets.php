<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final class CorePerformanceBudgets
{
    public const OWNER='cms.core';

    public static function register(PerformanceBudgetRegistry $registry): void
    {
        foreach ([
            new PerformanceBudgetDefinition('perf.boot',self::OWNER,'CMS Boot',PerformanceMetric::BootMs,80,150,false,'ms'),
            new PerformanceBudgetDefinition('perf.api',self::OWNER,'Typical CMS API',PerformanceMetric::ApiMs,150,350,false,'ms'),
            new PerformanceBudgetDefinition('perf.db_queries',self::OWNER,'Typical request DB queries',PerformanceMetric::DbQueries,25,50,false,'queries'),
            new PerformanceBudgetDefinition('perf.db_time',self::OWNER,'Typical request DB time',PerformanceMetric::DbMs,80,180,false,'ms'),
            new PerformanceBudgetDefinition('perf.memory_peak',self::OWNER,'Peak request memory',PerformanceMetric::MemoryPeakBytes,32*1024*1024,64*1024*1024,false,'bytes'),
            new PerformanceBudgetDefinition('perf.extension_boot',self::OWNER,'Single Extension boot cost',PerformanceMetric::ExtensionBootMs,5,20,false,'ms'),
            new PerformanceBudgetDefinition('perf.builder_render',self::OWNER,'Builder server render',PerformanceMetric::BuilderRenderMs,80,200,false,'ms'),
            new PerformanceBudgetDefinition('perf.search',self::OWNER,'Search request',PerformanceMetric::SearchMs,100,250,false,'ms'),
            new PerformanceBudgetDefinition('perf.queue_job',self::OWNER,'Typical Queue job',PerformanceMetric::QueueJobMs,250,1000,false,'ms'),
            new PerformanceBudgetDefinition('perf.cache_hit_ratio',self::OWNER,'Semantic cache hit ratio',PerformanceMetric::CacheHitRatio,0.90,0.70,true,'ratio'),
        ] as $budget) {
            $registry->register($budget);
        }
    }
}
