{**
 * plugins/generic/fileLibrary/templates/fileLibrary.tpl
 *
 * File library management page: upload arbitrary files to the journal's
 * public directory and copy a direct link to them.
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.generic.fileLibrary.displayName"}
	</h1>

	<div class="app__contentPanel fileLibrary">
		<p class="fileLibrary__intro">{translate key="plugins.generic.fileLibrary.intro"}</p>

		<form method="post" action="{url router=$smarty.const.ROUTE_PAGE page="fileLibrary" op="upload"}" enctype="multipart/form-data" class="fileLibrary__uploadForm">
			{csrf}
			<input type="file" name="file" required>
			<button type="submit" class="pkp_button pkp_button_primary">
				{translate key="plugins.generic.fileLibrary.upload"}
			</button>
		</form>

		{if $files|@count}
			<table class="fileLibrary__table">
				<thead>
					<tr>
						<th>{translate key="plugins.generic.fileLibrary.column.name"}</th>
						<th>{translate key="plugins.generic.fileLibrary.column.size"}</th>
						<th>{translate key="plugins.generic.fileLibrary.column.date"}</th>
						<th>{translate key="plugins.generic.fileLibrary.column.link"}</th>
						<th class="fileLibrary__colActions"></th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$files item=file}
						<tr>
							<td class="fileLibrary__cellName">{$file.name|escape}</td>
							<td class="fileLibrary__cellMuted">{$file.size|escape}</td>
							<td class="fileLibrary__cellMuted">{$file.date|escape}</td>
							<td>
								<div class="fileLibrary__linkRow">
									<input type="text" readonly value="{$file.url|escape}" class="fileLibrary__urlInput" onclick="this.select();" title="{$file.url|escape}">
									<button type="button" class="pkp_button" data-copy-url="{$file.url|escape}">
										{translate key="plugins.generic.fileLibrary.copyLink"}
									</button>
								</div>
							</td>
							<td class="fileLibrary__colActions">
								<form method="post" action="{url router=$smarty.const.ROUTE_PAGE page="fileLibrary" op="delete"}" onsubmit="return confirm('{translate key="plugins.generic.fileLibrary.confirmDelete"|escape:"javascript"}');">
									{csrf}
									<input type="hidden" name="fileName" value="{$file.name|escape}">
									<button type="submit" class="pkp_button fileLibrary__deleteButton">
										{translate key="common.delete"}
									</button>
								</form>
							</td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p class="fileLibrary__empty">{translate key="plugins.generic.fileLibrary.noFiles"}</p>
		{/if}
	</div>
{/block}
