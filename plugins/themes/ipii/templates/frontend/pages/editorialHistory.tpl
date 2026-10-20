{**
 * templates/frontend/pages/editorialHistory.tpl
 * IPII Theme — editorial history
 *}
{include file="frontend/components/header.tpl" pageTitle="common.editorialHistory"}

<div class="container page-masthead">

	{* Page header *}
	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="common.editorialHistory.page"}</h1>
	</div>

	<div class="ipii-masthead-body ipii-animate-in ipii-animate-in-1">

		<p class="ipii-masthead-history-link">
			{translate key="common.editorialHistory.page.description"}
		</p>

		{* Roles and past members *}
		{foreach from=$mastheadRoles item="mastheadRole"}
			{if array_key_exists($mastheadRole->id, $mastheadUsers)}
				<div class="ipii-masthead-group">
					<h2 class="ipii-masthead-role">{$mastheadRole->getLocalizedData('name')|escape}</h2>
					<ul class="ipii-masthead-list" role="list">
						{foreach from=$mastheadUsers[$mastheadRole->id] item="mastheadUser"}
							<li class="ipii-masthead-member">
								<div class="ipii-masthead-member-name">
									{$mastheadUser['user']->getFullName()|escape}
									{if $mastheadUser['user']->getData('orcid') && $mastheadUser['user']->hasVerifiedOrcid()}
										<a class="ipii-masthead-orcid"
										   href="{$mastheadUser['user']->getData('orcid')|escape}"
										   target="_blank"
										   aria-label="{translate key="common.editorialHistory.page.orcidLink" name=$mastheadUser['user']->getFullName()|escape}">
											{$orcidIcon}
										</a>
									{/if}
								</div>
								{if !empty($mastheadUser['user']->getLocalizedData('affiliation'))}
									<div class="ipii-masthead-member-affil">
										{$mastheadUser['user']->getLocalizedData('affiliation')|escape}
									</div>
								{/if}
								<div class="ipii-masthead-member-date">
									{foreach name="services" from=$mastheadUser['services'] item="service"}
										{translate key="common.fromUntil" from=$service['dateStart'] until=$service['dateEnd']}
										{if !$smarty.foreach.services.last}{translate key="common.commaListSeparator"}{/if}
									{/foreach}
								</div>
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}
		{/foreach}

		{* Additional editorial history content *}
		{if $currentContext->getLocalizedData('editorialHistory')}
			<div class="ipii-masthead-group ipii-about-content">
				{$currentContext->getLocalizedData('editorialHistory')}
			</div>
		{/if}

		{include file="frontend/components/editLink.tpl"
			page="management" op="settings" path="context"
			anchor="masthead" sectionTitleKey="common.editorialHistory"}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
