{**
 * templates/frontend/components/loginForm.tpl
 * IPII Theme — login form
 *}
{if $formType && $formType === "loginPage"}
	{assign var=usernameId value="username"}
	{assign var=passwordId value="password"}
	{assign var=rememberId value="remember"}
{elseif $formType && $formType === "loginModal"}
	{assign var=usernameId value="usernameModal"}
	{assign var=passwordId value="passwordModal"}
	{assign var=rememberId value="rememberModal"}
{/if}

<form class="form-login" method="post" action="{$loginUrl}">
	{csrf}
	<input type="hidden" name="source" value="{$source|strip_unsafe_html|escape}"/>

	<fieldset>
		<div class="form-group mb-3">
			<label class="form-label" for="{$usernameId}">
				{translate key="user.username"}
				<span class="required" aria-hidden="true">*</span>
				<span class="visually-hidden">{translate key="common.required"}</span>
			</label>
			<input type="text" class="form-control" name="username" id="{$usernameId}"
			       value="{$username|default:""|escape}" maxlength="32"
			       autocomplete="username" required>
		</div>

		<div class="form-group mb-3">
			<label class="form-label" for="{$passwordId}">
				{translate key="user.password"}
				<span class="required" aria-hidden="true">*</span>
				<span class="visually-hidden">{translate key="common.required"}</span>
			</label>
			<input type="password" class="form-control" name="password" id="{$passwordId}"
			       value="{$password|default:""|escape}" maxlength="32"
			       autocomplete="current-password" required>
		</div>

		<div class="row mb-3">
			<div class="col-md-6">
				<small>
					<a href="{url page="login" op="lostPassword"}">
						{translate key="user.login.forgotPassword"}
					</a>
				</small>
			</div>
			<div class="col-md-6">
				<div class="form-check">
					<input type="checkbox" class="form-check-input" name="remember"
					       id="{$rememberId}" value="1" checked="$remember">
					<label class="form-check-label" for="{$rememberId}">
						<small>{translate key="user.login.rememberUsernameAndPassword"}</small>
					</label>
				</div>
			</div>
		</div>

		{if $recaptchaPublicKey}
			<div class="form-group mb-3">
				<div class="g-recaptcha" data-sitekey="{$recaptchaPublicKey|escape}"></div>
				<label for="g-recaptcha-response" style="display:none;" hidden>Recaptcha response</label>
			</div>
		{/if}

		{if $altchaEnabled}
			<div class="form-group mb-3">
				<altcha-widget challengejson='{$altchaChallenge|@json_encode}' floating></altcha-widget>
			</div>
		{/if}

		<div class="form-group mb-3">
			<button class="btn btn-primary w-100" type="submit">
				{translate key="user.login"}
			</button>
		</div>

		{if !$disableUserReg}
			<div class="form-group text-center">
				<small class="text-muted">
					{translate key="plugins.themes.ipii.register.noAccount"}
					{capture assign=registerUrl}{url page="user" op="register" source=$source}{/capture}
					<a href="{$registerUrl}" class="fw-semibold">
						{translate key="plugins.themes.ipii.register.registerHere"}
					</a>
				</small>
			</div>
		{/if}
	</fieldset>
</form>
