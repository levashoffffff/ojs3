{**
 * templates/frontend/components/footer.tpl
 * IPII Theme — site footer
 *}

<footer class="ipii-footer" role="contentinfo">

	{* Sidebar widgets *}
	<div class="ipii-footer-sidebar">
		<div class="container">
			<div class="row" role="complementary" aria-label="{translate|escape key="common.navigation.sidebar"}">
				{call_hook name="Templates::Common::Sidebar"}
			</div>
		</div>
	</div>

	{* Main footer bar *}
	<div class="ipii-footer-main">
		<div class="container">
			<div class="row gy-4">

				{* Col 1 — journal identity *}
				<div class="col-md-4 ipii-footer-identity">
					{if $currentContext}
						<div class="ipii-footer-journal-name">{$currentContext->getLocalizedName()|escape}</div>
					{/if}
					<div class="ipii-footer-journal-desc">{translate key="plugins.themes.ipii.footer.journalDesc"}</div>
					{if $currentContext->getData('onlineIssn')}
						<div class="ipii-footer-issn">ISSN (online): {$currentContext->getData('onlineIssn')|escape}</div>
					{/if}
					{if $currentContext->getData('printIssn')}
						<div class="ipii-footer-issn">ISSN (print): {$currentContext->getData('printIssn')|escape}</div>
					{/if}
				</div>

				{* Col 2 — address & phone *}
				<div class="col-md-3 offset-md-1 ipii-footer-contacts">
					<div class="ipii-footer-contacts-title">{translate key="plugins.themes.ipii.footer.contacts"}</div>
					{if $currentContext->getData('mailingAddress')}
						<div class="ipii-footer-contact-row">{$currentContext->getData('mailingAddress')|escape|nl2br}</div>
					{/if}
					{if $currentContext->getData('contactPhone')}
						<div class="ipii-footer-contact-row">{$currentContext->getData('contactPhone')|escape}</div>
					{/if}
				</div>

				{* Col 3 — editorial *}
				<div class="col-md-3 offset-md-1 ipii-footer-contacts">
					<div class="ipii-footer-contacts-title">{translate key="plugins.themes.ipii.footer.editorial"}</div>
					{if $currentContext->getData('contactName')}
						<div class="ipii-footer-contact-row ipii-footer-contact-editor">{$currentContext->getData('contactName')|escape}</div>
					{/if}
					{if $currentContext->getData('contactEmail')}
						<div class="ipii-footer-contact-row">
							<a href="mailto:{$currentContext->getData('contactEmail')|escape}">{$currentContext->getData('contactEmail')|escape}</a>
						</div>
					{/if}
				</div>

				{* Custom page footer HTML from journal settings *}
				{if $pageFooter}
					<div class="col-12 ipii-footer-text">
						{$pageFooter}
					</div>
				{/if}

			</div>
		</div>
	</div>

	{* Bottom strip *}
	<div class="ipii-footer-bottom">
		<div class="container">
			<p class="ipii-footer-copy">
				&copy; {$currentYear}
				{if $currentContext}
					{$currentContext->getLocalizedName()|escape}
				{/if}
			</p>
		</div>
	</div>

</footer>

{* Author biography modals *}
{if !empty($smarty.capture.authorBiographyModals|default:""|trim)}
	{$smarty.capture.authorBiographyModals}
{/if}

{* Login modal *}
<div id="loginModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="loginModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="loginModalLabel">{translate key="user.login"}</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				{include file="frontend/components/loginForm.tpl" formType="loginModal"}
			</div>
		</div>
	</div>
</div>

{load_script context="frontend" scripts=$scripts}
{call_hook name="Templates::Common::Footer::PageFooter"}
</body>
</html>
