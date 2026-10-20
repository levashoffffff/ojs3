{**
 * templates/frontend/pages/indexJournal.tpl
 * IPII Theme — journal homepage
 *}
{include file="frontend/components/header.tpl" pageTitleTranslated=$currentJournal->getLocalizedName()}

{* ── HERO IMAGE ─────────────────────────────────────────────── *}
{if $homepageImage}
	<div class="ipii-hero{if $issue} ipii-hero-behind-issue{/if}">
		<img src="{$publicFilesDir}/{$homepageImage.uploadName|escape:"url"}"
		     alt="{$homepageImageAltText|escape}">
	</div>
{/if}

{* ── MAIN ───────────────────────────────────────────────────── *}
<div class="container page-content ipii-homepage">

	{* ── Current issue ─────────────────────────────────────── *}
	{if $issue}
		<section class="ipii-current-issue ipii-animate-in">

			<div class="ipii-issue-header{if !$issue->getLocalizedCoverImageUrl()} ipii-issue-header-nocover{/if}">

				{* Cover image — left column *}
				{if $issue->getLocalizedCoverImageUrl()}
					<div class="ipii-issue-cover-col">
						<a href="{url op="view" page="issue" path=$issue->getBestIssueId()}">
							<img class="ipii-issue-cover-img"
							     src="{$issue->getLocalizedCoverImageUrl()|escape}"
							     alt="{$issue->getLocalizedCoverImageAltText()|escape|default:''}">
						</a>
					</div>
				{/if}

				{* Issue info — right column *}
				<div class="ipii-issue-info">
					<span class="ipii-issue-badge">{translate key="journal.currentIssue"}</span>
					<div class="ipii-issue-number">{$issue->getIssueSeries()|escape}</div>
					<div class="ipii-issue-date">
						{translate key="plugins.themes.ipii.currentIssuePublished" date=$issue->getDatePublished()|date_format:$dateFormatLong}
					</div>

					{if $issue->hasDescription()}
						<div class="ipii-issue-description">
							{$issue->getLocalizedDescription()|strip_unsafe_html}
						</div>
					{elseif $journalDescription}
						<div class="ipii-issue-description">
							{$journalDescription|strip_unsafe_html}
						</div>
					{/if}

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
		</section>

		{* ── Announcements ──────────────────────────────────── *}
		{if $numAnnouncementsHomepage && $announcements|@count}
			<section class="ipii-announcements ipii-animate-in ipii-animate-in-1">
				<h2 class="ipii-section-label" aria-label="{translate key="announcement.announcementsHome"}">
					{translate key="announcement.announcementsHome"}
				</h2>
				<div class="row g-3">
					{foreach from=$announcements item=announcement}
						<div class="col-md-4">
							{include file="frontend/objects/announcement_summary.tpl" heading="h3"}
						</div>
					{/foreach}
				</div>
			</section>
		{/if}

		{* ── Table of contents ──────────────────────────────── *}
		<section class="ipii-toc-wrapper ipii-animate-in ipii-animate-in-2">
			{include file="frontend/objects/issue_toc.tpl" sectionHeading="h3"}
		</section>

		<div class="ipii-archive-link">
			<a class="btn btn-outline-primary"
			   href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}">
				{translate key="journal.viewAllIssues"}
			</a>
		</div>
	{/if}

	{* ── Additional home content ────────────────────────────── *}
	{if $additionalHomeContent}
		<div class="ipii-additional-content">
			{$additionalHomeContent}
		</div>
	{/if}

</div>

{include file="frontend/components/footer.tpl"}
