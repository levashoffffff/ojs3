{**
 * templates/frontend/components/languageSwitcher.tpl
 * IPII Theme — language switcher dropdown
 *}
{if $languageToggleLocales && $languageToggleLocales|@count > 1}
	<div id="{$id|escape}" class="dropdown language-toggle">
		<button class="btn dropdown-toggle" type="button"
		        id="languageToggleMenu{$id|escape}"
		        data-bs-toggle="dropdown"
		        aria-haspopup="true"
		        aria-expanded="false">
			<span class="visually-hidden">{translate key="plugins.themes.ipii.language.toggle"}</span>
			{$languageToggleLocales[$currentLocale]}
		</button>
		<div class="dropdown-menu" aria-labelledby="languageToggleMenu{$id|escape}">
			{foreach from=$languageToggleLocales item="localeName" key="localeKey"}
				{if $localeKey !== $currentLocale}
					<a class="dropdown-item"
					   href="{url router=$smarty.const.ROUTE_PAGE page="user" op="setLocale" path=$localeKey}">
						{$localeName}
					</a>
				{/if}
			{/foreach}
		</div>
	</div>
{/if}
