<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Review;

interface ExtensionReviewTicketRepositoryInterface
{
    public function save(ExtensionReviewTicket $ticket): void;
    public function findByTokenHash(string $hash): ?ExtensionReviewTicket;
}
