<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use actra\yuf\form\upload\GdImageReencoder;
use GdImage;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Re-encoding with GD: data appended to the image is dropped, the format and the permissions stay, broken and too large
 * images are rejected before they are written.
 */
#[RequiresPhpExtension('gd')]
final class GdImageReencoderTest extends TestCase
{
    private const string PAYLOAD = '<?php system($_GET["c"]); ?>';

    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-reencode-'
            . bin2hex(string: random_bytes(length: 8));
        mkdir(directory: $this->directory, permissions: 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = scandir(directory: $this->directory);
        foreach ($files === false ? [] : $files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink(filename: $this->directory . DIRECTORY_SEPARATOR . $file);
            }
        }
        rmdir(directory: $this->directory);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formats(): array
    {
        return [
            'JPEG' => ['image/jpeg'],
            'PNG' => ['image/png'],
            'GIF' => ['image/gif'],
            'WebP' => ['image/webp'],
        ];
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function createImage(string $mimeType, int $width = 20, int $height = 10, string $appended = ''): string
    {
        $image = imagecreatetruecolor(width: $width, height: $height);
        $this->assertInstanceOf(GdImage::class, $image);
        imagefill(image: $image, x: 0, y: 0, color: (int) imagecolorallocate(image: $image, red: 200, green: 10, blue: 10));
        $path = $this->directory . DIRECTORY_SEPARATOR . 'upload';
        match ($mimeType) {
            'image/jpeg' => imagejpeg(image: $image, file: $path),
            'image/png' => imagepng(image: $image, file: $path),
            'image/gif' => imagegif(image: $image, file: $path),
            default => imagewebp(image: $image, file: $path),
        };
        file_put_contents(filename: $path, data: $appended, flags: FILE_APPEND);
        chmod(filename: $path, permissions: 0o600);

        return $path;
    }

    #[DataProvider('formats')]
    public function testAppendedDataIsDroppedAndTheFormatStays(string $mimeType): void
    {
        $reencoder = new GdImageReencoder();
        if (!$reencoder->supports(mimeType: $mimeType)) {
            GdImageReencoderTest::markTestSkipped('GD of this PHP cannot write ' . $mimeType . '.');
        }
        $path = $this->createImage(mimeType: $mimeType, appended: GdImageReencoderTest::PAYLOAD);

        $size = $reencoder->reencode(path: $path, mimeType: $mimeType);

        clearstatcache();
        $this->assertSame(filesize(filename: $path), $size);
        $this->assertStringNotContainsString('system(', (string) file_get_contents(filename: $path));
        $info = getimagesize(filename: $path);
        $this->assertNotFalse($info);
        $this->assertSame($mimeType, $info['mime']);
        $this->assertSame([20, 10], [$info[0], $info[1]]);
        $this->assertSame(0o600, fileperms(filename: $path) & 0o777);
        $this->assertSame(['.', '..', 'upload'], scandir(directory: $this->directory));
    }

    public function testImageWithTooManyPixelsIsRejectedBeforeDecoding(): void
    {
        $path = $this->createImage(mimeType: 'image/png', width: 100, height: 100);
        $before = file_get_contents(filename: $path);

        $this->assertNull(new GdImageReencoder(maxPixels: 9999)->reencode(path: $path, mimeType: 'image/png'));
        $this->assertSame($before, file_get_contents(filename: $path));
    }

    public function testContentOfAnotherTypeIsRejected(): void
    {
        $path = $this->createImage(mimeType: 'image/png');

        $this->assertNull(new GdImageReencoder()->reencode(path: $path, mimeType: 'image/jpeg'));
    }

    public function testBrokenImageIsRejected(): void
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'upload';
        file_put_contents(filename: $path, data: "\xff\xd8\xff\xe0" . GdImageReencoderTest::PAYLOAD);

        $this->assertNull(new GdImageReencoder()->reencode(path: $path, mimeType: 'image/jpeg'));
        $this->assertSame(['.', '..', 'upload'], scandir(directory: $this->directory));
    }

    public function testOtherTypesAreNotSupported(): void
    {
        $reencoder = new GdImageReencoder();

        $this->assertFalse($reencoder->supports(mimeType: 'application/pdf'));
        $this->assertFalse($reencoder->supports(mimeType: 'image/svg+xml'));
        $this->assertNull($reencoder->reencode(path: $this->directory . '/missing', mimeType: 'application/pdf'));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidSettings(): array
    {
        return ['no pixels' => [0, 90], 'quality too low' => [100, -1], 'quality too high' => [100, 101]];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsAreRefused(int $maxPixels, int $quality): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GdImageReencoder(maxPixels: $maxPixels, quality: $quality);
    }
}
