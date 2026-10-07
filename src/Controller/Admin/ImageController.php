<?php
/**
 * Copyright (C) 2025 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\ImageOptimizer\Controller\Admin;

include_once _PS_MODULE_DIR_ . 'pixel_image_optimizer/pixel_image_optimizer.php';

use Pixel\Module\ImageOptimizer\ImageResizer;
use Pixel_image_optimizer;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use PrestaShopLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

class ImageController extends FrameworkBundleAdminController
{
    /**
     * @AdminSecurity(
     *     "is_granted('delete', request.get('_legacy_controller'))",
     *     redirectRoute="admin_performance"
     * )
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function clearCacheAction(Request $request): RedirectResponse
    {
        try {
            (new ImageResizer(_PS_ROOT_DIR_, Pixel_image_optimizer::CACHE_IMAGE_PATH))->clear();
            $this->addMessage(
                'success',
                $this->trans('Image cache has been flushed', 'Modules.Pixelimageoptimizer.Admin')
            );
        } catch (Throwable $throwable) {
            $this->addMessage('error', $throwable->getMessage());
        }

        $redirect = $request->headers->get('referer');
        if (!$redirect) {
            return $this->redirectToRoute('admin_performance');
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
                PrestaShopLogger::LOG_SEVERITY_LEVEL_ERROR :
                PrestaShopLogger::LOG_SEVERITY_LEVEL_INFORMATIVE
        );
    }
}
