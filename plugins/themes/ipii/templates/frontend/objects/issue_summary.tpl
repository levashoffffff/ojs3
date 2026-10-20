{**
 * templates/frontend/objects/issue_summary.tpl
 * IPII Theme — issue tile card for archive grid
 *}
{capture assign="issueTitle"}
	{if $issue->getIssueSeries()}
		{$issue->getIssueSeries()|escape}
	{elseif $issue->getShowTitle() && $issue->getLocalizedTitle()}
		{$issue->getLocalizedTitle()|escape}
	{else}
		{translate key="issue.issue"}
	{/if}
{/capture}

<a class="ipii-issue-card" href="{url op="view" path=$issue->getBestIssueId()}">

	{* Cover image or placeholder *}
	<div class="ipii-issue-card-cover">
		{if $issue->getLocalizedCoverImageUrl()}
			<img src="{$issue->getLocalizedCoverImageUrl()|escape}"
			     alt="{$issue->getLocalizedCoverImageAltText()|escape|default:''}">
		{else}
			<div class="ipii-issue-card-placeholder">
				<span>{$issue->getData('number')|escape|default:$issue->getData('year')|escape}</span>
			</div>
		{/if}
	</div>

	{* Text info *}
	<div class="ipii-issue-card-body">
		<div class="ipii-issue-card-series">{$issueTitle}</div>
		{if $issue->getDatePublished()}
			<div class="ipii-issue-card-date">
				{$issue->getDatePublished()|date_format:$dateFormatLong}
			</div>
		{/if}
	</div>

</a>
