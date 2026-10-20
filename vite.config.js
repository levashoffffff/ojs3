// Импортируем утилиты из зависимостей
import {defineConfig} from 'vite'; // Основная функция для создания конфигурации Vite
import Vue from '@vitejs/plugin-vue'; // Плагин для поддержки Vue.js
import path from 'path'; // Модуль Node.js для работы с путями к файлам
import copy from 'rollup-plugin-copy'; // Плагин для копирования файлов во время сборки
import i18nExtractKeys from './lib/pkp/tools/i18nExtractKeys.vite.js'; // Кастомный плагин для извлечения ключей локализации (i18n)

// Экспортируем конфигурацию
export default defineConfig(({mode}) => {
	// 'mode' может быть 'development' или 'production'.
	// Это позволяет создавать разные сборки для разработки и для конечных пользователей.

	// Устанавливаем переменную окружения NODE_ENV равной текущему режиму.
	// Это стандартная практика, на которую ориентируются многие библиотеки (включая Vue),
	// чтобы включать или отключать отладочные инструменты.
	process.env.NODE_ENV = mode;
	return {
		// Список плагинов, которые Vite будет использовать
		plugins: [
			// Запускаем кастомный плагин для сбора ключей перевода
			i18nExtractKeys({
				// Дополнительные ключи, которые нужно включить в сборку локализации
				// existing in tpl files, to be replaced in future
				extraKeys: [
					'common.view',
					'common.close',
					'common.editItem',
					'stats.descriptionForStat',
					'common.commaListSeparator',
					// use directly via pkp.localeKeys
					'form.dataHasChanged',
				],
			}),
			// Подключаем плагин Vue
			Vue({
				// Явно указываем, является ли сборка продуктовой
				isProduction: mode === 'production',
				template: {
					compilerOptions: {
						// Эта опция сохраняет пробелы между HTML-тегами,
						// имитируя поведение старых версий Vue (Vue 2).
						whitespace: 'preserve',
					},
				},
			}),
			// Плагин для копирования файлов
			copy({
				targets: [
					// Копируем стили для редактора TinyMCE
					{
						src: 'node_modules/tinymce/skins/ui/tinymce-5/**/*.css',
						dest: 'lib/pkp/styles/tinymce',
					},
					// Копируем файлы jQuery, jQuery-UI и других старых библиотек
					// в директорию `js/build`, чтобы они были доступны PHP-шаблонам.
					{
						src: 'node_modules/jquery/dist/*',
						dest: 'js/build/jquery',
					},
					{
						src: 'node_modules/jquery-ui/dist/**/*.js',
						dest: 'js/build/jquery-ui',
					},
					{
						src: 'node_modules/jquery-validation/dist/*',
						dest: 'js/build/jquery-validation',
					},
					{
						src: 'node_modules/chart.js/dist/**/*.umd.js',
						dest: 'js/build/chart.js',
					},
				],
				// Указываем, что копирование должно произойти после того, как основная сборка будет завершена
				hook: 'writeBundle',
			}),
		],
		// Отключаем использование стандартной папки 'public'.
		// Все файлы управляются через сборку.
		publicDir: false,
		// Настройки для разрешения (поиска) модулей
		resolve: {
			alias: {
				// Создаем псевдоним '@', который всегда будет указывать на папку с UI-компонентами.
				'@': path.resolve(__dirname, 'lib/ui-library/src'),
				// Указываем использовать специальную версию Vue ('vue.esm-bundler'),
				// которая включает в себя компилятор шаблонов. Это нужно для рендеринга Vue-компонентов
				// прямо на PHP-страницах.
				vue: 'vue/dist/vue.esm-bundler.js',
			},
			// Устраняет дублирование зависимостей. Гарантирует, что для всего проекта
			// используется только один экземпляр каждой из указанных библиотек.
			// https://github.com/vitejs/vite/discussions/15906
			dedupe: [
				'@headlessui/vue',
				'@lk77/vue3-color',
				'tinymce/tinymce-vue',
				'@vue-a11y/announcer',
				'@vueuse/core',
				'chart.js',
				'clone-deep',
				'copyfiles',
				'debounce',
				'dropzone-vue3',
				'element-resize-event',
				'floating-vue',
				'highlight.js',
				'ofetch',
				'pinia',
				'reka-ui',
				'swiper',
				'tiny-emitter',
				'tinymce',
				'uuid',
				'vue',
				'vue-chartjs',
				'vue-draggable-plus',
				'vue-scrollto',
				'vue3-highlightjs',
			],
		},
		// Настройки сборки
		build: {
			// Генерировать sourcemap (карты кода для отладки) только в режиме разработки
			sourcemap: mode === 'development' ? 'inline' : false,
			// Целевые браузеры, для которых будет скомпилирован код
			target: ['chrome66', 'edge79', 'firefox67', 'safari12'],
			// Не очищать папку сборки перед каждой сборкой
			emptyOutDir: false,
			// Не разделять CSS на несколько файлов, а собрать все в один.
			cssCodeSplit: false,
			// Дополнительные настройки для Rollup (внутренний сборщик Vite)
			rollupOptions: {
				// Входная точка для сборки
				input: {
					build: './js/load.js',
				},
				// Настройки выходного файла
				output: {
					// Формат 'iife' создает самовызывающуюся функцию. Это "классический" способ
					// подключения JS-файла через тег <script>, он не загрязняет глобальную область видимости.
					format: 'iife', // Set the format to IIFE
					// Имя и путь для итогового JS-файла
					entryFileNames: 'js/build.js',
					// Функция для определения имени и пути для ассетов (например, CSS)
					assetFileNames: (assetInfo) => {
						const info = assetInfo.name.split('.');
						const extType = info[info.length - 1];
						if (/\.(css)$/.test(assetInfo.name)) {
							// Все CSS-файлы будут объединены в один styles/build.css
							return 'styles/build.css';
						}
						return `[name].${extType}`;
					},
					// Определяет, как внешние зависимости будут доступны в сборке.
					// Здесь предполагается, что Vue будет доступен глобально как `pkp.Vue`.
					globals: {
						vue: 'pkp.Vue',
					},
				},
			},
			// Корневая папка для выходных файлов. __dirname — это текущая директория проекта.
			outDir: path.resolve(__dirname),
		},
	};
});
