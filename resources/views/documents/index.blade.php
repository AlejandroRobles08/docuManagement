<x-app-layout>
    <x-slot name="header">
        <div class="uk-flex uk-flex-middle uk-flex-between" uk-grid>
            <div>
                <h2 class="uk-h3 uk-margin-remove">Personas registradas</h2>
                <p class="uk-text-meta uk-margin-remove-top">Listado de personas con al menos un documento subido.</p>
            </div>
            <a href="{{ route('documents.create') }}" class="uk-button uk-button-primary">
                <span uk-icon="icon: plus" class="uk-margin-small-right"></span> Subir documento
            </a>
        </div>
    </x-slot>

    {{-- El mensaje de "status" ya se muestra globalmente en layouts/app.blade.php --}}

    <div class="uk-child-width-1-3@s uk-grid-small uk-margin-bottom" uk-grid>
        <div>
            <div class="stat-card">
                <div class="stat-card__value">{{ $stats['total_persons'] }}</div>
                <div class="stat-card__label">Personas registradas</div>
            </div>
        </div>
        <div>
            <div class="stat-card">
                <div class="stat-card__value">{{ $stats['total_documents'] }}</div>
                <div class="stat-card__label">Documentos subidos</div>
            </div>
        </div>
        <div>
            <div class="stat-card">
                <div class="stat-card__value">{{ $stats['high_risk_documents'] }}</div>
                <div class="stat-card__label">Documentos de riesgo alto</div>
            </div>
        </div>
    </div>

    <div class="uk-card uk-card-default uk-card-body">
        <form method="GET" action="{{ route('documents.index') }}" class="uk-margin-bottom">
            <div class="uk-inline uk-width-1-1 uk-width-1-2@s">
                <span class="uk-form-icon" uk-icon="icon: search"></span>
                <input type="text" name="q" value="{{ $search }}" class="uk-input" placeholder="Buscar por nombre, CURP o número de documento...">
            </div>
        </form>

        <div class="uk-overflow-auto">
            <table class="uk-table uk-table-divider uk-table-middle uk-table-hover">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>CURP</th>
                        <th>Documentos</th>
                        <th>Último riesgo</th>
                        <th>Registrada</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($persons as $person)
                        <tr>
                            <td>{{ $person->full_name }}</td>
                            <td>{{ $person->curp ?? '—' }}</td>
                            <td>{{ $person->documents_count }}</td>
                            <td>
                                @if ($person->documents->first())
                                    <span class="{{ $person->documents->first()->risk_level->badgeClass() }}">
                                        {{ $person->documents->first()->risk_level->label() }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $person->created_at->format('d/m/Y') }}</td>
                            <td>
                                <a href="{{ route('documents.show', $person) }}" class="uk-button uk-button-default uk-button-small">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="uk-text-center uk-text-muted">
                                @if ($search)
                                    No se encontraron personas para "{{ $search }}".
                                @else
                                    Todavía no hay personas registradas.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="uk-margin-top">
            {{ $persons->links() }}
        </div>
    </div>
</x-app-layout>
