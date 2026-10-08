<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\PluginWidget;
use AdmidioPlugin\Statistics\Statistics;

/**
 * Who may look at the statistics and who may configure them.
 *
 * Both decisions live here rather than in the pages, so that they are made once and can be tested
 * without a request. Each page still requires system/login_valid.php as well, which is what makes
 * the answer fail closed even if an administrator sets the access preference to "everybody".
 *
 * Version 3 decided this in four copied blocks that read the role rights of its own menu entry and
 * ended in "else { $hasAccess = true; }". No role holds that right until somebody grants one, which
 * is the state right after its installer ran, so the default was access for everybody including
 * visitors who were not logged in at all. Its editor action script checked nothing whatsoever.
 *
 * The role lists $plgAllowShow and $plgAllowConfig are not ported: nothing ever assigned them, so
 * they always held the single value "Administrator", and no page read them anyway. Who may see the
 * statistics is answered by the access preference below, and more finely by the role rights of the
 * menu entry that Admidio creates for the plugin and that an administrator can edit in the menu
 * administration.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticsAccess
{
    /**
     * Whether the current user may look at the saved statistics.
     *
     * @return bool
     * @throws Exception
     */
    public static function mayView(): bool
    {
        global $gValidLogin;

        if (empty($gValidLogin)) {
            return false;
        }

        return PluginWidget::isVisible(Statistics::plugin()->getAccessSettingName());
    }

    /**
     * Whether the current user may create, change and delete statistics.
     *
     * @return bool
     * @throws Exception
     */
    public static function mayConfigure(): bool
    {
        global $gValidLogin, $gCurrentUser;

        if (empty($gValidLogin) || !isset($gCurrentUser)) {
            return false;
        }

        return $gCurrentUser->isAdministrator();
    }

    /**
     * @return void
     * @throws Exception SYS_NO_RIGHTS when the current user may not look at the statistics.
     */
    public static function assertMayView(): void
    {
        if (!self::mayView()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }

    /**
     * @return void
     * @throws Exception SYS_NO_RIGHTS when the current user may not configure the statistics.
     */
    public static function assertMayConfigure(): void
    {
        if (!self::mayConfigure()) {
            throw new Exception('SYS_NO_RIGHTS');
        }
    }
}
