{{-- Paginador con clases de UIkit (uk-pagination), en vez de las vistas de
     Tailwind que trae Laravel por defecto. Ver AppServiceProvider::boot(). --}}
@if ($paginator->hasPages())
    <ul class="uk-pagination uk-flex-center">
        @if ($paginator->onFirstPage())
            <li class="uk-disabled"><span uk-icon="icon: chevron-left"></span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev"><span uk-icon="icon: chevron-left"></span></a></li>
        @endif

        @foreach ($paginator->elements() as $element)
            @if (is_string($element))
                <li class="uk-disabled"><span>{{ $element }}</span></li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="uk-active"><span>{{ $page }}</span></li>
                    @else
                        <li><a href="{{ $url }}">{{ $page }}</a></li>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}" rel="next"><span uk-icon="icon: chevron-right"></span></a></li>
        @else
            <li class="uk-disabled"><span uk-icon="icon: chevron-right"></span></li>
        @endif
    </ul>
@endif
