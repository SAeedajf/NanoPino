<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;


final class MediaAssetModel extends CmsModel
{
    protected $table = 'media_assets';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'site_id','native_file_id','native_hash_id','kind','mime','original_name',
        'title','alt','caption','description','size','width','height','duration',
        'focal_x','focal_y','owner_id','status','url','thumb','metadata',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'native_file_id' => 'integer',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration' => 'float',
        'focal_x' => 'float',
        'focal_y' => 'float',
        'owner_id' => 'integer',
        'metadata' => 'array',
    ];
}
