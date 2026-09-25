<?php

namespace Tests\Unit\Services\Forgery\Checks;

use App\Services\Forgery\Checks\WatermarkDetectionCheck;
use GdImage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class WatermarkDetectionCheckTest extends TestCase
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

    public function test_flags_image_with_repeating_horizontal_stripes_as_watermarked(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        for ($y = 0; $y < self::SIZE; $y++) {
            $gray = (intdiv($y, 5) % 2 === 0) ? 230 : 60;
            $color = imagecolorallocate($image, $gray, $gray, $gray);
            imageline($image, 0, $y, self::SIZE - 1, $y, $color);
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(30, $finding->score);
    }

    public function test_flags_image_with_repeating_diagonal_tiles_as_watermarked(): void
    {
        // Aproxima el mosaico rotado de una marca de agua real (texto
        // repetido en diagonal, como "CONFIDENCIAL") con franjas diagonales
        // paralelas, sin depender de una fuente TrueType en el entorno de
        // pruebas.
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $white);
        $gray = imagecolorallocate($image, 210, 210, 210);

        for ($offset = -self::SIZE; $offset < self::SIZE * 2; $offset += 14) {
            imageline($image, $offset, 0, $offset - self::SIZE, self::SIZE, $gray);
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(30, $finding->score);
    }

    public function test_does_not_flag_random_noise_image(): void
    {
        mt_srand(1234);
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                $gray = mt_rand(0, 255);
                imagesetpixel($image, $x, $y, imagecolorallocate($image, $gray, $gray, $gray));
            }
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_does_not_flag_smooth_lighting_gradient_image(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        for ($x = 0; $x < self::SIZE; $x++) {
            $gray = (int) round(($x / (self::SIZE - 1)) * 255);
            $color = imagecolorallocate($image, $gray, $gray, $gray);
            imageline($image, $x, 0, $x, self::SIZE - 1, $color);
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_does_not_flag_busy_diagram_without_watermark(): void
    {
        // Un documento con muchos recuadros y bordes rectos (una tabla, un
        // diagrama) ya produce algo de energía alineada a los ejes por sí
        // solo: esta es la prueba de regresión para ese falso positivo.
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $white);

        mt_srand(42);
        $fill = imagecolorallocate($image, 200, 210, 235);
        $border = imagecolorallocate($image, 50, 50, 50);

        for ($i = 0; $i < 6; $i++) {
            $x0 = mt_rand(0, self::SIZE - 40);
            $y0 = mt_rand(0, self::SIZE - 30);
            imagefilledrectangle($image, $x0, $y0, $x0 + 35, $y0 + 25, $fill);
            imagerectangle($image, $x0, $y0, $x0 + 35, $y0 + 25, $border);
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->jpegUploadedFile($image), $this->context());

        $this->assertFalse($finding->triggered);
    }

    public function test_flags_png_with_repeating_semi_transparent_stripes_as_watermarked(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $opaque = imagecolorallocatealpha($image, 255, 255, 255, 0);
        $semiTransparent = imagecolorallocatealpha($image, 255, 255, 255, 90);

        for ($y = 0; $y < self::SIZE; $y++) {
            $color = (intdiv($y, 5) % 2 === 0) ? $opaque : $semiTransparent;
            imageline($image, 0, $y, self::SIZE - 1, $y, $color);
        }

        $finding = (new WatermarkDetectionCheck)->evaluate($this->pngUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(45, $finding->score);
    }

    public function test_flags_png_with_large_semi_transparent_area_that_is_not_periodic(): void
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        $opaque = imagecolorallocatealpha($image, 255, 255, 255, 0);
        imagefilledrectangle($image, 0, 0, self::SIZE - 1, self::SIZE - 1, $opaque);

        $semiTransparent = imagecolorallocatealpha($image, 200, 200, 200, 90);
        imagefilledrectangle($image, 15, 15, 90, 90, $semiTransparent);

        $finding = (new WatermarkDetectionCheck)->evaluate($this->pngUploadedFile($image), $this->context());

        $this->assertTrue($finding->triggered);
        $this->assertSame(20, $finding->score);
    }

    public function test_does_not_analyze_non_image_documents(): void
    {
        $path = sys_get_temp_dir().'/'.uniqid('watermark_test_').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $this->tempFiles[] = $path;

        $file = new UploadedFile($path, 'documento.pdf', 'application/pdf', null, true);

        $finding = (new WatermarkDetectionCheck)->evaluate($file, $this->context());

        $this->assertFalse($finding->triggered);
    }

    private function jpegUploadedFile(GdImage $image): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('watermark_test_').'.jpg';
        imagejpeg($image, $path, 100);
        imagedestroy($image);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'documento.jpg', 'image/jpeg', null, true);
    }

    private function pngUploadedFile(GdImage $image): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('watermark_test_').'.png';
        imagepng($image, $path);
        imagedestroy($image);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'documento.png', 'image/png', null, true);
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
