<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class MediaVariantModel extends CmsModel
{
    protected $table = 'media_variants';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'media_id','variant_key','native_file_id','width','height','size','mime',
        'metadata','created_at',
    ];

    protected $casts = [
        'media_id' => 'integer',
        'native_file_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'metadata' => 'array',
    ];
}
