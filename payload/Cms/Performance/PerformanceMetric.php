<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

enum PerformanceMetric: string
{
    case BootMs = 'boot_ms';
    case ApiMs = 'api_ms';
    case DbQueries = 'db_queries';
    case DbMs = 'db_ms';
    case MemoryPeakBytes = 'memory_peak_bytes';
    case ExtensionBootMs = 'extension_boot_ms';
    case BuilderRenderMs = 'builder_render_ms';
    case SearchMs = 'search_ms';
    case QueueJobMs = 'queue_job_ms';
    case CacheHitRatio = 'cache_hit_ratio';
}
