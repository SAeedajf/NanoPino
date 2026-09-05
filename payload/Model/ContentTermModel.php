<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContentTermModel extends CmsModel
{
    protected $table = 'content_terms';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'content_id',
        'term_id',
        'taxonomy',
        'sort_order',
    ];

    protected $casts = [
        'content_id' => 'integer',
        'term_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(ContentModel::class, 'content_id', 'id');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(TermModel::class, 'term_id', 'id');
    }
}
