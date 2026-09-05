<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

enum BuilderTargetType: string
{
    case Content = 'content';
    case Template = 'template';
    case TemplatePart = 'template_part';
    case Site = 'site';
}
