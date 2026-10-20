{**
 * templates/frontend/objects/article_details.tpl
 * IPII Theme — full article view
 *}

{* ── HEADER ──────────────────────────────────────────────── *}
<div class="ipii-article-header">

	{* Outdated version notice *}
	{if $currentPublication->getId() !== $publication->getId()}
		{capture assign="latestVersionUrl"}{url page="article" op="view" path=$article->getBestId()}{/capture}
		<div class="alert alert-primary mb-3" role="alert">
			{translate key="submission.outdatedVersion"
				datePublished=$publication->getData('datePublished')|date_format:$dateFormatShort
				urlRecentVersion=$latestVersionUrl|escape}
		</div>
	{/if}

	{* Breadcrumb — once, no duplication *}
	{if $issue || $section}
		<nav class="ipii-breadcrumb" aria-label="breadcrumb">
			{if $issue}
				<a href="{url page="issue" op="view" path=$issue->getBestIssueId()}">{$issue->getIssueSeries()|escape}</a>
			{/if}
			{if $issue && $section}<span class="ipii-breadcrumb-sep">/</span>{/if}
			{if $section}<span>{$section->getLocalizedTitle()|escape}</span>{/if}
		</nav>
	{/if}

	{* Title *}
	<h1 class="ipii-article-title">
		{$publication->getLocalizedFullTitle(null, 'html')|strip_unsafe_html}
	</h1>

	{* Authors inline *}
	{if $publication->getData('authors')}
		<div class="ipii-authors-inline">
			{foreach from=$publication->getData('authors') item=authorStr key=authorStrKey}
				{strip}<span class="ipii-author-chip">
					{if count($authorStr->getAffiliations()) > 0 || $authorStr->getLocalizedBiography() || $authorStr->getData('orcid')}
						<a class="author-string-href ipii-author-link" href="#author-{$authorStrKey+1}">
							{$authorStr->getFullName()|escape}<sup class="author-symbol author-plus">+</sup><sup class="author-symbol author-minus hidden">−</sup>
						</a>
					{else}
						<span class="ipii-author-name">{$authorStr->getFullName()|escape}</span>
					{/if}
					{if $authorStr->getData('orcid')}
						<a class="ipii-orcid-badge" href="{$authorStr->getData('orcid')|escape}" target="_blank">
							{if $authorStr->hasVerifiedOrcid()}{$orcidIcon}{else}{$orcidUnauthenticatedIcon}{/if}
						</a>
					{/if}
					{if !$smarty.foreach.loop.last}<span class="ipii-author-sep">,&nbsp;</span>{/if}
				</span>{/strip}
			{/foreach}
		</div>

		{* Affiliation blocks (expandable via JS) *}
		<div class="ipii-affiliations-wrap">
			{foreach from=$publication->getData('authors') item=author key=authorKey}
				<div class="ipii-affil-card article-details-author hideAuthor" id="author-{$authorKey+1}">
					<span class="ipii-affil-name">{$author->getFullName()|escape}</span>
					{if count($author->getAffiliations()) > 0}
						{foreach from=$author->getAffiliations() item=affiliation}
							<span class="ipii-affil-org">
								{$affiliation->getLocalizedName()|escape}
								{if $affiliation->getRor()}
									<a class="rorImage" href="{$affiliation->getRor()|escape}">{$rorIdIcon}</a>
								{/if}
							</span>
						{/foreach}
					{/if}
					{if $author->getData('orcid')}
						<a class="ipii-affil-orcid" href="{$author->getData('orcid')|escape}" target="_blank">
							{if $author->hasVerifiedOrcid()}{$orcidIcon}{else}{$orcidUnauthenticatedIcon}{/if}
							{$author->getOrcidDisplayValue()|escape}
						</a>
					{/if}
					{if $author->getLocalizedBiography()}
						<button type="button" class="btn btn-sm btn-outline-secondary mt-2"
							data-bs-toggle="modal" data-bs-target="#ipii-bio-{$authorKey+1}">
							{translate key="plugins.themes.ipii.article.authorBio"}
						</button>
						{capture append="authorBiographyModalsTemp"}
							<div class="modal fade" id="ipii-bio-{$authorKey+1}" tabindex="-1" aria-hidden="true">
								<div class="modal-dialog"><div class="modal-content">
									<div class="modal-header">
										<span class="modal-title fw-semibold">{$author->getFullName()|escape}</span>
										<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
									</div>
									<div class="modal-body">{$author->getLocalizedBiography()|strip_unsafe_html}</div>
								</div></div>
							</div>
						{/capture}
					{/if}
				</div>
			{/foreach}
		</div>
	{/if}

	{* Meta: date + DOI *}
	<div class="ipii-article-meta">
		{if $publication->getData('datePublished')}
			<span class="ipii-meta-item">
				{if $firstPublication->getId() === $publication->getId()}
					{translate key="submissions.published"}&nbsp;{$firstPublication->getData('datePublished')|date_format:$dateFormatShort}
				{else}
					{translate key="submission.updatedOn"
						datePublished=$firstPublication->getData('datePublished')|date_format:$dateFormatShort
						dateUpdated=$publication->getData('datePublished')|date_format:$dateFormatShort}
				{/if}
			</span>
		{/if}
		{* OJS 3.5 keeps the DOI as a DOI object on the publication, not as a pub-id plugin *}
		{assign var=doiObject value=$publication->getData('doiObject')}
		{if $doiObject}
			{assign var="doiUrl" value=$doiObject->getData('resolvingUrl')|escape}
			<span class="ipii-meta-item">
				DOI:&nbsp;<a href="{$doiUrl}">{$doiUrl}</a>
			</span>
		{/if}
		{* UDC: field added by the JATS export plugin *}
		{if $publication->getData('udk')}
			<span class="ipii-meta-item">
				{translate key="plugins.themes.ipii.article.udk"}&nbsp;{$publication->getData('udk')|escape}
			</span>
		{/if}
	</div>

	{* Galley download buttons *}
	{if $primaryGalleys}
		<div class="ipii-article-galleys">
			{foreach from=$primaryGalleys item=galley}
				{include file="frontend/objects/galley_link.tpl" parent=$article publication=$publication galley=$galley purchaseFee=$currentJournal->getData('purchaseArticleFee') purchaseCurrency=$currentJournal->getData('currency')}
			{/foreach}
		</div>
	{/if}

