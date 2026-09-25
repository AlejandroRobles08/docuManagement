<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Enums\RiskLevel;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Person;
use App\Services\Forgery\ForgeryAnalyzer;
use App\Services\PersonMatch;
use App\Services\PersonMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentController extends Controller
{
    /**
     * Prefijo de sesión bajo el que se guarda cada subida pendiente de
     * confirmación, mientras el staff revisa el resultado del análisis.
     */
    private const string SESSION_PREFIX = 'pending_upload.';

    private const string TEMP_DISK = 'local';

    private const string TEMP_DIRECTORY = 'pending-uploads';

    public function __construct(
        private readonly ForgeryAnalyzer $analyzer,
        private readonly PersonMatcher $matcher,
    ) {}

    /**
     * Listado de personas registradas, con búsqueda simple.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $persons = Person::query()
            ->withCount('documents')
            ->with(['documents' => fn ($query) => $query->latest()->limit(1)])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('curp', 'like', "%{$search}%")
                        ->orWhereHas('documents', fn ($q) => $q->where('document_number', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', [
            'persons' => $persons,
            'search' => $search,
            'stats' => [
                'total_persons' => Person::count(),
                'total_documents' => Document::count(),
                'high_risk_documents' => Document::where('risk_level', RiskLevel::Alto->value)->count(),
            ],
        ]);
    }

    public function show(Person $person): View
    {
        $person->load(['documents' => fn ($query) => $query->latest()->with('uploader')]);

        return view('documents.show', [
            'person' => $person,
        ]);
    }

    public function create(): View
    {
        $selectedPerson = old('person_id') ? Person::find(old('person_id')) : null;
        $selectedPerson?->loadCount('documents')->load(['documents:id,person_id,risk_level']);

        return view('documents.create', [
            'documentTypes' => DocumentType::options(),
            'documentTypeHints' => collect(DocumentType::cases())
                ->mapWithKeys(fn (DocumentType $case) => [$case->value => $case->identifierHint()])
                ->all(),
            'selectedPerson' => $selectedPerson,
            'selectedPersonRiskBadges' => $selectedPerson ? $this->riskBadges($selectedPerson) : collect(),
        ]);
    }

    /**
     * Búsqueda en vivo de personas ya registradas, para el buscador del
     * formulario de subida (evita tener que volver a escribir los datos de
     * alguien que ya existe en la base de datos). Incluye cuántos documentos
     * tiene ya registrados y con qué nivel de riesgo, para que el staff lo
     * vea antes de decidir si le agrega un documento nuevo.
     */
    public function searchPersons(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            return response()->json([]);
        }

        $persons = Person::query()
            ->select(['id', 'full_name', 'curp', 'birth_date'])
            ->where(function ($query) use ($search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('curp', 'like', "%{$search}%");
            })
            ->withCount('documents')
            ->with(['documents:id,person_id,risk_level'])
            ->orderBy('full_name')
            ->limit(10)
            ->get();

        return response()->json($persons->map(fn (Person $person): array => [
            'id' => $person->id,
            'full_name' => $person->full_name,
            'curp' => $person->curp,
            'birth_date' => $person->birth_date?->format('Y-m-d'),
            'documents_count' => $person->documents_count,
            'risk_counts' => $this->riskCounts($person),
        ]));
    }

    /**
     * Paso 1: valida los datos, corre el análisis anti-falsificación y busca
     * duplicados, pero TODAVÍA no guarda nada en la base de datos. El
     * archivo se deja en almacenamiento privado temporal hasta el paso 2.
     */
    public function analyze(StoreDocumentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $file = $request->file('document');

        $selectedPerson = isset($data['person_id']) ? Person::find($data['person_id']) : null;

        if ($selectedPerson) {
            // La persona ya se eligió en el buscador: no hace falta adivinar
            // si es un duplicado, ya sabemos exactamente a quién pertenece.
            $personData = [
                'full_name' => $selectedPerson->full_name,
                'curp' => $selectedPerson->curp,
                'birth_date' => $selectedPerson->birth_date?->format('Y-m-d'),
            ];
            $duplicate = new PersonMatch(
                $selectedPerson,
                'selected',
                "Este documento se agregará a la persona seleccionada: \"{$selectedPerson->full_name}\".",
            );
        } else {
            $personData = [
                'full_name' => $data['full_name'],
                'curp' => $data['curp'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
            ];
            $duplicate = $this->matcher->findDuplicate($data);
        }

        $report = $this->analyzer->analyze($file, $data['document_type'], $data['document_number'], $duplicate?->person->id);

        $token = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $tempPath = $file->storeAs(self::TEMP_DIRECTORY, "{$token}.{$extension}", self::TEMP_DISK);

        $request->session()->put(self::SESSION_PREFIX.$token, [
            'form' => [
                ...$personData,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'],
            ],
            'file' => [
                'temp_path' => $tempPath,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ],
            'report' => $report->toArray(),
            'duplicate' => $duplicate ? [
                'person_id' => $duplicate->person->id,
                'matched_by' => $duplicate->matchedBy,
                'message' => $duplicate->message,
            ] : null,
            'created_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('documents.review', $token);
    }

    public function review(Request $request, string $token): RedirectResponse|View
    {
        $pending = $request->session()->get(self::SESSION_PREFIX.$token);

        if (! $pending) {
            return redirect()->route('documents.create')
                ->with('error', 'Esa revisión ya no está disponible (pudo haber expirado). Vuelve a subir el documento.');
        }

        $duplicatePerson = isset($pending['duplicate']['person_id'])
            ? Person::find($pending['duplicate']['person_id'])
            : null;

        return view('documents.review', [
            'token' => $token,
            'form' => $pending['form'],
            'file' => $pending['file'],
            'report' => $pending['report'],
            'riskLevel' => RiskLevel::from($pending['report']['level']),
            'duplicate' => $pending['duplicate'],
            'duplicatePerson' => $duplicatePerson,
            'documentTypeLabel' => DocumentType::from($pending['form']['document_type'])->label(),
        ]);
    }

    /**
     * Paso 2: confirma y persiste. Si en el paso 1 se detectó que la persona
     * ya existe, esta acción SOLO permite adjuntar el documento a esa
     * persona existente (nunca crea silenciosamente un registro duplicado).
     */
    public function confirm(Request $request, string $token): RedirectResponse
    {
        $sessionKey = self::SESSION_PREFIX.$token;
        $pending = $request->session()->get($sessionKey);

        if (! $pending) {
            return redirect()->route('documents.create')
                ->with('error', 'Esa revisión ya no está disponible (pudo haber expirado). Vuelve a subir el documento.');
        }

        $riskLevel = RiskLevel::from($pending['report']['level']);

        if ($riskLevel->blocksUpload()) {
            Storage::disk(self::TEMP_DISK)->delete($pending['file']['temp_path']);
            $request->session()->forget($sessionKey);

            return redirect()->route('documents.create')
                ->with('error', 'Este documento tiene un nivel de riesgo alto y no se puede guardar. Sube un documento distinto para continuar.');
        }

        if ($pending['duplicate'] && (int) $request->input('attach_to_person_id') !== $pending['duplicate']['person_id']) {
            return back()->with('error', 'Esta persona ya está registrada. Confirma si deseas agregar el documento a su registro existente.');
        }

        if (! Storage::disk(self::TEMP_DISK)->exists($pending['file']['temp_path'])) {
            return redirect()->route('documents.create')
                ->with('error', 'El archivo temporal ya no existe. Vuelve a subir el documento.');
        }

        $person = $pending['duplicate']
            ? Person::findOrFail($pending['duplicate']['person_id'])
            : Person::create([
                'full_name' => $pending['form']['full_name'],
                'curp' => $pending['form']['curp'],
                'birth_date' => $pending['form']['birth_date'],
            ]);

        $publicPath = $this->moveToPublicStorage($pending['file']['temp_path'], $person->id);

        Document::create([
            'person_id' => $person->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => $pending['form']['document_type'],
            'document_number' => $pending['form']['document_number'],
            'disk' => 'public',
            'file_path' => $publicPath,
            'original_filename' => $pending['file']['original_filename'],
            'mime_type' => $pending['file']['mime_type'],
            'file_size' => $pending['file']['size'],
            'file_hash' => $pending['report']['file_hash'],
            'risk_score' => $pending['report']['score'],
            'risk_level' => $riskLevel->value,
            'risk_reasons' => $pending['report']['reasons'],
        ]);

        $request->session()->forget($sessionKey);

        $message = $pending['duplicate']
            ? "Se agregó el nuevo documento a la persona existente \"{$person->full_name}\"."
            : "Se registró a \"{$person->full_name}\" correctamente.";

        return redirect()->route('documents.show', $person)->with('status', $message);
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        $sessionKey = self::SESSION_PREFIX.$token;
        $pending = $request->session()->get($sessionKey);

        if ($pending) {
            Storage::disk(self::TEMP_DISK)->delete($pending['file']['temp_path']);
            $request->session()->forget($sessionKey);
        }

        return redirect()->route('documents.create');
    }

    /**
     * @return array<string, int> conteo de documentos por nivel de riesgo
     *                            (bajo/medio/alto), incluidos los niveles
     *                            en cero. Requiere $person->documents
     *                            precargada (evita N+1).
     */
    private function riskCounts(Person $person): array
    {
        $counts = $person->documents->countBy(fn (Document $document): string => $document->risk_level->value);

        return collect(RiskLevel::cases())
            ->mapWithKeys(fn (RiskLevel $level) => [$level->value => $counts->get($level->value, 0)])
            ->all();
    }

    /**
     * @return Collection<int, array{level: RiskLevel, count: int}> solo los
     *                                                              niveles con al menos un documento, listos para pintar en la vista.
     */
    private function riskBadges(Person $person): Collection
    {
        $counts = $this->riskCounts($person);

        return collect(RiskLevel::cases())
            ->map(fn (RiskLevel $level): array => ['level' => $level, 'count' => $counts[$level->value]])
            ->filter(fn (array $badge): bool => $badge['count'] > 0)
            ->values();
    }

    private function moveToPublicStorage(string $tempPath, int $personId): string
    {
        $extension = pathinfo($tempPath, PATHINFO_EXTENSION);
        $publicPath = "documentos/{$personId}/".Str::uuid().($extension ? ".{$extension}" : '');

        $stream = Storage::disk(self::TEMP_DISK)->readStream($tempPath);
        Storage::disk('public')->put($publicPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        Storage::disk(self::TEMP_DISK)->delete($tempPath);

        return $publicPath;
    }
}
