<script type="text/javascript">
import Page from '@/components/Container/Page.vue';
import StartSubmissionForm from '@/components/Form/submission/StartSubmissionForm.vue';

export default {
	components: {
		StartSubmissionForm,
	},
	extends: Page,
	data() {
		return {
			form: {},
		};
	},
	methods: {
		/**
		 * Update the form values
		 */
		updateForm(formId, data) {
			let form = {...this.form};
			Object.keys(data).forEach((key) => (form[key] = data[key]));
			this.form = form;
		},
	},
};
</script>

<style lang="less">
@import '../../styles/_import';

.startSubmissionPage__form {
	.pkpFormPage__footer {
		display: block;
		padding: 2rem 0 0;
		border-top: none;

		// Saving status
		[role='status']:not(:empty) {
			display: block;
			width: 100%;
			margin: 0 0 2rem;
			text-align: center;

			.pkpFormPage__status {
				padding: 0.5rem;
				border: @bg-border;
				border-radius: 2rem;
			}
		}
	}

	.pkpFormErrors {
		display: block;
		margin: 0 0 2rem;
		padding: 1rem;
		text-align: center;
		border: 1px solid @no;
	}

	// Belt-and-braces: whatever width .pkpButton ends up with in any given
	// state, never let this row's own flex-end alignment pull it toward the
	// right edge — pin it to fill from the left instead.
	.buttonRow {
		justify-content: flex-start;

		// The [role="status"] announcer span (and, once the form fails
		// validation, the FormErrors block) always renders ahead of the
		// submit button in the DOM, even when empty/hidden. ButtonRow's own
		// `> * + * { margin-inline-start: 1rem }` therefore always matches
		// the button. Overriding margin on .pkpButton itself isn't reliable
		// once FormErrors also competes for width in this flex row, so
		// neutralize the exact rule that causes it instead.
		> * + * {
			margin-inline-start: 0;
		}
	}

	.pkpButton {
		box-sizing: border-box;
		margin: 0;
		padding: 0.5rem;
		width: 100%;
		font-size: @font-sml;
		text-align: center;
		// .pkpButton is an inline-flex container, so text-align above has no
		// effect on its (icon + label) content — justify-content does.
		justify-content: center;
	}
}
</style>
