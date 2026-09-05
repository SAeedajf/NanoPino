<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Json = 'json';
    case StringList = 'string_list';
}
