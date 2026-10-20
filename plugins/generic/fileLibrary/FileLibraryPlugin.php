<?php

/**
 * @file FileLibraryPlugin.php
 *
 * @class FileLibraryPlugin
 *
 * @brief Lets journal managers upload arbitrary files (PDFs, DOCX, etc.) to
 *  the journal's public files directory and copy a direct link to them, for
 *  use inside static pages / navigation menu item content.
 */

namespace APP\plugins\generic\fileLibrary;

use APP\core\Application;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class FileLibraryPlugin extends GenericPlugin
{
    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.fileLibrary.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.fileLibrary.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                Hook::add('LoadHandler', $this->callbackLoadHandler(...));
            }
            return true;
        }
        return false;
    }

    /**
     * Route requests for the plugin's own management page to our handler.
     *
     * @param string $hookName
     * @param array $args
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $page = &$args[0];
        $handler = &$args[3];

        if ($page === 'fileLibrary') {
            $handler = new FileLibraryHandler($this);
            return true;
        }
        return false;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $dispatcher = $request->getDispatcher();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'manage',
                    new RedirectAction($dispatcher->url(
                        $request,
                        Application::ROUTE_PAGE,
                        null,
                        'fileLibrary'
                    )),
                    __('plugins.generic.fileLibrary.manage'),
                    null
                ),
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\fileLibrary\FileLibraryPlugin', '\FileLibraryPlugin');
}
