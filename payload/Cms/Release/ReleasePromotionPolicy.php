<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Release;

final readonly class ReleasePromotionPolicy
{
    public function __construct(
        private int $minimumWindowSeconds = 300,
        private int $minimumSamples = 20,
        private float $maximumErrorRatePercent = 1.0,
        private float $minimumAvailabilityPercent = 99.0,
        private float $maximumP95LatencyMs = 1200.0,
    ) {
        if ($this->minimumWindowSeconds < 1 || $this->minimumSamples < 1) {
            throw new \InvalidArgumentException('Canary minimums must be positive.');
        }
        if ($this->maximumErrorRatePercent < 0 || $this->minimumAvailabilityPercent < 0 || $this->minimumAvailabilityPercent > 100 || $this->maximumP95LatencyMs < 0) {
            throw new \InvalidArgumentException('Canary thresholds are invalid.');
        }
    }

    public function evaluate(
        ReleaseChannel $channel,
        array $release,
        CanaryHealthSnapshot $canary,
        ReleaseSupportPlan $support,
    ): ReleasePromotionDecision {
        $blockers = [];

        if (($release['candidate_ready'] ?? false) !== true) {
            $blockers[] = ['code' => 'release.candidate_not_ready', 'message' => 'The technical release candidate is not ready.'];
        }
        if (($release['signed'] ?? false) !== true) {
            $blockers[] = ['code' => 'release.signature_required', 'message' => 'Canary and Stable promotion require a signed artifact.'];
        }
        if (($release['archive_integrity'] ?? null) !== 'verified') {
            $blockers[] = ['code' => 'release.archive_integrity_unverified', 'message' => 'The release archive integrity is not verified.'];
        }
        if (($release['security_high_findings'] ?? null) !== 0) {
            $blockers[] = ['code' => 'release.security_high_findings', 'message' => 'High-risk security findings block promotion.'];
        }
        if (!is_string($release['artifact_sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $release['artifact_sha256']) !== 1) {
            $blockers[] = ['code' => 'release.artifact_hash_invalid', 'message' => 'A verified SHA-256 artifact hash is required.'];
        }
        if (!$support->ready()) {
            foreach ($support->blockers() as $code) {
                $blockers[] = ['code' => $code, 'message' => 'Post-release support readiness is incomplete.'];
            }
        }

        if ($canary->windowSeconds < $this->minimumWindowSeconds) {
            $blockers[] = ['code' => 'canary.window_too_short', 'message' => 'The Canary observation window is too short.'];
        }
        if ($canary->sampleCount < $this->minimumSamples) {
            $blockers[] = ['code' => 'canary.samples_insufficient', 'message' => 'The Canary sample count is insufficient.'];
        }
        if ($canary->errorRatePercent() > $this->maximumErrorRatePercent) {
            $blockers[] = ['code' => 'canary.error_rate_exceeded', 'message' => 'The Canary error rate exceeds the promotion threshold.'];
        }
        if ($canary->availabilityPercent < $this->minimumAvailabilityPercent) {
            $blockers[] = ['code' => 'canary.availability_below_threshold', 'message' => 'Canary availability is below the promotion threshold.'];
        }
        if ($canary->p95LatencyMs > $this->maximumP95LatencyMs) {
            $blockers[] = ['code' => 'canary.latency_exceeded', 'message' => 'Canary p95 latency exceeds the promotion threshold.'];
        }
        if ($canary->criticalIncidents > 0) {
            $blockers[] = ['code' => 'canary.critical_incident', 'message' => 'A critical incident was observed during Canary.'];
        }
        if (!$canary->healthChecksPassed) {
            $blockers[] = ['code' => 'canary.health_checks_failed', 'message' => 'Canary health checks did not pass.'];
        }
        if ($canary->rollbackRequested) {
            $blockers[] = ['code' => 'canary.rollback_requested', 'message' => 'A rollback was requested; promotion is blocked.'];
        }

        if ($channel === ReleaseChannel::Stable) {
            if (($release['canary_promoted'] ?? false) !== true) {
                $blockers[] = ['code' => 'stable.canary_promotion_required', 'message' => 'Stable requires an approved Canary promotion first.'];
            }
            if (($release['canary_window_closed'] ?? false) !== true) {
                $blockers[] = ['code' => 'stable.canary_window_open', 'message' => 'Stable requires the Canary observation window to be closed.'];
            }
            if (($release['stable_approval'] ?? false) !== true) {
                $blockers[] = ['code' => 'stable.approval_required', 'message' => 'An explicit Stable approval is required.'];
            }
        }

        return new ReleasePromotionDecision(
            $channel,
            $blockers === [],
            $blockers,
            [
                'thresholds' => [
                    'minimum_window_seconds' => $this->minimumWindowSeconds,
                    'minimum_samples' => $this->minimumSamples,
                    'maximum_error_rate_percent' => $this->maximumErrorRatePercent,
                    'minimum_availability_percent' => $this->minimumAvailabilityPercent,
                    'maximum_p95_latency_ms' => $this->maximumP95LatencyMs,
                ],
                'canary' => $canary->toArray(),
                'support' => $support->toArray(),
            ],
        );
    }
}
