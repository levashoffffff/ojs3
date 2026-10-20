{**
 * templates/frontend/pages/contact.tpl
 * IPII Theme — contact page
 *}
{include file="frontend/components/header.tpl" pageTitle="about.contact"}

<div class="container page-contact">

	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="about.contact"}</h1>
	</div>

	<div class="ipii-contact-body ipii-animate-in ipii-animate-in-1">

		{* Mailing address *}
		{if $mailingAddress}
			<div class="ipii-contact-address">
				<div class="ipii-contact-address-label">{translate key="plugins.themes.ipii.contact.mailingAddress"}</div>
				<div class="ipii-contact-address-text">{$mailingAddress|nl2br|strip_unsafe_html}</div>
			</div>
		{/if}

		{* Contact cards grid *}
		{if $contactTitle || $contactName || $contactAffiliation || $contactPhone || $contactEmail || $supportName || $supportPhone || $supportEmail}
			<div class="ipii-contact-grid">

				{* Principal contact *}
				{if $contactTitle || $contactName || $contactAffiliation || $contactPhone || $contactEmail}
					<div class="ipii-contact-card">
						<div class="ipii-contact-card-label">{translate key="about.contact.principalContact"}</div>
						{if $contactName}
							<div class="ipii-contact-card-name">{$contactName|escape}</div>
						{/if}
						{if $contactTitle}
							<div class="ipii-contact-card-title">{$contactTitle|escape}</div>
						{/if}
						{if $contactAffiliation}
							<div class="ipii-contact-card-affil">{$contactAffiliation|strip_unsafe_html}</div>
						{/if}
						{if $contactPhone}
							<div class="ipii-contact-card-row">
								<span class="ipii-contact-card-row-label">{translate key="about.contact.phone"}</span>
								<span>{$contactPhone|escape}</span>
							</div>
						{/if}
						{if $contactEmail}
							<div class="ipii-contact-card-row">
								<span class="ipii-contact-card-row-label">{translate key="about.contact.email"}</span>
								<span>{mailto address=$contactEmail encode='javascript'}</span>
							</div>
						{/if}
					</div>
				{/if}

				{* Support contact *}
				{if $supportName || $supportPhone || $supportEmail}
					<div class="ipii-contact-card">
						<div class="ipii-contact-card-label">{translate key="about.contact.supportContact"}</div>
						{if $supportName}
							<div class="ipii-contact-card-name">{$supportName|escape}</div>
						{/if}
						{if $supportPhone}
							<div class="ipii-contact-card-row">
								<span class="ipii-contact-card-row-label">{translate key="about.contact.phone"}</span>
								<span>{$supportPhone|escape}</span>
							</div>
						{/if}
						{if $supportEmail}
							<div class="ipii-contact-card-row">
								<span class="ipii-contact-card-row-label">{translate key="about.contact.email"}</span>
								<span>{mailto address=$supportEmail encode='javascript'}</span>
							</div>
						{/if}
					</div>
				{/if}

			</div>
		{/if}

		{include file="frontend/components/editLink.tpl"
			page="management" op="settings" path="context"
			anchor="contact" sectionTitleKey="about.contact"}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
