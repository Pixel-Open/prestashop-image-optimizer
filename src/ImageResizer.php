<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\ImageOptimizer;

use GdImage;
use RuntimeException;

/**
 * Resize and convert images with GD, keeping the ratio and never upscaling.
 *
 * Resized files are written once in the cache directory: the file name contains a hash of the
 * source path and modification time, so replacing a source image produces new files.
 */
class ImageResizer
{
    /**
     * Output formats and the GD function able to write them
     */
    private const WRITERS = [
        'jpg' => 'imagejpeg',
        'jpeg' => 'imagejpeg',
        'png' => 'imagepng',
        'gif' => 'imagegif',
        'webp' => 'imagewebp',
        'avif' => 'imageavif',
    ];

    /**
     * Source formats and the GD function able to read them
     */
    private const READERS = [
        IMAGETYPE_JPEG => 'imagecreatefromjpeg',
        IMAGETYPE_PNG => 'imagecreatefrompng',
        IMAGETYPE_GIF => 'imagecreatefromgif',
        IMAGETYPE_WEBP => 'imagecreatefromwebp',
        IMAGETYPE_AVIF => 'imagecreatefromavif',
    ];

    /**
     * @param string $rootDir absolute path of the shop root directory
     * @param string $cacheDir cache directory, relative to the root directory
     */
    public function __construct(
        private readonly string $rootDir,
        private readonly string $cacheDir
    ) {
    }

    /**
     * Resize an image in several boxes, the source is decoded at most once
     *
     * @param string $filepath image path with the full absolute path
     * @param array<array-key, array{0: int, 1: int}> $boxes maximum width and height for each output (0 = no limit)
     * @param int $quality between 0 and 100 (only for jpg, webp and avif)
     * @param string|null $newName the new file name (null keeps the same file name)
     * @param string|null $toExt convert image to jpg, png, gif, webp or avif (null keeps the same extension)
     *
     * @return array<array-key, array{path: string, width: int, height: int}|null> the images data, with the boxes keys
     */
    public function resize(
        string $filepath,
        array $boxes,
        int $quality = 85,
        ?string $newName = null,
        ?string $toExt = null
    ): array {
        $results = array_fill_keys(array_keys($boxes), null);

        $size = is_file($filepath) ? @getimagesize($filepath) : false;
        if (!$size || !isset(self::READERS[$size[2]]) || !function_exists(self::READERS[$size[2]])) {
            return $results;
        }

        $info = pathinfo($filepath);
        $toExt = strtolower($toExt ?: ($info['extension'] ?? ''));
        if (!isset(self::WRITERS[$toExt]) || !function_exists(self::WRITERS[$toExt])) {
            return $results;
        }

        $directory = $this->getCacheDirectory();
        if ($directory === null) {
            return $results;
        }

        $orientation = $size[2] === IMAGETYPE_JPEG ? $this->getOrientation($filepath) : 1;
        [$origWidth, $origHeight] = $orientation >= 5 ? [$size[1], $size[0]] : [$size[0], $size[1]];

        $quality = max(0, min(100, $quality));
        $name = $newName !== null ? $this->formatKey($newName) : $info['filename'];
        $hash = substr(md5($filepath . '|' . filemtime($filepath)), 0, 8);

        $source = null;

        foreach ($boxes as $key => [$maxWidth, $maxHeight]) {
            [$width, $height] = $this->getDimensions($origWidth, $origHeight, $maxWidth, $maxHeight);

            $file = $name . '-' . $width . 'x' . $height . '-' . $quality . '-' . $hash . '.' . $toExt;
            $destination = $directory . DIRECTORY_SEPARATOR . $file;

            if (!is_file($destination)) {
                $source ??= $this->load($filepath, $size[2], $orientation);
                if (!$source || !$this->write($source, $destination, $width, $height, $toExt, $quality)) {
                    continue;
                }
            }

            $results[$key] = [
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $this->cacheDir) . '/' . $file,
                'width' => $width,
                'height' => $height,
            ];
        }

