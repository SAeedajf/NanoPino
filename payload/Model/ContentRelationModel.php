<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContentRelationModel extends CmsModel
{
    protected $table = 'content_relations';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'source_content_id',
        'field_key',
        'target_content_id',
        'sort_order',
    ];

    protected $casts = [
        'source_content_id' => 'integer',
        'target_content_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentModel::class, 'source_content_id', 'id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(ContentModel::class, 'target_content_id', 'id');
    }
}
