{**
 * templates/frontend/pages/navigationMenuItemViewContent.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Display NavigationMenuItem custom page content
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$title}

<div class="container page-custom">

	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{$title|escape}</h1>
	</div>

	<div class="ipii-about-body page-content ipii-animate-in ipii-animate-in-1">
		{$content}
	</div>

</div>

{include file="frontend/components/footer.tpl"}
