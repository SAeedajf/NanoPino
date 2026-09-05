<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Audit;

use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxAuditRepository implements AuditRepositoryInterface
{
    public const TABLE = 'audit_events';

    public function append(AuditEvent $event): void
    {
        CmsDatabase::table(self::TABLE)->insert([
            'event_id' => $event->id,
            'action' => $event->action,
            'owner' => $event->owner,
            'outcome' => $event->outcome->value,
            'actor_id' => $event->actorId,
            'scope_type' => $event->scopeType->value,
            'scope_id' => $event->scopeType === ScopeType::Global ? '' : (string)$event->scopeId,
            'target_type' => $event->targetType,
            'target_id' => $event->targetId !== null ? (string)$event->targetId : null,
            'correlation_id' => $event->correlationId,
            'metadata_json' => json_encode(
                $event->metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ),
            'occurred_at' => date('Y-m-d H:i:s', (int)$event->occurredAt),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recent(int $limit = 100): array
    {
        $limit = max(1, min($limit, 1000));

        return CmsDatabase::table(self::TABLE)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static function ($row): AuditEvent {
                $scopeType = ScopeType::from((string)$row->scope_type);

                return new AuditEvent(
                    (string)$row->event_id,
                    (string)$row->action,
                    (string)$row->owner,
                    AuditOutcome::from((string)$row->outcome),
                    strtotime((string)$row->occurred_at) ?: 0,
                    $row->actor_id !== null ? (int)$row->actor_id : null,
                    $scopeType,
                    $scopeType === ScopeType::Global ? null : (string)$row->scope_id,
                    $row->target_type !== null ? (string)$row->target_type : null,
                    $row->target_id !== null ? (string)$row->target_id : null,
                    $row->correlation_id !== null ? (string)$row->correlation_id : null,
                    is_array(json_decode((string)$row->metadata_json, true))
                        ? json_decode((string)$row->metadata_json, true)
                        : [],
                );
            })
            ->all();
    }
}
