<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Storage;

interface StorageDriverInterface
{
    public function id(): string;
    public function exists(StorageObjectKey $key): bool;
    public function read(StorageObjectKey $key): string;

    /** @param array<string,mixed> $options */
    public function write(StorageObjectKey $key,string $contents,array $options=[]): void;

    public function delete(StorageObjectKey $key): void;
    public function copy(StorageObjectKey $from,StorageObjectKey $to): void;
    public function size(StorageObjectKey $key): ?int;
    public function url(StorageObjectKey $key): ?string;

    /** @return array{status:string,message:string,details?:array<string,mixed>} */
    public function health(): array;
}
