<?php

/**
 * @file plugins/themes/ipii/IpiiThemePlugin.inc.php
 *
 * @class IpiiThemePlugin
 * @ingroup plugins_themes_ipii
 *
 * @brief IPII — a clean, academic theme for scientific publications.
 */

use APP\core\Application;
use PKP\config\Config;
use PKP\facades\Locale;
use PKP\plugins\ThemePlugin;

class IpiiThemePlugin extends ThemePlugin
{
    public function init()
    {
        $this->addOption('baseColour', 'colour', [
            'label' => 'plugins.themes.ipii.option.colour.label',
            'description' => 'plugins.themes.ipii.option.colour.description',
            'default' => '#1E3A5F',
        ]);

        $additionalLessVariables = [];
        $baseColour = $this->getOption('baseColour');
        if (!preg_match('/^#[0-9a-fA-F]{1,6}$/', (string) $baseColour)) {
            $baseColour = '#1E3A5F';
        }
        if ($baseColour !== '#1E3A5F') {
            $additionalLessVariables[] = '@primary:' . $baseColour . ';';
            $additionalLessVariables[] = '
                @primary-light: desaturate(lighten(@primary, 45%), 20%);
                @primary-text: darken(@primary, 5%);
                @primary-link: lighten(@primary, 15%);
            ';
        }

        $checkMarkColour = 'FFF';
        if (!$this->isColourDark($baseColour)) {
            $checkMarkColour = '000';
        }

        $checkImageUrl = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">' .
            '<path fill="none" stroke="#' . $checkMarkColour . '" stroke-linecap="round" stroke-linejoin="round" ' .
            'stroke-width="3" d="M6 10l3 3l6-6"/></svg>';

        $additionalLessVariables[] = '
            @check-image-url: url(\'' . str_replace(['<', '>', '#'], ['%3c', '%3e', '%23'], $checkImageUrl) . '\');
        ';

        $this->addScript('app-js', 'libs/app.min.js');
        $this->addStyle('app-css', 'libs/app.min.css');
        $this->addStyle('stylesheet', 'styles/index.less');
        $this->modifyStyle('stylesheet', ['addLessVariables' => join("\n", $additionalLessVariables)]);

        $this->addStyle('htmlFont', 'styles/htmlGalley.less', ['contexts' => 'htmlGalley']);
        $this->addStyle('htmlGalley', 'templates/plugins/generic/htmlArticleGalley/css/default.css', ['contexts' => 'htmlGalley']);

        $locale = Locale::getLocale();
        if (Locale::getMetadata($locale)->isRightToLeft()) {
            $this->addStyle('rtl', 'styles/rtl.less');
        }

        $this->addMenuArea(['primary', 'user']);

        HookRegistry::add('TemplateManager::display', [$this, 'loadTemplateData']);
    }

    public function saveOption($name, $value, $contextId = null)
    {
        if ($name == 'baseColour' && !preg_match('/^#[0-9a-fA-F]{1,6}$/', $value)) {
            $value = null;
        }
        parent::saveOption($name, $value, $contextId);
    }

    public function getDisplayName(): string
    {
        return __('plugins.themes.ipii.name');
    }

    public function getDescription(): string
    {
        return __('plugins.themes.ipii.description');
    }

    public function loadTemplateData($hookName, $args)
    {
        $templateMgr = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        if (!defined('SESSION_DISABLE_INIT')) {
            if ($context) {
                $locales = $context->getSupportedLocaleNames();
            } else {
                $locales = $request->getSite()->getSupportedLocaleNames();
            }

            $loginUrl = $request->url(null, 'login', 'signIn');
            if (Config::getVar('security', 'force_login_ssl')) {
                $loginUrl = preg_replace('/^http:/u', 'https:', $loginUrl);
            }

            $templateMgr->assign([
                'languageToggleLocales' => $locales,
                'loginUrl'             => $loginUrl,
                'currentYear'          => date('Y'),
            ]);
        }

        // Issue table of contents (issue page and current issue on the home page)
        $sections = $templateMgr->getTemplateVars('publishedSubmissions');
        if (is_array($sections) && $sections && isset(reset($sections)['articles'])) {
            $templateMgr->assign('publishedSubmissions', $this->sortSectionsByPages($sections));
        }
    }

    /**
     * Articles inside each section in page order, as in the printed issue
     * (4-14, 15-24, …); articles without pages keep their order at the end.
     * The order of the sections themselves is left as set by the editors.
     */
    protected function sortSectionsByPages(array $sections): array
    {
        $firstPage = function ($article) {
            $pages = (string) $article->getCurrentPublication()?->getData('pages');
            return preg_match('/\d+/', $pages, $m) ? (int) $m[0] : PHP_INT_MAX;
        };
        foreach ($sections as &$section) {
            if (!empty($section['articles']) && is_array($section['articles'])) {
                // usort is stable: equal/missing pages keep the editors' order
                usort($section['articles'], fn ($a, $b) => $firstPage($a) <=> $firstPage($b));
            }
        }
        unset($section);
        return $sections;
    }
}
