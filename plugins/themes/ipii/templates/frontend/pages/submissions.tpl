{**
 * templates/frontend/pages/submissions.tpl
 * IPII Theme — submissions page
 *}
{include file="frontend/components/header.tpl" pageTitle="about.submissions"}

<div class="container page-submissions">

	{* Page header *}
	<div class="ipii-about-header ipii-animate-in">
		<h1 class="ipii-about-title">{translate key="about.submissions"}</h1>
	</div>

	<div class="ipii-about-body ipii-animate-in ipii-animate-in-1">

		{* Login / register prompt *}
		{if $isUserLoggedIn}
			{capture assign="newSubmission"}<a href="{url page="submission" op="wizard"}">{translate key="about.onlineSubmissions.newSubmission"}</a>{/capture}
			{capture assign="viewSubmissions"}<a href="{url page="submissions"}">{translate key="about.onlineSubmissions.viewSubmissions"}</a>{/capture}
			<div class="alert alert-primary ipii-submissions-alert">
				{translate key="about.onlineSubmissions.submissionActions" newSubmission=$newSubmission viewSubmissions=$viewSubmissions}
			</div>
		{else}
			{capture assign="login"}<a href="{url page="login"}">{translate key="about.onlineSubmissions.login"}</a>{/capture}
			{capture assign="register"}<a href="{url page="user" op="register"}">{translate key="about.onlineSubmissions.register"}</a>{/capture}
			<div class="alert alert-primary ipii-submissions-alert">
				{translate key="about.onlineSubmissions.registrationRequired" login=$login register=$register}
			</div>
		{/if}

		{* Author guidelines *}
		{if $currentContext->getLocalizedData('authorGuidelines')}
			<div class="ipii-submissions-section ipii-about-content">
				<h2>
					{translate key="about.authorGuidelines"}
					{include file="frontend/components/editLink.tpl" page="management" op="settings" path="workflow" anchor="submission/instructions" sectionTitleKey="about.authorGuidelines"}
				</h2>
				{$currentContext->getLocalizedData('authorGuidelines')}
			</div>
		{/if}

		{* Submission checklist *}
		{if $submissionChecklist}
			<div class="ipii-submissions-section ipii-about-content">
				<h2>
					{translate key="about.submissionPreparationChecklist"}
					{include file="frontend/components/editLink.tpl" page="management" op="settings" path="workflow" anchor="submission/instructions" sectionTitleKey="about.submissionPreparationChecklist"}
				</h2>
				{$submissionChecklist}
			</div>
		{/if}

		{* Section policies *}
		{foreach from=$sections item="section"}
			{if $section->getLocalizedPolicy()}
				<div class="ipii-submissions-section ipii-about-content">
					<h2>{$section->getLocalizedTitle()|escape}</h2>
					{$section->getLocalizedPolicy()}
					{if $isUserLoggedIn}
						{capture assign="sectionSubmissionUrl"}{url page="submission" op="wizard" sectionId=$section->getId()}{/capture}
						<p>{translate key="about.onlineSubmissions.submitToSection" name=$section->getLocalizedTitle() url=$sectionSubmissionUrl}</p>
					{/if}
				</div>
			{/if}
		{/foreach}

		{* Copyright notice *}
		{if $currentContext->getLocalizedData('copyrightNotice')}
			<div class="ipii-submissions-section ipii-about-content">
				<h2>
					{translate key="about.copyrightNotice"}
					{include file="frontend/components/editLink.tpl" page="management" op="settings" path="workflow" anchor="submission/authorGuidelines" sectionTitleKey="about.copyrightNotice"}
				</h2>
				{$currentContext->getLocalizedData('copyrightNotice')}
			</div>
		{/if}

		{* Privacy statement *}
		{if $currentContext->getLocalizedData('privacyStatement')}
			<div class="ipii-submissions-section ipii-about-content">
				<h2>
					{translate key="about.privacyStatement"}
					{include file="frontend/components/editLink.tpl" page="management" op="settings" path="website" anchor="setup/privacy" sectionTitleKey="about.privacyStatement"}
				</h2>
				{$currentContext->getLocalizedData('privacyStatement')}
			</div>
		{/if}

	</div>
</div>

{include file="frontend/components/footer.tpl"}