</div>{* /ipii-article-header *}

{* ── TWO-COLUMN BODY ─────────────────────────────────────── *}
<div class="row g-4 ipii-article-body">

	{* ── SIDEBAR ────────────────────────────────────────── *}
	<div class="col-lg-4 order-lg-2">
		<div class="ipii-article-sidebar">

			{* Flush author bio modals to footer *}
			{capture name="authorBiographyModals"}
				{foreach from=$authorBiographyModalsTemp item="modal"}{$modal}{/foreach}
			{/capture}

			{* Keywords *}
			{assign var="kwData" value=$publication->getLocalizedData('keywords')}
			{if $kwData}
				<div class="ipii-sidebar-card">
					<h2 class="ipii-sidebar-label">{translate key="article.subject"}</h2>
					<div class="ipii-kw-list">
						{foreach from=$kwData item=kw}
							<span class="ipii-kw-pill">
								{if is_array($kw)}{$kw|@implode:', '|escape}{else}{$kw|escape}{/if}
							</span>
						{/foreach}
					</div>
				</div>
			{/if}

			{* Supplementary files *}
			{if $supplementaryGalleys}
				<div class="ipii-sidebar-card">
					<h2 class="ipii-sidebar-label">{translate key="plugins.themes.ipii.article.supplementaryFiles"}</h2>
					{foreach from=$supplementaryGalleys item=galley}
						{include file="frontend/objects/galley_link.tpl" parent=$article publication=$publication galley=$galley isSupplementary="1"}
					{/foreach}
				</div>
			{/if}

			{* Cover image (article or issue) *}
			{if $publication->getLocalizedData('coverImage') || ($issue && $issue->getLocalizedCoverImage())}
				<div class="ipii-sidebar-card ipii-sidebar-cover">
					{if $publication->getLocalizedData('coverImage')}
						{assign var="coverImage" value=$publication->getLocalizedData('coverImage')}
						<img class="img-fluid" src="{$publication->getLocalizedCoverImageUrl($article->getData('contextId'))|escape}" alt="{$coverImage.altText|escape|default:''}">
					{else}
						<a href="{url page="issue" op="view" path=$issue->getBestIssueId()}">
							<img class="img-fluid" src="{$issue->getLocalizedCoverImageUrl()|escape}" alt="{$issue->getLocalizedCoverImageAltText()|escape|default:''}">
						</a>
					{/if}
				</div>
			{/if}

			{* Other versions *}
			{if $publication->getData('datePublished') && count($article->getPublishedPublications()) > 1}
				<div class="ipii-sidebar-card">
					<h2 class="ipii-sidebar-label">{translate key="submission.versions"}</h2>
					<ul class="list-unstyled mb-0">
						{foreach from=array_reverse($article->getPublishedPublications()) item=iPublication}
							{capture assign="vName"}{translate key="submission.versionIdentity" datePublished=$iPublication->getData('datePublished')|date_format:$dateFormatShort version=$iPublication->getData('version')}{/capture}
							<li class="mb-1">
								{if $iPublication->getId() === $publication->getId()}
									<span class="fw-medium">{$vName}</span>
								{elseif $iPublication->getId() === $currentPublication->getId()}
									<a href="{url page="article" op="view" path=$article->getBestId()}">{$vName}</a>
								{else}
									<a href="{url page="article" op="view" path=$article->getBestId()|to_array:"version":$iPublication->getId()}">{$vName}</a>
								{/if}
							</li>
						{/foreach}
					</ul>
				</div>
			{/if}

			{* Categories *}
			{if $categories}
				<div class="ipii-sidebar-card">
					<h2 class="ipii-sidebar-label">{translate key="category.category"}</h2>
					<ul class="list-unstyled mb-0">
						{foreach from=$categories item=category}
							<li><a href="{url router=$smarty.const.ROUTE_PAGE page="catalog" op="category" path=$category->getPath()|escape}">{$category->getLocalizedTitle()|escape}</a></li>
						{/foreach}
					</ul>
				</div>
			{/if}

			{* Non-DOI pub IDs *}
			{foreach from=$pubIdPlugins item=pubIdPlugin}
				{if $pubIdPlugin->getPubIdType() == 'doi'}{continue}{/if}
				{assign var=pubId value=$article->getStoredPubId($pubIdPlugin->getPubIdType())}
				{if $pubId}
					<div class="ipii-sidebar-card">
						<h2 class="ipii-sidebar-label">{$pubIdPlugin->getPubIdDisplayType()|escape}</h2>
						{assign var="pUrl" value=$pubIdPlugin->getResolvingURL($currentJournal->getId(), $pubId)|escape}
						{if $pUrl}<a href="{$pUrl}" class="small">{$pUrl}</a>{else}<span class="small">{$pubId|escape}</span>{/if}
					</div>
				{/if}
			{/foreach}

			{call_hook name="Templates::Article::Details"}
		</div>
	</div>

	{* ── MAIN CONTENT ───────────────────────────────────── *}
	<div class="col-lg-8 order-lg-1">
		<div class="ipii-article-main">

			{* Abstract *}
			{if $publication->getLocalizedData('abstract')}
				<div class="ipii-abstract-block">
					<h2 class="ipii-abstract-label">{translate key="article.abstract"}</h2>
					<div class="ipii-abstract-text">{$publication->getLocalizedData('abstract')|strip_unsafe_html}</div>
				</div>
			{/if}

			{* References *}
			{if $parsedCitations || $publication->getData('citationsRaw')}
				<div class="ipii-content-section">
					<h2 class="ipii-section-heading">{translate key="submission.citations"}</h2>
					<div class="ipii-refs-list">
						{if $parsedCitations}
							{foreach from=$parsedCitations item=parsedCitation}
								<div class="ipii-ref-item">{$parsedCitation->getCitationWithLinks()|strip_unsafe_html}</div>
							{/foreach}
						{else}
							<div class="ipii-ref-raw">{$publication->getData('citationsRaw')|escape|nl2br}</div>
						{/if}
					</div>
				</div>
			{/if}

			{* License *}
			{assign 'licenseTerms' $currentContext->getLocalizedData('licenseTerms')}
			{assign 'copyrightHolder' $publication->getLocalizedData('copyrightHolder')}
			{assign 'licenseUrl' $publication->getData('licenseUrl')}
			{assign 'copyrightYear' $publication->getData('copyrightYear')}
			{if $licenseTerms || $licenseUrl}
				<div class="ipii-content-section ipii-license-block">
					{if $licenseUrl}
						{if $ccLicenseBadge}
							{$ccLicenseBadge}
							{if $copyrightHolder}
								<p class="mt-2 mb-0">{translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}</p>
							{/if}
						{else}
							<a href="{$licenseUrl|escape}" class="copyright">
								{if $copyrightHolder}{translate key="submission.copyrightStatement" copyrightHolder=$copyrightHolder copyrightYear=$copyrightYear}{else}{translate key="submission.license"}{/if}
							</a>
						{/if}
					{else}
						{$licenseTerms}
					{/if}
				</div>
			{/if}

			{call_hook name="Templates::Article::Main"}

			{* Usage stats *}
			{if $activeTheme->getOption('displayStats') != 'none'}
				{$activeTheme->displayUsageStatsGraph($article->getId())}
				<div class="ipii-content-section ipii-stats-block">
					<h2 class="ipii-section-heading">{translate key="plugins.themes.ipii.displayStats.downloads"}</h2>
					<canvas class="usageStatsGraph" data-object-type="Submission" data-object-id="{$article->getId()|escape}"></canvas>
					<div class="usageStatsUnavailable" data-object-type="Submission" data-object-id="{$article->getId()|escape}">
						{translate key="plugins.themes.ipii.displayStats.noStats"}
					</div>
				</div>
			{/if}

		</div>
	</div>

	<div class="col-12 order-lg-3">
		{call_hook name="Templates::Article::Footer::PageFooter"}
	</div>

</div>
