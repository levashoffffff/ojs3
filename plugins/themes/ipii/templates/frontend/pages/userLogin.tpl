{**
 * templates/frontend/pages/userLogin.tpl
 * IPII Theme — login page
 *}
{include file="frontend/components/header.tpl" pageTitle="user.login"}

<div class="container page-login">
	<div class="ipii-auth-card ipii-animate-in">

		<h1 class="ipii-auth-title">{translate key="user.login"}</h1>
		{if $currentContext}
			<p class="ipii-auth-subtitle">{$currentContext->getLocalizedName()|escape}</p>
		{/if}

		{if $loginMessage}
			<div class="alert alert-info">{translate key=$loginMessage}</div>
		{/if}

		{if $error}
			<div class="alert alert-danger" role="alert">
				{translate key=$error reason=$reason}
			</div>
		{/if}

		{include file="frontend/components/loginForm.tpl" formType="loginPage"}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
