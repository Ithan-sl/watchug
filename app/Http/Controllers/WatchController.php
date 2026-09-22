<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\Post;
use App\Models\PostEpisode;
use App\Models\PostVideo;
use Illuminate\Http\Request;
use App\Models\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Auth;
use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\TVEpisode;

class WatchController extends Controller
{

    public function movie(Request $request, $slug)
    {
        $listing = Cache::remember("post-movie-{$slug}", 3600, function () use ($slug) {
            return Post::with(['country', 'genres', 'peoples', 'videos', 'downloads', 'tags'])
                ->where('slug', $slug)
                ->where('status', 'publish')
                ->where('type', 'movie')
                ->firstOrFail();
        });

        $genres = $listing->genres->modelKeys();
        $listingId = $listing->id;
        $recommends = Cache::remember("recommends-movie-{$listingId}", 3600, function () use ($genres, $listingId) {
            return Post::with(['country', 'genres'])
                ->where('type', 'movie')
                ->whereHas('genres', function ($q) use ($genres) {
                    $q->whereIn('genres.id', $genres);
                })
                ->where('id', '!=', $listingId)
                ->where('status', 'publish')
                ->take(8)
                ->get();
        });


        ## SEO ##
        $config['breadcrumb'] = Schema::breadcrumbList()
            ->itemListElement([
                Schema::listItem()
                    ->position(1)
                    ->item(
                        Schema::thing()
                            ->name(__('Home'))
                            ->id(route('index'))
                    ),
                Schema::listItem()
                    ->position(2)
                    ->item(
                        Schema::thing()
                            ->name(__('Movies'))
                            ->id(route('movies'))
                    )
            ]);
        $schema = Schema::movie()
            ->name($listing->title)
            ->description($listing->overview)
            ->image($listing->imageurl)
            ->datePublished($listing->created_at->format('Y-m-d'))
            ->if(isset($listing->trailer), function ($schema) use ($listing) {
                $schema->trailer(
                    Schema::videoObject()
                        ->name($listing->title)
                        ->description($listing->overview)
                        ->thumbnailUrl($listing->imageurl)
                        ->embedUrl($listing->trailer)
                        ->uploadDate($listing->created_at->format('Y-m-d'))
                        ->contentUrl(route($listing->type, $listing->slug))
                );
            })
            ->potentialAction(
                Schema::WatchAction()
                    ->target(route($listing->type, $listing->slug))
            )
            ->if(isset($listing->country->name), function ($schema) use ($listing) {
                $schema->countryOfOrigin(
                    Schema::country()
                        ->name($listing->country->name)
                );
            })
            ->review(
                Schema::review()
                    ->author(Schema::person()->name(config('settings.site_name')))
                    ->datePublished($listing->updated_at->format('Y-m-d'))
                    ->reviewBody($listing->overview)
            )
            ->aggregateRating(
                Schema::aggregateRating()
                    ->ratingValue($listing->vote_average)
                    ->bestRating('10.0')
                    ->worstRating('1.0')
                    ->ratingCount($listing->view == 0 ? 1 : $listing->view)
            );

        foreach ($listing->peoples as $people) {
            $peopleSchema[] = Schema::person()
                ->name($people->name)
                ->url(route('people', $people->slug));

        }
        if (isset($peopleSchema)) {
            $schema->actor($peopleSchema);
        }
        $config['schema'] = $schema;

        if ($listing->meta_title and $listing->meta_description) {
            $config['title'] = $listing->meta_title;
            $config['description'] = $listing->meta_description;
        } else {
            $new = array(
                $listing->title,
                $listing->overview,
                $listing->release_date->format('Y'),
                !empty($listing->country->name) ? $listing->country->name : null,
                isset($listing->genres[0]) ? $listing->genres[0]->title : null,
            );
            $old = array('[title]', '[description]', '[release]', '[country]', '[genre]');

            $config['title'] = trim(str_replace($old, $new, trim(config('settings.movie_title'))));
            $config['description'] = trim(str_replace($old, $new, trim(config('settings.movie_description'))));
            $config['image'] = $listing->coverurl;
        }
        ## SEO ##

        if ($request->user() and !$listing->logs()->exists() and config('settings.history') == 'active') {

            $data = new Log();
            $data->user_id = $request->user()->id;
            $listing->logs()->save($data);

            $listing->view = (int) $listing->view + 1;
            $listing->save();

        }
        return view('watch.movie', compact('config', 'listing', 'recommends'));
    }