        return $results;
    }

    /**
     * Remove all resized images
     *
     * @return void
     */
    public function clear(): void
    {
        $directory = $this->rootDir . DIRECTORY_SEPARATOR . $this->cacheDir;

        if (is_dir($directory)) {
            $this->removeFiles($directory);
        }
    }

    /**
     * Compute the output dimensions, keeping the ratio and never upscaling
     *
     * @return int[]
     */
    private function getDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $ratio = 1;

        if ($maxWidth > 0 && $width > $maxWidth) {
            $ratio = min($ratio, $maxWidth / $width);
        }
        if ($maxHeight > 0 && $height > $maxHeight) {
            $ratio = min($ratio, $maxHeight / $height);
        }

        return [max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio))];
    }

    /**
     * Decode the source image and apply its EXIF orientation
     */
    private function load(string $filepath, int $type, int $orientation): ?GdImage
    {
        $image = @(self::READERS[$type])($filepath);
        if (!$image instanceof GdImage) {
            return null;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        $rotation = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };
        if ($rotation) {
            $image = imagerotate($image, $rotation, 0) ?: $image;
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    /**
     * Resample the source image and write it, through a temporary file to avoid serving a partial image
     */
    private function write(GdImage $source, string $destination, int $width, int $height, string $ext, int $quality): bool
    {
        $result = imagecreatetruecolor($width, $height);
        if (!$result) {
            return false;
        }

        if (in_array($ext, ['jpg', 'jpeg'], true)) {
            // JPEG has no alpha channel: transparent areas become white instead of black
            imagefill($result, 0, 0, (int) imagecolorallocate($result, 255, 255, 255));
            imagealphablending($result, true);
        } else {
            imagealphablending($result, false);
            imagesavealpha($result, true);
            imagefill($result, 0, 0, (int) imagecolorallocatealpha($result, 0, 0, 0, 127));
        }

        if (!imagecopyresampled($result, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source))) {
            return false;
        }

        $temporary = $destination . '.' . bin2hex(random_bytes(4)) . '.tmp';

        $written = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($result, $temporary, $quality),
            'webp' => imagewebp($result, $temporary, $quality),
            'avif' => imageavif($result, $temporary, $quality),
            'png' => imagepng($result, $temporary),
            'gif' => imagegif($result, $temporary),
        };

        if (!$written || !@rename($temporary, $destination)) {
            @unlink($temporary);

            return false;
        }

        return true;
    }

    /**
     * Retrieve the EXIF orientation of a JPEG image (1 when unknown)
     */
    private function getOrientation(string $filepath): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($filepath, 'IFD0');
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    /**
     * Create the cache directory if needed
     *
     * @return string|null the absolute path, null if it can not be written
     */
    private function getCacheDirectory(): ?string
    {
        $directory = $this->rootDir . DIRECTORY_SEPARATOR . $this->cacheDir;

        // The second is_dir() covers a directory created by a concurrent request
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }

        return is_writable($directory) ? $directory : null;
    }

    /**
     * Remove directory files recursively, keeping the directories
     */
    private function removeFiles(string $directory): void
    {
        $files = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $current = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($current) && !is_link($current)) {
                $this->removeFiles($current);
            } elseif (!unlink($current)) {
                throw new RuntimeException(sprintf('Unable to remove %s', $current));
            }
        }
    }

    /**
     * Format a file name
     */
    public function formatKey(string $value, string $replace = '-'): string
    {
        $string = trim($value, '/');
        $string = strtr($string, ['&amp;' => 'and', '@' => 'at', '©' => 'c', '®' => 'r', '™' => 'tm']);

        if (function_exists('transliterator_transliterate')) {
            $string = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
        } else {
            $string = (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        }

        $string = strtolower($string);
        $string = (string) preg_replace('#[^a-z0-9]+#', $replace, $string);

        return trim($string, $replace);
    }
}
