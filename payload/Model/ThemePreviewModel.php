<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class ThemePreviewModel extends CmsModel
{
    protected $table = 'theme_previews';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'token_hash',
        'site_id',
        'package',
        'theme_name',
        'context',
        'variation',
        'design_overrides',
        'actor_id',
        'expires_at',
        'created_at',
        'revoked_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'actor_id' => 'integer',
        'design_overrides' => 'array',
    ];
}
