<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Support\OwnerIdentifier;
use Closure;
use InvalidArgumentException;

final readonly class PolicyDefinition implements OwnedDefinitionInterface
{
    public Closure $evaluator;

    public function __construct(
        private string $identifier,
        private string $owner,
        public string $capabilityPattern,
        callable $evaluator,
        public int $priority = 100,
        public string $description = '',
    ) {
        new OwnerIdentifier($owner);
        if ($identifier === '') {
            throw new InvalidArgumentException('Policy identifier is required.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._*-]{1,190}$/', $capabilityPattern) !== 1) {
            throw new InvalidArgumentException('Invalid policy capability pattern.');
        }

        $this->evaluator = Closure::fromCallable($evaluator);
    }

    public function identifier(): string { return $this->identifier; }
    public function owner(): string { return $this->owner; }

    public function matches(string $capability): bool
    {
        if ($this->capabilityPattern === '*' || $this->capabilityPattern === $capability) {
            return true;
        }

        if (str_ends_with($this->capabilityPattern, '.*')) {
            $prefix = substr($this->capabilityPattern, 0, -2);
            return str_starts_with($capability, $prefix . '.');
        }

        return false;
    }

    public function evaluate(AuthorizationRequest $request): PolicyDecision
    {
        $result = ($this->evaluator)($request);

        if ($result instanceof PolicyDecision) {
            return $result;
        }

        if ($result === true) {
            return PolicyDecision::Allow;
        }

        if ($result === false) {
            return PolicyDecision::Deny;
        }

        return PolicyDecision::Abstain;
    }
}
