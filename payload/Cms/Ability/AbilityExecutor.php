<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Ability;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use ReflectionFunction;
use Throwable;

final class AbilityExecutor
{
    public function __construct(
        private readonly AbilityRegistry $registry,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
        private readonly AbilitySchemaValidator $validator = new AbilitySchemaValidator(),
        private readonly IdempotencyStoreInterface $idempotency = new InMemoryIdempotencyStore(),
    ) {}

    /** @param array<string,mixed> $input */
    public function execute(
        string $abilityId,
        array $input,
        AbilityExecutionContext $context = new AbilityExecutionContext(),
    ): AbilityExecutionResult {
        $correlationId = $context->correlationIdOrCreate();

        $definition = $this->registry->get($abilityId);
        if (!$definition instanceof AbilityDefinition) {
            return new AbilityExecutionResult(
                false,
                $abilityId,
                $correlationId,
                errorCode: 'ABILITY_NOT_FOUND',
                errorMessage: 'Ability is not registered.',
            );
        }

        if ($definition->idempotent && $context->idempotencyKey !== null) {
            $cached = $this->idempotency->get($abilityId, $context->idempotencyKey);
            if ($cached !== null) {
                return $cached->asReplay();
            }
        }

        try {
            if ($definition->permission !== null) {
                $this->authorization->authorize(new AuthorizationRequest(
                    $definition->permission,
                    $context->actorId,
                    $context->scopeType,
                    $context->scopeId,
                    'ability',
                    $abilityId,
                ));
            }

            $this->validator->validate($definition->inputSchema, $input);

            $reflection = new ReflectionFunction($definition->executor);
            $data = $reflection->getNumberOfParameters() >= 2
                ? ($definition->executor)($input, $context)
                : ($definition->executor)($input);

            $this->validator->validate($definition->outputSchema, $data);

            $result = new AbilityExecutionResult(
                true,
                $abilityId,
                $correlationId,
                $data,
            );

            if ($definition->idempotent && $context->idempotencyKey !== null) {
                $this->idempotency->put($abilityId, $context->idempotencyKey, $result);
            }

            if ($definition->audit) {
                $this->audit->log(
                    'ability.execute',
                    $definition->owner(),
                    AuditOutcome::Success,
                    $context->actorId,
                    $context->scopeType,
                    $context->scopeId,
                    'ability',
                    $abilityId,
                    $correlationId,
                    [
                        'source' => $context->source->value,
                        'version' => $definition->version,
                        'idempotent' => $definition->idempotent,
                    ],
                );
            }

            return $result;
        } catch (Throwable $e) {
            if ($definition->audit) {
                $this->audit->log(
                    'ability.execute',
                    $definition->owner(),
                    $e instanceof \App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException
                        ? AuditOutcome::Denied
                        : AuditOutcome::Failed,
                    $context->actorId,
                    $context->scopeType,
                    $context->scopeId,
                    'ability',
                    $abilityId,
                    $correlationId,
                    [
                        'source' => $context->source->value,
                        'error_class' => $e::class,
                        'error' => $e->getMessage(),
                    ],
                );
            }

            return new AbilityExecutionResult(
                false,
                $abilityId,
                $correlationId,
                errorCode: $this->errorCode($e),
                errorMessage: $e->getMessage(),
            );
        }
    }

    private function errorCode(Throwable $error): string
    {
        return match (true) {
            $error instanceof AbilitySchemaValidationException => 'ABILITY_INPUT_INVALID',
            $error instanceof \App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException => 'ABILITY_FORBIDDEN',
            default => 'ABILITY_EXECUTION_FAILED',
        };
    }
}
