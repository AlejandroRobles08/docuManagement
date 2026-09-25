<?php

namespace App\Services\Forgery;

use App\Enums\RiskLevel;
use App\Services\Forgery\Checks\BlurDetectionCheck;
use App\Services\Forgery\Checks\DuplicateFileHashCheck;
use App\Services\Forgery\Checks\ErrorLevelAnalysisCheck;
use App\Services\Forgery\Checks\ExifMetadataCheck;
use App\Services\Forgery\Checks\FileIntegrityCheck;
use App\Services\Forgery\Checks\ScratchMarkCheck;
use App\Services\Forgery\Checks\WatermarkDetectionCheck;
use App\Services\Forgery\Contracts\ForgeryCheck;
use Illuminate\Http\UploadedFile;

/**
 * Corre todas las heurísticas anti-falsificación disponibles sobre un
 * archivo recién subido y combina sus resultados en una única puntuación
 * de 0 a 100.
 *
 * Ninguna heurística por sí sola es concluyente: son señales locales,
 * pensadas para apoyar -no reemplazar- la revisión de un miembro del staff.
 */
class ForgeryAnalyzer
{
    /**
     * @var list<ForgeryCheck>
     */
    private array $checks;

    public function __construct(?array $checks = null)
    {
        $this->checks = $checks ?? [
            new FileIntegrityCheck,
            new DuplicateFileHashCheck,
            new ExifMetadataCheck,
            new ErrorLevelAnalysisCheck,
            new WatermarkDetectionCheck,
            new BlurDetectionCheck,
            new ScratchMarkCheck,
        ];
    }

    public function analyze(UploadedFile $file, string $documentType, string $documentNumber, ?int $personId = null): ForgeryReport
    {
        $fileHash = hash_file('sha256', $file->getRealPath());

        $context = [
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'file_hash' => $fileHash,
            // La persona a la que quedará asociado este documento si se
            // confirma: la seleccionada explícitamente, o la que ya
            // coincidió por CURP/nombre. Null si esta subida creará una
            // persona nueva.
            'person_id' => $personId,
        ];

        $score = 0;
        $reasons = [];

        foreach ($this->checks as $check) {
            $finding = $check->evaluate($file, $context);

            if ($finding->triggered) {
                $score += $finding->score;
                $reasons[] = $finding->reason;
            }
        }

        $score = min(100, $score);

        return new ForgeryReport(
            score: $score,
            level: RiskLevel::fromScore($score),
            reasons: $reasons,
            fileHash: $fileHash,
        );
    }
}
