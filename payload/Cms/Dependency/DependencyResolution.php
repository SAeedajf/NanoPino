<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

final readonly class DependencyResolution
{
    /** @param list<DependencyIssue> $issues */
    public function __construct(public array $issues)
    {
    }

    public function canProceed(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->blocking) {
                return false;
            }
        }
        return true;
    }

    /** @return list<DependencyIssue> */
    public function blockingIssues(): array
    {
        return array_values(array_filter($this->issues, static fn (DependencyIssue $i): bool => $i->blocking));
    }

    /** @return list<DependencyIssue> */
    public function warnings(): array
    {
        return array_values(array_filter($this->issues, static fn (DependencyIssue $i): bool => !$i->blocking));
    }
}
