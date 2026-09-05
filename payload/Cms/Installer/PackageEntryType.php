<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Installer;

enum PackageEntryType: string
{
    case File = 'file';
    case Directory = 'directory';
    case Symlink = 'symlink';
}