    public function tv(Request $request, $slug)
    {
        $listing = Cache::remember("post-tv-{$slug}", 3600, function () use ($slug) {
            return Post::withCount(['seasons'])
                ->with(['country', 'genres', 'peoples', 'videos', 'seasons.episodes'])
                ->where('slug', $slug)
                ->where('status', 'publish')
                ->where('type', 'tv')
                ->firstOrFail();
        });

        $genres = $listing->genres->modelKeys();
        $listingId = $listing->id;
        $recommends = Cache::remember("recommends-tv-{$listingId}", 3600, function () use ($genres, $listingId) {
            return Post::with(['country', 'genres'])
                ->where('type', 'tv')
                ->whereHas('genres', function ($q) use ($genres) {
                    $q->whereIn('genres.id', $genres);
                })
                ->where('id', '!=', $listingId)
                ->where('status', 'publish')
                ->take(8)
                ->get();
        });

        ## SEO ##
        $config['breadcrumb'] = Schema::breadcrumbList()
            ->itemListElement([
                Schema::listItem()
                    ->position(1)
                    ->item(
                        Schema::thing()
                            ->name(__('Home'))
                            ->id(route('index'))
                    ),
                Schema::listItem()
                    ->position(2)
                    ->item(
                        Schema::thing()
                            ->name(__('TV Shows'))
                            ->id(route('tvshows'))
                    )
            ]);
        $schema = Schema::tvSeries()
            ->name($listing->title)
            ->url(route('tv', $listing->slug))
            ->description($listing->overview)
            ->image($listing->imageurl)
            ->datePublished($listing->created_at->format('Y-m-d'))
            ->if(isset($listing->trailer), function ($schema) use ($listing) {
                $schema->trailer(
                    Schema::videoObject()
                        ->name($listing->title)
                        ->description($listing->overview)
                        ->thumbnailUrl($listing->imageurl)
                        ->embedUrl($listing->trailer)
                        ->uploadDate($listing->created_at->format('Y-m-d'))
                        ->contentUrl(route($listing->type, $listing->slug))
                );
            })
            ->potentialAction(
                Schema::WatchAction()
                    ->target(route($listing->type, $listing->slug))
            )
            ->if(isset($listing->country->name), function ($schema) use ($listing) {
                $schema->countryOfOrigin(
                    Schema::country()
                        ->name($listing->country->name)
                );
            })
            ->review(
                Schema::review()
                    ->author(Schema::person()->name(config('settings.site_name')))
                    ->datePublished($listing->updated_at->format('Y-m-d'))
                    ->reviewBody($listing->overview)
            )
            ->aggregateRating(
                Schema::aggregateRating()
                    ->ratingValue($listing->vote_average)
                    ->bestRating('10.0')
                    ->worstRating('1.0')
                    ->ratingCount($listing->view == 0 ? 1 : $listing->view)
            );


        foreach ($listing->peoples as $people) {
            $peopleSchema[] = Schema::person()
                ->name($people->name)
                ->url(route('people', $people->slug));

        }
        if (isset($peopleSchema)) {
            $schema->actor($peopleSchema);
        }
        foreach ($listing->seasons as $season) {
            $seasonSchema[$season->id] = [
                'name' => $season->season_number
            ];
            foreach ($season->episodes as $episode) {
                $seasonSchema[$season->id]['episodes'][] = [
                    'episodeNumber' => $episode->episode_number,
                    'name' => $episode->name,
                    'datePublished' => $episode->created_at->format('Y-m-d'),
                    'url' => route('episode', [
                        'slug' => $listing->slug, 'season' => $season->season_number,
                        'episode' => $episode->episode_number
                    ])
                ];
            }
        }
        $config['schema'] = $schema;

        if ($listing->meta_title and $listing->meta_description) {
            $config['title'] = $listing->meta_title;
            $config['description'] = $listing->meta_description;
        } else {
            $new = array(
                $listing->title,
                $listing->overview,
                $listing->release_date->format('Y'),
                !empty($listing->country->name) ? $listing->country->name : null,
                isset($listing->genres[0]) ? $listing->genres[0]->title : null,
            );
            $old = array('[title]', '[description]', '[release]', '[country]', '[genre]');

            $config['title'] = trim(str_replace($old, $new, trim(config('settings.tvshow_title'))));
            $config['description'] = trim(str_replace($old, $new, trim(config('settings.tvshow_description'))));
            $config['image'] = $listing->coverurl;
        }
        ## SEO ##

        return view('watch.tv', compact('config', 'listing', 'recommends'));
    }

