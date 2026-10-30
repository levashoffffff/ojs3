{**
 * templates/block.tpl
 *
 * @brief Sidebar block with partner / indexing service links.
 *
 * @uses $partnerLinks array List of ['title', 'url', 'logo']
 * @uses $partnerLinksTitle string Custom block title (optional)
 *}
{* Это шаблон Smarty. Всё в фигурных скобках — команды Smarty, остальное — обычный HTML.
   Переменные $partnerLinks и $partnerLinksTitle сюда передал метод getContents() плагина. *}

{* Общая обёртка блока. Класс pkp_block даёт стандартный вид блока из темы,
   block_partner_links — наш собственный класс для своих стилей (см. <style> внизу). *}
<div class="pkp_block block_partner_links">
	{* Заголовок блока *}
	<h2 class="title">
		{* Если менеджер задал свой заголовок — выводим его, иначе стандартный текст из locale.po.
		   |escape — экранирование: символы < > " & превращаются в безопасные коды,
		   поэтому введённый HTML покажется как текст и не выполнится (защита от XSS). *}
		{if $partnerLinksTitle}{$partnerLinksTitle|escape}{else}{translate key="plugins.block.partnerLinks.title"}{/if}
	</h2>
	<div class="content">
		{* Список ссылок *}
		<ul class="partner_links">
			{* Цикл: для каждого элемента массива $partnerLinks создаём переменную $partnerLink *}
			{foreach from=$partnerLinks item=partnerLink}
				<li>
					{* Ссылка на сайт партнёра.
					   target="_blank" — открыть в новой вкладке;
					   rel="noopener noreferrer" — новая вкладка не получит доступа к нашей странице
					   и не узнает, с какой страницы пришёл посетитель. *}
					<a href="{$partnerLink.url|escape}" target="_blank" rel="noopener noreferrer">
						{* Логотип выводим, только если адрес картинки заполнен *}
						{if $partnerLink.logo}
							{* alt="" — картинка декоративная, название и так написано рядом текстом;
							   loading="lazy" — браузер загрузит картинку, только когда до неё долистают *}
							<img class="partner_logo" src="{$partnerLink.logo|escape}" alt="" loading="lazy">
						{/if}
						{* Название ссылки *}
						<span class="partner_title">{$partnerLink.title|escape}</span>
					</a>
				</li>
			{/foreach}
		</ul>
	</div>
</div>

{* Стили блока. Селекторы начинаются с .pkp_block.block_partner_links — так они «сильнее»
   стилей темы для подвала сайта и не задевают другие блоки. *}
<style>
	{* Белый текст заголовка на синем фоне, без отступа снизу *}
	.pkp_block.block_partner_links .title { color: #fff; margin-bottom: 0; }
	{* Убираем внутренние отступы контейнера — отступы зададим самим ссылкам *}
	.pkp_block.block_partner_links .content { padding: 0; }
	{* Ссылка — строка из логотипа и текста: flex ставит их в ряд, gap — расстояние между ними *}
	.pkp_block.block_partner_links .partner_links li a { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 1rem; }
	{* Логотип 40×40, картинка вписывается целиком без искажения, не сжимается *}
	.pkp_block.block_partner_links .partner_logo { width: 40px; height: 40px; object-fit: contain; flex-shrink: 0; }
</style>
