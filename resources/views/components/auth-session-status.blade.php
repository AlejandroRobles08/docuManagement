@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'uk-text-success uk-text-small']) }}>
        {{ $status }}
    </div>
@endif