    public function episode(Request $request, $slug, $season, $episode)
    {
        $listing = Cache::remember("post-tv-{$slug}", 3600, function () use ($slug) {
            return Post::with(['country', 'genres', 'peoples', 'seasons.episodes'])
                ->where('slug', $slug)
                ->where('type', 'tv')
                ->where('status', 'publish')
                ->firstOrFail();
        });
        $listingId = $listing->id;
        $episode = Cache::remember("episode-{$listingId}-{$season}-{$episode}", 3600, function () use ($listingId, $season, $episode) {
            return PostEpisode::with(['videos', 'season'])
                ->where('post_id', $listingId)
                ->where('status', 'publish')
                ->where('season_number', $season)
                ->where('episode_number', $episode)
                ->firstOrFail();
        });

        $genres = $listing->genres->modelKeys();
        $recommends = Cache::remember("recommends-tv-{$listingId}", 3600, function () use ($genres, $listingId) {
            return Post::with(['country', 'genres'])
                ->where('type', 'tv')
                ->whereHas('genres', function ($q) use ($genres) {
                    $q->whereIn('genres.id', $genres);
                })
                ->where('id', '!=', $listingId)
                ->where('status', 'publish')
                ->take(8)
                ->get();
        });

        ## SEO ##
        $config['breadcrumb'] = Schema::breadcrumbList()
            ->itemListElement([
                Schema::listItem()
                    ->position(1)
                    ->item(
                        Schema::thing()
                            ->name(__('Home'))
                            ->id(route('index'))
                    ),
                Schema::listItem()
                    ->position(2)
                    ->item(
                        Schema::thing()
                            ->name(__('TV Shows'))
                            ->id(route('tvshows'))
                    )
            ]);
        $schema = Schema::tVEpisode()
            ->name($listing->title.' '.__(':number Season',
                    ['number' => $episode->season_number]).', '.__(':number Episode',
                    ['number' => $episode->episode_number]))
            ->description($listing->overview)
            ->image($listing->imageurl)
            ->datePublished($episode->created_at->format('Y-m-d'))
            ->if(isset($listing->trailer), function (tVEpisode $schema) use ($listing, $episode) {
                $schema->trailer(
                    Schema::videoObject()
                        ->name($episode->name)
                        ->description($episode->overview)
                        ->thumbnailUrl($listing->imageurl)
                        ->uploadDate($episode->created_at->format('Y-m-d'))
                        ->contentUrl(route('episode', [
                            'slug' => $listing->slug, 'season' => $episode->season->season_number,
                            'episode' => $episode->episode_number
                        ]))
                );
            })
            ->potentialAction(
                Schema::WatchAction()
                    ->target(route('episode', [
                        'slug' => $listing->slug, 'season' => $episode->season->season_number,
                        'episode' => $episode->episode_number
                    ]))
            )
            ->aggregateRating(
                Schema::aggregateRating()
                    ->ratingValue($listing->vote_average)
                    ->bestRating('10.0')
                    ->worstRating('1.0')
                    ->ratingCount($listing->view == 0 ? 1 : $listing->view)
            );

        $config['schema'] = $schema;
        if ($episode->meta_title and $episode->meta_description) {
            $config['title'] = $episode->meta_title;
            $config['description'] = $episode->meta_description;
        } else {
            $new = array(
                $listing->title,
                $episode->season->season_number,
                $episode->episode_number,
                $listing->overview,
                $listing->release_date->format('Y'),
                !empty($listing->country->name) ? $listing->country->name : null,
                isset($listing->genres[0]) ? $listing->genres[0]->title : null,
            );
            $old = array('[title]', '[season]', '[episode]', '[description]', '[release]', '[country]', '[genre]');

            $config['title'] = trim(str_replace($old, $new, trim(config('settings.episode_title'))));
            $config['description'] = trim(str_replace($old, $new, trim(config('settings.episode_description'))));
            $config['image'] = $listing->coverurl;
        }
        ## SEO ##


        if ($request->user() and !$episode->logs()->exists() and config('settings.history') == 'active') {

            $data = new Log();
            $data->user_id = $request->user()->id;
            $episode->logs()->save($data);

            $listing->view = (int) $listing->view + 1;
            $listing->save();
            $episode->view = (int) $episode->view + 1;
            $episode->save();

        }
        return view('watch.episode', compact('config', 'listing', 'episode', 'recommends'));
    }

