<?php

/**
 * @file FileLibraryHandler.php
 *
 * @class FileLibraryHandler
 *
 * @brief Handle requests for the file library management page.
 */

namespace APP\plugins\generic\fileLibrary;

use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class FileLibraryHandler extends Handler
{
    /** Required so Handler::setupTemplate() registers the standard backend
     * JS/CSS bundle (jQuery, the `pkp` global, Vue) — without this, the
     * page renders but stays inert/blank since nothing bootstraps it. */
    public $_isBackendPage = true;

    /** Sub-folder, inside the context's public files directory, that library uploads live in */
    public const LIBRARY_SUBDIR = 'library';

    /** Extensions allowed for upload. Deny-by-default: only what's listed here is accepted. */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt',
        'xls', 'xlsx', 'ods', 'csv',
        'ppt', 'pptx', 'odp',
        'zip',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
    ];

    public FileLibraryPlugin $plugin;

    public function __construct(FileLibraryPlugin $plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
            ['index', 'upload', 'delete']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Show the upload form and the list of already-uploaded files.
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        // A plain <style> tag inside the page body gets discarded by the
        // Vue admin shell, so the stylesheet has to go through the proper
        // registration API (same mechanism every other backend page's CSS
        // uses) to end up in <head> where it actually takes effect.
        $templateMgr->addStyleSheet(
            'fileLibrary',
            $this->getInlineStyles(),
            ['inline' => true, 'contexts' => ['backend']]
        );
        $templateMgr->addJavaScript(
            'fileLibraryCopy',
            "function fileLibraryCopyFallback(text) {\n"
            . "    var el = document.createElement('textarea');\n"
            . "    el.value = text;\n"
            . "    el.setAttribute('readonly', '');\n"
            . "    el.style.position = 'fixed';\n"
            . "    el.style.left = '-9999px';\n"
            . "    document.body.appendChild(el);\n"
            . "    el.select();\n"
            . "    el.setSelectionRange(0, text.length);\n"
            . "    try { document.execCommand('copy'); } catch (e) {}\n"
            . "    document.body.removeChild(el);\n"
            . "}\n"
            . "document.addEventListener('click', function (e) {\n"
            . "    var btn = e.target.closest('[data-copy-url]');\n"
            . "    if (!btn) return;\n"
            . "    var url = btn.getAttribute('data-copy-url');\n"
            // navigator.clipboard requires a secure context (HTTPS/localhost);
            // over plain HTTP it's undefined, so fall back to execCommand.
            . "    if (window.isSecureContext && navigator.clipboard) {\n"
            . "        navigator.clipboard.writeText(url);\n"
            . "    } else {\n"
            . "        fileLibraryCopyFallback(url);\n"
            . "    }\n"
            . "});",
            ['inline' => true, 'contexts' => ['backend']]
        );

        $templateMgr->assign([
            'pageComponent' => 'Page',
            'pageTitle' => __('plugins.generic.fileLibrary.displayName'),
            'files' => $this->getFileList($request),
        ]);

        $templateMgr->display($this->plugin->getTemplateResource('fileLibrary.tpl'));
    }

    /**
     * CSS for the file library page, registered via addStyleSheet() so it
     * actually lands in <head> instead of being dropped from the page body.
     */
    protected function getInlineStyles(): string
    {
        return <<<CSS
			.fileLibrary__intro {
				color: #777;
				font-size: 0.9375rem;
				margin: 0 0 1.25rem;
			}
			.fileLibrary__uploadForm {
				display: flex;
				align-items: center;
				gap: 0.75rem;
				margin: 0 0 1.5rem;
				padding: 1rem 1.25rem;
				background: #eaedee;
				border-radius: 0.375rem;
			}
			.fileLibrary__uploadForm input[type="file"] {
				flex: 1 1 auto;
				min-width: 0;
				font-size: 0.875rem;
			}
			.fileLibrary__table {
				width: 100%;
				border-collapse: collapse;
				font-size: 0.875rem;
			}
			.fileLibrary__table thead th {
				text-align: left;
				padding: 0.625rem 0.75rem;
				background: #eaedee;
				color: #1B3A6B;
				font-weight: 700;
				font-size: 0.8125rem;
				text-transform: uppercase;
				letter-spacing: 0.03em;
				border-bottom: 2px solid #ddd;
			}
			.fileLibrary__table thead th:first-child {
				border-top-left-radius: 0.375rem;
			}
			.fileLibrary__table thead th:last-child {
				border-top-right-radius: 0.375rem;
			}
			.fileLibrary__table tbody td {
				padding: 0.75rem;
				border-bottom: 1px solid #ddd;
				vertical-align: middle;
			}
			.fileLibrary__table tbody tr:hover {
				background: #f8f9fa;
			}
			.fileLibrary__table tbody tr:last-child td {
				border-bottom: none;
			}
			.fileLibrary__cellName {
				font-weight: 600;
				color: #333;
				word-break: break-word;
			}
			.fileLibrary__cellMuted {
				color: #777;
				white-space: nowrap;
			}
			.fileLibrary__colActions {
				width: 1%;
				white-space: nowrap;
				text-align: right;
			}
			.fileLibrary__linkRow {
				display: flex;
				align-items: center;
				gap: 0.5rem;
			}
			.fileLibrary__urlInput {
				flex: 1 1 auto;
				min-width: 12rem;
				max-width: 26rem;
				padding: 0.375rem 0.625rem;
				border: 1px solid #ddd;
				border-radius: 0.375rem;
				font-size: 0.8125rem;
				color: #777;
				background: #fff;
			}
			.fileLibrary__urlInput:focus {
				outline: 0;
				border-color: #1B3A6B;
				color: #333;
			}
			.fileLibrary__deleteButton {
				color: #d00;
				border-color: #ddd;
			}
			.fileLibrary__deleteButton:hover {
				background: #d00;
				border-color: #d00;
				color: #fff;
			}
			.fileLibrary__empty {
				color: #777;
				font-style: italic;
				margin: 0;
			}
			CSS;
    }

    /**
     * Handle a file upload.
     */
    public function upload($args, $request)
    {
        $this->_redirectUnlessValidPost($request);

        $context = $request->getContext();
        $notificationMgr = new NotificationManager();
        $user = $request->getUser();

        if (empty($_FILES['file']['name'])) {
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, ['contents' => __('plugins.generic.fileLibrary.upload.noFile')]);
            $request->redirect(null, 'fileLibrary');
            return;
        }

        $originalName = $_FILES['file']['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, ['contents' => __('plugins.generic.fileLibrary.upload.invalidType', ['extensions' => implode(', ', self::ALLOWED_EXTENSIONS)])]);
            $request->redirect(null, 'fileLibrary');
            return;
        }

        $safeName = $this->sanitizeFileName(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
        $safeName = $this->getAvailableFileName($request, $safeName);

        $publicFileManager = new PublicFileManager();
        $destPath = self::LIBRARY_SUBDIR . '/' . $safeName;

        if ($publicFileManager->uploadContextFile($context->getId(), 'file', $destPath)) {
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('plugins.generic.fileLibrary.upload.success', ['fileName' => $safeName])]);
        } else {
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, ['contents' => __('plugins.generic.fileLibrary.upload.failed')]);
        }

        $request->redirect(null, 'fileLibrary');
    }

    /**
     * Delete a previously uploaded file.
     */
    public function delete($args, $request)
    {
        $this->_redirectUnlessValidPost($request);

        $context = $request->getContext();
        $fileName = $this->sanitizeFileName((string) $request->getUserVar('fileName'));

        $publicFileManager = new PublicFileManager();
        $publicFileManager->removeContextFile($context->getId(), self::LIBRARY_SUBDIR . '/' . $fileName);

        $request->redirect(null, 'fileLibrary');
    }

    /**
     * Reject the request unless it's a valid, CSRF-protected POST.
     */
    protected function _redirectUnlessValidPost(PKPRequest $request): void
    {
        if (!$request->isPost() || !$request->checkCSRF()) {
            $request->redirect(null, 'fileLibrary');
            exit;
        }
    }

    /** Cyrillic → Latin transliteration map (lowercase; input is lowercased before lookup) */
    protected const TRANSLIT_MAP = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
        'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
        'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
        'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
        'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        // Ukrainian/Belarusian extras, harmless to support too
        'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g', 'ў' => 'u',
    ];

    /**
     * Transliterate Cyrillic to Latin, then strip anything that isn't a
     * safe filename character (letters, digits, dot, dash, underscore).
     * Spaces and anything else collapse to a single dash.
     */
    protected function sanitizeFileName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = strtr($name, self::TRANSLIT_MAP);
        $name = preg_replace('/[^a-z0-9._-]+/u', '-', $name);
        $name = preg_replace('/-{2,}/', '-', $name);
        $name = trim($name, '.-_');
        return $name !== '' ? $name : 'file';
    }

    /**
     * Avoid clobbering an existing file: append -2, -3, ... if the name is taken.
     */
    protected function getAvailableFileName(PKPRequest $request, string $fileName): string
    {
        $publicFileManager = new PublicFileManager();
        $context = $request->getContext();
        $dir = $publicFileManager->getContextFilesPath($context->getId()) . '/' . self::LIBRARY_SUBDIR;

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $base = pathinfo($fileName, PATHINFO_FILENAME);

        $candidate = $fileName;
        $i = 2;
        while (file_exists($dir . '/' . $candidate)) {
            $candidate = $base . '-' . $i . '.' . $extension;
            $i++;
        }
        return $candidate;
    }

    /**
     * Build the list of currently uploaded files, with their public URLs.
     */
    protected function getFileList(PKPRequest $request): array
    {
        $context = $request->getContext();
        $publicFileManager = new PublicFileManager();
        $dir = $publicFileManager->getContextFilesPath($context->getId()) . '/' . self::LIBRARY_SUBDIR;

        if (!is_dir($dir)) {
            return [];
        }

        $baseUrl = $request->getBaseUrl() . '/' . \PKP\config\Config::getVar('files', 'public_files_dir') . '/journals/' . $context->getId() . '/' . self::LIBRARY_SUBDIR;

        $files = [];
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_file($dir . '/' . $entry)) {
                continue;
            }
            $files[] = [
                'name' => $entry,
                'url' => $baseUrl . '/' . rawurlencode($entry),
                'size' => $this->formatFileSize(filesize($dir . '/' . $entry)),
                'date' => date('Y-m-d H:i', filemtime($dir . '/' . $entry)),
            ];
        }

        usort($files, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $files;
    }

    /**
     * Format a byte count for display.
     */
    protected function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
