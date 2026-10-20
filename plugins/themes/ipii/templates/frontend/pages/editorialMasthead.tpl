{**
 * templates/frontend/pages/editorialMasthead.tpl
 * IPII Theme — editorial masthead
 *}
{include file="frontend/components/header.tpl" pageTitle="common.editorialMasthead"}

<div class="container page-masthead">

	{* Page header *}
	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="common.editorialMasthead"}</h1>
	</div>

	<div class="ipii-masthead-body ipii-animate-in ipii-animate-in-1">

		{* Link to editorial history *}
		<p class="ipii-masthead-history-link">
			{capture assign=editorialHistoryUrl}{url page="about" op="editorialHistory" router=\PKP\core\PKPApplication::ROUTE_PAGE}{/capture}
			{translate key="about.editorialMasthead.linkToEditorialHistory" url=$editorialHistoryUrl}
		</p>

		{* Roles and members *}
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
									{translate key="common.fromUntil" from=$mastheadUser['dateStart'] until=""}
								</div>
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}
		{/foreach}

		{* Peer reviewers *}
		{if !empty($reviewers)}
			<div class="ipii-masthead-group">
				<h2 class="ipii-masthead-role">{translate key="common.editorialMasthead.peerReviewers"}</h2>
				<p class="ipii-masthead-reviewers-desc">
					{translate key="common.editorialMasthead.peerReviewers.description" year=$previousYear}
				</p>
				<ul class="ipii-masthead-list ipii-masthead-list-reviewers" role="list">
					{foreach from=$reviewers item="reviewer"}
						<li class="ipii-masthead-member">
							<div class="ipii-masthead-member-name">
								{$reviewer->getFullName()|escape}
								{if $reviewer->getData('orcid') && $reviewer->hasVerifiedOrcid()}
									<a class="ipii-masthead-orcid"
									   href="{$reviewer->getData('orcid')|escape}"
									   target="_blank"
									   aria-label="{translate key="common.editorialHistory.page.orcidLink" name=$reviewer->getFullName()|escape}">
										{$orcidIcon}
									</a>
								{/if}
							</div>
							{if !empty($reviewer->getLocalizedData('affiliation'))}
								<div class="ipii-masthead-member-affil">
									{$reviewer->getLocalizedData('affiliation')|escape}
								</div>
							{/if}
						</li>
					{/foreach}
				</ul>
			</div>
		{/if}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
