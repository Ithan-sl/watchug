<?php

namespace App\Livewire;

use App\Models\Genre;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithPagination;

class SearchPage extends Component
{
    use WithPagination;

    public $q = '';
    public $type = '';
    public $genre = '';
    public $sort = 'created_at';

    protected $queryString = [
        'q' => ['except' => ''],
        'type' => ['except' => ''],
        'genre' => ['except' => ''],
        'sort' => ['except' => 'created_at'],
    ];

    public function mount(Request $request, $initialQuery = '', $initialType = '', $initialGenre = '', $initialSort = 'created_at')
    {
        $this->q = $request->route('search')
            ?? $request->query('q')
            ?? $request->query('search')
            ?? $initialQuery
            ?? '';

        $this->type = $request->query('type', $initialType);
        $this->genre = $request->query('genre', $initialGenre);
        $this->sort = $request->query('sort', $initialSort ?: 'created_at');
    }

    public function updatedQ()
    {
        $this->resetPage();
    }

    public function updatedType()
    {
        $this->resetPage();
    }

    public function updatedGenre()
    {
        $this->resetPage();
    }

    public function updatedSort()
    {
        $this->resetPage();
    }

    public function clearQuery()
    {
        $this->q = '';
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->type = '';
        $this->genre = '';
        $this->sort = 'created_at';
        $this->resetPage();
    }

    public function setType($type)
    {
        $this->type = ($this->type === $type) ? '' : $type;
        $this->resetPage();
    }

    public function setGenre($genreId)
    {
        $this->genre = ((string) $this->genre === (string) $genreId) ? '' : (string) $genreId;
        $this->resetPage();
    }

    public function setSort($sort)
    {
        $this->sort = $sort;
        $this->resetPage();
    }

    public function render()
    {
        $hasSearch = !empty(trim($this->q));
        $query = Post::where('status', 'publish')->with(['genres']);

        if ($hasSearch) {
            $searchTerm = trim($this->q);
            $query->where(function ($sub) use ($searchTerm) {
                $sub->where('title', 'like', '%' . $searchTerm . '%')
                    ->orWhere('title_sub', 'like', '%' . $searchTerm . '%')
                    ->orWhere('tagline', 'like', '%' . $searchTerm . '%');
            });
        }

        if (!empty($this->type)) {
            $query->where('type', $this->type);
        }

        if (!empty($this->genre)) {
            $genreId = $this->genre;
            $query->whereHas('genres', function ($g) use ($genreId) {
                $g->where('genres.id', $genreId);
            });
        }

        switch ($this->sort) {
            case 'vote_average':
                $query->orderBy('vote_average', 'desc');
                break;
            case 'view':
                $query->orderBy('view', 'desc');
                break;
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'release_date':
                $query->orderBy('release_date', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $perPage = config('settings.listing_limit') ?: 24;
        $posts = $query->simplePaginate($perPage);

        // Trending / recommended items when no query or no results
        $recommendations = [];
        if (!$hasSearch || $posts->count() === 0) {
            $recommendations = Cache::remember('search_trending_recommendations', 1800, function () {
                return Post::where('status', 'publish')
                    ->with(['genres'])
                    ->orderBy('view', 'desc')
                    ->limit(12)
                    ->get();
            });
        }

        $genres = Cache::rememberForever('search_genres_all', function () {
            return Genre::orderBy('title', 'asc')->get();
        });

        return view('livewire.search-page', [
            'posts' => $posts,
            'hasSearch' => $hasSearch,
            'recommendations' => $recommendations,
            'genres' => $genres,
        ]);
    }
}
