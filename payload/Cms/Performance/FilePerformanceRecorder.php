<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance;

final readonly class FilePerformanceRecorder implements PerformanceRecorderInterface
{
    public function __construct(
        private string $file,
        private int $maxBytes=5_242_880,
    ) {}

    public function record(PerformanceSample $sample): void
    {
        $dir=dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir)) {
            throw new \RuntimeException('Unable to create performance telemetry directory.');
        }

        $handle=fopen($this->file,'c+');
        if ($handle===false) throw new \RuntimeException('Unable to open performance telemetry file.');

        try {
            if (!flock($handle,LOCK_EX)) throw new \RuntimeException('Unable to lock performance telemetry file.');
            fseek($handle,0,SEEK_END);
            $line=json_encode($sample->toArray(),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
            fwrite($handle,$line);
            fflush($handle);

            $size=ftell($handle);
            if (is_int($size) && $size>$this->maxBytes) {
                rewind($handle);
                $raw=stream_get_contents($handle);
                $keep=is_string($raw) ? substr($raw,-(int)($this->maxBytes*0.7)) : '';
                $newline=strpos($keep,"\n");
                if ($newline!==false) $keep=substr($keep,$newline+1);
                ftruncate($handle,0);
                rewind($handle);
                fwrite($handle,$keep);
                fflush($handle);
            }
        } finally {
            @flock($handle,LOCK_UN);
            fclose($handle);
        }
    }

    public function recent(int $limit=200): array
    {
        return $this->load($limit,null);
    }

    public function forMetric(PerformanceMetric $metric,int $limit=200): array
    {
        return $this->load($limit,$metric);
    }

    /** @return list<PerformanceSample> */
    private function load(int $limit,?PerformanceMetric $metric): array
    {
        if (!is_file($this->file)) return [];
        $lines=file($this->file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) return [];

        $samples=[];
        foreach (array_reverse($lines) as $line) {
            try {
                $d=json_decode($line,true,512,JSON_THROW_ON_ERROR);
                if (!is_array($d)) continue;
                $m=PerformanceMetric::from((string)$d['metric']);
                if ($metric!==null && $m!==$metric) continue;
                $samples[]=new PerformanceSample(
                    (string)$d['name'],$m,(float)$d['value'],(float)$d['recorded_at'],
                    is_array($d['tags']??null)?$d['tags']:[]
                );
            } catch (\Throwable) {
                continue;
            }
            if (count($samples)>=$limit) break;
        }
        return $samples;
    }
}
