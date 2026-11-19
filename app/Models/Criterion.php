<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Criterion extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'name', 'description', 'weight', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function evaluationScores()
    {
        return $this->hasMany(EvaluationScore::class);
    }
}
