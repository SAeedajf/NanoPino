<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class ContentRevisionModel extends CmsModel
{
    protected $table = 'content_revisions';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'content_id',
        'site_id',
        'content_type',
        'kind',
        'actor_id',
        'schema_version',
        'checksum',
        'source_revision_id',
        'payload_json',
        'created_at',
    ];

    protected $casts = [
        'content_id' => 'integer',
        'site_id' => 'integer',
        'actor_id' => 'integer',
        'schema_version' => 'integer',
        'source_revision_id' => 'integer',
    ];
}
