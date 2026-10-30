<?php

/**
 * @file PartnerLinksBlockPlugin.php
 *
 * @class PartnerLinksBlockPlugin
 *
 * @brief Sidebar block with links to partners and indexing services
 *  (title, URL, optional logo). The list is edited by journal managers
 *  in the plugin settings and stored as JSON in plugin_settings.
 */

// Пространство имён: OJS по нему находит класс плагина.
// Оно повторяет путь к папке: APP\plugins\blocks\partnerLinks = plugins/blocks/partnerLinks
namespace APP\plugins\blocks\partnerLinks;

// Подключаем классы OJS, которые используются ниже:
use APP\template\TemplateManager;           // менеджер шаблонов Smarty (рисует HTML)
use PKP\core\JSONMessage;                   // ответ сервера в формате JSON (нужен для всплывающих окон админки)
use PKP\linkAction\LinkAction;              // кнопка-действие в списке плагинов (у нас «Настройки»)
use PKP\linkAction\request\AjaxModal;       // открывает окно поверх страницы и грузит в него форму
use PKP\plugins\BlockPlugin;                // базовый класс всех блоков боковой панели

// Наш плагин — это «блок», поэтому наследуемся от BlockPlugin.
// Всё стандартное поведение (включение/выключение, вывод шаблона) берётся оттуда,
// а мы переопределяем только то, что нужно нам.
class PartnerLinksBlockPlugin extends BlockPlugin
{
    /** Number of link rows offered in the settings form */
    // Константа: сколько пустых строк для ссылок показывать в форме настроек.
    public const MAX_LINKS = 8;

    /**
     * @copydoc Plugin::getDisplayName()
     */
    // Название плагина, которое видно в списке модулей в админке.
    public function getDisplayName()
    {
        // __() — функция перевода: по ключу берёт текст из locale/ru/locale.po или locale/en/locale.po
        return __('plugins.block.partnerLinks.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    // Описание плагина под его названием в списке модулей.
    public function getDescription()
    {
        return __('plugins.block.partnerLinks.description');
    }

    /**
     * Saved links of the given journal, in display order.
     *
     * @return array<array{title: string, url: string, logo: string}>
     */
    // Возвращает сохранённый список ссылок для журнала с номером $contextId.
    // (в OJS «context» = журнал; на одной платформе может быть несколько журналов)
    public function getLinks(int $contextId): array
    {
        // getSetting() читает значение из таблицы plugin_settings (настройка 'links').
        // Там лежит строка JSON, json_decode(..., true) превращает её в PHP-массив.
        // (string) нужен, чтобы при отсутствии настройки (null) не было предупреждения.
        $links = json_decode((string) $this->getSetting($contextId, 'links'), true);
        // Если в базе мусор или ничего нет — возвращаем пустой массив, а не null/ошибку.
        return is_array($links) ? $links : [];
    }

    /**
     * @copydoc BlockPlugin::getContents()
     *
     * @param null|mixed $request
     */
    // Главный метод блока: OJS вызывает его, когда рисует страницу сайта
    // и доходит до места, где тема выводит блоки (Templates::Common::Sidebar).
    // Метод должен вернуть готовый HTML блока (или пустую строку — тогда блока не будет).
    public function getContents($templateMgr, $request = null)
    {
        // Узнаём, на странице какого журнала мы находимся.
        // ?-> — «безопасный вызов»: если $request = null, то и $context будет null без ошибки.
        $context = $request?->getContext();
        // Если журнала нет (например, общая страница всей платформы) — блок не показываем.
        if (!$context) {
            return '';
        }

        // Берём список ссылок этого журнала.
        $links = $this->getLinks($context->getId());
        // Пустой список — пустой блок не нужен, ничего не выводим.
        if (empty($links)) {
            return '';
        }

        // Передаём данные в шаблон block.tpl: там они будут доступны как переменные Smarty.
        $templateMgr->assign([
            'partnerLinks' => $links,                                                 // массив ссылок → {$partnerLinks}
            'partnerLinksTitle' => $this->getSetting($context->getId(), 'blockTitle'), // заголовок → {$partnerLinksTitle}
        ]);
        // Родительский метод сам найдёт templates/block.tpl, отрисует его и вернёт HTML.
        return parent::getContents($templateMgr, $request);
    }

    /**
     * @copydoc Plugin::getActions()
     */
    // Кнопки, которые появляются под плагином в списке модулей (Настройки → Веб-сайт → Модули).
    public function getActions($request, $actionArgs)
    {
        // Роутер умеет строить адреса (URL) внутри OJS.
        $router = $request->getRouter();
        // array_merge склеивает наши кнопки со стандартными (например, «Включить/Выключить»).
        return array_merge(
            // Кнопку «Настройки» показываем только у включённого плагина, иначе — пустой массив.
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',                         // внутреннее имя кнопки
                    new AjaxModal(                      // по нажатию — открыть всплывающее окно...
                        // ...и загрузить в него адрес вида .../manage?verb=settings&plugin=...&category=blocks
                        // OJS направит этот запрос в наш метод manage() (ниже).
                        $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'blocks']),
                        $this->getDisplayName()         // заголовок всплывающего окна
                    ),
                    __('manager.plugins.settings'),     // текст на кнопке: «Настройки»
                    null                                // иконка не нужна
                ),
            ] : [],
            // Стандартные кнопки родительского класса.
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * @copydoc Plugin::manage()
     */
    // Обработчик запросов из админки. Сюда попадаем при открытии окна «Настройки»
    // и при нажатии «Сохранить». Доступ сюда ядро OJS даёт только менеджеру журнала
    // и администратору — проверка ролей делается до вызова этого метода.
    public function manage($args, $request)
    {
        // verb — «что сделать». Нас интересует только 'settings';
        // все остальные действия отдаём стандартной реализации родителя.
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        // Текущий журнал (чьи настройки редактируем).
        $context = $request->getContext();
        // Регистрируем в Smarty функцию {plugin_url} — стандартная подготовка для шаблонов форм плагинов.
        TemplateManager::getManager($request)->registerPlugin('function', 'plugin_url', $this->smartyPluginUrl(...));

        // Создаём объект формы настроек для этого журнала.
        $form = new PartnerLinksSettingsForm($this, $context->getId());
        // В запросе есть save=1 → пользователь нажал «Сохранить».
        if ($request->getUserVar('save')) {
            // Читаем присланные из браузера поля формы.
            $form->readInputData();
            // Проверяем их (все правила описаны в конструкторе формы).
            if ($form->validate()) {
                // Всё в порядке — сохраняем в базу...
                $form->execute();
                // ...и отвечаем «успех»: админка закроет окно.
                return new JSONMessage(true);
            }
            // Если проверка не прошла — идём дальше и снова показываем форму, уже с ошибками.
        } else {
            // Окно только открыли — заполняем форму данными из базы.
            $form->initData();
        }
        // Отдаём HTML формы внутри JSON-ответа, админка вставит его во всплывающее окно.
        return new JSONMessage(true, $form->fetch($request));
    }
}

// Для совместимости со старым кодом OJS: делаем короткое имя класса без пространства имён.
// В «строгом режиме» (PKP_STRICT_MODE) это отключено.
if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\blocks\partnerLinks\PartnerLinksBlockPlugin', '\PartnerLinksBlockPlugin');
}
