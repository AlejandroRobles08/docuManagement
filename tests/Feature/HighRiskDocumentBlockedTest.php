<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class HighRiskDocumentBlockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_page_hides_save_actions_for_a_high_risk_document(): void
    {
        $review = $this->analyzeHighRiskDocument();

        $review->assertOk();
        $review->assertSee('Riesgo alto');
        $review->assertSee('Documento rechazado por riesgo alto');
        $review->assertDontSee('Confirmar y guardar');
        $review->assertDontSee('Agregar este documento a');
    }

    public function test_confirm_rejects_a_high_risk_document_and_saves_nothing(): void
    {
        [$review, $token] = $this->analyzeHighRiskDocumentWithToken();

        $confirm = $this->post("/documentos/revisar/{$token}/confirmar");

        $confirm->assertRedirect(route('documents.create'));
        $confirm->assertSessionHas('error');
        // Solo debe seguir existiendo el documento original ya sembrado; el
        // intento de riesgo alto no debe haber agregado uno nuevo.
        $this->assertSame(1, Document::count());

        // La revisión ya no debe seguir disponible: forzar a subir un archivo nuevo.
        $reviewAgain = $this->get("/documentos/revisar/{$token}");
        $reviewAgain->assertRedirect(route('documents.create'));
    }

    /**
     * @return array{0: TestResponse, 1: string}
     */
    private function analyzeHighRiskDocumentWithToken(): array
    {
        $user = User::factory()->create();
        $existingPerson = Person::factory()->create(['full_name' => 'Persona Original']);
        $path = sys_get_temp_dir().'/'.uniqid('high_risk_test_').'.jpg';
        $image = imagecreatetruecolor(20, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 5, 15, 25));
        imagejpeg($image, $path, 90);
        imagedestroy($image);

        Document::factory()->for($existingPerson)->create([
            'file_hash' => hash_file('sha256', $path),
        ]);

        $copy = sys_get_temp_dir().'/'.uniqid('high_risk_test_copy_').'.jpg';
        copy($path, $copy);

        $analyze = $this->actingAs($user)->post('/documentos/analizar', [
            'full_name' => 'Persona Nueva Distinta',
            'document_type' => 'ine',
            'document_number' => 'DOC-HIGH-RISK',
            'document' => new UploadedFile($copy, 'ine.jpg', 'image/jpeg', null, true),
        ]);

        $token = basename((string) $analyze->headers->get('Location'));

        return [$this->get("/documentos/revisar/{$token}"), $token];
    }

    private function analyzeHighRiskDocument()
    {
        [$review] = $this->analyzeHighRiskDocumentWithToken();

        return $review;
    }
}
