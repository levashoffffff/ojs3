<?php

/**
 * @file index.php
 *
 * @brief Wrapper for the partner links block plugin.
 */

// Точка входа плагина. Когда OJS загружает плагины из папки plugins/blocks/,
// он подключает index.php каждой папки и получает отсюда готовый объект плагина.
return new \APP\plugins\blocks\partnerLinks\PartnerLinksBlockPlugin();
