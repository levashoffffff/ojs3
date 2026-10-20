/**
 * Комментарий разработчиков:
 * "На начальном этапе это просто сборка рантайма Vue, который может быть запрошен для Reader UI"
 * Это подтверждает, что сборка предназначена для публичного интерфейса.
 */
import {defineConfig} from 'vite';
import Vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig(({mode}) => {
	// Та же установка переменной окружения, что и в первом файле
	process.env.NODE_ENV = mode;
	return {
		// Плагинов здесь гораздо меньше
		plugins: [
			// Используется только плагин для Vue
			Vue({
				isProduction: mode === 'production',
			}),
		],
		// Публичная папка так же отключена
		publicDir: false,
		// Настройки разрешения модулей
		resolve: {
			alias: {
				// Тот же псевдоним '@' для UI-библиотеки
				'@': path.resolve(__dirname, 'lib/ui-library/src'),
				// Та же версия Vue с компилятором
				vue: 'vue/dist/vue.esm-bundler.js',
			},
			// Список для устранения дубликатов намного короче, т.к. используется меньше библиотек
			// https://github.com/vitejs/vite/discussions/15906
			dedupe: ['pinia', 'vue'],
		},
		// Настройки сборки
		build: {
			sourcemap: mode === 'development' ? 'inline' : false,
			target: ['chrome66', 'edge79', 'firefox67', 'safari12'],
			emptyOutDir: false,
			cssCodeSplit: false,
			rollupOptions: {
				// Входная точка ДРУГАЯ
				input: {
					build: './js/load_frontend.js',
				},
				// Настройки выходного файла
				output: {
					// Формат 'iife' создает самовызывающуюся функцию. Это "классический" способ
					// подключения JS-файла через тег <script>, он не загрязняет глобальную область видимости.
					format: 'iife', // Set the format to IIFE
					// Имена выходных файлов ДРУГИЕ, чтобы не конфликтовать с админской сборкой
					entryFileNames: 'js/build_frontend.js',
					// Функция для определения имени и пути для ассетов (например, CSS)
					assetFileNames: (assetInfo) => {
						const info = assetInfo.name.split('.');
						const extType = info[info.length - 1];
						if (/\.(css)$/.test(assetInfo.name)) {
							return 'styles/build_frontend.css';
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
