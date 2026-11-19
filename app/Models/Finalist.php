<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Finalist extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'department',
        'project_title',
        'description',
        'display_order',
        'is_active',
        'presentation_disk',
        'presentation_path',
        'presentation_original_name',
        'presentation_mime_type',
        'presentation_size',
        'presentation_uploaded_at',
        'presentation_uploaded_by',
        'winner_rank',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'presentation_size' => 'integer',
            'presentation_uploaded_at' => 'datetime',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function votingVotes()
    {
        return $this->hasMany(VotingVote::class);
    }

    public function hasPresentation(): bool
    {
        return filled($this->presentation_path) && filled($this->presentation_disk);
    }

    public function presentationFileExists(): bool
    {
        if (! $this->hasPresentation()) {
            return false;
        }

        return Storage::disk($this->presentation_disk)->exists($this->presentation_path);
    }

    public function presentationSizeForHumans(): ?string
    {
        if (! $this->presentation_size) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->presentation_size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2).' '.$units[$i];
    }

    public function isTopThreeWinner(): bool
    {
        return in_array($this->winner_rank, [1, 2, 3], true);
    }
}
