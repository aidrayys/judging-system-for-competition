<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'display_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function criteria()
    {
        return $this->hasMany(Criterion::class)->orderBy('display_order')->orderBy('id');
    }

    public function activeCriteria()
    {
        return $this->criteria()->where('is_active', true);
    }

    public function finalists()
    {
        return $this->hasMany(Finalist::class)->orderBy('display_order')->orderBy('id');
    }

    public function activeFinalists()
    {
        return $this->finalists()->where('is_active', true);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function resultRelease()
    {
        return $this->hasOne(ResultRelease::class);
    }
}
