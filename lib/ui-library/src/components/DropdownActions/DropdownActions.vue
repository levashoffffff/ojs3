<template>
	<div
		class="relative inline-block items-start justify-between"
		:class="{'leading-none': displayAsEllipsis}"
	>
		<Menu as="div" v-slot="{open, close: menuClose}">
			<MenuButton
				ref="buttonTriggerRef"
				:class="menuButtonStyle"
				:aria-label="displayAsEllipsis ? label : ariaLabel"
			>
				<span v-if="!displayAsEllipsis">{{ label }}</span>
				<Icon
					v-if="buttonVariant === 'dropdown'"
					class="h-5 w-5 text-primary"
					icon="Dropdown"
					aria-hidden="true"
				/>
				<Icon
					v-else-if="displayAsEllipsis"
					class="h-6 w-6"
					icon="MoreOptions"
					aria-hidden="true"
				/>
			</MenuButton>

			<transition
				enter-active-class="transition ease-out duration-100"
				enter-from-class="transform opacity-0 scale-95"
				enter-to-class="transform opacity-100 scale-100"
				leave-active-class="transition ease-in duration-75"
				leave-from-class="transform opacity-100 scale-100"
				leave-to-class="transform opacity-0 scale-95"
			>
				<MenuItems
					v-if="open"
					class="absolute z-[9999] flex w-fit min-w-[96px] flex-col border border-light bg-secondary shadow focus:outline-none"
					:class="
						direction === 'right'
							? 'ltr:left-0 ltr:origin-top-left rtl:right-0 rtl:origin-top-left'
							: 'ltr:right-0 ltr:origin-top-right rtl:left-0 rtl:origin-top-right'
					"
					static
				>
					<div
						v-for="(action, i) in actions"
						:key="i"
						role="none"
					>
						<PkpButton
							:element="action.url ? 'a' : 'button'"
							:href="action.url"
							:icon="action.icon"
							:is-warnable="action.isWarnable"
							:class="i !== actions.length - 1 ? 'border-b' : ''"
							size-variant="fullWidth"
							:is-disabled="action.disabled || false"
							class="whitespace-nowrap border-light"
							@click="handleSelect(action, menuClose)"
						>
							{{ action.label }}
						</PkpButton>
					</div>
				</MenuItems>
			</transition>
		</Menu>
	</div>
</template>

<script setup>
import {Menu, MenuButton, MenuItems} from '@headlessui/vue';
import PkpButton from '@/components/Button/Button.vue';
import Icon from '@/components/Icon/Icon.vue';
import {computed, ref} from 'vue';

const props = defineProps({
	actions: {
		type: Array,
		required: true,
	},
	actionArgs: {
		type: Object,
		required: false,
		default: () => null,
	},
	label: {
		type: String,
		required: true,
	},
	buttonVariant: {
		type: String,
		default: 'dropdown',
		validator: (val) => ['dropdown', 'ellipsis', 'text'].includes(val),
	},
	ariaLabel: {
		type: String,
		default: '',
	},
	direction: {
		type: String,
		default: 'left',
		validator: (direction) => ['left', 'right'].includes(direction),
	},
});

const displayAsEllipsis = computed(() => props.buttonVariant === 'ellipsis');

const emit = defineEmits(['action']);

function handleSelect(action, close) {
	close();
	if (buttonTriggerRef.value && buttonTriggerRef.value.$el) {
		buttonTriggerRef.value.$el.focus();
	}
	emitAction(action);
}

const emitAction = (action) => {
	if (action.actionFn) {
		action.actionFn(props?.actionArgs);
	} else if (action.name) {
		emit('action', action.name, props?.actionArgs || {});
	}
};

const buttonTriggerRef = ref(null);

const menuButtonStyle = computed(() => ({
	'w-full justify-center': true,
	'inline-flex rounded border border-light bg-secondary px-3 py-2 text-lg-normal gap-x-1.5':
		props.buttonVariant === 'dropdown',
	'text-primary hover:text-hover mb-1': props.buttonVariant === 'text',
	'leading-none rounded hover:text-on-dark hover:bg-hover':
		displayAsEllipsis.value,
}));
</script>

<style lang="less" scoped>
@import '../../styles/_import';

a.text-on-dark:hover,
a.text-on-dark:focus,
a.text-on-dark:active {
	color: rgb(255 255 255 / var(--tw-text-opacity));
}
</style>
