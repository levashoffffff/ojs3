/** @type {import('tailwindcss').Config} */

import defaultTheme from 'tailwindcss/defaultTheme.js';

const colors = {
	primary: '#1B3A6B',
	hover: '#2E4C82',
	attention: '#D00A0A',
	success: '#00B24E',
	negative: '#D00A6C',
	'stage-desk-review': '#9B6FF8',
	'stage-in-review': '#E08914',
	'stage-in-internal-review': '#42427F',
	'stage-copyediting': '#F66AAF',
	'stage-production': '#4AC7E2',
	'stage-scheduled-for-publishing': '#DED15D',
	'stage-incomplete-submission': '#777777',
	'stage-published': '#00B24E',
	'stage-declined': '#D00A0A',
	'profile-1': '#AB7D94',
	'profile-2': '#598D70',
	'profile-3': '#9B7CDC',
	'profile-4': '#89AAE0',
	'profile-5': '#EBDA68',
	'profile-6': '#BD726C',
	transparent: 'transparent',
};

export default {
	content: [
		'./index.html',
		'./lib/ui-library/src/**/*.js',
		'./**/*.vue',
		'./**/*stories.js',
		'./**/*.tpl',
	],
	theme: {
		backgroundColor: {
			...colors,
			default: '#EAEDEE',
			secondary: '#FFFFFF',
			tertiary: 'rgba(234, 237, 238, 0.3)',
			blur: 'rgba(0,0,0,0.5)',
			'selection-light': '#D5E9F2',
			'selection-dark': '#002C40',
			disabled: 'rgba(28, 27, 31, 0.1)',
		},
		textColor: {
			...colors,
			default: '#222222',
			secondary: '#505050',
			heading: '#01354F',
			disabled: '#4E4F4F',
			'on-dark': '#FFFFFF',
		},
		borderColor: {
			...colors,
			dark: '#696969',
			light: '#BBBBBB',
			'form-fields': '#777777',
			transparent: 'transparent',
		},
		borderRadius: {
			DEFAULT: '0.375rem',
			lg: '0.5rem',
			full: '9999px',
			none: '0',
		},
		boxShadow: {
			DEFAULT: '0 0 1px rgba(0, 0, 0, 0.125), 0 1px 3px rgba(0, 0, 0, 0.2)',
			lg: '0 1rem 3rem rgba(0, 0, 0, 0.175)',
		},
		colors: {
			...colors,
		},
		fontFamily: {
			// this sets default font
			sans: ['"Source Sans 3"', '"Noto Sans"', ...defaultTheme.fontFamily.sans],
		},
		fontSize: {
			'xs-normal': [
				'0.75rem' /** 12px, was 10px */,
				{
					lineHeight: '1rem',
					fontWeight: '400',
				},
			],
			'sm-light': [
				'0.8125rem' /** 13px, was 11px */,
				{
					lineHeight: '1.125rem',
					fontWeight: '300',
				},
			],
			'sm-normal': [
				'0.8125rem' /** 13px, was 11px */,
				{
					lineHeight: '1.125rem',
					fontWeight: '400',
				},
			],
			'base-light': [
				'0.875rem' /** 14px, was 12px */,
				{
					lineHeight: '1.25rem',
					fontWeight: '300',
				},
			],
			'base-normal': [
				'0.875rem' /** 14px, was 12px */,
				{
					lineHeight: '1.25rem',
					fontWeight: '400',
				},
			],
			'base-bold': [
				'0.875rem' /** 14px, was 12px */,
				{
					lineHeight: '1.25rem',
					fontWeight: '700',
				},
			],
			'lg-normal': [
				'1rem' /** 16px, was 14px */,
				{
					lineHeight: '1.5rem',
					fontWeight: '400',
				},
			],
			'lg-medium': [
				'1rem' /** 16px, was 14px */,
				{
					lineHeight: '1.5rem',
					fontWeight: '500',
				},
			],
			'lg-semibold': [
				'1rem' /** 16px, was 14px */,
				{
					lineHeight: '1.5rem',
					fontWeight: '600',
				},
			],
			'lg-bold': [
				'1rem' /** 16px, was 14px */,
				{
					lineHeight: '1.5rem',
					fontWeight: '700',
				},
			],
			'xl-bold': [
				'1.125rem' /** 18px, was 16px */,
				{
					lineHeight: '1.75rem',
					fontWeight: '700',
				},
			],
			'2xl-bold': [
				'1.25rem' /** 20px, was 18px */,
				{
					lineHeight: '2rem',
					fontWeight: '700',
				},
			],
			'3xl-normal': [
				'1.375rem' /** 22px, was 20px */,
				{
					lineHeight: '2rem',
					fontWeight: '400',
				},
			],
			'3xl-medium': [
				'1.375rem' /** 22px, was 20px */,
				{
					lineHeight: '2rem',
					fontWeight: '500',
				},
			],
			'3xl-bold': [
				'1.375rem' /** 22px, was 20px */,
				{
					lineHeight: '2rem',
					fontWeight: '700',
				},
			],
			'4xl-bold': [
				'1.6875rem' /** 27px, was 24px */,
				{
					lineHeight: '2.25rem',
					fontWeight: '700',
				},
			],
			'5xl-bold': [
				'2.5rem' /** 40px, was 36px */,
				{
					lineHeight: '2.75rem',
					fontWeight: '700',
				},
			],
		},
	},
	plugins: [],
};
