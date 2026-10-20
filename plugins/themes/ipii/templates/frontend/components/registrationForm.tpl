{**
 * templates/frontend/components/registrationForm.tpl
 * IPII Theme — registration form fields (no section split)
 *}

<div class="ipii-reg-fields">

	<div class="row g-4">

		{* Given name *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="givenName">
					{translate key="user.givenName"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="text" name="givenName" id="givenName"
				       autocomplete="given-name" value="{$givenName|default:""|escape}"
				       maxlength="255" required>
			</div>
		</div>

		{* Family name *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="familyName">
					{translate key="user.familyName"}
				</label>
				<input class="form-control ipii-input" type="text" name="familyName" id="familyName"
				       autocomplete="family-name" value="{$familyName|default:""|escape}"
				       maxlength="255">
			</div>
		</div>

		{* Email *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="email">
					{translate key="user.email"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="email" name="email" id="email"
				       value="{$email|default:""|escape}" maxlength="90"
				       autocomplete="email" required>
			</div>
		</div>

		{* Username *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="username">
					{translate key="user.username"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="text" name="username" id="username"
				       value="{$username|default:""|escape}" maxlength="32"
				       autocomplete="username" required>
			</div>
		</div>

		{* Affiliation *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="affiliation">
					{translate key="user.affiliation"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="text" name="affiliation" id="affiliation"
				       autocomplete="organization" value="{$affiliation|default:""|escape}" required>
			</div>
		</div>

		{* Country *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="country">
					{translate key="common.country"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<select class="form-control ipii-input" name="country" id="country"
				        autocomplete="country-name" required>
					<option value=""></option>
					{html_options options=$countries selected=$country}
				</select>
			</div>
		</div>

		{* Password *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="password">
					{translate key="user.password"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="password" name="password" id="password"
				       maxlength="32" autocomplete="new-password" required>
			</div>
		</div>

		{* Repeat password *}
		<div class="col-md-6">
			<div class="ipii-field">
				<label class="ipii-label" for="password2">
					{translate key="user.repeatPassword"}
					<span class="ipii-required" aria-hidden="true">*</span>
				</label>
				<input class="form-control ipii-input" type="password" name="password2" id="password2"
				       maxlength="32" autocomplete="new-password" required>
			</div>
		</div>

	</div>{* .row *}

	{* Consent checkboxes *}
	{if $currentContext}
		<div class="ipii-reg-consent">

			{if $currentContext->getSetting('privacyStatement')}
				<div class="ipii-check">
					<input type="checkbox" class="form-check-input" name="privacyConsent"
					       id="privacyConsent" value="1"{if $privacyConsent} checked="checked"{/if}>
					<label for="privacyConsent" class="form-check-label">
						{capture assign="privacyUrl"}{url router=$smarty.const.ROUTE_PAGE page="about" op="privacy"}{/capture}
						{translate key="user.register.form.privacyConsent" privacyUrl=$privacyUrl}
					</label>
				</div>
			{/if}

			<div class="ipii-check">
				<input type="checkbox" class="form-check-input" name="emailConsent"
				       id="emailConsent" value="1"{if $emailConsent} checked="checked"{/if}>
				<label for="emailConsent" class="form-check-label">
					{translate key="user.register.form.emailConsent"}
				</label>
			</div>


			{* Reviewer sign-up — only shown on /register?asReviewer=1 *}
			{if $smarty.get.asReviewer}
				{assign var=contextId value=$currentContext->getId()}
				{foreach from=$reviewerUserGroups[$contextId] item=userGroup}
					{if $userGroup->permitSelfRegistration}
						<div class="ipii-check">
							<input type="checkbox" class="form-check-input"
							       name="reviewerGroup[{$userGroup->id}]"
							       id="reviewerGroup-{$userGroup->id}" value="1" checked="checked">
							<label for="reviewerGroup-{$userGroup->id}" class="form-check-label">
								{translate key="user.reviewerPrompt.optin" userGroup=$userGroup->getLocalizedData('name')|escape}
							</label>
						</div>
						<div class="ipii-field" style="margin-top:0.5rem">
							<label class="ipii-label" for="interests">{translate key="user.interests"}</label>
							<input class="form-control ipii-input" type="text" name="interests" id="interests"
							       value="{$interests|default:""|escape}">
						</div>
					{/if}
				{/foreach}
			{/if}

		</div>{* .ipii-reg-consent *}
	{/if}
</div>{* .ipii-reg-fields *}
