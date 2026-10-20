{**
 * templates/frontend/pages/userConfirmActivation.tpl
 * IPII Theme — account activation confirmation page
 *}
{include file="frontend/components/header.tpl" pageTitle="user.login.activate.description"}

<div class="container page-confirm-activation">
	<div class="ipii-auth-card ipii-animate-in">

		<div class="ipii-auth-icon">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
				<rect x="2" y="4" width="20" height="16" rx="2"/>
				<polyline points="2,4 12,13 22,4"/>
			</svg>
		</div>

		<h1 class="ipii-auth-title">{translate key="user.login.activate.description"}</h1>
		<p class="ipii-auth-subtitle">{translate key="user.login.accountNotValidated"}</p>

		<div class="form-group-buttons">
			<a href="{$activationUrl}" class="btn btn-primary">
				{translate key="user.login.activate"}
			</a>
		</div>

	</div>
</div>

{include file="frontend/components/footer.tpl"}
