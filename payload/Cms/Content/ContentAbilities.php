<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Cms\Ability\AbilityDefinition;
use App\com_pinoox_cms\Cms\Ability\AbilityExecutionContext;
use App\com_pinoox_cms\Cms\Ability\AbilityRegistry;

final class ContentAbilities
{
    public static function register(
        AbilityRegistry $registry,
        ContentService $service,
        string $owner = 'cms.core',
    ): void {
        $registry->register(new AbilityDefinition(
            'content/create',
            $owner,
            static fn (array $input, AbilityExecutionContext $context): array =>
                $service->create($input, $context->actorId, $context->correlationIdOrCreate())->toArray(),
            permission: 'content.create',
            inputSchema: [
                'type' => 'object',
                'required' => ['type', 'title'],
                'properties' => [
                    'site_id' => ['type' => 'integer'],
                    'type' => ['type' => 'string'],
                    'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'slug' => ['type' => 'string', 'maxLength' => 160],
                    'excerpt' => ['type' => 'string'],
                    'locale' => ['type' => 'string', 'maxLength' => 16],
                    'author_id' => ['type' => ['integer', 'null']],
                    'fields' => ['type' => 'object'],
                    'metadata' => ['type' => 'object'],
                ],
            ],
            outputSchema: [
                'type' => 'object',
                'required' => ['id', 'site_id', 'type', 'status', 'title', 'slug'],
            ],
            description: 'Create a content draft.',
            idempotent: true,
        ));

        $registry->register(new AbilityDefinition(
            'content/publish',
            $owner,
            static fn (array $input, AbilityExecutionContext $context): array =>
                $service->publish((int)$input['id'], $context->actorId, $context->correlationIdOrCreate())->toArray(),
            permission: 'content.publish',
            inputSchema: [
                'type' => 'object',
                'required' => ['id'],
                'additionalProperties' => false,
                'properties' => [
                    'id' => ['type' => 'integer'],
                ],
            ],
            outputSchema: [
                'type' => 'object',
                'required' => ['id', 'status'],
            ],
            description: 'Publish content.',
            idempotent: false,
        ));
    }
}
