<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DuplicateDocumentFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_the_same_file_for_the_same_person_is_flagged_as_low_risk(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create();
        $path = $this->fixedContentFile();

        Document::factory()->for($person)->create([
            'file_hash' => hash_file('sha256', $path),
        ]);

        $response = $this->actingAs($user)->post('/documentos/analizar', [
            'person_id' => $person->id,
            'document_type' => 'ine',
            'document_number' => 'DOC-SAME-PERSON',
            'document' => new UploadedFile($path, 'ine.jpg', 'image/jpeg', null, true),
        ]);

        $response->assertRedirect();
        $token = basename((string) $response->headers->get('Location'));

        $review = $this->get("/documentos/revisar/{$token}");

        $review->assertOk();
        $review->assertSee('ya se había subido antes para esta misma persona');
        $review->assertSee('Riesgo bajo');
    }

    public function test_reusing_the_same_file_for_a_different_person_is_flagged_as_high_risk(): void
    {
        $user = User::factory()->create();
        $existingPerson = Person::factory()->create(['full_name' => 'Persona Original']);
        $path = $this->fixedContentFile();

        Document::factory()->for($existingPerson)->create([
            'file_hash' => hash_file('sha256', $path),
        ]);

        $response = $this->actingAs($user)->post('/documentos/analizar', [
            'full_name' => 'Persona Nueva Distinta',
            'document_type' => 'ine',
            'document_number' => 'DOC-OTHER-PERSON',
            'document' => new UploadedFile($this->duplicateFile($path), 'ine.jpg', 'image/jpeg', null, true),
        ]);

        $response->assertRedirect();
        $token = basename((string) $response->headers->get('Location'));

        $review = $this->get("/documentos/revisar/{$token}");

        $review->assertOk();
        $review->assertSee('Persona Original');
        $review->assertSee('Riesgo alto');
    }

    private function fixedContentFile(): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('duplicate_test_').'.jpg';
        $image = imagecreatetruecolor(64, 64);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 250, 250));

        // Rayas con bordes marcados, no un color plano: BlurDetectionCheck
        // marca como "borrosa" cualquier imagen sin ningún borde nítido, y
        // un color sólido no tiene ninguno.
        $stripe = imagecolorallocate($image, 10, 20, 30);
        for ($x = 0; $x < 64; $x += 8) {
            imagefilledrectangle($image, $x, 0, $x + 3, 63, $stripe);
        }

        imagejpeg($image, $path, 90);
        imagedestroy($image);

        return $path;
    }

    private function duplicateFile(string $path): string
    {
        $copy = sys_get_temp_dir().'/'.uniqid('duplicate_test_copy_').'.jpg';
        copy($path, $copy);

        return $copy;
    }
}
