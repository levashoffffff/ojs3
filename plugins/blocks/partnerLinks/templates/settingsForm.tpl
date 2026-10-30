{**
 * templates/settingsForm.tpl
 *
 * @brief Partner links block settings: block title and link rows.
 *}
{* Шаблон формы настроек, которая открывается во всплывающем окне в админке.
   Переменные сюда передаёт метод fetch() класса PartnerLinksSettingsForm. *}

{* JavaScript: подключаем к форме стандартный обработчик OJS.
   Он отправляет форму без перезагрузки страницы (AJAX), показывает ошибки
   и закрывает окно при успешном сохранении.
   {ldelim} и {rdelim} — это «{» и «}»: напрямую их писать нельзя, Smarty примет их за свои команды. *}
<script>
	$(function() {ldelim}
		$('#partnerLinksSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

{* Сама форма. method="post" — данные уходят в теле запроса (это проверяет FormValidatorPost).
   action — адрес отправки: тот же manage() нашего плагина, но с save=true,
   поэтому плагин поймёт, что нужно сохранить. *}
<form class="pkp_form" id="partnerLinksSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="blocks" plugin=$pluginName verb="settings" save=true}">
	{* {csrf} вставляет скрытое поле с CSRF-токеном — его проверяет FormValidatorCSRF *}
	{csrf}
	{* Место, куда OJS выводит сообщения (например, «Сохранено» или ошибки) *}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="partnerLinksSettingsFormNotification"}

	{* Поясняющий текст над формой (берётся из locale.po) *}
	<p>{translate key="plugins.block.partnerLinks.settings.description"}</p>

	{* fbvFormArea / fbvElement — стандартные «конструкторы» полей OJS.
	   Создаём текстовое поле blockTitle (заголовок блока) с подписью и средней шириной. *}
	{fbvFormArea id="partnerLinksTitleArea"}
		{fbvElement type="text" id="blockTitle" value=$blockTitle label="plugins.block.partnerLinks.settings.blockTitle" size=$fbvStyles.size.MEDIUM}
	{/fbvFormArea}

	{* Таблица со строками для ссылок *}
	<table class="pkp_table partner_links_rows" style="width: 100%;">
		{* Шапка таблицы: №, Название, Адрес ссылки, Логотип *}
		<thead>
			<tr>
				<th style="width: 2rem;">№</th>
				<th>{translate key="plugins.block.partnerLinks.settings.linkTitle"}</th>
				<th>{translate key="plugins.block.partnerLinks.settings.linkUrl"}</th>
				<th>{translate key="plugins.block.partnerLinks.settings.linkLogo"}</th>
			</tr>
		</thead>
		<tbody>
			{* Цикл по строкам: $i — номер строки (0, 1, 2...), $row — данные строки *}
			{foreach from=$linkRows key=i item=row}
				<tr>
					{* Номер строки для человека: с 1, а не с 0 *}
					<td>{$i+1}</td>
					{* Имена полей вида links[0][title] — PHP соберёт из них вложенный массив:
					   links = [ 0 => [title, url, logo], 1 => [...], ... ].
					   value — текущее значение (экранировано), maxlength — ограничение длины. *}
					<td><input type="text" name="links[{$i}][title]" value="{$row.title|escape}" maxlength="255" style="width: 100%;"></td>
					{* type="url" — браузер сам подсветит явно неверный адрес (но главная проверка — на сервере) *}
					<td><input type="url" name="links[{$i}][url]" value="{$row.url|escape}" maxlength="2048" placeholder="https://" style="width: 100%;"></td>
					{* Логотип — type="text", потому что разрешаем и относительные пути /public/... *}
					<td><input type="text" name="links[{$i}][logo]" value="{$row.logo|escape}" maxlength="2048" placeholder="https://" style="width: 100%;"></td>
				</tr>
			{/foreach}
		</tbody>
	</table>
	{* Подсказка под таблицей: откуда взять логотип и какие адреса допустимы *}
	<p class="description">{translate key="plugins.block.partnerLinks.settings.hint"}</p>

	{* Стандартные кнопки «Сохранить» и «Отмена» *}
	{fbvFormButtons}
</form>
