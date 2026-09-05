<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class BuilderRevisionModel extends CmsModel
{
    protected $table = 'builder_revisions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'builder_id',
        'kind',
        'actor_id',
        'source_revision_id',
        'document_json',
        'checksum',
        'created_at',
    ];

    protected $casts = [
        'builder_id' => 'integer',
        'actor_id' => 'integer',
        'source_revision_id' => 'integer',
    ];
}
