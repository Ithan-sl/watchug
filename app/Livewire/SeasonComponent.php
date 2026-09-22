<?php

namespace App\Livewire;

use App\Models\PostSeason;
use Livewire\Component;

use Illuminate\Support\Facades\Cache;

class SeasonComponent extends Component
{
    public $model;
    public $type;
    public $seasonId;
    public $selectEpisode;
    public $openSort;
    public $episode_number;
    public $season_number;

    public function mount($model,$seasonId = null,$type = null,$selectEpisode = null) {
        $this->model = $model;
        $this->type = $type;
        $this->selectEpisode = $selectEpisode;
        $this->seasonId = $seasonId;
    }
    public function render()
    {
        $postId = $this->model->id;
        $seasonId = $this->seasonId;

        $selectSeason = Cache::remember("season-select-{$postId}-{$seasonId}", 3600, function () use ($postId, $seasonId) {
            $query = PostSeason::with(['episodes.post', 'episodes.season'])->where('post_id', $postId);
            if ($seasonId) {
                $query->where('id', $seasonId);
            }
            return $query->first();
        });

        if ($selectSeason) {
            $this->season_number = $selectSeason->season_number;
        }

        return view('livewire.season-component',compact('selectSeason'));
    }
    public function updateSeason($seasonId)
    {
        $this->seasonId = $seasonId;
        $this->openSort = false;
    }
    public function goto() {
        $this->redirect(route('episode',['slug'=>$this->model->slug,'season'=>$this->season_number,'episode'=>$this->episode_number]));
    }
}
