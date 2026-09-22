<?php

namespace App\Models;

use App\Traits\Commentable;
use App\Traits\Reactable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Broadcast extends Model
{
    use HasFactory,Sluggable,Commentable,Reactable;

    protected $casts = [
        'arguments' => 'object'
    ];
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
                return $this->image;
            }
            if (file_exists(public_path(config('attr.poster.path') . $this->image))) {
                return '/' . ltrim(config('attr.poster.path') . $this->image, '/');
            }
        }
        return '/static/img/placeholder/300.png';
    }
    public function getCoverUrlAttribute()
    {
        if ($this->cover) {
            if (str_starts_with($this->cover, 'http://') || str_starts_with($this->cover, 'https://')) {
                return $this->cover;
            }
            if (file_exists(public_path(config('attr.poster.path') . $this->cover))) {
                return '/' . ltrim(config('attr.poster.path') . $this->cover, '/');
            }
        }
        return '/static/img/placeholder/cover.png';
    }
    public function scopeSearchUrl(Builder $query, $value)
    {
        return $query->where('title', 'like', '%'.$value.'%');
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }
    public function report()
    {
        return $this->morphMany(Report::class, 'postable');
    }
    public function videos()
    {
        return $this->morphMany(PostVideo::class, 'Postable');
    }

}
