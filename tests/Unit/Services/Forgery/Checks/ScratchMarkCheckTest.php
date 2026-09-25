<?php

namespace Tests\Unit\Services\Forgery\Checks;

use App\Services\Forgery\Checks\ScratchMarkCheck;
use GdImage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class ScratchMarkCheckTest extends TestCase
{
    private const int SIZE = 256;

    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_flags_a_red_line_drawn_over_the_document(): void
    {
        $image = $this->documentLikeImage();

        $red = imagecolorallocate($image, 220, 20, 20);
        imagesetthickness($image, 6);
        imageline($image, 20, 30, self::SIZE - 20, self::SIZE - 30, $red);

        $finding = (new ScratchMarkCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(40, $finding->score);
    }

    public function test_flags_a_green_scribble_regardless_of_color(): void
    {
        $image = $this->documentLikeImage();

        $green = imagecolorallocate($image, 20, 200, 40);
        imagesetthickness($image, 5);
        imageline($image, 30, 40, 180, 90, $green);
        imageline($image, 180, 90, 60, 160, $green);
        imageline($image, 60, 160, 200, 200, $green);

        $finding = (new ScratchMarkCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
    }

    public function test_does_not_flag_a_plain_document_without_any_mark(): void
    {
        $finding = (new ScratchMarkCheck)->evaluate($this->jpegUploadedFile($this->documentLikeImage()), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_does_not_flag_a_natural_looking_portrait_photo(): void
    {
        // Simula una foto de credencial: tonos de piel (moderadamente
        // saturados) más un pequeño detalle de color (p. ej. un escudo),
        // pero nada que llegue a la saturación "de pincel" de un rayón.
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $skin = imagecolorallocate($image, 224, 172, 140);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $skin);

        $eye = imagecolorallocate($image, 90, 60, 40);
        imagefilledellipse($image, 100, 100, 12, 8, $eye);
        imagefilledellipse($image, 160, 100, 12, 8, $eye);

        $emblem = imagecolorallocate($image, 150, 40, 40);
        imagefilledrectangle($image, 10, 10, 20, 20, $emblem);

        $finding = (new ScratchMarkCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_does_not_flag_a_fully_saturated_color_photo(): void
    {
        // Cobertura casi total de color vivo: es una foto a color normal,
        // no una marca puntual.
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $vivid = imagecolorallocate($image, 200, 30, 30);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $vivid);

        $finding = (new ScratchMarkCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_does_not_analyze_non_image_documents(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('scratch_test_').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $this->tempFiles[] = $path;

        $file = new UploadedFile($path, 'documento.pdf', 'application/pdf', null, true);

        $finding = (new ScratchMarkCheck)->evaluate($file, $this->context());

        $this->assertFalse($finding->triggered);
    }

    /**
     * Fondo de bajo contraste en grises con algo de textura, parecido a un
     * documento escaneado: sin color vivo alguno.
     */
    private function documentLikeImage(): GdImage
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($image, 250, 250, 250);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $white);

        $gray = imagecolorallocate($image, 60, 60, 60);
        for ($y = 20; $y < self::SIZE - 20; $y += 20) {
            imageline($image, 20, $y, self::SIZE - 20, $y, $gray);
        }

        return $image;
    }

    private function jpegUploadedFile(GdImage $image): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('scratch_test_').'.jpg';
        imagejpeg($image, $path, 100);
        imagedestroy($image);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'documento.jpg', 'image/jpeg', null, true);
    }

    /**
     * @return array{document_type: string, document_number: string, file_hash: string, person_id: int|null}
     */
    private function context(): array
    {
        return [
            'document_type' => 'ine',
            'document_number' => 'TEST-1',
            'file_hash' => 'irrelevant-for-this-check',
            'person_id' => null,
        ];
    }
}
