<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

enum BlockAttributeType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Number = 'number';
    case Boolean = 'boolean';
    case Array = 'array';
    case Object = 'object';
    case RichText = 'richtext';
    case Url = 'url';
    case Media = 'media';
}
