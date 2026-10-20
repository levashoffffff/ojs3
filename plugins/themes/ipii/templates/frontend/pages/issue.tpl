{**
 * templates/frontend/pages/issue.tpl
 * IPII Theme — single issue page
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$issueIdentification}

<div class="container page-issue">

	{* No issue *}
	{if !$issue}
		<div class="ipii-issue-empty">
			{include file="frontend/components/notification.tpl" messageKey="current.noCurrentIssueDesc"}
		</div>

	{* Issue with TOC *}
	{else}

		{* Preview notice *}
		{if !$issue->getPublished()}
			{include file="frontend/components/notification.tpl" messageKey="editor.issues.preview"}
		{/if}

		{* ── Issue header: cover left + info right ──────────── *}
		<div class="ipii-issue-header{if !$issue->getLocalizedCoverImageUrl()} ipii-issue-header-nocover{/if} ipii-animate-in">

			{* Cover image *}
			{if $issue->getLocalizedCoverImageUrl()}
				<div class="ipii-issue-cover-col">
					<img class="ipii-issue-cover-img"
					     src="{$issue->getLocalizedCoverImageUrl()|escape}"
					     alt="{$issue->getLocalizedCoverImageAltText()|escape|default:''}">
				</div>
			{/if}

			{* Info *}
			<div class="ipii-issue-info">
				<h1 class="ipii-issue-number">{$issue->getIssueSeries()|escape}</h1>

				{if $issue->getDatePublished()}
					<div class="ipii-issue-date">
						{translate key="plugins.themes.ipii.currentIssuePublished" date=$issue->getDatePublished()|date_format:$dateFormatLong}
					</div>
				{/if}

				{* Pub IDs (DOI etc.) *}
				{foreach from=$pubIdPlugins item=pubIdPlugin}
					{assign var=pubId value=$issue->getStoredPubId($pubIdPlugin->getPubIdType())}
					{if $pubId}
						{assign var="doiUrl" value=$pubIdPlugin->getResolvingURL($currentJournal->getId(), $pubId)|escape}
						<div class="ipii-issue-pubid">
							{if $pubIdPlugin->getPubIdType() == 'doi'}
								DOI: <a href="{$doiUrl}">{$doiUrl}</a>
							{else}
								{translate key="plugins.themes.ipii.issuePubId" pubIdType=$pubIdPlugin->getPubIdDisplayType()|escape pubId=$pubId}
							{/if}
						</div>
					{/if}
				{/foreach}

				{* Description *}
				{if $issue->hasDescription()}
					<div class="ipii-issue-description">
						{$issue->getLocalizedDescription()|strip_unsafe_html}
					</div>
				{/if}

				{* Full-issue galleys *}
				{if $issueGalleys}
					<div class="ipii-issue-galleys">
						{foreach from=$issueGalleys item=galley}
							{include file="frontend/objects/galley_link.tpl" parent=$issue
							         purchaseFee=$currentJournal->getSetting('purchaseIssueFee')
							         purchaseCurrency=$currentJournal->getSetting('currency')}
						{/foreach}
					</div>
				{/if}
			</div>

		</div>{* /ipii-issue-header *}

		{* ── Table of contents ──────────────────────────────── *}
		<div class="ipii-toc-wrapper ipii-animate-in ipii-animate-in-1">
			{include file="frontend/objects/issue_toc.tpl" sectionHeading="h2"}
		</div>

	{/if}
</div>

{include file="frontend/components/footer.tpl"}
