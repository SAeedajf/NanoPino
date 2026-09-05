<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Storage;

use Pinoox\Portal\Storage;

final readonly class PinooxStorageDriver implements StorageDriverInterface
{
    public function __construct(private ?string $disk=null)
    {
        if ($disk!==null && preg_match('/^[a-zA-Z0-9._-]{1,64}$/',$disk)!==1) {
            throw new \InvalidArgumentException('Invalid Pinoox storage disk.');
        }
    }

    public function id(): string { return 'pinoox:' . ($this->disk ?? 'default'); }

    private function disk(): object
    {
        return Storage::disk($this->disk);
    }

    public function exists(StorageObjectKey $key): bool
    {
        return $this->disk()->exists((string)$key);
    }

    public function read(StorageObjectKey $key): string
    {
        $value=$this->disk()->get((string)$key);
        if (!is_string($value)) throw new \RuntimeException('Storage object cannot be read.');
        return $value;
    }

    public function write(StorageObjectKey $key,string $contents,array $options=[]): void
    {
        if ($this->disk()->put((string)$key,$contents,$options)===false) {
            throw new \RuntimeException('Storage write failed.');
        }
    }

    public function delete(StorageObjectKey $key): void
    {
        if ($this->exists($key) && !$this->disk()->delete((string)$key)) {
            throw new \RuntimeException('Storage delete failed.');
        }
    }

    public function copy(StorageObjectKey $from,StorageObjectKey $to): void
    {
        if (!$this->disk()->copy((string)$from,(string)$to)) {
            throw new \RuntimeException('Storage copy failed.');
        }
    }

    public function size(StorageObjectKey $key): ?int
    {
        return $this->exists($key) ? (int)$this->disk()->size((string)$key) : null;
    }

    public function url(StorageObjectKey $key): ?string
    {
        try {
            return (string)$this->disk()->url((string)$key);
        } catch (\Throwable) {
            return null;
        }
    }

    public function health(): array
    {
        try {
            $key=new StorageObjectKey('cms-health/' . bin2hex(random_bytes(6)) . '.txt');
            $this->write($key,'ok');
            $ok=$this->read($key)==='ok';
            $this->delete($key);
            return [
                'status'=>$ok?'ok':'error',
                'message'=>$ok?'Pinoox Storage disk is reachable.':'Pinoox Storage verification failed.',
                'details'=>['disk'=>$this->disk ?? 'default'],
            ];
        } catch (\Throwable $error) {
            return [
                'status'=>'error',
                'message'=>'Pinoox Storage disk is unavailable.',
                'details'=>['disk'=>$this->disk ?? 'default','error_class'=>$error::class],
            ];
        }
    }
}
