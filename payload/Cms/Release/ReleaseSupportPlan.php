<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

final readonly class ReleaseSupportPlan
{
    /**
     * The plan is deliberately capability-based: URLs, credentials and customer
     * data never belong in a release decision payload.
     */
    public function __construct(
        public string $releaseId,
        public int $hypercareHours,
        public bool $onCallReady,
        public bool $monitoringReady,
        public bool $incidentRunbookReady,
        public bool $rollbackRunbookReady,
        public bool $customerCommunicationReady,
    ) {
        if ($this->releaseId === '') {
            throw new \InvalidArgumentException('Release support plan requires a release id.');
        }
        if ($this->hypercareHours < 1 || $this->hypercareHours > 720) {
            throw new \InvalidArgumentException('Hypercare must be between 1 and 720 hours.');
        }
    }

    /** @return list<string> */
    public function blockers(): array
    {
        $blockers = [];
        foreach ([
            'support.on_call_ready' => $this->onCallReady,
            'support.monitoring_ready' => $this->monitoringReady,
            'support.incident_runbook_ready' => $this->incidentRunbookReady,
            'support.rollback_runbook_ready' => $this->rollbackRunbookReady,
            'support.customer_communication_ready' => $this->customerCommunicationReady,
        ] as $code => $ready) {
            if (!$ready) {
                $blockers[] = $code;
            }
        }

        return $blockers;
    }

    public function ready(): bool
    {
        return $this->blockers() === [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'release_id' => $this->releaseId,
            'hypercare_hours' => $this->hypercareHours,
            'ready' => $this->ready(),
            'blockers' => $this->blockers(),
            'on_call_ready' => $this->onCallReady,
            'monitoring_ready' => $this->monitoringReady,
            'incident_runbook_ready' => $this->incidentRunbookReady,
            'rollback_runbook_ready' => $this->rollbackRunbookReady,
            'customer_communication_ready' => $this->customerCommunicationReady,
        ];
    }
}
