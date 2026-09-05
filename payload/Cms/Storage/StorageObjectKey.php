<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Storage;

final readonly class StorageObjectKey
{
    public string $value;

    public function __construct(string $value)
    {
        $value=str_replace('\\','/',$value);
        $value=trim($value,'/');
        if (
            $value===''
            || strlen($value)>1000
            || str_contains($value,"\0")
            || preg_match('#(^|/)\.{1,2}(/|$)#',$value)===1
            || preg_match('#^[A-Za-z]:#',$value)===1
        ) {
            throw new \InvalidArgumentException('Invalid storage object key.');
        }

        foreach (explode('/',$value) as $segment) {
            if ($segment==='' || strlen($segment)>255) {
                throw new \InvalidArgumentException('Invalid storage path segment.');
            }
        }

        $this->value=$value;
    }

    public function __toString(): string { return $this->value; }
}
