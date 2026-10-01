<?php
/**
 * Makes the classes of the plugin loadable in its tests.
 *
 * Admidio reaches them through the plugin loader once the plugin is enabled. A test runs without that,
 * and composer only knows the core, so the namespace of the plugin is mapped here.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'AdmidioPlugin\\Statistics\\';
    if (!str_starts_with($class, $prefix) || str_starts_with($class, $prefix . 'Tests\\')) {
        return;
    }

    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
