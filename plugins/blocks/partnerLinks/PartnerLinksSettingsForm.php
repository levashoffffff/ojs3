<?php

/**
 * @file PartnerLinksSettingsForm.php
 *
 * @class PartnerLinksSettingsForm
 *
 * @brief Settings form for the partner links block: block title and a fixed
 *  number of rows (title, URL, logo URL). Empty rows are dropped on save.
 */

// То же пространство имён, что и у главного класса плагина.
namespace APP\plugins\blocks\partnerLinks;

use APP\template\TemplateManager;                      // менеджер шаблонов Smarty
use PKP\form\Form;                                     // базовый класс всех форм OJS
use PKP\form\validation\FormValidatorCSRF;             // проверка CSRF-токена (защита от подделки запроса)
use PKP\form\validation\FormValidatorCustom;           // проверка по нашей собственной функции
use PKP\form\validation\FormValidatorPost;             // проверка, что данные пришли методом POST

// Форма настроек наследуется от Form: чтение полей, проверки, вывод шаблона уже реализованы там.
class PartnerLinksSettingsForm extends Form
{
    // Конструктор. Запись «protected PartnerLinksBlockPlugin $plugin» в скобках — это
    // сокращение PHP 8: параметр сразу становится свойством $this->plugin.
    public function __construct(
        protected PartnerLinksBlockPlugin $plugin,   // наш плагин (через него читаем/пишем настройки)
        protected int $contextId                     // номер журнала, чьи настройки редактируем
    ) {
        // Говорим родителю, какой шаблон рисует форму: templates/settingsForm.tpl
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        // Правило проверки №1: у каждой заполненной строки должно быть название.
        $this->addCheck(new FormValidatorCustom(
            $this,                                                  // какая форма
            'links',                                                // какое поле проверяем
            FormValidatorCustom::FORM_VALIDATOR_OPTIONAL_VALUE,     // поле необязательное: пустой список — допустим
            'plugins.block.partnerLinks.error.titleRequired',       // ключ текста ошибки (из locale.po)
            // Функция проверки: нормализуем строки и убеждаемся, что у каждой title не пустой.
            // collect(...)->every(...) — «для всех элементов условие верно».
            fn ($links) => collect($this->normalizeLinks($links))->every(fn ($link) => $link['title'] !== '')
        ));
        // Правило проверки №2: адреса ссылок и логотипов должны быть безопасными.
        $this->addCheck(new FormValidatorCustom(
            $this,
            'links',
            FormValidatorCustom::FORM_VALIDATOR_OPTIONAL_VALUE,
            'plugins.block.partnerLinks.error.invalidUrl',
            fn ($links) => collect($this->normalizeLinks($links))->every(
                // Ссылка обязательно http/https; логотип — либо пустой, либо http/https, либо путь на нашем сайте.
                fn ($link) => self::isSafeUrl($link['url']) && ($link['logo'] === '' || self::isSafeUrl($link['logo'], true))
            )
        ));
        // Правило №3: принимаем данные только методом POST (а не из адресной строки).
        $this->addCheck(new FormValidatorPost($this));
        // Правило №4: в форме должен быть верный CSRF-токен (его добавляет {csrf} в шаблоне).
        // Так чужой сайт не сможет незаметно отправить форму от имени менеджера.
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Only absolute http(s) links are allowed, so a "javascript:" or "data:"
     * URL can never end up in the public page. Logos may also be site-relative.
     */
    // Проверка одного адреса. static — можно вызывать без объекта формы (так удобно тестировать).
    // $allowRelative = true разрешает ещё и пути на нашем сайте вида /public/... (для логотипов).
    public static function isSafeUrl(string $url, bool $allowRelative = false): bool
    {
        // Относительный путь: начинается с одного «/», но не с «//».
        // «//evil.com/...» браузер понял бы как адрес чужого сайта, поэтому его не пускаем.
        if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return true;
        }
        // parse_url(..., PHP_URL_SCHEME) достаёт схему: 'https', 'javascript', 'data' и т. п.
        // strtolower — чтобы «JaVaScRiPt:» не проскочил из-за больших букв.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        // Пропускаем только http/https И только если это в целом правильный URL.
        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Trim the submitted rows and drop the completely empty ones.
     *
     * @return array<array{title: string, url: string, logo: string}>
     */
    // Приводит присланные строки формы в порядок: обрезает пробелы и выкидывает пустые строки.
    protected function normalizeLinks($links): array
    {
        $result = [];
        // Если пришёл не массив (например, ничего) — перебираем пустой массив.
        foreach (is_array($links) ? $links : [] as $row) {
            // Собираем ссылку из трёх полей.
            // ?? '' — если поля нет, подставить пустую строку; trim() — убрать пробелы по краям.
            $link = [
                'title' => trim((string) ($row['title'] ?? '')),
                'url' => trim((string) ($row['url'] ?? '')),
                'logo' => trim((string) ($row['logo'] ?? '')),
            ];
            // Строку оставляем, если заполнено хоть одно поле. Полностью пустые — пропускаем.
            if ($link['title'] !== '' || $link['url'] !== '' || $link['logo'] !== '') {
                $result[] = $link;
            }
        }
        return $result;
    }

    /**
     * @copydoc Form::initData()
     */
    // Заполняет форму текущими значениями из базы (вызывается при открытии окна).
    public function initData()
    {
        // _data — внутреннее хранилище значений формы (из него шаблон берёт переменные).
        $this->_data = [
            'blockTitle' => $this->plugin->getSetting($this->contextId, 'blockTitle'),  // заголовок блока
            'links' => $this->plugin->getLinks($this->contextId),                        // список ссылок
        ];
    }

    /**
     * @copydoc Form::readInputData()
     */
    // Берёт из присланного запроса только эти два поля (остальное игнорируется).
    public function readInputData()
    {
        $this->readUserVars(['blockTitle', 'links']);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    // Готовит данные и рисует HTML формы.
    public function fetch($request, $template = null, $display = false)
    {
        // Берём текущие ссылки без пустых строк; array_values перенумеровывает с 0.
        $links = array_values($this->normalizeLinks($this->getData('links')));
        // Дополняем список пустыми строками до MAX_LINKS (8), чтобы было куда вписать новые ссылки.
        $rows = array_pad($links, PartnerLinksBlockPlugin::MAX_LINKS, ['title' => '', 'url' => '', 'logo' => '']);

        // Передаём в шаблон settingsForm.tpl:
        TemplateManager::getManager($request)->assign([
            'pluginName' => $this->plugin->getName(),   // имя плагина — нужно для адреса отправки формы
            // Строки таблицы. Если ссылок больше 8, показываем все (берём максимум из двух чисел).
            'linkRows' => array_slice($rows, 0, max(PartnerLinksBlockPlugin::MAX_LINKS, count($links))),
        ]);
        // Родитель отрисует шаблон и вернёт HTML.
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    // Сохранение. Вызывается только после успешной проверки (validate).
    public function execute(...$functionArgs)
    {
        // Пишем заголовок блока в plugin_settings (тип 'string').
        $this->plugin->updateSetting($this->contextId, 'blockTitle', trim((string) $this->getData('blockTitle')), 'string');
        // Пишем список ссылок: чистим, превращаем в JSON-строку и сохраняем.
        // JSON_UNESCAPED_UNICODE — хранить русские буквы как есть, а не в виде А...
        $this->plugin->updateSetting($this->contextId, 'links', json_encode($this->normalizeLinks($this->getData('links')), JSON_UNESCAPED_UNICODE), 'string');
        // Стандартные действия родителя после сохранения (в т. ч. вызов хуков).
        parent::execute(...$functionArgs);
    }
}
