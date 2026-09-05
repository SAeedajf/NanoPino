<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class MediaUsageModel extends CmsModel
{
    protected $table = 'media_usages';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'media_id','site_id','resource_type','resource_id','context','created_at',
    ];

    protected $casts = [
        'media_id' => 'integer',
        'site_id' => 'integer',
    ];
}
