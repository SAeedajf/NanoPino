<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

enum FieldStorageStrategy: string
{
    case Document = 'document';
    case Meta = 'meta';
    case Relation = 'relation';
    case Taxonomy = 'taxonomy';
    case Virtual = 'virtual';
    case External = 'external';
}
