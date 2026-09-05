<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Storage;

final class InMemoryStorageDriver implements StorageDriverInterface
{
    /** @var array<string,string> */
    private array $objects=[];

    public function id(): string { return 'memory'; }
    public function exists(StorageObjectKey $key): bool { return array_key_exists((string)$key,$this->objects); }

    public function read(StorageObjectKey $key): string
    {
        if (!$this->exists($key)) throw new \RuntimeException('Storage object not found.');
        return $this->objects[(string)$key];
    }

    public function write(StorageObjectKey $key,string $contents,array $options=[]): void
    {
        $this->objects[(string)$key]=$contents;
    }

    public function delete(StorageObjectKey $key): void { unset($this->objects[(string)$key]); }

    public function copy(StorageObjectKey $from,StorageObjectKey $to): void
    {
        $this->write($to,$this->read($from));
    }

    public function size(StorageObjectKey $key): ?int
    {
        return $this->exists($key) ? strlen($this->objects[(string)$key]) : null;
    }

    public function url(StorageObjectKey $key): ?string { return null; }

    public function health(): array
    {
        return ['status'=>'ok','message'=>'In-memory storage is available.','details'=>['objects'=>count($this->objects)]];
    }
}
