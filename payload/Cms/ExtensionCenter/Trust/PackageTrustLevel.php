<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Trust;

enum PackageTrustLevel: string
{
    case Verified = 'verified';
    case IntegrityOnly = 'integrity_only';
    case Unverified = 'unverified';
    case Failed = 'failed';
}
