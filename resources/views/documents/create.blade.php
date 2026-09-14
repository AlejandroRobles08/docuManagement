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
                    <div class="uk-width-1-1">
                        <x-input-label for="full_name" value="Nombre completo" />
                        <x-text-input id="full_name" name="full_name" type="text" class="uk-width-1-1" :value="old('full_name')" required autofocus />
                        <x-input-error :messages="$errors->get('full_name')" />
                    </div>

                    <div class="uk-width-1-2@s">
                        <x-input-label for="curp" value="CURP (opcional, si el documento la incluye)" />
                        <x-text-input id="curp" name="curp" type="text" class="uk-width-1-1" maxlength="18" style="text-transform:uppercase" :value="old('curp')" />
                        <x-input-error :messages="$errors->get('curp')" />
                    </div>

                    <div class="uk-width-1-2@s">
                        <x-input-label for="birth_date" value="Fecha de nacimiento (opcional)" />
                        <x-text-input id="birth_date" name="birth_date" type="date" class="uk-width-1-1" :value="old('birth_date')" />
                        <x-input-error :messages="$errors->get('birth_date')" />
                    </div>
                </div>
            </fieldset>

            <fieldset class="uk-fieldset uk-margin-top">
                <legend class="uk-legend">Documento</legend>

                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-2@s">
                        <x-input-label for="document_type" value="Tipo de documento" />
                        <select id="document_type" name="document_type" class="uk-select" required>
                            <option value="" disabled {{ old('document_type') ? '' : 'selected' }}>Selecciona una opción</option>
                            @foreach ($documentTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('document_type')" />
                    </div>

                    <div class="uk-width-1-2@s">
                        <x-input-label for="document_number" value="Número de documento" />
                        <x-text-input id="document_number" name="document_number" type="text" class="uk-width-1-1" :value="old('document_number')" required />
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
</x-app-layout>
