<x-app-layout>
    <x-slot name="header">
        <h2 class="uk-h3 uk-margin-remove">Subir documento de identificación</h2>
        <p class="uk-text-meta uk-margin-remove-top">
            El documento se analizará (posible falsificación) y se buscará en la base de datos antes de guardarse.
        </p>
    </x-slot>

    @if (session('error'))
        <div class="uk-alert-danger" uk-alert>
            <a class="uk-alert-close" uk-close></a>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="uk-card uk-card-default uk-card-body uk-width-2-3@l">
        <form method="POST" action="{{ route('documents.analyze') }}" enctype="multipart/form-data" class="uk-form-stacked">
            @csrf

            <fieldset class="uk-fieldset">
                <legend class="uk-legend">Datos de la persona</legend>

                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-1 person-picker" data-person-picker data-search-url="{{ route('documents.persons.search') }}">
                        <x-input-label for="person_search" value="Persona" />

                        <input type="hidden" name="person_id" value="{{ old('person_id', $selectedPerson?->id) }}" data-person-id-input>

                        <div class="uk-inline uk-width-1-1">
                            <span class="uk-form-icon" uk-icon="icon: search"></span>
                            <input
                                type="text"
                                id="person_search"
                                class="uk-input"
                                placeholder="Busca a la persona por nombre o CURP..."
                                autocomplete="off"
                                autofocus
                                data-person-search-input
                            >
                        </div>

                        <div class="person-picker-results uk-box-shadow-medium" data-person-search-results hidden>
                            <ul class="uk-nav uk-nav-default uk-margin-remove" data-person-search-list></ul>
                        </div>

                        <x-input-error :messages="$errors->get('person_id')" />

                        <div class="uk-alert-primary uk-margin-small-top" uk-alert data-person-selected-summary @if (! $selectedPerson) hidden @endif>
                            <p class="uk-margin-remove">
                                <span uk-icon="icon: check" class="uk-margin-small-right"></span>
                                Persona seleccionada: <strong data-person-selected-name>{{ $selectedPerson?->full_name }}</strong>
                                <button type="button" class="uk-button uk-button-link uk-margin-small-left" data-person-clear>Cambiar / crear nueva</button>
                            </p>

                            <p class="uk-margin-small-top uk-margin-remove-bottom" data-person-documents-summary>
                                @if ($selectedPerson)
                                    @if ($selectedPersonRiskBadges->isNotEmpty())
                                        <strong>{{ $selectedPerson->documents_count }}</strong> documento(s) registrado(s):
                                        @foreach ($selectedPersonRiskBadges as $badge)
                                            <span class="{{ $badge['level']->badgeClass() }}">{{ $badge['count'] }} {{ $badge['level']->label() }}</span>
                                        @endforeach
                                    @else
                                        <span class="uk-text-meta">Sin documentos registrados todavía.</span>
                                    @endif
                                @endif
                            </p>
                        </div>

                        <p class="uk-text-meta uk-margin-small-top" data-person-empty-hint hidden>
                            No encontramos a nadie con ese nombre o CURP. Usa "Crear persona nueva" o completa los datos abajo.
                        </p>
                    </div>

                    <div class="uk-width-1-1" data-person-new-fields>
                        <div class="uk-grid-small" uk-grid>
                            <div class="uk-width-1-1">
                                <x-input-label for="full_name" value="Nombre completo" />
                                <x-text-input id="full_name" name="full_name" type="text" class="uk-width-1-1" :value="old('full_name', $selectedPerson?->full_name)" :disabled="(bool) $selectedPerson" />
                                <x-input-error :messages="$errors->get('full_name')" />
                            </div>

                            <div class="uk-width-1-2@s">
                                <x-input-label for="curp" value="CURP (opcional, si el documento la incluye)" />
                                <x-text-input id="curp" name="curp" type="text" class="uk-width-1-1" maxlength="18" style="text-transform:uppercase" :value="old('curp', $selectedPerson?->curp)" :disabled="(bool) $selectedPerson" />
                                <x-input-error :messages="$errors->get('curp')" />
                            </div>

                            <div class="uk-width-1-2@s">
                                <x-input-label for="birth_date" value="Fecha de nacimiento (opcional)" />
                                <x-text-input id="birth_date" name="birth_date" type="date" class="uk-width-1-1" :value="old('birth_date', $selectedPerson?->birth_date?->format('Y-m-d'))" :disabled="(bool) $selectedPerson" />
                                <x-input-error :messages="$errors->get('birth_date')" />
                            </div>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset class="uk-fieldset uk-margin-top">
                <legend class="uk-legend">Documento</legend>

                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-2@s">
                        <x-input-label for="document_type" value="Tipo de documento" />
                        <select id="document_type" name="document_type" class="uk-select" required data-document-number-hints>
                            <option value="" disabled {{ old('document_type') ? '' : 'selected' }}>Selecciona una opción</option>
                            @foreach ($documentTypes as $value => $label)
                                <option value="{{ $value }}" data-hint="{{ $documentTypeHints[$value] }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('document_type')" />
                    </div>

                    <div class="uk-width-1-2@s">
                        <x-input-label for="document_number" value="Identificador (folio o número de serie del documento)" />
                        <x-text-input id="document_number" name="document_number" type="text" class="uk-width-1-1" :value="old('document_number')" required />
                        <p class="uk-text-meta uk-margin-small-top" data-document-number-hint></p>
                        <x-input-error :messages="$errors->get('document_number')" />
                    </div>

                    <div class="uk-width-1-1">
                        <x-input-label value="Archivo (JPG, PNG o PDF, máx. 8 MB)" />

                        <label class="document-dropzone" data-dropzone for="document">
                            <span uk-icon="icon: cloud-upload; ratio: 1.6"></span>
                            <p data-dropzone-default class="uk-margin-small-top uk-margin-remove-bottom">
                                Arrastra el archivo aquí o haz clic para elegirlo
                            </p>
                            <p data-dropzone-filename hidden class="uk-margin-small-top uk-margin-remove-bottom uk-text-bold"></p>
                            <input id="document" name="document" type="file" accept=".jpg,.jpeg,.png,.pdf" required hidden>
                        </label>
                        <x-input-error :messages="$errors->get('document')" />
                    </div>
                </div>
            </fieldset>

            <div class="uk-flex uk-flex-right uk-margin-top">
                <x-primary-button>
                    <span uk-icon="icon: search" class="uk-margin-small-right"></span>
                    Analizar documento
                </x-primary-button>
            </div>
        </form>
    </div>

    {{-- Al elegir un archivo en el campo "document" de arriba, document-ocr.js abre
         este modal con la vista previa y, si es una imagen, intenta leer el nombre
         y apellidos con OCR (Tesseract.js, en el navegador). Los campos quedan
         editables porque el OCR sobre fotos de documentos físicos puede fallar. --}}
    <div id="document-ocr-modal" class="uk-modal-container" uk-modal="bg-close: false">
        <div class="uk-modal-dialog uk-modal-body document-preview-dialog">
            <button class="uk-modal-close-default" type="button" uk-close data-ocr-cancel></button>
            <h3 class="uk-modal-title">Verifica los datos del documento</h3>

            <div class="uk-grid-medium" uk-grid>
                <div class="uk-width-1-2@s document-ocr-preview">
                    <img data-ocr-preview-image alt="Vista previa del documento" hidden>
                    <iframe data-ocr-preview-pdf title="Vista previa del documento" hidden></iframe>
                </div>

                <div class="uk-width-1-2@s">
                    <div data-ocr-status class="uk-flex uk-flex-middle uk-margin-small-bottom" hidden>
                        <div uk-spinner="ratio: 0.7" class="uk-margin-small-right"></div>
                        <span data-ocr-status-text></span>
                    </div>

                    <x-input-label for="ocr_nombre" value="Nombre" />
                    <x-text-input id="ocr_nombre" type="text" class="uk-width-1-1 uk-margin-small-bottom" data-ocr-field="nombre" autocomplete="off" />

                    <x-input-label for="ocr_apellidos" value="Apellidos" />
                    <x-text-input id="ocr_apellidos" type="text" class="uk-width-1-1 uk-margin-small-bottom" data-ocr-field="apellidos" autocomplete="off" />

                    <x-input-label for="ocr_birth_date" value="Fecha de nacimiento" />
                    <x-text-input id="ocr_birth_date" type="date" class="uk-width-1-1" data-ocr-field="birth_date" />

                    <p class="uk-text-meta uk-margin-small-top">
                        Revisa y corrige los datos si el escaneo automático no fue preciso.
                    </p>
                </div>
            </div>

            <div class="uk-flex uk-flex-right uk-margin-top">
                <button type="button" class="uk-button uk-button-default uk-margin-small-right" data-ocr-cancel>Cancelar</button>
                <button type="button" class="uk-button uk-button-primary" data-ocr-confirm>Usar estos datos</button>
            </div>
        </div>
    </div>
</x-app-layout>
