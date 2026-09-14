@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'uk-list uk-text-small uk-text-danger uk-margin-remove-top uk-margin-small-top']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
