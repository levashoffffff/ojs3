{**
 * templates/frontend/components/header.tpl
 * IPII Theme — site header (horizontal nav)
 *}

{assign var="showingLogo" value=true}
{if !$displayPageHeaderLogo}
	{assign var="showingLogo" value=false}
{/if}

{capture assign="homeUrl"}
	{url page="index" router=$smarty.const.ROUTE_PAGE}
{/capture}

{if $requestedOp == 'index'}
	{assign var="siteNameTag" value="h1"}
{else}
	{assign var="siteNameTag" value="div"}
{/if}

{capture assign="brand"}{strip}
	{if $displayPageHeaderLogo}
		<img src="{$publicFilesDir}/{$displayPageHeaderLogo.uploadName|escape:"url"}"
		     {if $displayPageHeaderLogo.altText != ''}alt="{$displayPageHeaderLogo.altText|escape}"
		     {else}alt="{translate key="common.pageHeaderLogo.altText"}"{/if}
		     class="img-fluid">
	{elseif $displayPageHeaderTitle}
		<span class="ipii-brand-text">{$displayPageHeaderTitle|escape}</span>
	{else}
		<img src="{$baseUrl}/templates/images/structure/logo.png" alt="{$applicationName|escape}" class="img-fluid">
	{/if}
{/strip}{/capture}

<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{if !$pageTitleTranslated}{capture assign="pageTitleTranslated"}{translate key=$pageTitle}{/capture}{/if}
{include file="frontend/components/headerHead.tpl"}
<body class="body ipii-body" dir="{$currentLocaleLangDir|escape|default:"ltr"}">

<header class="ipii-header">
	<div class="container">
		<{$siteNameTag} class="visually-hidden">{$pageTitleTranslated|escape}</{$siteNameTag}>

		{* Single navbar: logo + nav + user — all in one row *}
		<nav class="navbar navbar-expand-lg ipii-navbar"
		     aria-label="{translate key="common.navigation.main"}">

			{* Brand / logo *}
			<a class="navbar-brand ipii-brand" href="{$homeUrl}">
				{$brand}
			</a>

			{* Mobile: language + burger *}
			<div class="d-flex align-items-center gap-2 d-lg-none">
				{include file="frontend/components/languageSwitcher.tpl" id="languageSmallNav"}
				<button class="navbar-toggler ipii-toggler" type="button"
				        data-bs-toggle="collapse" data-bs-target="#ipiiMainNav"
				        aria-controls="ipiiMainNav" aria-expanded="false"
				        aria-label="{translate key="plugins.themes.ipii.nav.toggle"}">
					<span class="ipii-toggler-icon">
						<span></span><span></span><span></span>
					</span>
				</button>
			</div>

			{* Collapsible content *}
			<div class="collapse navbar-collapse" id="ipiiMainNav">

				{* Primary menu — horizontal *}
				{capture assign="primaryMenu"}
					{load_menu name="primary" id="primaryNav" ulClass="navbar-nav ipii-nav-list" liClass="nav-item"}
				{/capture}
				{if !empty(trim($primaryMenu)) || $currentContext}
					{$primaryMenu}
				{/if}

				{* Right-side: submit CTA + search + user menu + language *}
				<div class="ms-auto d-flex align-items-center gap-2 ipii-nav-right">
					{if $currentContext}
					<a href="{url page="submission" op="wizard"}" class="ipii-submit-cta">
						{translate key="plugins.themes.ipii.nav.submitArticle"}
					</a>
					{/if}
					{if $currentContext && $requestedPage !== 'search'}
					<button type="button" class="ipii-search-toggle" id="ipiiSearchToggle"
					        aria-label="{translate key="common.search"}"
					        aria-expanded="false" aria-controls="ipiiSearchOverlay">
						<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<circle cx="11" cy="11" r="8"/>
							<line x1="21" y1="21" x2="16.65" y2="16.65"/>
						</svg>
						<span class="ipii-search-toggle-label">{translate key="common.search"}</span>
					</button>
					{/if}
					{load_menu name="user" id="userNav" ulClass="navbar-nav" liClass="nav-item"}
					{include file="frontend/components/languageSwitcher.tpl" id="languageLargeNav"}
				</div>

			</div>
		</nav>
	</div>
	{if $currentContext && $requestedPage !== 'search'}
	<div class="ipii-search-modal" id="ipiiSearchOverlay" aria-hidden="true" role="dialog" aria-modal="true" aria-label="{translate key="common.search"}">
		<button type="button" class="ipii-search-modal__close" id="ipiiSearchClose" aria-label="{translate key="common.close"}">
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
				<line x1="18" y1="6" x2="6" y2="18"/>
				<line x1="6" y1="6" x2="18" y2="18"/>
			</svg>
		</button>
		<div class="ipii-search-modal__body" role="search">
			<p class="ipii-search-modal__hint">{translate key="common.search"}</p>
			<form action="{url page="search"}" method="get" class="ipii-search-modal__form">
				<div class="ipii-search-modal__field">
					<input type="search" name="query" id="ipiiSearchInput"
					       class="ipii-search-modal__input"
					       placeholder="{translate key="common.searchQuery"}"
					       aria-label="{translate key="common.search"}"
					       autocomplete="off">
					<button type="submit" class="ipii-search-modal__submit" aria-label="{translate key="common.search"}">
						<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
							<circle cx="11" cy="11" r="8"/>
							<line x1="21" y1="21" x2="16.65" y2="16.65"/>
						</svg>
					</button>
				</div>
			</form>
		</div>
	</div>
	<script>
	(function () {
		var toggle  = document.getElementById('ipiiSearchToggle');
		var overlay = document.getElementById('ipiiSearchOverlay');
		var close   = document.getElementById('ipiiSearchClose');
		var input   = document.getElementById('ipiiSearchInput');
		if (!toggle || !overlay) { return; }

		function openSearch() {
			overlay.classList.add('is-open');
			document.body.classList.add('ipii-search-open');
			toggle.setAttribute('aria-expanded', 'true');
			overlay.setAttribute('aria-hidden', 'false');
			setTimeout(function () { input.focus(); }, 50);
		}
		function closeSearch() {
			overlay.classList.remove('is-open');
			document.body.classList.remove('ipii-search-open');
			toggle.setAttribute('aria-expanded', 'false');
			overlay.setAttribute('aria-hidden', 'true');
			toggle.focus();
		}

		toggle.addEventListener('click', function () {
			overlay.classList.contains('is-open') ? closeSearch() : openSearch();
		});
		if (close) { close.addEventListener('click', closeSearch); }

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) { closeSearch(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay.classList.contains('is-open')) { closeSearch(); }
		});
	}());
	</script>
	{/if}
</header>
