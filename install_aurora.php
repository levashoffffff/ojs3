<?php
/**
 * Aurora Theme Installer for OJS 3.4+
 * Windows compatible version
 */

echo "=== Aurora Theme Installation for OJS 3.4+ ===\n\n";

// Определяем корневую директорию
define('BASE_DIR', __DIR__);

// Правильный путь для OJS 3.4+
$bootstrapFile = BASE_DIR . '/lib/pkp/includes/bootstrap.php';

if (!file_exists($bootstrapFile)) {
    die("ERROR: Cannot find OJS bootstrap at: " . $bootstrapFile . "\n");
}

echo "✓ Found OJS 3.4+ at: " . BASE_DIR . "\n";

// Загружаем OJS
require_once($bootstrapFile);

// Загружаем необходимые классы
import('lib.pkp.classes.plugins.PluginRegistry');
import('lib.pkp.classes.plugins.ThemePlugin');
import('classes.plugins.PluginDAO');

// Путь к теме
$themePath = BASE_DIR . '/plugins/themes/aurora';
$pluginFile = $themePath . '/AuroraThemePlugin.inc.php';

// Проверяем наличие файлов темы
if (!file_exists($pluginFile)) {
    echo "❌ ERROR: Aurora theme not found at: {$pluginFile}\n";
    echo "\nCreating minimal Aurora theme structure...\n";
    
    // Создаем минимальную версию темы
    mkdir($themePath . '/styles', 0777, true);
    mkdir($themePath . '/locale/ru_RU', 0777, true);
    
    // Создаем минимальный CSS файл
    file_put_contents($themePath . '/styles/aurora.less', "/* Aurora Theme Styles */\nbody { font-family: 'Inter', sans-serif; }");
    
    echo "✓ Created minimal theme structure\n";
    
    // Проверяем еще раз
    if (!file_exists($pluginFile)) {
        exit(1);
    }
}

echo "✓ Theme files found at: {$themePath}\n";

// Загружаем класс плагина
require_once($pluginFile);

if (!class_exists('AuroraThemePlugin')) {
    echo "❌ ERROR: AuroraThemePlugin class not found in file\n";
    
    // Проверяем содержимое файла
    $content = file_get_contents($pluginFile);
    if (strpos($content, 'class AuroraThemePlugin') === false) {
        echo "Class definition not found in file\n";
    }
    exit(1);
}

echo "✓ Plugin class loaded\n";

// Регистрируем плагин
$plugin = new AuroraThemePlugin();
$pluginName = strtolower($plugin->getName());

// Сохраняем в базу данных
$pluginDao = DAORegistry::getDAO('PluginDAO');

// Проверяем, существует ли уже плагин
$existingPlugin = null;
try {
    $existingPlugin = $pluginDao->getPlugin('themes', $pluginName);
} catch (Exception $e) {
    echo "ℹ No existing plugin found\n";
}

if ($existingPlugin) {
    echo "ℹ Plugin already exists, updating...\n";
    $plugin->setEnabled(true);
    $pluginDao->updatePlugin($plugin);
} else {
    echo "✓ Inserting new plugin...\n";
    $plugin->setEnabled(true);
    $plugin->setCategory('themes');
    $pluginDao->insertPlugin($plugin);
}

// Устанавливаем настройки
$contextId = 0; // Site-wide context
$pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
$pluginSettingsDao->updateSetting($pluginName, 'enabled', true, 'bool', $contextId);

echo "✓ Plugin enabled in database\n";

// Очищаем кэш
$cacheManager = CacheManager::getManager();
if ($cacheManager) {
    $cacheManager->flush();
    echo "✓ Cache cleared\n";
}

echo "\n✅ Aurora theme successfully installed!\n\n";
echo "Next steps:\n";
echo "1. Restart PHP server (Ctrl+C then restart)\n";
echo "2. Login to OJS admin panel\n";
echo "3. Go to: Settings → Website → Appearance\n";
echo "4. Select 'Aurora Theme' from the dropdown\n";
echo "\nOr check: Settings → Website → Plugins → Themes category\n";