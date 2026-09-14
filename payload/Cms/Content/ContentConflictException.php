<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use Throwable;

final class ContentConflictException extends \RuntimeException
{
    public function __construct(string $message = 'Content slug is already in use.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function from(Throwable $exception): ?self
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $message = strtolower($current->getMessage());
            $isUniqueViolation = str_contains($message, 'cms_content_slug_unique')
                && (
                    str_contains($message, 'duplicate')
                    || str_contains($message, 'unique')
                    || str_contains($message, 'integrity')
                );

            if ($isUniqueViolation) {
                return new self(previous: $exception);
            }
        }

        return null;
    }
}
