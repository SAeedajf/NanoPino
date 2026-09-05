<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Admin;

enum AdminSurface: string
{
    case Navigation = 'navigation';
    case Route = 'route';
    case DashboardWidget = 'dashboard_widget';
    case SettingsPanel = 'settings_panel';
    case EditorPanel = 'editor_panel';
    case Command = 'command';
}
