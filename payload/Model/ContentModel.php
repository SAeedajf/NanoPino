<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pinoox\Model\UserModel;

final class ContentModel extends CmsModel
{
    protected $table = 'contents';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'site_id',
        'type',
        'status',
        'title',
        'slug',
        'excerpt',
        'author_id',
        'parent_id',
        'locale',
        'document',
        'metadata',
        'revision_id',
        'published_at',
        'scheduled_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'author_id' => 'integer',
        'parent_id' => 'integer',
        'revision_id' => 'integer',
        'document' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'scheduled_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'author_id', 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ContentFieldValueModel::class, 'content_id', 'id');
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ContentRelationModel::class, 'source_content_id', 'id');
    }

    public function termRelations(): HasMany
    {
        return $this->hasMany(ContentTermModel::class, 'content_id', 'id');
    }
}
