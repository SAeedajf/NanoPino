<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TermModel extends CmsModel
{
    protected $table = 'terms';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'taxonomy',
        'name',
        'slug',
        'description',
        'parent_id',
        'locale',
        'metadata',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'parent_id' => 'integer',
        'metadata' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function contentRelations(): HasMany
    {
        return $this->hasMany(ContentTermModel::class, 'term_id', 'id');
    }
}
