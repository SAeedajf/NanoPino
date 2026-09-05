<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Cache;

use RuntimeException;

final readonly class FileCacheTagClock implements CacheTagClockInterface
{
    public function __construct(private string $directory) {}

    public function generation(string $tag): int
    {
        return $this->withLock($tag,static fn(array $state):array=>[$state,(int)($state['generation']??1)]);
    }

    public function bump(string $tag): int
    {
        return $this->withLock($tag,static function(array $state):array{
            $generation=(int)($state['generation']??1)+1;
            $state['generation']=$generation;
            return [$state,$generation];
        });
    }

    private function withLock(string $tag,callable $callback): int
    {
        if (preg_match('/^[a-zA-Z0-9._:-]{1,190}$/',$tag)!==1) {
            throw new RuntimeException('Invalid cache tag.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create cache tag directory.');
        }

        $file=rtrim($this->directory,'/\\') . '/' . hash('sha256',$tag) . '.json';
        $handle=fopen($file,'c+');
        if ($handle===false) throw new RuntimeException('Unable to open cache tag clock.');

        try {
            if (!flock($handle,LOCK_EX)) throw new RuntimeException('Unable to lock cache tag clock.');
            rewind($handle);
            $raw=stream_get_contents($handle);
            $state=$raw!==false && trim($raw)!=='' ? json_decode($raw,true) : [];
            if (!is_array($state)) $state=[];
            [$next,$result]=$callback($state);
            ftruncate($handle,0);
            rewind($handle);
            fwrite($handle,json_encode($next,JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle,LOCK_UN);
            return (int)$result;
        } finally {
            fclose($handle);
        }
    }
}
