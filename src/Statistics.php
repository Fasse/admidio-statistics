<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Plugins\PluginRegistry;

/**
 * The identity of the statistics plugin.
 *
 * Every page of the plugin needs the Plugin object to build its own URLs, to find its templates and
 * to read its settings, and it must not be reachable when an administrator has switched the plugin
 * off. Both answers live here so that no page repeats them.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class Statistics
{
    /**
     * The plugin id, which is the name of the directory below plugins/. Admidio derives the
     * component name, the preference names and the menu entry from it, so it is not free to change.
     */
    public const PLUGIN_ID = 'statistics';

    /**
     * The plugin as Admidio knows it, refusing the request when the plugin is not installed or has
     * been disabled for the current organization.
     *
     * @return Plugin
     * @throws \Admidio\Infrastructure\Exception
     */
    public static function plugin(): Plugin
    {
        return PluginRegistry::requireEnabled(self::PLUGIN_ID);
    }
}
