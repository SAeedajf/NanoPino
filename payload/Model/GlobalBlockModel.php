<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class GlobalBlockModel extends CmsModel
{
    protected $table = 'global_blocks';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'name',
        'document_json',
        'checksum',
        'version',
        'actor_id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'version' => 'integer',
        'actor_id' => 'integer',
    ];
}
