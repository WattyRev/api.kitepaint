<?php

use PHPUnit\Framework\TestCase;

class DesignImageTest extends TestCase
{
    // A 1x1 PNG.
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    // A 1x1 GIF, which must be rejected.
    private const GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function png(): string
    {
        return base64_decode(self::PNG_BASE64);
    }

    public function testAcceptsPng(): void
    {
        $this->assertTrue(is_valid_design_png($this->png()));
    }

    public function testRejectsGif(): void
    {
        $this->assertFalse(is_valid_design_png(base64_decode(self::GIF_BASE64)));
    }

    public function testRejectsNonImageData(): void
    {
        $this->assertFalse(is_valid_design_png('this is not an image'));
    }

    public function testRejectsEmptyAndNonStringData(): void
    {
        $this->assertFalse(is_valid_design_png(''));
        $this->assertFalse(is_valid_design_png(false));
        $this->assertFalse(is_valid_design_png(null));
    }

    public function testRejectsImagesOverTheSizeLimit(): void
    {
        $oversized = $this->png() . str_repeat('A', KP_MAX_DESIGN_IMAGE_BYTES);
        $this->assertFalse(is_valid_design_png($oversized));
    }

    public function testPrepareAcceptsDataUrlPrefix(): void
    {
        $json = json_encode(['main' => 'data:image/png;base64,' . self::PNG_BASE64]);
        $result = prepare_design_images($json);
        $this->assertTrue($result['valid']);
        $this->assertSame($this->png(), $result['images']['main']);
    }

    public function testPrepareRejectsTheWholeRequestIfOneImageIsBad(): void
    {
        $json = json_encode([
            'main' => self::PNG_BASE64,
            'thumb' => base64_encode('not an image'),
        ]);
        $result = prepare_design_images($json);
        $this->assertFalse($result['valid']);
        $this->assertArrayNotHasKey('images', $result);
    }

    public function testPrepareRejectsMalformedInput(): void
    {
        $this->assertFalse(prepare_design_images('not json')['valid']);
        $this->assertFalse(prepare_design_images(['not', 'a', 'string'])['valid']);
        $this->assertFalse(prepare_design_images(json_encode(['main' => 123]))['valid']);
    }

    public function testWriteStoresFilesAndReturnsPaths(): void
    {
        $dir = sys_get_temp_dir() . '/kpimages-' . uniqid();
        mkdir($dir);
        $this->tempDirs[] = $dir;

        $paths = write_design_images(42, ['main' => $this->png()], $dir . '/');

        $this->assertIsArray($paths);
        $this->assertStringEndsWith('42main.png', $paths['main']);
        $this->assertSame($this->png(), file_get_contents($paths['main']));
    }
}
