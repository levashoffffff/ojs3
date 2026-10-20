{**
 * templates/frontend/pages/about.tpl
 * IPII Theme — about the journal
 *}
{include file="frontend/components/header.tpl" pageTitle="about.aboutContext"}

<div class="container page-about">

	{* Page title *}
	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="about.aboutContext"}</h1>
	</div>

	{* Content *}
	<div class="ipii-about-body ipii-animate-in ipii-animate-in-1">
		<div class="ipii-about-content">
			{$currentContext->getLocalizedData('about')}
		</div>
		{include file="frontend/components/editLink.tpl"
			page="management" op="settings" path="context"
			anchor="masthead" sectionTitleKey="about.aboutContext"}
	</div>

</div>

{include file="frontend/components/footer.tpl"}
