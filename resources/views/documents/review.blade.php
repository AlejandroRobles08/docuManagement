<x-app-layout>
    <x-slot name="header">
        <h2 class="uk-h3 uk-margin-remove">Revisión del documento</h2>
        <p class="uk-text-meta uk-margin-remove-top">Verifica el resultado antes de guardar el registro.</p>
    </x-slot>

    @if (session('error'))
        <div class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="uk-grid-medium" uk-grid>
        <div class="uk-width-1-1 uk-width-1-2@m">
            <div class="uk-card uk-card-default uk-card-body">
                <h3 class="uk-card-title">Datos capturados</h3>

                <table class="uk-table uk-table-small uk-table-divider">
                    <tbody>
                        <tr>
                            <th>Nombre completo</th>
                            <td>{{ $form['full_name'] }}</td>
                        </tr>
                        <tr>
                            <th>CURP</th>
                            <td>{{ $form['curp'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Fecha de nacimiento</th>
                            <td>{{ $form['birth_date'] ?? '—' }}</td>
                        </tr>
                        <tr>
                            <th>Tipo de documento</th>
                            <td>{{ $documentTypeLabel }}</td>
                        </tr>
                        <tr>
                            <th>Número de documento</th>
                            <td>{{ $form['document_number'] }}</td>
                        </tr>
                        <tr>
                            <th>Archivo</th>
                            <td>{{ $file['original_filename'] }} ({{ number_format($file['size'] / 1024, 0) }} KB)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="uk-width-1-1 uk-width-1-2@m">
            <div class="uk-card uk-card-default uk-card-body">
                <h3 class="uk-card-title">Resultado del análisis anti-falsificación</h3>

                <p>
                    <span class="{{ $riskLevel->badgeClass() }}">
                        <span uk-icon="icon: {{ $riskLevel->icon() }}; ratio: 0.8"></span>
                        {{ $riskLevel->label() }} ({{ $report['score'] }}/100)
                    </span>
                </p>

                @if (count($report['reasons']))
                    <p class="uk-text-meta uk-margin-remove-bottom">Motivos detectados:</p>
                    <ul class="risk-reasons">
                        @foreach ($report['reasons'] as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="uk-text-meta">No se detectaron indicios de manipulación con las heurísticas disponibles.</p>
                @endif

                <p class="uk-text-small uk-text-muted uk-margin-top">
                    Este análisis es heurístico (metadatos, compresión, coincidencia de archivos) y no sustituye una verificación forense ni legal de autenticidad.
                </p>
            </div>
        </div>
    </div>

    @if ($duplicatePerson)
        <div class="uk-alert-warning uk-margin-top" uk-alert>
            <h3 class="uk-margin-remove-bottom"><span uk-icon="icon: warning"></span> Esta persona ya está registrada</h3>
            <p>{{ $duplicate['message'] }}</p>
            <p>
                Persona existente: <strong>{{ $duplicatePerson->full_name }}</strong>
                &mdash; <a href="{{ route('documents.show', $duplicatePerson) }}">ver su registro completo</a>
            </p>

            <form id="attach-form" method="POST" action="{{ route('documents.confirm', $token) }}" class="uk-margin-top">
                @csrf
                <input type="hidden" name="attach_to_person_id" value="{{ $duplicatePerson->id }}">

                @if ($riskLevel->requiresManualConfirmation())
                    <label class="uk-display-block uk-margin-small-bottom">
                        <input type="checkbox" class="uk-checkbox" name="confirm_high_risk" value="1" required>
                        Revisé el documento y confirmo que deseo continuar a pesar del riesgo alto.
                    </label>
                @endif
            </form>

            <div class="uk-flex" style="gap: .5rem;">
                <x-primary-button form="attach-form">Agregar este documento a {{ $duplicatePerson->full_name }}</x-primary-button>

                <form method="POST" action="{{ route('documents.cancel', $token) }}">
                    @csrf
                    <x-secondary-button type="submit">Cancelar / corregir datos</x-secondary-button>
                </form>
            </div>
        </div>
    @else
        <div class="uk-card uk-card-default uk-card-body uk-margin-top">
            <p class="uk-margin-remove-top">
                <span uk-icon="icon: check" class="uk-text-success"></span>
                No se encontró a esta persona en la base de datos: se registrará como una persona nueva.
            </p>

            <form id="save-form" method="POST" action="{{ route('documents.confirm', $token) }}">
                @csrf

                @if ($riskLevel->requiresManualConfirmation())
                    <label class="uk-display-block uk-margin-small-bottom">
                        <input type="checkbox" class="uk-checkbox" name="confirm_high_risk" value="1" required>
                        Revisé el documento y confirmo que deseo continuar a pesar del riesgo alto.
                    </label>
                @endif
            </form>

            <div class="uk-flex" style="gap: .5rem;">
                <x-primary-button form="save-form">Confirmar y guardar</x-primary-button>

                <form method="POST" action="{{ route('documents.cancel', $token) }}">
                    @csrf
                    <x-secondary-button type="submit">Cancelar</x-secondary-button>
                </form>
            </div>
        </div>
    @endif
</x-app-layout>
