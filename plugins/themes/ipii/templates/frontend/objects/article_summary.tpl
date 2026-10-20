{**
 * templates/frontend/objects/article_summary.tpl
 * IPII Theme — article list item (issue TOC, search, homepage)
 *}
{assign var="articlePath"   value=$article->getBestId()}
{assign var="publication"   value=$article->getCurrentPublication()}
{assign var="submissionPages" value=$publication->getData('pages')}
{assign var="submissionDatePublished" value=$publication->getData('datePublished')}

{if (!$section.hideAuthor && $publication->getData('hideAuthor') == \APP\submission\Submission::AUTHOR_TOC_DEFAULT) || $publication->getData('hideAuthor') == \APP\submission\Submission::AUTHOR_TOC_SHOW}
	{assign var="showAuthor" value=true}
{/if}

<article class="ipii-article-item">

	{* Authors + pages *}
	{if $showAuthor || $submissionPages}
		<div class="ipii-article-item-meta">
			{if $showAuthor}
				<span class="ipii-article-item-authors">
					{$publication->getAuthorString($authorUserGroups)|escape}
				</span>
			{/if}
			{if $submissionPages}
				<span class="ipii-article-item-pages">{$submissionPages|escape}</span>
			{/if}
		</div>
	{/if}

	{* Title *}
	<h3 class="ipii-article-item-title">
		<a {if $journal}href="{url journal=$journal->getPath() page="article" op="view" path=$articlePath}"{else}href="{url page="article" op="view" path=$articlePath}"{/if}>
			{$publication->getLocalizedFullTitle(null, 'html')|strip_unsafe_html}
		</a>
	</h3>

	{* Date (when explicitly requested) *}
	{if $showDatePublished && $submissionDatePublished}
		<div class="ipii-article-item-date">
			{$submissionDatePublished|date_format:$dateFormatLong}
		</div>
	{/if}

	{* DOI *}
	{if $requestedPage === 'issue'}
		{foreach from=$pubIdPlugins item=pubIdPlugin}
			{if $pubIdPlugin->getPubIdType() != 'doi'}{continue}{/if}
			{assign var=pubId value=$article->getStoredPubId($pubIdPlugin->getPubIdType())}
			{if $pubId}
				{assign var="doiUrl" value=$pubIdPlugin->getResolvingURL($currentJournal->getId(), $pubId)|escape}
				<div class="ipii-article-item-doi"><a href="{$doiUrl}">{$doiUrl}</a></div>
			{/if}
		{/foreach}
	{elseif ($requestedPage === "search" || $requestedPage === "catalog") && $article->getStoredPubId('doi')}
		{assign var="doiUrl" value=$article->getStoredPubId('doi')|substr_replace:'https://doi.org/':0:0|escape}
		{if $doiUrl}
			<div class="ipii-article-item-doi"><a href="{$doiUrl}">{$doiUrl}</a></div>
		{/if}
	{/if}

	{* Galleys *}
	{assign var="galleys" value=$article->getGalleys()}
	{if !$hideGalleys && $galleys}
		<div class="ipii-article-item-galleys">
			{foreach from=$galleys item=galley}
				{if $primaryGenreIds}
					{assign var="file" value=$galley->getFile()}
					{if !$galley->getData('urlRemote') && !($file && in_array($file->getGenreId(), $primaryGenreIds))}
						{continue}
					{/if}
				{/if}
				{assign var="hasArticleAccess" value=$hasAccess}
				{if $currentContext->getSetting('publishingMode') == \APP\journal\Journal::PUBLISHING_MODE_OPEN || $publication->getData('accessStatus') == \APP\submission\Submission::ARTICLE_ACCESS_OPEN}
					{assign var="hasArticleAccess" value=1}
				{/if}
				{include file="frontend/objects/galley_link.tpl" parent=$article publication=$publication hasAccess=$hasArticleAccess}
			{/foreach}
		</div>
	{/if}

	{call_hook name="Templates::Issue::Issue::Article"}
</article>
