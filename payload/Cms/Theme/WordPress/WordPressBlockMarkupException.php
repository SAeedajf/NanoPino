<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\WordPress;

use RuntimeException;

final class WordPressBlockMarkupException extends RuntimeException
{
    /** @param list<string> $issues */
    public function __construct(string $message, public readonly array $issues = [])
    {
        parent::__construct($message);
    }
}
