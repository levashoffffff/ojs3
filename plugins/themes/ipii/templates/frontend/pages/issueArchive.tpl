{**
 * templates/frontend/pages/issueArchive.tpl
 * IPII Theme — issue archive grouped by year, paginated by year (4 years/page)
 *}
{capture assign="pageTitle"}
	{if $currentPage > 1}
		{translate key="archive.archivesPageNumber" pageNumber=$currentPage}
	{else}
		{translate key="archive.archives"}
	{/if}
{/capture}
{include file="frontend/components/header.tpl" pageTitleTranslated=$pageTitle}

<div class="container page-archives">

	<div class="ipii-archives-header ipii-animate-in">
		<h1 class="ipii-archives-title">{$pageTitle|escape}</h1>
	</div>

	{* No issues *}
	{if empty($issues)}
		<div class="ipii-archives-empty">
			{include file="frontend/components/notification.tpl" messageKey="current.noCurrentIssueDesc"}
		</div>

	{* Issues grouped by year (already ordered newest-year-first by the handler) *}
	{else}
		{assign var="currentYear" value=""}
		{assign var="sectionOpen" value=false}

		{foreach $issues as $issue}
			{assign var="issueYear" value=$issue->getDatePublished()|date_format:'Y'}

			{if $issueYear != $currentYear}
				{if $sectionOpen}
					</div>{* /row *}
					</div>{* /ipii-archive-year *}
				{/if}
				{assign var="currentYear" value=$issueYear}
				{assign var="sectionOpen" value=true}
				<div class="ipii-archive-year ipii-animate-in" data-year="{$issueYear}">
					<h2 class="ipii-archive-year-label">{$issueYear}</h2>
					<div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
			{/if}

			<div class="col">
				{include file="frontend/objects/issue_summary.tpl" heading="h3"}
			</div>

			{if $issue@last && $sectionOpen}
				</div>{* /row *}
				</div>{* /ipii-archive-year *}
			{/if}
		{/foreach}

		{* Pagination: first / numbered pages / last, shown at the bottom of the archive *}
		{if $totalPages > 1}
			<nav aria-label="{translate|escape key="common.pagination.label"}" class="ipii-archive-pagination">
				<ul class="pagination justify-content-center">
					<li class="page-item{if $currentPage == 1} disabled{/if}">
						<a class="page-link" href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}">{translate key="plugins.themes.ipii.archive.firstPage"}</a>
					</li>
					{for $pageNum=1; $pageNum<=$totalPages; $pageNum++}
						<li class="page-item{if $pageNum == $currentPage} active{/if}">
							<a class="page-link" href="{if $pageNum == 1}{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive"}{else}{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive" path=$pageNum}{/if}"{if $pageNum == $currentPage} aria-current="page"{/if}>{$pageNum}</a>
						</li>
					{/for}
					<li class="page-item{if $currentPage == $totalPages} disabled{/if}">
						<a class="page-link" href="{url router=$smarty.const.ROUTE_PAGE page="issue" op="archive" path=$totalPages}">{translate key="plugins.themes.ipii.archive.lastPage"}</a>
					</li>
				</ul>
			</nav>
		{/if}
	{/if}
</div>

<script>
(function () {
	var container = document.querySelector('.page-archives');
	if (!container) return;

	// Year filter buttons
	var btns = document.querySelectorAll('.ipii-year-btn');
	var allSections = document.querySelectorAll('.ipii-archive-year');

	btns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var year = this.dataset.year;
			btns.forEach(function (b) { b.classList.remove('active'); });
			this.classList.add('active');
			allSections.forEach(function (sec) {
				sec.style.display = (year === 'all' || sec.dataset.year === year) ? '' : 'none';
			});
		});
	});
}());
</script>

{include file="frontend/components/footer.tpl"}
