{**
 * templates/frontend/pages/privacy.tpl
 * IPII Theme — privacy policy
 *}
{include file="frontend/components/header.tpl" pageTitle="manager.setup.privacyStatement"}

<div class="container page-about">

	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="manager.setup.privacyStatement"}</h1>
	</div>

	<div class="ipii-about-body ipii-animate-in ipii-animate-in-1">
		<div class="ipii-about-content">
			{$currentContext->getLocalizedData('privacyStatement')}
		</div>
	</div>

</div>

{include file="frontend/components/footer.tpl"}
