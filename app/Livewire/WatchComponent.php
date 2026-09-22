<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Crypt;

class WatchComponent extends Component
{
    public $cover;
    public $listing;
    public $videos = [];
    public $isPreloader = true;

    public function mount($listing)
    {
        $theme_color = empty(config('settings.color')) ? '8871FD' : str_replace('#', '', config('settings.color'));
        if ($listing->type == 'movie') {
            $this->cover = $listing->coverurl;
        } elseif (isset($listing->post->type) AND $listing->post->type == 'tv') {
            $this->cover = $listing->post->coverurl;
        } else {
            $this->cover = $listing->coverurl;
        }

        // 1. Processa vídeos cadastrados no banco de dados local
        if (isset($listing->videos)) {
            foreach ($listing->videos as $video) {
                $label = $video->label ?? 'Stream';
                if ($video->type == 'embed') {
                    $this->videos[] = [
                        'label' => $label,
                        'type' => 'embed',
                        'link' => route('embed.server', ['t' => Crypt::encryptString($video->link), 'label' => $label]),
                    ];
                } else {
                    $this->videos[] = [
                        'label' => $label,
                        'type' => $video->type,
                        'link' => route('embed', $video->id),
                    ];
                }
            }
        }

        // 2. Se NÃO houver vídeos no banco de dados, utiliza os provedores configurados (MegaEmbed / mgeb.top)
        if ($listing->type == 'movie') {
            if (empty($this->videos) && config('settings.megaembed') == 'active' && !empty($listing->tmdb_id)) {
                $rawLink = 'https://mgeb.top/embed/' . $listing->tmdb_id . '?player=vidstack';
                $this->videos[] = [
                    'label' => 'Dublado',
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Dublado']),
                ];
            }
            if (config('settings.vidsrc') == 'active' && !empty($listing->tmdb_id)) {
                $rawLink = 'https://vsembed.ru/embed/movie/' . $listing->tmdb_id . '/color-' . $theme_color;
                $this->videos[] = [
                    'label' => 'Legendado',
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Legendado']),
                ];
            }
        } elseif (isset($listing->post->type) && $listing->post->type == 'tv') {
            // 3. Episódio individual de série
            if (empty($this->videos) && config('settings.megaembed') == 'active' && !empty($listing->post->tmdb_id)) {
                $rawLink = 'https://mgeb.top/embed/' . $listing->post->tmdb_id . '/' . $listing->season_number . '/' . $listing->episode_number . '?player=vidstack';
                $this->videos[] = [
                    'label' => 'Dublado',
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Dublado']),
                ];
            }
            if (config('settings.vidsrc') == 'active' && !empty($listing->post->tmdb_id)) {
                $rawLink = 'https://vsembed.ru/embed/tv/' . $listing->post->tmdb_id . '/' . $listing->season_number . '-' . $listing->episode_number . '/color-' . $theme_color;
                $this->videos[] = [
                    'label' => 'Legendado',
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Legendado']),
                ];
            }
        } elseif ($listing->type == 'tv') {
            // 4. Página principal da série (busca vídeos do 1º episódio cadastrado no banco de dados)
            $firstEpisode = \App\Models\PostEpisode::with(['videos', 'season'])
                ->where('post_id', $listing->id)
                ->where('status', 'publish')
                ->orderBy('season_number', 'asc')
                ->orderBy('episode_number', 'asc')
                ->first();

            $seasonNum = $firstEpisode ? $firstEpisode->season_number : 1;
            $episodeNum = $firstEpisode ? $firstEpisode->episode_number : 1;
            $epSuffix = ' (T' . $seasonNum . ':EP' . $episodeNum . ')';

            if ($firstEpisode && isset($firstEpisode->videos) && $firstEpisode->videos->isNotEmpty()) {
                foreach ($firstEpisode->videos as $video) {
                    $label = ($video->label ?? 'Stream') . $epSuffix;
                    if ($video->type == 'embed') {
                        $this->videos[] = [
                            'label' => $label,
                            'type' => 'embed',
                            'link' => route('embed.server', ['t' => Crypt::encryptString($video->link), 'label' => $label]),
                        ];
                    } else {
                        $this->videos[] = [
                            'label' => $label,
                            'type' => $video->type,
                            'link' => route('embed', $video->id),
                        ];
                    }
                }
            } elseif (config('settings.megaembed') == 'active' && !empty($listing->tmdb_id)) {
                $rawLink = 'https://mgeb.top/embed/' . $listing->tmdb_id . '/' . $seasonNum . '/' . $episodeNum . '?player=vidstack';
                $this->videos[] = [
                    'label' => 'Dublado' . $epSuffix,
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Dublado']),
                ];
            }

            if (config('settings.vidsrc') == 'active' && !empty($listing->tmdb_id)) {
                $rawLink = 'https://vsembed.ru/embed/tv/' . $listing->tmdb_id . '/' . $seasonNum . '-' . $episodeNum . '/color-' . $theme_color;
                $this->videos[] = [
                    'label' => 'Legendado' . $epSuffix,
                    'type' => 'embed',
                    'link' => route('embed.server', ['t' => Crypt::encryptString($rawLink), 'label' => 'Legendado']),
                ];
            }
        }
    }

    public function watching()
    {
        $this->isPreloader = false;
    }

    public function render()
    {
        return view('livewire.watch');
    }
}
