<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

final class UpdateCandidateSelector
{
    /**
     * @param list<UpdateCandidate> $candidates
     */
    public function select(
        string $extensionId,
        int $installedVersionCode,
        ExtensionUpdatePolicy $policy,
        array $candidates,
    ): ?UpdateCandidate {
        $eligible = array_values(array_filter(
            $candidates,
            static function (UpdateCandidate $candidate) use ($extensionId, $installedVersionCode, $policy): bool {
                if ($candidate->extensionId !== $extensionId) return false;
                if ($candidate->channel->riskRank() > $policy->channel->riskRank()) return false;

                if ($candidate->versionCode < $installedVersionCode && !$policy->allowDowngrade) {
                    return false;
                }
                if ($candidate->versionCode === $installedVersionCode) return false;

                return true;
            }
        ));

        usort($eligible, static fn (UpdateCandidate $a, UpdateCandidate $b): int =>
            $b->versionCode <=> $a->versionCode
        );

        return $eligible[0] ?? null;
    }

    public function autoUpdateAllowed(
        ExtensionUpdatePolicy $policy,
        string $installedVersion,
        UpdateCandidate $candidate,
    ): bool {
        return match ($policy->autoUpdate) {
            AutoUpdateMode::Disabled => false,
            AutoUpdateMode::SecurityOnly => $candidate->security,
            AutoUpdateMode::PatchOnly => $this->sameMajorMinor(
                $installedVersion,
                $candidate->version,
            ),
            AutoUpdateMode::Enabled => true,
        };
    }

    private function sameMajorMinor(string $installed, string $candidate): bool
    {
        $pattern = '/^v?(\d+)\.(\d+)\.(\d+)(?:[-+].*)?$/i';
        if (
            preg_match($pattern, $installed, $a) !== 1
            || preg_match($pattern, $candidate, $b) !== 1
        ) {
            return false;
        }

        return $a[1] === $b[1] && $a[2] === $b[2];
    }
}
