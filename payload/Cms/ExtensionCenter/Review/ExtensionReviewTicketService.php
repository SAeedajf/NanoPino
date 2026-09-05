<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageInspection;
use App\com_pinoox_cms\Cms\ExtensionCenter\Package\ExtensionPackageReference;
use RuntimeException;

final readonly class ExtensionReviewTicketService
{
    public function __construct(
        private ExtensionReviewTicketRepositoryInterface $repository,
        private int $ttlSeconds = 900,
    ) {
        if ($ttlSeconds < 60 || $ttlSeconds > 3600) {
            throw new \InvalidArgumentException('Extension review ticket TTL must be between 60 and 3600 seconds.');
        }
    }

    /** @return array{token:string,ticket:ExtensionReviewTicket} */
    public function issue(
        ExtensionPackageInspection $inspection,
        ExtensionInstallReview $review,
        bool $approved = false,
    ): array {
        if ($review->manifest->identifier() !== $inspection->manifest->identifier()) {
            throw new RuntimeException('Review does not belong to inspected package.');
        }

        if ($review->decision === ExtensionReviewDecision::Block) {
            throw new RuntimeException('Blocked extension review cannot issue an execution ticket.');
        }

        if ($review->decision === ExtensionReviewDecision::ApprovalRequired && !$approved) {
            throw new RuntimeException('Explicit approval is required before issuing execution ticket.');
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $ticket = new ExtensionReviewTicket(
            'extr-' . bin2hex(random_bytes(8)),
            hash('sha256', $token),
            $inspection->manifest->identifier(),
            $inspection->packageSha256,
            $review->decision,
            microtime(true) + $this->ttlSeconds,
            $approved,
        );
        $this->repository->save($ticket);

        return ['token' => $token, 'ticket' => $ticket];
    }

    public function consume(
        string $token,
        ExtensionPackageReference $package,
        string $expectedExtensionId,
    ): ExtensionReviewTicket {
        if (strlen($token) < 40 || strlen($token) > 128) {
            throw new RuntimeException('Invalid extension review ticket.');
        }

        $ticket = $this->repository->findByTokenHash(hash('sha256', $token));
        if ($ticket === null || !$ticket->active()) {
            throw new RuntimeException('Extension review ticket is missing, expired or already consumed.');
        }

        if ($ticket->extensionId !== $expectedExtensionId) {
            throw new RuntimeException('Extension review ticket belongs to another extension.');
        }

        $digest = hash_file('sha256', $package->localPath);
        if (!is_string($digest) || !hash_equals($ticket->packageSha256, $digest)) {
            throw new RuntimeException('Extension package changed after review.');
        }

        $ticket->consumed = true;
        $this->repository->save($ticket);
        return $ticket;
    }
}
