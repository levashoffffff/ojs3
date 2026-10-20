{**
 * plugins/generic/jatsExport/templates/jatsExport.tpl
 *
 * JATS export page: pick published articles, download their JATS XML
 * (.txt / .xml / .zip) or send them to the RCSI "Metafora" system and track
 * their processing and signature status there.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.generic.jatsExport.displayName"}
	</h1>

	<div class="app__contentPanel jatsExport">
		{foreach from=$messages item=message}
			<div class="jatsExport__message jatsExport__message--{if $message.ok}ok{else}error{/if}">{$message.text|escape|nl2br}</div>
		{/foreach}

		<div class="jatsExport__bar">
			{* Metafora API key: own small form, separate from the article list *}
			<form class="jatsExport__metaforaSettings" method="post" action="{url router=$smarty.const.ROUTE_PAGE page="jatsExport" op="metaforaSettings"}">
				{csrf}
				<div class="jatsExport__metaforaSettingsTitle">{translate key="plugins.generic.jatsExport.metafora.settings"}</div>
				<label class="jatsExport__metaforaKey">
					{translate key="plugins.generic.jatsExport.metafora.apiKey"}
					<input type="password" name="apiKey" autocomplete="off"
						placeholder="{if $metaforaKeyHint}{translate key="plugins.generic.jatsExport.metafora.keyStored" hint=$metaforaKeyHint}{else}{translate key="plugins.generic.jatsExport.metafora.keyPlaceholder"}{/if}">
				</label>
				<button type="submit" class="pkp_button">{translate key="plugins.generic.jatsExport.metafora.saveAndTest"}</button>
				{if $metaforaKeyHint}
					<button type="submit" name="removeKey" value="1" class="pkp_button pkp_button_offset" data-confirm="{translate key="plugins.generic.jatsExport.metafora.confirmRemoveKey"}">{translate key="plugins.generic.jatsExport.metafora.removeKey"}</button>
				{/if}
			</form>

			{* Filters work in the browser: every published article is already in the table *}
			{if $articles|@count}
				<div class="jatsExport__filters">
					<input type="search" class="jatsExport__search" data-filter-search
						placeholder="{translate key="plugins.generic.jatsExport.filter.search"}">
					<button type="button" class="jatsExport__filterBtn is-active" data-filter-all>{translate key="plugins.generic.jatsExport.filter.all"}</button>
					<div class="jatsExport__dropdown">
						<button type="button" class="jatsExport__filterBtn" data-dropdown-toggle data-filter-button="issue"
							data-default-label="{translate key="plugins.generic.jatsExport.filter.issue"}">{translate key="plugins.generic.jatsExport.filter.issue"}</button>
						<div class="jatsExport__menu" hidden>
							{foreach from=$filters.issues item=issue}
								<button type="button" class="jatsExport__menuItem" data-filter="issue" data-value="{$issue.id|escape}" data-label="{$issue.label|escape}" data-year="{$issue.year|escape}">
									{$issue.label|escape}<span class="jatsExport__menuCount">{$issue.count}</span>
								</button>
							{/foreach}
						</div>
					</div>
					<div class="jatsExport__dropdown">
						<button type="button" class="jatsExport__filterBtn" data-dropdown-toggle data-filter-button="year"
							data-default-label="{translate key="plugins.generic.jatsExport.filter.year"}">{translate key="plugins.generic.jatsExport.filter.year"}</button>
						<div class="jatsExport__menu" hidden>
							{foreach from=$filters.years item=year}
								<button type="button" class="jatsExport__menuItem" data-filter="year" data-value="{$year.year|escape}" data-label="{$year.year|escape}">
									{$year.year|escape}<span class="jatsExport__menuCount">{$year.count}</span>
								</button>
							{/foreach}
						</div>
					</div>
				</div>
			{/if}
		</div>

		<p class="jatsExport__intro">{translate key="plugins.generic.jatsExport.intro"}</p>

		{if $articles|@count}
			{capture assign="metaforaUrl"}{url router=$smarty.const.ROUTE_PAGE page="jatsExport" op="metafora"}{/capture}
			<form id="jatsExportForm" method="post" action="{url router=$smarty.const.ROUTE_PAGE page="jatsExport" op="export"}">
				{csrf}

				<div class="jatsExport__toolbar">
					<label class="jatsExport__selectAll">
						<input type="checkbox" data-select-all>
						{translate key="plugins.generic.jatsExport.selectAll"}
					</label>
					<span class="jatsExport__count">
						{translate key="plugins.generic.jatsExport.selectedCount"}: <span data-selected-count>0</span> / <span data-visible-count>{$articles|@count}</span>
					</span>
				</div>

				<table class="jatsExport__table">
					<thead>
						<tr>
							<th class="jatsExport__colCheck"></th>
							<th class="jatsExport__colId">ID</th>
							<th>{translate key="plugins.generic.jatsExport.column.title"}</th>
							<th class="jatsExport__colIssue">{translate key="plugins.generic.jatsExport.column.issue"}</th>
							<th class="jatsExport__colMetafora">{translate key="plugins.generic.jatsExport.column.metafora"}</th>
						</tr>
					</thead>
					<tbody>
						{assign var="prevIssue" value=-1}
						{foreach from=$articles item=article}
							{assign var="m" value=$article.metafora}
							{* Group header: the list is ordered by issue, newest first *}
							{if $article.issueId != $prevIssue}
								<tr class="jatsExport__group" data-group-row data-issue="{$article.issueId|escape}" data-year="{$article.year|escape}">
									<td colspan="5">{if $article.issueLabel}{$article.issueLabel|escape}{else}{translate key="plugins.generic.jatsExport.filter.noIssue"}{/if}</td>
								</tr>
								{assign var="prevIssue" value=$article.issueId}
							{/if}
							<tr data-article-row data-issue="{$article.issueId|escape}" data-year="{$article.year|escape}"
								data-search="{$article.id|escape} {$article.title|lower|escape}">
								<td class="jatsExport__colCheck">
									<input type="checkbox" name="submissionIds[]" value="{$article.id|escape}" data-article-check>
								</td>
								<td class="jatsExport__colId">{$article.id|escape}</td>
								<td>{$article.title|escape}</td>
								<td class="jatsExport__colIssue">
									{$article.issueLabel|escape}
									{if $article.pages}<div class="jatsExport__pages">{translate key="plugins.generic.jatsExport.filter.pages" pages=$article.pages|escape}</div>{/if}
								</td>
								<td class="jatsExport__colMetafora">
									<span class="jatsExport__badge jatsExport__badge--{$m.tone|escape}">{$m.label|escape}</span>
									{if $m.originLabel}
										<div class="jatsExport__origin">{$m.originLabel|escape}</div>
									{/if}
									{if $m.error}
										<div class="jatsExport__error">{$m.error|escape}</div>
									{/if}
									<div class="jatsExport__rowActions">
										{foreach from=$m.actions item=a}
											<button type="submit" name="metaforaRow" value="{$a.action|escape}:{$article.id|escape}" formaction="{$metaforaUrl}"
												class="jatsExport__link{if $a.danger} jatsExport__link--danger{/if}"
												{if !$a.enabled}disabled title="{$a.hint|escape}"{/if}
												{if $a.confirm}data-confirm="{$a.confirm|escape}"{/if}>{$a.label|escape}</button>
										{/foreach}
									</div>
								</td>
							</tr>
						{/foreach}
						<tr class="jatsExport__noResults" data-no-results hidden>
							<td colspan="5">{translate key="plugins.generic.jatsExport.filter.noResults"}</td>
						</tr>
					</tbody>
				</table>

				<div class="jatsExport__footer">
					<div class="jatsExport__footerGroup">
						<button type="submit" name="format" value="txt" class="pkp_button pkp_button_primary">
							{translate key="plugins.generic.jatsExport.exportTxt"}
						</button>
						<button type="submit" name="format" value="xml" class="pkp_button">
							{translate key="plugins.generic.jatsExport.exportXml"}
						</button>
					</div>
					<div class="jatsExport__footerGroup">
						<span class="jatsExport__footerLabel">{translate key="plugins.generic.jatsExport.metafora.selected"}</span>
						<button type="submit" name="metaforaAction" value="send" formaction="{$metaforaUrl}" class="pkp_button" data-confirm="{translate key="plugins.generic.jatsExport.metafora.confirmSendSelected"}">{translate key="plugins.generic.jatsExport.metafora.send"}</button>
						<button type="submit" name="metaforaAction" value="refresh" formaction="{$metaforaUrl}" class="pkp_button">{translate key="plugins.generic.jatsExport.metafora.refresh"}</button>
						<button type="submit" name="metaforaAction" value="sign" formaction="{$metaforaUrl}" class="pkp_button" data-confirm="{translate key="plugins.generic.jatsExport.metafora.confirmSign"}">{translate key="plugins.generic.jatsExport.metafora.sign"}</button>
					</div>
				</div>
			</form>
		{else}
			<p class="jatsExport__empty">{translate key="plugins.generic.jatsExport.noArticles"}</p>
		{/if}
	</div>
{/block}
