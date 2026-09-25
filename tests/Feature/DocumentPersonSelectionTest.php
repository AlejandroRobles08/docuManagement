<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentPersonSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_persons_finds_matches_by_full_name(): void
    {
        $user = User::factory()->create();
        $match = Person::factory()->create(['full_name' => 'Juana Pérez López']);
        Person::factory()->create(['full_name' => 'Alguien Distinto']);

        $response = $this->actingAs($user)->get('/documentos/personas/buscar?q=Juana');

        $response->assertOk();
        $response->assertJson([
            ['id' => $match->id, 'full_name' => 'Juana Pérez López'],
        ]);
        $response->assertJsonCount(1);
    }

    public function test_search_persons_finds_matches_by_curp(): void
    {
        $user = User::factory()->create();
        $match = Person::factory()->create(['curp' => 'ABCD800101HDFRRN09']);

        $response = $this->actingAs($user)->get('/documentos/personas/buscar?q=ABCD800101');

        $response->assertOk();
        $response->assertJson([
            ['id' => $match->id],
        ]);
    }

    public function test_search_persons_returns_empty_for_blank_query(): void
    {
        $user = User::factory()->create();
        Person::factory()->create();

        $response = $this->actingAs($user)->get('/documentos/personas/buscar?q=');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_analyze_accepts_selected_person_without_full_name(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create(['full_name' => 'Carlos Ramírez']);

        $response = $this->actingAs($user)->post('/documentos/analizar', [
            'person_id' => $person->id,
            'document_type' => 'ine',
            'document_number' => 'DOC123',
            'document' => UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $token = basename((string) $response->headers->get('Location'));
        $review = $this->get("/documentos/revisar/{$token}");

        $review->assertOk();
        $review->assertSee('Carlos Ramírez');
        $review->assertSee('Persona seleccionada');
    }

    public function test_analyze_without_person_or_full_name_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/documentos/analizar', [
            'document_type' => 'ine',
            'document_number' => 'DOC123',
            'document' => UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('full_name');
    }

    public function test_confirming_a_selected_person_attaches_the_document_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->create();

        $analyze = $this->actingAs($user)->post('/documentos/analizar', [
            'person_id' => $person->id,
            'document_type' => 'ine',
            'document_number' => 'DOC123',
            'document' => UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf'),
        ]);

        $token = basename((string) $analyze->headers->get('Location'));

        $confirm = $this->post("/documentos/revisar/{$token}/confirmar", [
            'attach_to_person_id' => $person->id,
        ]);

        $confirm->assertRedirect(route('documents.show', $person));
        $this->assertSame(1, Person::count());
        $this->assertSame(1, Document::where('person_id', $person->id)->count());
    }
}
