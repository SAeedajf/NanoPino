<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class BuilderDocumentModel extends CmsModel
{
    protected $table = 'builder_documents';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'target_type',
        'target_key',
        'locale',
        'status',
        'document_json',
        'checksum',
        'version',
        'actor_id',
        'published_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'version' => 'integer',
        'actor_id' => 'integer',
    ];
}
