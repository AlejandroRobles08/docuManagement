<x-app-layout>
    <x-slot name="header">
        <div class="uk-flex uk-flex-middle uk-flex-between" uk-grid>
            <div>
                <h2 class="uk-h3 uk-margin-remove">{{ $person->full_name }}</h2>
                <p class="uk-text-meta uk-margin-remove-top">
                    CURP: {{ $person->curp ?? 'no capturado' }}
                    @if ($person->birth_date)
                        &middot; Nacimiento: {{ $person->birth_date->format('d/m/Y') }}
                    @endif
                </p>
            </div>
            <a href="{{ route('documents.index') }}" class="uk-button uk-button-default">
                <span uk-icon="icon: arrow-left" class="uk-margin-small-right"></span> Volver al listado
            </a>
        </div>
    </x-slot>

    {{-- El mensaje de "status" ya se muestra globalmente en layouts/app.blade.php --}}

    <h3 class="uk-h4">Documentos registrados ({{ $person->documents->count() }})</h3>

    <div class="uk-child-width-1-1 uk-child-width-1-2@m uk-grid-small" uk-grid>
        @foreach ($person->documents as $document)
            <div>
                <div class="uk-card uk-card-default uk-card-body">
                    <div class="uk-flex uk-flex-between uk-flex-middle">
                        <h4 class="uk-card-title uk-margin-remove">{{ $document->document_type->label() }}</h4>
                        <span class="{{ $document->risk_level->badgeClass() }}">{{ $document->risk_level->label() }} ({{ $document->risk_score }})</span>
                    </div>

                    <p class="uk-text-meta">
                        N.º {{ $document->document_number }} &middot;
                        subido el {{ $document->created_at->format('d/m/Y H:i') }}
                        @if ($document->uploader)
                            por {{ $document->uploader->name }}
                        @endif
                    </p>

                    @if ($document->risk_reasons && count($document->risk_reasons))
                        <ul class="risk-reasons">
                            @foreach ($document->risk_reasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($document->authenticity_confirmed_by_staff)
                        <p class="uk-text-small uk-text-warning">
                            <span uk-icon="icon: warning; ratio: 0.8"></span>
                            Un miembro del staff confirmó manualmente este documento pese a su riesgo alto.
                        </p>
                    @endif

                    <a href="#document-preview-{{ $document->id }}" uk-toggle class="uk-button uk-button-default uk-button-small uk-margin-small-top">
                        <span uk-icon="icon: file-pdf; ratio: 0.8"></span> Ver archivo original
                    </a>

                    <div id="document-preview-{{ $document->id }}" class="uk-modal-container" uk-modal>
                        <div class="uk-modal-dialog uk-modal-body document-preview-dialog">
                            <button class="uk-modal-close-default" type="button" uk-close></button>
                            <h3 class="uk-modal-title">
                                {{ $document->document_type->label() }} &middot; N.º {{ $document->document_number }}
                            </h3>

                            @if ($document->mime_type === 'application/pdf')
                                <iframe src="{{ $document->url() }}" class="document-preview-frame" title="Vista previa de {{ $document->original_filename }}"></iframe>
                            @else
                                <img src="{{ $document->url() }}" alt="Vista previa de {{ $document->original_filename }}" class="document-preview-image">
                            @endif

                            <p class="uk-text-right uk-margin-small-top uk-margin-remove-bottom">
                                <a href="{{ $document->url() }}" target="_blank" rel="noopener" class="uk-link-muted">
                                    Abrir en una pestaña nueva
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
