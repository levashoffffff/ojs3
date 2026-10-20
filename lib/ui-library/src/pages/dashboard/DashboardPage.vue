<template>
	<div class="min-h-screen bg-default p-5 text-base-normal">
		<!-- AdminLTE "small-box" stat widget for the current view's count,
		     built from data the page already has (no new data fetching). -->
		<div class="mb-4 overflow-hidden rounded bg-primary text-on-dark shadow">
			<div class="flex items-center justify-between gap-4 p-4">
				<div class="min-w-0">
					<div class="flex items-center gap-3">
						<h1 class="text-4xl-bold">
							{{ store.submissionsPagination.itemCount }}
						</h1>
						<Spinner
							size-variant="big"
							:class="!store.isSubmissionsLoading ? 'invisible' : ''"
						/>
					</div>
					<div class="text-lg-medium truncate">
						{{ store.currentView.name }}
					</div>
				</div>
				<Icon icon="MySubmissions" class="h-16 w-16 flex-shrink-0 opacity-25" />
			</div>
		</div>

		<!-- Toolbar sits above the table card (AdminLTE doesn't nest a toolbar
		     inside its table cards — it's a separate control row). -->
		<div class="mb-3 flex flex-wrap justify-between gap-3">
			<div class="flex flex-row items-center gap-x-3">
				<component
					:is="Components[action.component] || action.component"
					v-bind="action.props || {}"
					v-for="(action, i) in store.leftControlItems"
					:key="i"
				></component>
			</div>
			<div class="flex flex-row items-center gap-x-3">
				<component
					:is="Components[action.component] || action.component"
					v-bind="action.props || {}"
					v-for="(action, i) in store.rightControlItems"
					:key="i"
				></component>
			</div>
		</div>
		<div v-if="store.filtersFormList.length" class="mb-3">
			<DashboardActiveFilters
				:active-filters-list="store.filtersFormList"
				@clear-filters="store.clearFiltersForm"
				@remove-filter="store.clearFiltersFormField"
			/>
		</div>

		<!-- The table itself already renders as a bordered, rounded, shadowed
		     card (see Table.vue) — matching the reference's white content cards. -->
		<DashboardTable
			:items="store.submissions"
			:columns="store.columns"
			:sort-descriptor="store.sortDescriptor"
			:pagination="store.submissionsPagination"
			@sort-column="store.applySort"
			@set-page="store.setCurrentPage"
		/>
	</div>
</template>
<script setup>
import DashboardActiveFilters from './components/DashboardActiveFilters.vue';
import DashboardTable from './components/DashboardTable/DashboardTable.vue';
import DashboardControlBulkActions from './components/DashboardControlBulkActions.vue';
import DashboardControlBulkDeleteButton from './components/DashboardControlBulkDeleteButton.vue';
import DashboardActionButton from './components/DashboardActionButton.vue';
import DashboardControlSearch from './components/DashboardControlSearch.vue';

import {useDashboardPageStore} from './dashboardPageStore';
import Spinner from '@/components/Spinner/Spinner.vue';
import Icon from '@/components/Icon/Icon.vue';

const Components = {
	DashboardControlBulkActions,
	DashboardControlBulkDeleteButton,
	DashboardActionButton,
	DashboardControlSearch,
};

const props = defineProps({
	dashboardPage: {
		required: true,
		type: String,
		validator: (prop) =>
			['editorialDashboard', 'myReviewAssignments', 'mySubmissions'].includes(
				prop,
			),
	},
	/** Form for selection type of revision */
	selectRevisionDecisionForm: {type: Object, required: true},
	/** Form for selection type of revision for recommending editor */
	selectRevisionRecommendationForm: {type: Object, required: true},
	/** Forms used by managers or other components within dashboard/workflow */
	componentForms: {type: Object, required: true},
	/** List of Views */
	views: {
		type: Array,
		required: true,
	},
	/** Minimum reviews per submission */
	contextMinReviewsPerSubmission: {
		type: Number,
		required: true,
	},
	/** Filters form config  */
	filtersForm: {
		type: Object,
		required: true,
	},
	/** How many submissions to show per page */
	countPerPage: {
		type: Number,
		required: true,
	},
	/** context settings {supportsCitations, } */
	publicationSettings: {
		type: Object,
		required: true,
	},
});

const store = useDashboardPageStore(props);
</script>

<style>
.pkp_page_dashboard {
	@apply min-w-fit;
}

.pkp_page_dashboard .app__main {
	@apply bg-default p-0;
}
</style>
