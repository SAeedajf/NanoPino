<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

final readonly class ReleasePromotionDecision
{
    /**
     * @param list<array{code:string,message:string}> $blockers
     * @param array<string,mixed> $evidence
     */
    public function __construct(
        public ReleaseChannel $channel,
        public bool $allowed,
        public array $blockers,
        public array $evidence,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'schema' => 1,
            'channel' => $this->channel->value,
            'allowed' => $this->allowed,
            'blockers' => $this->blockers,
            'evidence' => $this->evidence,
        ];
    }
}
