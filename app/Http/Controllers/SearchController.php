<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\SchemaOrg\Schema;

class SearchController extends Controller
{
    public function index(Request $request, $search = null)
    {
        $q = $search ?? $request->query('q') ?? $request->query('search') ?? '';

        $config = [];
        $siteTitle = config('settings.title') ?: 'WatChug';

        if (!empty($q)) {
            $heading = __('Search results for ":search"', ['search' => $q]);
            $old = ['[sortable]', '[search]'];
            $new = [null, $q];
            $searchTitle = config('settings.search_title');
            $config['title'] = $searchTitle
                ? trim(str_replace($old, $new, trim($searchTitle)))
                : $heading . ' - ' . $siteTitle;

            $searchDesc = config('settings.search_description');
            $config['description'] = $searchDesc
                ? trim(str_replace($old, $new, trim($searchDesc)))
                : $heading;
        } else {
            $heading = __('Search');
            $config['title'] = __('Search') . ' - ' . $siteTitle;
            $config['description'] = __('Search for movies, TV shows, anime and more on :title', ['title' => $siteTitle]);
        }

        $config['heading'] = $heading;

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
                            ->name(__('Search'))
                            ->id(route('search'))
                    )
            ]);

        return view('search.index', [
            'config' => $config,
            'initialQuery' => $q,
            'initialType' => $request->query('type', ''),
            'initialGenre' => $request->query('genre', ''),
            'initialSort' => $request->query('sort', 'created_at'),
        ]);
    }
}
