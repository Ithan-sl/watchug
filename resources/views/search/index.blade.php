@extends('layouts.app')

@section('content')
    <livewire:search-page
        :initialQuery="$initialQuery ?? ''"
        :initialType="$initialType ?? ''"
        :initialGenre="$initialGenre ?? ''"
        :initialSort="$initialSort ?? 'created_at'"
    />
@endsection
