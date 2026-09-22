@php
    $ads = \Illuminate\Support\Facades\Cache::remember('ad-' . ($id ?? 0), 3600, function () use ($id) {
        return \App\Models\Advertisement::where('id', $id)->where('status', 'publish')->first();
    });
@endphp
@php
    $showAd = isset($ads->body) && !empty(trim($ads->body));
    if ($showAd && isset($ads->user_hide) && $ads->user_hide == 'active' && auth()->check() && !empty(auth()->user()->plan_recurring_at) && auth()->user()->plan_recurring_at > now()) {
        $showAd = false;
    }
@endphp
@if($showAd)
    <div class="text-center mb-4">{!!html_entity_decode($ads->body)!!}</div>
@endif
