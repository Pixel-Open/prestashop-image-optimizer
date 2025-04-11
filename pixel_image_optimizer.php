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

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Pixel_image_optimizer extends Module implements WidgetInterface
{
    public const CACHE_IMAGE_PATH = 'img' . DIRECTORY_SEPARATOR . 'web';

    protected $templateFile;

    /**
     * Module's constructor.
     */
    public function __construct()
    {
        $this->name = 'pixel_image_optimizer';
        $this->version = '1.0.3';
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
            'min' => '1.7.6.0',
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

        return $this->get('twig')->render('@Modules/' . $this->name . '/views/templates/admin/toolbar.html.twig', [
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
     * @param string[] $configuration
     *
     * @return string
     */
    public function renderWidget($hookName, array $configuration): string
    {
        $imagePath = null;
        $configuration['image'] = null;

        if (isset($configuration['id_image'])) {
            $image = new Image((int)$configuration['id_image']);
            if ($image->getPathForCreation()) {
                $imagePath = $image->getPathForCreation() . '.jpg';
                if (isset($configuration['image_name'])) {
                    $configuration['image_name'] = $image->id . '-' . $configuration['image_name'];
                }
            }
        }

        if (isset($configuration['image_path'])) {
            $imagePath = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . ltrim($configuration['image_path'], '/');
        }

        if ($imagePath) {
            $config = $this->getImageConfig($configuration);

            $configuration['image'] = $this->imageResize(
                $imagePath,
                $config['width'],
                $config['height'],
                $config['quality'],
                $config['image_name'],
                $config['ext']
            );
            $configuration['sources'] = [];

            if (isset($configuration['breakpoints'])) {
                $breakpoints = explode(',', $configuration['breakpoints']);
                foreach ($breakpoints as $breakpoint) {
                    $width = (int)trim($breakpoint);
                    if (!$width) {
                        continue;
                    }
                    $configuration['sources'][$width] = $this->imageResize(
                        $imagePath,
                        $width,
                        $config['height'],
                        $config['quality'],
                        $config['image_name'],
                        $config['ext']
                    );
                }
            }

            krsort($configuration['sources']);
        }

        $template = $configuration['template'] ?? $this->templateFile;

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch($template);
    }

    /**
     * Retrieve the widget variables
     *
     * @param string $hookName
     * @param string[] $configuration
     *
     * @return string[]
     */
    public function getWidgetVariables($hookName, array $configuration): array
    {
        return [
            'image'   => $configuration['image'],
            'sources' => $configuration['sources'] ?? [],
            'class'   => $configuration['class'] ?? '',
            'alt'     => $configuration['alt'] ?? '',
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
        return [
            'width' => isset($configuration['width']) ? (int)$configuration['width'] : 0,
            'height' => isset($configuration['height']) ? (int)$configuration['height'] : 0,
            'quality' => isset($configuration['quality']) ? (int)$configuration['quality'] : 100,
            'image_name' => $configuration['image_name'] ?? null,
            'ext' => $configuration['ext'] ?? null,
        ];
    }

    /**
     * Resize an image and keep the ratio
     *
     * @param string      $filepath image path with the full absolute path
     * @param int         $maxWidth image maximum width (keep the ratio)
     * @param int         $maxHeight image maximum height (keep the ratio)
     * @param int         $quality between 0 and 100 (only for jpg and webp)
     * @param string|null $newName the new file name (null keep the same file name)
     * @param string|null $toExt convert image to jpg, webp, png, gif (null keep the same extension)
     * @param string      $folder copy resized image in this directory
     *
     * @return string[]|null the image data
     */
    public function imageResize(
        string $filepath,
        int $maxWidth,
        int $maxHeight,
        int $quality = 100,
        string $newName = null,
        string $toExt = null,
        string $folder = self::CACHE_IMAGE_PATH
    ): ?array {
        if (!is_file($filepath)) {
            return null;
        }

        $info = pathinfo(basename($filepath));

        if (!isset($info['extension'], $info['dirname'], $info['filename'])) {
            return null;
        }

        $toExt = $toExt ?: $info['extension'];

        if ($newName !== null) {
            $info['filename'] = $this->formatKey($newName);
        }

        $folder = trim($folder, '/');
        $folder = trim($folder, DIRECTORY_SEPARATOR);

        $resizeDirectory = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR;

        try {
            $this->mkdir($resizeDirectory);
        } catch (Throwable $throwable) {
            return null;
        }

        if (!is_writable($resizeDirectory)) {
            return null;
        }

        list($origWidth, $origHeight) = getimagesize($filepath);

        $width  = $origWidth;
        $height = $origHeight;

        if ($maxHeight && $height > $maxHeight) {
            $width = ($maxHeight / $height) * $width;
            $height = $maxHeight;
        }

        if ($maxWidth && $width > $maxWidth) {
            $height = ($maxWidth / $width) * $height;
            $width = $maxWidth;
        }

        $file = $info['filename'] . '-' . (int)$width . 'x' . (int)$height . '-' . (int)$quality . '.' . $toExt;
        $destination = $resizeDirectory . $file;

        if (is_file($destination)) {
            return [
                'path'   => str_replace(DIRECTORY_SEPARATOR, '/', $folder) . '/' . $file,
                'width'  => (int)$width,
                'height' => (int)$height,
            ];
        }

        $result = imagecreatetruecolor((int)$width, (int)$height);

        if (!$result) {
            return null;
        }

        $image = null;

        if ($info['extension'] === 'jpg') {
            $image = imagecreatefromjpeg($filepath);
        }
        if ($info['extension'] === 'jpeg') {
            $image = imagecreatefromjpeg($filepath);
        }
        if ($info['extension'] === 'png') {
            $image = imagecreatefrompng($filepath);
        }
        if ($info['extension'] === 'gif') {
            $image = imagecreatefromgif($filepath);
        }

        if (!$image) {
            return null;
        }

        $copy = imagecopyresampled(
            $result,
            $image,
            0,
            0,
            0,
            0,
            (int)$width,
            (int)$height,
            (int)$origWidth,
            (int)$origHeight
        );

        if (!$copy) {
            return null;
        }

        $resized = null;

        if ($toExt === 'jpg') {
            $resized = imagejpeg($result, $destination, $quality);
        }
        if ($toExt === 'jpeg') {
            $resized = imagejpeg($result, $destination, $quality);
        }
        if ($toExt === 'webp') {
            $resized = imagewebp($result, $destination, $quality);
        }
        if ($toExt === 'png') {
            $resized = imagepng($result, $destination);
        }
        if ($toExt === 'gif') {
            $resized = imagegif($result, $destination);
        }

        if (!$resized) {
            return null;
        }

        return [
            'path'   => str_replace(DIRECTORY_SEPARATOR, '/', $folder) . '/' . $file,
            'width'  => (int)$width,
            'height' => (int)$height,
        ];
    }

    /**
     * Create directory
     *
     * @param string $directory
     *
     * @return bool
     * @throws Exception
     */
    public function mkdir(string $directory): bool
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
            $error = error_get_last();
            if (isset($error['message'])) {
                throw new Exception($error['message']);
            }
        }

        return true;
    }

    /**
     * Format a key
     *
     * @param string $value
     * @param string $replace
     *
     * @return string
     */
    public function formatKey(string $value, string $replace = '-'): string
    {
        $string = trim($value, '/');
        $string = strtolower($string);
        $string = strtr($string, $this->getConvertTable());
        $string = preg_replace('#[^a-z0-9]+#i', $replace, $string);

        return trim($string, $replace);
    }

    /**
     * Retrieve chars convert table
     *
     * @return string[]
     */
    public function getConvertTable(): array
    {
        return [
            '&amp;' => 'and',   '@' => 'at',    '©' => 'c', '®' => 'r', 'À' => 'a',
            'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'Å' => 'a', 'Æ' => 'ae','Ç' => 'c',
            'È' => 'e', 'É' => 'e', 'Ë' => 'e', 'Ì' => 'i', 'Í' => 'i', 'Î' => 'i',
            'Ï' => 'i', 'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'Ø' => 'o', 'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ý' => 'y',
            'ß' => 'ss','à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
            'æ' => 'ae','ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ù' => 'u', 'ú' => 'u',
            'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'þ' => 'p', 'ÿ' => 'y', 'Ā' => 'a',
            'ā' => 'a', 'Ă' => 'a', 'ă' => 'a', 'Ą' => 'a', 'ą' => 'a', 'Ć' => 'c',
            'ć' => 'c', 'Ĉ' => 'c', 'ĉ' => 'c', 'Ċ' => 'c', 'ċ' => 'c', 'Č' => 'c',
            'č' => 'c', 'Ď' => 'd', 'ď' => 'd', 'Đ' => 'd', 'đ' => 'd', 'Ē' => 'e',
            'ē' => 'e', 'Ĕ' => 'e', 'ĕ' => 'e', 'Ė' => 'e', 'ė' => 'e', 'Ę' => 'e',
            'ę' => 'e', 'Ě' => 'e', 'ě' => 'e', 'Ĝ' => 'g', 'ĝ' => 'g', 'Ğ' => 'g',
            'ğ' => 'g', 'Ġ' => 'g', 'ġ' => 'g', 'Ģ' => 'g', 'ģ' => 'g', 'Ĥ' => 'h',
            'ĥ' => 'h', 'Ħ' => 'h', 'ħ' => 'h', 'Ĩ' => 'i', 'ĩ' => 'i', 'Ī' => 'i',
            'ī' => 'i', 'Ĭ' => 'i', 'ĭ' => 'i', 'Į' => 'i', 'į' => 'i', 'İ' => 'i',
            'ı' => 'i', 'Ĳ' => 'ij','ĳ' => 'ij','Ĵ' => 'j', 'ĵ' => 'j', 'Ķ' => 'k',
            'ķ' => 'k', 'ĸ' => 'k', 'Ĺ' => 'l', 'ĺ' => 'l', 'Ļ' => 'l', 'ļ' => 'l',
            'Ľ' => 'l', 'ľ' => 'l', 'Ŀ' => 'l', 'ŀ' => 'l', 'Ł' => 'l', 'ł' => 'l',
            'Ń' => 'n', 'ń' => 'n', 'Ņ' => 'n', 'ņ' => 'n', 'Ň' => 'n', 'ň' => 'n',
            'ŉ' => 'n', 'Ŋ' => 'n', 'ŋ' => 'n', 'Ō' => 'o', 'ō' => 'o', 'Ŏ' => 'o',
            'ŏ' => 'o', 'Ő' => 'o', 'ő' => 'o', 'Œ' => 'oe','œ' => 'oe','Ŕ' => 'r',
            'ŕ' => 'r', 'Ŗ' => 'r', 'ŗ' => 'r', 'Ř' => 'r', 'ř' => 'r', 'Ś' => 's',
            'ś' => 's', 'Ŝ' => 's', 'ŝ' => 's', 'Ş' => 's', 'ş' => 's', 'Š' => 's',
            'š' => 's', 'Ţ' => 't', 'ţ' => 't', 'Ť' => 't', 'ť' => 't', 'Ŧ' => 't',
            'ŧ' => 't', 'Ũ' => 'u', 'ũ' => 'u', 'Ū' => 'u', 'ū' => 'u', 'Ŭ' => 'u',
            'ŭ' => 'u', 'Ů' => 'u', 'ů' => 'u', 'Ű' => 'u', 'ű' => 'u', 'Ų' => 'u',
            'ų' => 'u', 'Ŵ' => 'w', 'ŵ' => 'w', 'Ŷ' => 'y', 'ŷ' => 'y', 'Ÿ' => 'y',
            'Ź' => 'z', 'ź' => 'z', 'Ż' => 'z', 'ż' => 'z', 'Ž' => 'z', 'ž' => 'z',
            'ſ' => 'z', 'Ə' => 'e', 'ƒ' => 'f', 'Ơ' => 'o', 'ơ' => 'o', 'Ư' => 'u',
            'ư' => 'u', 'Ǎ' => 'a', 'ǎ' => 'a', 'Ǐ' => 'i', 'ǐ' => 'i', 'Ǒ' => 'o',
            'ǒ' => 'o', 'Ǔ' => 'u', 'ǔ' => 'u', 'Ǖ' => 'u', 'ǖ' => 'u', 'Ǘ' => 'u',
            'ǘ' => 'u', 'Ǚ' => 'u', 'ǚ' => 'u', 'Ǜ' => 'u', 'ǜ' => 'u', 'Ǻ' => 'a',
            'ǻ' => 'a', 'Ǽ' => 'ae','ǽ' => 'ae','Ǿ' => 'o', 'ǿ' => 'o', 'ə' => 'e',
            'Ё' => 'jo','Є' => 'e', 'І' => 'i', 'Ї' => 'i', 'А' => 'a', 'Б' => 'b',
            'В' => 'v', 'Г' => 'g', 'Д' => 'd', 'Е' => 'e', 'Ж' => 'zh','З' => 'z',
            'И' => 'i', 'Й' => 'j', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
            'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't', 'У' => 'u',
            'Ф' => 'f', 'Х' => 'h', 'Ц' => 'c', 'Ч' => 'ch','Ш' => 'sh','Щ' => 'sch',
            'Ъ' => '-', 'Ы' => 'y', 'Ь' => '-', 'Э' => 'je','Ю' => 'ju','Я' => 'ja',
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ж' => 'zh','з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l',
            'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's',
            'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh','щ' => 'sch','ъ' => '-','ы' => 'y', 'ь' => '-', 'э' => 'je',
            'ю' => 'ju','я' => 'ja','ё' => 'jo','є' => 'e', 'і' => 'i', 'ї' => 'i',
            'Ґ' => 'g', 'ґ' => 'g', 'א' => 'a', 'ב' => 'b', 'ג' => 'g', 'ד' => 'd',
            'ה' => 'h', 'ו' => 'v', 'ז' => 'z', 'ח' => 'h', 'ט' => 't', 'י' => 'i',
            'ך' => 'k', 'כ' => 'k', 'ל' => 'l', 'ם' => 'm', 'מ' => 'm', 'ן' => 'n',
            'נ' => 'n', 'ס' => 's', 'ע' => 'e', 'ף' => 'p', 'פ' => 'p', 'ץ' => 'C',
            'צ' => 'c', 'ק' => 'q', 'ר' => 'r', 'ש' => 'w', 'ת' => 't', '™' => 'tm',
        ];
    }
}
