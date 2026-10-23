<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use GdImage;
use InvalidArgumentException;
use LogicException;
use Override;

/**
 * Re-encodes JPEG, PNG, GIF and WebP images with GD (`ext-gd`, not required by yuf): the image is decoded and
 * written again in its format, without metadata and without data after the image. Animated GIF and WebP keep their
 * first frame only, colour profiles are dropped. The dimensions are read from the header first (`getimagesize()`):
 * an image with more than `$maxPixels` pixels is rejected before it is decoded (decompression bomb).
 */
final readonly class GdImageReencoder implements ImageReencoder
{
    private const array MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @param int $maxPixels Width times height of the largest accepted image (40 megapixels by default, about 160 MB
     *                       of memory while it is decoded)
     * @param int $quality JPEG and WebP quality (0 to 100)
     *
     * @throws LogicException if `ext-gd` is not loaded
     * @throws InvalidArgumentException if a value is out of range
     */
    public function __construct(
        private int $maxPixels = 40_000_000,
        private int $quality = 90,
    ) {
        if (!extension_loaded(extension: 'gd')) {
            throw new LogicException(message: 'GdImageReencoder needs the PHP extension gd.');
        }
        if ($maxPixels < 1) {
            throw new InvalidArgumentException(message: 'The maximal number of pixels must be at least 1.');
        }
        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException(message: 'The quality must be between 0 and 100.');
        }
    }

    #[Override]
    public function supports(string $mimeType): bool
    {
        return in_array(needle: $mimeType, haystack: GdImageReencoder::MIME_TYPES, strict: true)
            && (imagetypes() & GdImageReencoder::toImgFlag(mimeType: $mimeType)) !== 0;
    }

    #[Override]
    public function reencode(string $path, string $mimeType): ?int
    {
        if (!$this->supports(mimeType: $mimeType)) {
            return null;
        }
        // A broken image makes GD and getimagesize() emit warnings; the result tells the failure
        set_error_handler(callback: static fn(): bool => true);
        try {
            $image = $this->decode(path: $path, mimeType: $mimeType);
            if ($image === null) {
                return null;
            }
            $directory = dirname(path: $path);
            // tempnam() creates the file with mode 0600 in the same directory, so rename() replaces atomically
            $temporaryPath = tempnam(directory: $directory, prefix: 'reencode');
            if ($temporaryPath === false || dirname(path: $temporaryPath) !== $directory) {
                return null;
            }
            if (!$this->encode(image: $image, path: $temporaryPath, mimeType: $mimeType)) {
                unlink(filename: $temporaryPath);

                return null;
            }
            if (!rename(from: $temporaryPath, to: $path)) {
                unlink(filename: $temporaryPath);

                return null;
            }
            clearstatcache(clear_realpath_cache: false, filename: $path);
            $size = filesize(filename: $path);

            return $size === false ? null : $size;
        } finally {
            restore_error_handler();
        }
    }

    private function decode(string $path, string $mimeType): ?GdImage
    {
        $info = getimagesize(filename: $path);
        if (
            $info === false
            || $info[2] !== GdImageReencoder::toImageType(mimeType: $mimeType)
            || $info[0] < 1
            || $info[1] < 1
            || $this->maxPixels < $info[0] * $info[1]
        ) {
            return null;
        }
        $image = match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg(filename: $path),
            'image/png' => imagecreatefrompng(filename: $path),
            'image/gif' => imagecreatefromgif(filename: $path),
            default => imagecreatefromwebp(filename: $path),
        };

        return $image === false ? null : $image;
    }

    private function encode(GdImage $image, string $path, string $mimeType): bool
    {
        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending(image: $image, enable: false);
            imagesavealpha(image: $image, enable: true);
        }

        return match ($mimeType) {
            'image/jpeg' => imagejpeg(image: $image, file: $path, quality: $this->quality),
            'image/png' => imagepng(image: $image, file: $path),
            'image/gif' => imagegif(image: $image, file: $path),
            default => imagewebp(image: $image, file: $path, quality: $this->quality),
        };
    }

    private static function toImageType(string $mimeType): int
    {
        return match ($mimeType) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/gif' => IMAGETYPE_GIF,
            default => IMAGETYPE_WEBP,
        };
    }

    private static function toImgFlag(string $mimeType): int
    {
        return match ($mimeType) {
            'image/jpeg' => IMG_JPG,
            'image/png' => IMG_PNG,
            'image/gif' => IMG_GIF,
            default => IMG_WEBP,
        };
    }
}
