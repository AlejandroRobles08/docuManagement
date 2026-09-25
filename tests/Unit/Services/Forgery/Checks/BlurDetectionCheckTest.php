<?php

namespace Tests\Unit\Services\Forgery\Checks;

use App\Services\Forgery\Checks\BlurDetectionCheck;
use GdImage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class BlurDetectionCheckTest extends TestCase
{
    private const int SIZE = 128;

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

    public function test_does_not_flag_a_sharp_detailed_image(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $white);

        // Tablero de ajedrez de celdas chicas: muchísimos bordes nítidos,
        // como el texto y las líneas de un documento real.
        for ($y = 0; $y < self::SIZE; $y += 4) {
            for ($x = 0; $x < self::SIZE; $x += 4) {
                if ((intdiv($x, 4) + intdiv($y, 4)) % 2 === 0) {
                    imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $black);
                }
            }
        }

        $finding = (new BlurDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_flags_a_heavily_blurred_version_of_the_same_image_as_medium_risk(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $white);

        for ($y = 0; $y < self::SIZE; $y += 4) {
            for ($x = 0; $x < self::SIZE; $x += 4) {
                if ((intdiv($x, 4) + intdiv($y, 4)) % 2 === 0) {
                    imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $black);
                }
            }
        }

        for ($i = 0; $i < 25; $i++) {
            imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        }

        $finding = (new BlurDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(50, $finding->score);
    }

    public function test_flags_a_flat_solid_color_image_as_blurry(): void
    {
        // Caso límite conocido: un color plano no tiene NINGÚN borde, así
        // que es indistinguible de una foto borrosa para esta heurística.
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 120, 120));

        $finding = (new BlurDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(50, $finding->score);
    }

    public function test_does_not_analyze_non_image_documents(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('blur_test_').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $this->tempFiles[] = $path;

        $file = new UploadedFile($path, 'documento.pdf', 'application/pdf', null, true);

        $finding = (new BlurDetectionCheck)->evaluate($file, $this->context());

        $this->assertFalse($finding->triggered);
    }

    private function jpegUploadedFile(GdImage $image): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('blur_test_').'.jpg';
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
