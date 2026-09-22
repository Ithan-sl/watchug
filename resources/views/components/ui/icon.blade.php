@props(['name','class'])
<svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    stroke-linecap="round"
    stroke-width="1.75"
    stroke-linejoin="round"
    {{ $attributes->merge(['class' => "$class"]) }}>
    <use href="/static/sprite/sprite.svg#{{ $name }}" xlink:href="/static/sprite/sprite.svg#{{ $name }}"></use>
</svg>
