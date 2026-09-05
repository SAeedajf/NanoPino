<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

enum InstallPhase: string
{
    case Snapshot = 'snapshot';
    case Stage = 'stage';
    case Migrate = 'migrate';
    case Lifecycle = 'lifecycle';
    case Cache = 'cache';
    case Health = 'health';
    case Switch = 'switch';
    case Verify = 'verify';
    case Commit = 'commit';
    case Rollback = 'rollback';
}
