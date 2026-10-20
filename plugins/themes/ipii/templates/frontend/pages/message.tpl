{**
 * templates/frontend/pages/message.tpl
 * IPII Theme — generic message page
 *}
{include file="frontend/components/header.tpl"}

<div class="container page-message">
	<div class="ipii-auth-card ipii-animate-in">

		{if $pageTitle}
			<h1 class="ipii-auth-title">{translate key=$pageTitle}</h1>
		{/if}

		<div class="ipii-message-body">
			{if $messageTranslated}
				{$messageTranslated}
			{else}
				{translate key=$message}
			{/if}
		</div>

		{if $backLink}
			<div class="ipii-auth-footer">
				<a href="{$backLink}">{translate key=$backLinkLabel}</a>
			</div>
		{/if}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
