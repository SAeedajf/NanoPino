<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Revision;

final class RevisionComparer
{
    public function compare(RevisionRecord $from, RevisionRecord $to): RevisionDiff
    {
        $left = $from->snapshot->payload();
        $right = $to->snapshot->payload();
        $changes = [];
        $this->walk('', $left, $right, $changes);

        return new RevisionDiff($from->id, $to->id, $changes);
    }

    /**
     * @param array<string,array{from:mixed,to:mixed}> $changes
     */
    private function walk(string $path, mixed $from, mixed $to, array &$changes): void
    {
        if ($from === $to) {
            return;
        }

        if (is_array($from) && is_array($to) && !array_is_list($from) && !array_is_list($to)) {
            $keys = array_values(array_unique([...array_keys($from), ...array_keys($to)]));
            sort($keys);
            foreach ($keys as $key) {
                $next = $path === '' ? (string)$key : $path . '.' . $key;
                $this->walk($next, $from[$key] ?? null, $to[$key] ?? null, $changes);
            }
            return;
        }

        $changes[$path !== '' ? $path : '$'] = ['from' => $from, 'to' => $to];
    }
}
