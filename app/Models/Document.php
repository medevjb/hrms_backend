<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §82 — one stored employee file.
 *
 * @property int $id
 * @property int $employee_id
 * @property string $title
 * @property DocumentCategory $category
 * @property string $file_path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $uploaded_by_user_id
 */
#[Fillable([
    'employee_id', 'title', 'category', 'file_path', 'original_filename',
    'mime_type', 'size_bytes', 'uploaded_by_user_id',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
