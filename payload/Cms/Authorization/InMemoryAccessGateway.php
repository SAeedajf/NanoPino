<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

final class InMemoryAccessGateway implements AccessGatewayInterface
{
    /** @var array<int|string,list<string>> */
    private array $abilitiesBySubject = [];

    /** @param list<string> $abilities */
    public function grant(?int $subjectId, array $abilities): void
    {
        $this->abilitiesBySubject[$subjectId ?? 'current'] = array_values(array_unique($abilities));
    }

    public function can(string $capability, ?int $subjectId = null): bool
    {
        foreach ($this->abilities($subjectId) as $granted) {
            if ($granted === '*' || $granted === $capability) {
                return true;
            }

            if (str_ends_with($granted, '.*')) {
                $prefix = substr($granted, 0, -2);
                if (str_starts_with($capability, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function abilities(?int $subjectId = null): array
    {
        return $this->abilitiesBySubject[$subjectId ?? 'current'] ?? [];
    }
}
