<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContentFieldValueModel extends CmsModel
{
    protected $table = 'content_fields';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'content_id',
        'field_key',
        'value_type',
        'value_json',
        'version',
    ];

    protected $casts = [
        'content_id' => 'integer',
        'version' => 'integer',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(ContentModel::class, 'content_id', 'id');
    }
}
