<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use Pinoox\Component\Http\Request;

/**
 * Reads mutation payloads without turning malformed JSON into an empty write.
 */
final class CmsRequestPayload
{
    /** @return array<string,mixed> */
    public static function read(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable $exception) {
            throw new \InvalidArgumentException(
                'Request body must be a valid JSON object or array.',
                0,
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Request body must be a valid JSON object or array.');
        }

        return $data;
    }
}
