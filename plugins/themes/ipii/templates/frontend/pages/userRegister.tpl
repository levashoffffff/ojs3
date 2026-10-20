{**
 * templates/frontend/pages/userRegister.tpl
 * IPII Theme — registration page
 *}
{include file="frontend/components/header.tpl" pageTitle="user.register"}

<div class="ipii-register-page">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-lg-8 col-xl-7">

				<div class="ipii-register-card">

					<div class="ipii-register-card-header">
						<h1 class="ipii-register-title">{translate key="user.register"}</h1>
						<p class="ipii-register-subtitle">
							{translate key="plugins.themes.ipii.register.haveAccount"}
							{capture assign="rolesProfileUrl"}{url page="user" op="profile" path="roles"}{/capture}
							<a href="{url page="login" source=$rolesProfileUrl}">
								{translate key="plugins.themes.ipii.register.loginHere"}
							</a>
						</p>
					</div>

					<div class="ipii-register-card-body">
						<form class="form-register" id="register" method="post" action="{url op="register"}">
							{csrf}
							<input type="hidden" name="source" value="{$source|default:""|escape}" />

							{include file="common/formErrors.tpl"}

							{include file="frontend/components/registrationForm.tpl"}

							{include file="frontend/components/registrationFormContexts.tpl"}

							{if $recaptchaPublicKey}
								<div class="mt-3">
									<div class="g-recaptcha" data-sitekey="{$recaptchaPublicKey|escape}"></div>
									<label for="g-recaptcha-response" style="display:none;" hidden>Recaptcha response</label>
								</div>
							{/if}

							{if $altchaEnabled}
								<div class="mt-3">
									<altcha-widget challengejson='{$altchaChallenge|@json_encode}' floating></altcha-widget>
								</div>
							{/if}

							<div class="ipii-register-submit">
								<button class="btn btn-primary ipii-register-btn" type="submit">
									{translate key="user.register"}
								</button>
							</div>

						</form>
					</div>

				</div>
			</div>
		</div>
	</div>
</div>

{include file="frontend/components/footer.tpl"}
