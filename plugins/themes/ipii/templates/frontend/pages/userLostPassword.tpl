{**
 * templates/frontend/pages/userLostPassword.tpl
 * IPII Theme — password reset page
 *}
{include file="frontend/components/header.tpl" pageTitle="user.login.resetPassword"}

<div class="container page-lost-password">
	<div class="ipii-auth-card ipii-animate-in">

		<h1 class="ipii-auth-title">{translate key="user.login.resetPassword"}</h1>
		<p class="ipii-auth-subtitle">{translate key="user.login.resetPasswordInstructions"}</p>

		{if $error}
			<div class="alert alert-danger" role="alert">
				{translate key=$error}
			</div>
		{/if}

		<form action="{url page="login" op="requestResetPassword"}" method="post">
			{csrf}

			<div class="form-group">
				<label for="email">
					{translate key="user.login.registeredEmail"}
					<span class="required" aria-hidden="true">*</span>
					<span class="visually-hidden">{translate key="common.required"}</span>
				</label>
				<input type="email" class="form-control" name="email" id="email"
				       value="{$email|escape}" required autocomplete="email" autofocus>
			</div>

			{if $recaptchaPublicKey}
				<fieldset class="recaptcha_wrapper">
					<div class="fields">
						<div class="recaptcha">
							<div class="g-recaptcha" data-sitekey="{$recaptchaPublicKey|escape}"></div>
							<label for="g-recaptcha-response" style="display:none;" hidden>Recaptcha response</label>
						</div>
					</div>
				</fieldset>
			{/if}

			{if $altchaEnabled}
				<fieldset class="altcha_wrapper">
					<div class="fields">
						<altcha-widget challengejson='{$altchaChallenge|@json_encode}' floating></altcha-widget>
					</div>
				</fieldset>
			{/if}

			<div class="form-group-buttons">
				<button class="btn btn-primary" type="submit">
					{translate key="user.login.resetPassword"}
				</button>
			</div>
		</form>

		<div class="ipii-auth-footer">
			{translate key="plugins.themes.ipii.register.haveAccount"}
			<a href="{url page="login"}">{translate key="plugins.themes.ipii.register.loginHere"}</a>
		</div>

	</div>
</div>

{include file="frontend/components/footer.tpl"}
