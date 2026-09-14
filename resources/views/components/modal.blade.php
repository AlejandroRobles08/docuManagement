@props([
    'name',
    'show' => false,
])

{{-- Modal nativo de UIkit: se abre con un elemento que tenga
     uk-toggle="target: #{{ $name }}" y se cierra solo con la clase
     "uk-modal-close" en cualquier botón dentro de él (o la X por defecto). --}}
<div id="{{ $name }}" uk-modal>
    <div class="uk-modal-dialog uk-modal-body">
        <button class="uk-modal-close-default" type="button" uk-close></button>
        {{ $slot }}
    </div>
</div>

@if ($show)
    <script>
        document.addEventListener('DOMContentLoaded', () => UIkit.modal('#{{ $name }}').show());
    </script>
@endif