    public function broadcast(Request $request, $slug)
    {
        $listing = Cache::remember("broadcast-{$slug}", 3600, function () use ($slug) {
            return Broadcast::where('slug', $slug)->firstOrFail();
        });

        $config = [
            'title' => __('Broadcast'),
            'route' => 'broadcast',
            'nav' => 'broadcast',
        ];

        ## SEO ##
        if ($listing->meta_title and $listing->meta_description) {
            $config['title'] = $listing->meta_title;
            $config['description'] = $listing->meta_description;
        } else {
            $new = array(
                $listing->title,
                $listing->overview,
            );
            $old = array('[title]', '[description]');

            $config['title'] = trim(str_replace($old, $new, trim(config('settings.broadcast_title'))));
            $config['description'] = trim(str_replace($old, $new, trim(config('settings.broadcast_description'))));
            $config['image'] = $listing->imageurl;
        }
        ## SEO ##
        return view('watch.broadcast', compact('config', 'listing'));
    }

    public function embed(Request $request, $slug)
    {

        $listing = PostVideo::where('id', $slug)->firstOrFail() ?? abort(404);

        $Key = $listing->postable->id.'-'.$listing->postable->slug;

        if (!\Session::has($Key)) {
            \Session::put($Key, 1);
            $listing->postable->view = (int) $listing->postable->view + 1;
            $listing->postable->save();
        }
        return response()
            ->view('watch.embed', compact('listing'))
            ->header('Permissions-Policy', 'notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()');
    }

    public function embedServer(Request $request)
    {
        $token = $request->query('t');
        $url = null;
        if ($token) {
            try {
                $url = Crypt::decryptString($token);
            } catch (\Exception $e) {
                $url = null;
            }
        }
        if (!$url && $request->has('url')) {
            $raw = $request->query('url');
            $url = base64_decode($raw);
        }
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400);
        }

        $type = $request->query('type');
        if (!$type) {
            if (str_contains($url, '.m3u8') || str_contains($url, '/hls/')) {
                $type = 'hls';
            } elseif (str_contains($url, '.mp4')) {
                $type = 'mp4';
            } else {
                $type = 'embed';
            }
        }

        $listing = (object) [
            'id' => 0,
            'type' => $type,
            'link' => $url,
            'label' => $request->query('label', 'Stream'),
            'postable' => null,
        ];

        return response()
            ->view('watch.embed', compact('listing'))
            ->header('Permissions-Policy', 'notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()');
    }
}
