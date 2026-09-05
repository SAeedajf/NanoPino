<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final class QueuePayloadValidator
{
    /** @param array<string,mixed> $payload */
    public function validate(array $payload,int $maxBytes): void
    {
        $json=json_encode($payload,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json)>$maxBytes) {
            throw new \InvalidArgumentException('Queue payload exceeds job limit.');
        }
        $this->depth($payload,0);
    }

    private function depth(mixed $value,int $depth): void
    {
        if ($depth>20) throw new \InvalidArgumentException('Queue payload nesting is too deep.');
        if (!is_array($value)) {
            if (is_object($value) || is_resource($value)) {
                throw new \InvalidArgumentException('Queue payload must be JSON-serializable data.');
            }
            return;
        }
        foreach ($value as $item) $this->depth($item,$depth+1);
    }
}
