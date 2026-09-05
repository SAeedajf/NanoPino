<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

final class TemplateHierarchyResolver
{
    public function __construct(private readonly TemplateRuleRegistry $rules) {}

    /** @return list<string> */
    public function candidates(TemplateRequest $request): array
    {
        $candidates = [];
        foreach ($this->rules->forKind($request->kind) as $rule) {
            foreach ($rule->patterns as $pattern) {
                $candidate = $this->expand($pattern, $request->variables);
                if ($candidate !== null && !in_array($candidate, $candidates, true)) {
                    $candidates[] = $candidate;
                }
            }
        }

        if ($request->kind !== 'part' && !in_array('index', $candidates, true)) {
            $candidates[] = 'index';
        }

        return $candidates;
    }

    /** @param array<string,string|int|null> $variables */
    private function expand(string $pattern, array $variables): ?string
    {
        $missing = false;
        $result = preg_replace_callback('/\{([a-z][a-z0-9_]*)\}/', function (array $m) use ($variables, &$missing): string {
            $value = $variables[$m[1]] ?? null;
            if ($value === null || trim((string)$value) === '') {
                $missing = true;
                return '';
            }

            $value = strtolower(trim((string)$value));
            $value = preg_replace('/[^a-z0-9._-]+/', '-', $value) ?? '';
            $value = trim($value, '-.');

            if ($value === '') {
                $missing = true;
            }

            return $value;
        }, $pattern);

        if ($missing || !is_string($result) || $result === '') {
            return null;
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,190}$/', $result) !== 1) {
            return null;
        }

        return $result;
    }
}
