<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

final class QueueEnvelope
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
        public QueueJobStatus $status,
        public int $attempts,
        public float $availableAt,
        public float $createdAt,
        public float $updatedAt,
        public ?string $dedupKey=null,
        public ?string $correlationId=null,
        public ?string $lastError=null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(bool $public=false): array
    {
        $data=[
            'id'=>$this->id,
            'type'=>$this->type,
            'payload'=>$this->payload,
            'status'=>$this->status->value,
            'attempts'=>$this->attempts,
            'available_at'=>$this->availableAt,
            'created_at'=>$this->createdAt,
            'updated_at'=>$this->updatedAt,
            'dedup_key'=>$this->dedupKey,
            'correlation_id'=>$this->correlationId,
        ];
        if (!$public) $data['last_error']=$this->lastError;
        return $data;
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)$data['id'],
            (string)$data['type'],
            is_array($data['payload']??null) ? $data['payload'] : [],
            QueueJobStatus::from((string)$data['status']),
            (int)$data['attempts'],
            (float)$data['available_at'],
            (float)$data['created_at'],
            (float)$data['updated_at'],
            isset($data['dedup_key']) ? (string)$data['dedup_key'] : null,
            isset($data['correlation_id']) ? (string)$data['correlation_id'] : null,
            isset($data['last_error']) ? (string)$data['last_error'] : null,
        );
    }
}
