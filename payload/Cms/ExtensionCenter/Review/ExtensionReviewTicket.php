<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

final class ExtensionReviewTicket
{
    public function __construct(
        public readonly string $id,
        public readonly string $tokenHash,
        public readonly string $extensionId,
        public readonly string $packageSha256,
        public readonly ExtensionReviewDecision $decision,
        public readonly float $expiresAt,
        public bool $approved = false,
        public bool $consumed = false,
    ) {}

    public function active(): bool
    {
        return !$this->consumed && $this->expiresAt > microtime(true);
    }
}
