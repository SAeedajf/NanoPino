<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Storage;

final readonly class CmsStorage
{
    public function __construct(
        private StorageDriverInterface $driver,
        private string $prefix='cms',
    ) {
        new StorageObjectKey($prefix . '/probe');
    }

    public function key(int $siteId,string $domain,string $relative): StorageObjectKey
    {
        if ($siteId<1 || preg_match('/^[a-z][a-z0-9._-]{1,63}$/',$domain)!==1) {
            throw new \InvalidArgumentException('Invalid CMS storage scope.');
        }
        $relative=(string)new StorageObjectKey($relative);
        return new StorageObjectKey(
            trim($this->prefix,'/') . '/site-' . $siteId . '/' . $domain . '/' . $relative
        );
    }

    /** @param array<string,mixed> $options */
    public function write(int $siteId,string $domain,string $relative,string $contents,array $options=[]): StorageObjectKey
    {
        $key=$this->key($siteId,$domain,$relative);
        $this->driver->write($key,$contents,$options);
        return $key;
    }

    public function read(int $siteId,string $domain,string $relative): string
    {
        return $this->driver->read($this->key($siteId,$domain,$relative));
    }

    public function delete(int $siteId,string $domain,string $relative): void
    {
        $this->driver->delete($this->key($siteId,$domain,$relative));
    }
}
