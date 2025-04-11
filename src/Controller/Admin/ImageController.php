<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\ImageOptimizer\Controller\Admin;

include_once _PS_MODULE_DIR_ . 'pixel_image_optimizer/pixel_image_optimizer.php';

use Pixel_image_optimizer;
use PrestaShopLogger;
use PrestaShopLoggerCore;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;
use Throwable;

class ImageController extends FrameworkBundleAdminController
{
    /**
     * @var TranslatorInterface $translator
     */
    protected $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;

        parent::__construct();
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function clearCacheAction(Request $request): RedirectResponse
    {
        try {
            $this->removeImages(_PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . Pixel_image_optimizer::CACHE_IMAGE_PATH);
            $this->addMessage(
                'success',
                $this->translator->trans('Image cache has been flushed', [], 'Modules.Pixelimageoptimizer.Admin')
            );
        } catch (Throwable $throwable) {
            $this->addMessage('error', $throwable->getMessage());
        }

        $redirect = $request->headers->get('referer');
        if (!$redirect) {
            $redirect = 'admin_dashboard';
        }

        return $this->redirect($redirect);
    }

    /**
     * Add message
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    protected function addMessage(string $type, string $message): void
    {
        $this->addFlash($type, $message);
        PrestaShopLogger::addLog(
            $message,
            $type === 'error' ?
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_ERROR :
                PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE
        );
    }

    /**
     * Remove directory files recursively
     *
     * @param string $directory
     * @return void
     */
    protected function removeImages(string $directory): void
    {
        $files = array_diff(scandir($directory) ?: [], ['.', '..']);

        foreach ($files as $file) {
            $current = $directory . DIRECTORY_SEPARATOR . $file;
            if (is_dir($current) && !is_link($current)) {
                $this->removeImages($current);
            } else {
                unlink($current);
            }
        }
    }
}
