<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

final class InMemoryExtensionReviewTicketRepository implements ExtensionReviewTicketRepositoryInterface
{
    /** @var array<string,ExtensionReviewTicket> */
    private array $items = [];

    public function save(ExtensionReviewTicket $ticket): void
    {
        $this->items[$ticket->tokenHash] = $ticket;
    }

    public function findByTokenHash(string $hash): ?ExtensionReviewTicket
    {
        return $this->items[$hash] ?? null;
    }
}
