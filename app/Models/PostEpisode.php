<?php

namespace App\Models;

use App\Traits\Commentable;
use App\Traits\Reactable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PostEpisode extends Model
{
    use HasFactory,Commentable,Reactable;

    protected $table = 'post_episodes';
    protected $fillable = [
        'name',
        'episode_number',
        'season_number',
        'overview',
        'image',
        'runtime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($post) {
            $post->reactions()->delete();
            $post->logs()->delete();
            $post->watchlist()->delete();
        });
    }

    public function scopeSearchUrl(Builder $query, $value)
    {
        return $query->where('name', 'like', '%'.$value.'%');
    }
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            $path = config('attr.poster.episode_path') . ($this->created_at ? $this->created_at->translatedFormat('m-Y') : '') . '/' . $this->image;
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        if ($this->tmdb_image) {
            return 'https://image.tmdb.org/t/p/w300' . $this->tmdb_image;
        }

        if ($this->post) {
            if ($this->post->tmdb_image) {
                return 'https://image.tmdb.org/t/p/w227_and_h127_bestv2' . $this->post->tmdb_image;
            }
            return $this->post->imageurl;
        }

        return '';
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
    public function season()
    {
        return $this->belongsTo(PostSeason::class,'post_season_id');
    }
    public function videos()
    {
        return $this->morphMany(PostVideo::class, 'postable')->whereNot('type','download');
    }
    public function downloads()
    {
        return $this->morphMany(PostVideo::class, 'postable')->where('type','download');
    }
    public function subtitles()
    {
        return $this->morphMany(PostSubtitle::class, 'postable');
    }
    public function report()
    {
        return $this->morphOne(Report::class, 'postable');
    }
    public function watchlist()
    {
        return $this->morphOne(Watchlist::class, 'postable');
    }
    public function likes()
    {
        return $this->morphMany(Reaction::class, 'reactable')->where('reaction','like');
    }
    public function dislikes()
    {
        return $this->morphMany(Reaction::class, 'reactable')->where('reaction','dislike');
    }
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
    public function logs(): MorphMany
    {
        return $this->morphMany(Log::class, 'postable');
    }
    public function isLog($user): bool
    {
        return $this->logs()->where('user_id', $user->id)->exists();
    }
}
