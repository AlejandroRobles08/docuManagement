<button {{ $attributes->merge(['type' => 'submit', 'class' => 'uk-button uk-button-danger']) }}>
    {{ $slot }}
</button>
