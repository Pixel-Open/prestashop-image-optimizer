<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Pixel\Module\ImageOptimizer\ImageResizer;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Pixel_image_optimizer extends Module implements WidgetInterface
{
    public const CACHE_IMAGE_PATH = 'img' . DIRECTORY_SEPARATOR . 'web';

    public const DEFAULT_QUALITY = 85;

    protected $templateFile;

    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_image_optimizer';
        $this->version = '2.0.0';
        $this->author = 'Pixel Open';
        $this->tab = 'front_office_features';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans(
            'Image Optimizer',
            [],
            'Modules.Pixelimageoptimizer.Admin'
        );
        $this->description = $this->trans(
            'Image optimizer module is an easy way to resize and compress images on the fly. Use responsive images with size alternatives.',
            [],
            'Modules.Pixelimageoptimizer.Admin'
        );
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];

        $this->templateFile = 'module:' . $this->name . '/image.tpl';
    }

    /**
     * Install the module
     *
     * @return bool
     */
    public function install(): bool
    {
        return parent::install() && $this->registerHook('displayDashboardToolbarTopMenu');
    }

    /**
     * Use the new translation system
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    /**
     * Retrieve the image resizer
     *
     * @return ImageResizer
     */
    public function getResizer(): ImageResizer
    {
        return new ImageResizer(_PS_ROOT_DIR_, self::CACHE_IMAGE_PATH);
    }

    /***********/
    /** HOOKS **/
    /***********/

    /**
     * Add toolbar buttons
     *
     * @param mixed[] $params
     *
     * @return string
     * @throws Exception
     */
    public function hookDisplayDashboardToolbarTopMenu(array $params): string
    {
        $controller = $this->context->controller;
        $allowed = $controller->controller_type === 'admin' && $controller->php_self === 'AdminPerformance';

        if (!$allowed) {
            return '';
        }

        $buttons = [
            [
                'label' => $this->trans('Clear Image Cache', [], 'Modules.Pixelimageoptimizer.Admin'),
                'route' => 'admin_image_optimizer_clear_cache',
                'class' => 'btn btn-info',
                'icon'  => 'delete'
            ]
        ];

        // Module::getTwig() replaces Module::get('twig') since PrestaShop 9
        $twig = method_exists($this, 'getTwig') ? $this->getTwig() : $this->get('twig');

        return $twig->render('@Modules/' . $this->name . '/views/templates/admin/toolbar.html.twig', [
            'buttons' => $buttons,
        ]);
    }

    /*********************/
    /** FRONTEND WIDGET **/
    /*********************/

    /**
     * Render the widget
     *
     * @param string|null $hookName
     * @param mixed[] $configuration
     *
     * @return string
     */
    public function renderWidget($hookName, array $configuration): string
    {
        $template = $configuration['template'] ?? $this->templateFile;

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch($template);
    }

    /**
     * Retrieve the widget variables
     *
     * @param string|null $hookName
     * @param mixed[] $configuration
     *
     * @return mixed[]
     */
    public function getWidgetVariables($hookName, array $configuration): array
    {
        $config = $this->getImageConfig($configuration);
        $imagePath = $this->getImagePath($configuration);

        $image = null;
        $sources = [];

        if ($imagePath) {
            $boxes = ['main' => [$config['width'], $config['height']]];
            foreach ($config['breakpoints'] as $width) {
                // A breakpoint wider than the main image would only give a heavier duplicate
                if (!$config['width'] || $width < $config['width']) {
                    $boxes[$width] = [$width, $config['height']];
                }
            }

            $images = $this->getResizer()->resize(
                $imagePath,
                $boxes,
                $config['quality'],
                $config['image_name'],
                $config['ext']
            );

            $image = $images['main'];
            unset($images['main']);

            if ($image) {
                // Breakpoints wider than the image give the same file: keep one source per width
                foreach (array_filter($images) as $source) {
                    $sources[$source['width']] = $source;
                }
                unset($sources[$image['width']]);
                ksort($sources);
            } else {
                // The image can not be resized (unsupported format, unwritable cache...): serve the original
                $image = $this->getOriginalImage($imagePath);
            }
        }

        if ($image) {
            $image['url'] = $this->getImageUrl($image['path']);
        }

        $srcset = [];
        foreach ($sources as $width => $source) {
            $sources[$width]['url'] = $this->getImageUrl($source['path']);
            $srcset[$width] = $sources[$width]['url'] . ' ' . $width . 'w';
        }
        if ($srcset) {
            $srcset[$image['width']] = $image['url'] . ' ' . $image['width'] . 'w';
            ksort($srcset);
        }

        $fetchPriority = $configuration['fetchpriority'] ?? '';

        return [
            'image'         => $image,
            'sources'       => $sources,
            'srcset'        => implode(', ', $srcset),
            'sizes'         => $configuration['sizes'] ?? '100vw',
            'class'         => $configuration['class'] ?? '',
            'alt'           => $configuration['alt'] ?? '',
            'loading'       => ($configuration['loading'] ?? 'lazy') === 'eager' ? 'eager' : 'lazy',
            'fetchpriority' => in_array($fetchPriority, ['high', 'low', 'auto'], true) ? $fetchPriority : '',
        ];
    }

    /**
     * Retrieve image configuration
     *
     * @param mixed[] $configuration
     *
     * @return mixed[]
     */
    public function getImageConfig(array $configuration): array
    {
        $breakpoints = array_map('intval', explode(',', (string)($configuration['breakpoints'] ?? '')));

        $imageName = isset($configuration['image_name']) ? (string)$configuration['image_name'] : null;
        if ($imageName !== null && isset($configuration['id_image'])) {
            $imageName = (int)$configuration['id_image'] . '-' . $imageName;
        }

        return [
            'width' => isset($configuration['width']) ? (int)$configuration['width'] : 0,
            'height' => isset($configuration['height']) ? (int)$configuration['height'] : 0,
            'quality' => isset($configuration['quality']) ? (int)$configuration['quality'] : self::DEFAULT_QUALITY,
            'image_name' => $imageName,
            'ext' => isset($configuration['ext']) ? (string)$configuration['ext'] : null,
            'breakpoints' => array_values(array_unique(array_filter($breakpoints, fn (int $width) => $width > 0))),
        ];
    }

    /**
     * Retrieve the absolute path of the image to optimize
     *
     * @param mixed[] $configuration
     *
     * @return string|null null if the image is missing or outside the shop directory
     */
    public function getImagePath(array $configuration): ?string
    {
        $imagePath = null;

        if (isset($configuration['id_image'])) {
            $image = new Image((int)$configuration['id_image']);
            if (Validate::isLoadedObject($image) && $image->getExistingImgPath()) {
                $imagePath = _PS_PRODUCT_IMG_DIR_ . $image->getExistingImgPath() . '.jpg';
            }
        }

        if (!empty($configuration['image_path'])) {
            $imagePath = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . ltrim((string)$configuration['image_path'], '/');
        }

        if (!empty($configuration['image_url'])) {
            $relativePath = $this->getRelativePathFromUrl((string)$configuration['image_url']);
            $imagePath = $relativePath !== null ? _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $relativePath : null;
        }

        if ($imagePath === null) {
            return null;
        }

        // Refuse any path leaving the shop directory (../)
        $realPath = realpath($imagePath);
        $rootDir = realpath(_PS_ROOT_DIR_);

        if (!$realPath || !$rootDir || !str_starts_with($realPath, $rootDir . DIRECTORY_SEPARATOR) || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Convert an image URL of the shop (absolute or relative) to a path relative to the shop directory
     *
     * @param string $url
     *
     * @return string|null null if the URL targets another domain
     */
    public function getRelativePathFromUrl(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['path'])) {
            return null;
        }

        $shop = $this->context->shop;

        if (!empty($parts['host'])) {
            $domains = array_map(
                fn ($domain) => strtolower((string)preg_replace('/:\d+$/', '', (string)$domain)),
                [$shop->domain, $shop->domain_ssl]
            );
            if (!in_array(strtolower($parts['host']), $domains, true)) {
                return null;
            }
        }

        $path = rawurldecode($parts['path']);
        $baseUri = $shop->physical_uri ?: '/';

        if (str_starts_with($path, $baseUri)) {
            $path = substr($path, strlen($baseUri));
        }

        return ltrim($path, '/');
    }

    /**
     * Retrieve the original image data, when it can not be resized
     *
     * @param string $imagePath absolute path, inside the shop directory
     *
     * @return array{path: string, width: int, height: int}
     */
    protected function getOriginalImage(string $imagePath): array
    {
        $size = @getimagesize($imagePath);
        $relativePath = substr($imagePath, strlen(realpath(_PS_ROOT_DIR_) . DIRECTORY_SEPARATOR));

        return [
            'path'   => str_replace(DIRECTORY_SEPARATOR, '/', $relativePath),
            'width'  => $size ? (int)$size[0] : 0,
            'height' => $size ? (int)$size[1] : 0,
        ];
    }

    /**
     * Retrieve the public URL of an image
     *
     * @param string $path path relative to the shop directory
     *
     * @return string
     */
    protected function getImageUrl(string $path): string
    {
        $baseUrl = $this->context->shop->getBaseURL(true) ?: __PS_BASE_URI__;

        return rtrim($baseUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Resize an image and keep the ratio
     *
     * @param string      $filepath image path with the full absolute path
     * @param int         $maxWidth image maximum width (keep the ratio)
     * @param int         $maxHeight image maximum height (keep the ratio)
     * @param int         $quality between 0 and 100 (only for jpg, webp and avif)
     * @param string|null $newName the new file name (null keep the same file name)
     * @param string|null $toExt convert image to jpg, webp, avif, png, gif (null keep the same extension)
     *
     * @return array{path: string, width: int, height: int}|null the image data
     */
    public function imageResize(
        string $filepath,
        int $maxWidth,
        int $maxHeight,
        int $quality = self::DEFAULT_QUALITY,
        ?string $newName = null,
        ?string $toExt = null
    ): ?array {
        return $this->getResizer()->resize($filepath, [[$maxWidth, $maxHeight]], $quality, $newName, $toExt)[0];
    }
}
