<button {{ $attributes->merge(['type' => 'submit', 'class' => 'uk-button uk-button-primary']) }}>
    {{ $slot }}
</button>
