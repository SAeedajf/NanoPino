<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case RichText = 'richtext';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case Select = 'select';
    case Relation = 'relation';
    case Taxonomy = 'taxonomy';
    case Media = 'media';
    case Gallery = 'gallery';
    case Json = 'json';
    case Repeater = 'repeater';
    case Group = 'group';
}
