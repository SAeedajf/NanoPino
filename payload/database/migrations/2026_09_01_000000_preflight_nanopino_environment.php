<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use App\com_pinoox_cms\Cms\Installer\Installability\PinooxInstallabilityProbe;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        (new PinooxInstallabilityProbe())->assertReady($packageRoot);
    }

    public function down(): void
    {
        // Read-only preflight: no mutation to undo.
    }
};
