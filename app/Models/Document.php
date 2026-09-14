<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'person_id',
    'uploaded_by',
    'document_type',
    'document_number',
    'disk',
    'file_path',
    'original_filename',
    'mime_type',
    'file_size',
    'file_hash',
    'risk_score',
    'risk_level',
    'risk_reasons',
    'authenticity_confirmed_by_staff',
])]
class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'risk_level' => RiskLevel::class,
            'risk_reasons' => 'array',
            'authenticity_confirmed_by_staff' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }
}
