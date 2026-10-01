<?php
/**
 * The manifest and the language files of the plugin.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;

final class ManifestTest extends PluginTestCase
{
    /**
     * Every preference the plugin owns. Renaming one orphans the row every organization already has,
     * so the list is frozen here on purpose.
     * @var array<int,string>
     */
    private const SETTINGS = array('statistics_plugin_enabled');

    private function readPlugin(): Plugin
    {
        return Plugin::read(dirname(__DIR__, 2));
    }

    /**
     * The keys of one language file of the plugin.
     * @return array<int,string>
     */
    private function languageKeys(string $language): array
    {
        $strings = simplexml_load_file(dirname(__DIR__, 2) . '/languages/' . $language . '.xml');
        $this->assertNotFalse($strings, 'languages/' . $language . '.xml is well-formed');

        $keys = array();
        foreach ($strings->string as $string) {
            $keys[] = (string)$string['name'];
        }
        sort($keys);

        return $keys;
    }

    public function testTheManifestIsValid(): void
    {
        $plugin = $this->readPlugin();

        $this->assertNull($plugin->error, 'the manifest is read without an error');
        $this->assertTrue($plugin->isValid());
        $this->assertSame('statistics', $plugin->id);
        $this->assertNotSame('', $plugin->version, 'a version is declared');
    }

    /**
     * The plugin brings pages, so Admidio creates the one menu entry that points at index.php. This
     * is the opposite of the impersonate plugin, which declares "menu": false.
     */
    public function testThePluginWantsItsMenuEntry(): void
    {
        $plugin = $this->readPlugin();

        $this->assertTrue($plugin->hasPages());
        $this->assertTrue($plugin->wantsMenuEntry());
        $this->assertContains('index.php', $plugin->getPages(), 'the menu entry points at index.php');
    }

    public function testEveryPageOfTheEditorIsDeclared(): void
    {
        $pages = $this->readPlugin()->getPages();

        foreach (array('index.php', 'show.php', 'editor.php', 'help.php') as $page) {
            $this->assertContains($page, $pages);
        }
    }

    public function testTheSettingsAreTheOnesTheCodeExpects(): void
    {
        $plugin = $this->readPlugin();

        $this->assertSame(self::SETTINGS, array_keys($plugin->settings));
    }

    /**
     * The access setting has to be exactly the name Admidio derives, because Component::isVisible()
     * asks for that name when it decides whether the menu entry is shown. A plugin that declares none
     * is visible to everybody as soon as it is enabled.
     */
    public function testTheAccessSettingIsTheOneAdmidioAsksFor(): void
    {
        $plugin = $this->readPlugin();

        $this->assertArrayHasKey($plugin->getAccessSettingName(), $plugin->settings);
        $this->assertNotSame(
            $plugin->getAccessSettingName(),
            $plugin->getEnabledSettingName(),
            'the plugin-owned and the Admidio-owned flag are different preferences'
        );
    }

    /**
     * Registered users only, so that installing the plugin does not put the statistics in front of
     * everybody who visits the site.
     */
    public function testTheAccessSettingDefaultsToRegisteredUsers(): void
    {
        $plugin = $this->readPlugin();
        $setting = $plugin->settings[$plugin->getAccessSettingName()];

        $this->assertSame('enum', $setting['type']);
        $this->assertSame('2', $setting['default']);
    }

    public function testEverySettingLabelIsATranslatedKey(): void
    {
        $ownKeys = $this->languageKeys('en');
        $coreKeys = array();
        $coreStrings = simplexml_load_file(dirname(__DIR__, 4) . '/languages/en.xml');
        $this->assertNotFalse($coreStrings);
        foreach ($coreStrings->string as $string) {
            $coreKeys[] = (string)$string['name'];
        }

        foreach ($this->readPlugin()->settings as $name => $setting) {
            foreach (array('label', 'description') as $part) {
                $key = (string)($setting[$part] ?? '');
                if ($key === '') {
                    continue;
                }

                $this->assertTrue(
                    Language::isTranslationStringId($key),
                    $name . ': ' . $part . ' is a translation key'
                );
                $this->assertContains(
                    $key,
                    str_starts_with($key, 'PLG_') ? $ownKeys : $coreKeys,
                    $name . ': ' . $key . ' is defined'
                );
            }
        }
    }

    /**
     * The one divergence this replaces was real: de.xml used to define a key that en.xml did not have
     * and the editor asked for the other spelling, so the German tooltip showed a raw key for years.
     *
     * @dataProvider translations
     */
    public function testEveryTranslationHasTheSameKeys(string $language): void
    {
        $this->assertSame(
            $this->languageKeys('en'),
            $this->languageKeys($language),
            $language . '.xml has exactly the keys of en.xml'
        );
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function translations(): array
    {
        return array(array('de'), array('de-DE'));
    }

    /**
     * German exists twice, informally and formally, and the two differ only where the text speaks to
     * the reader.
     */
    public function testTheFormalGermanAddressesTheReaderFormally(): void
    {
        $informal = file_get_contents(dirname(__DIR__, 2) . '/languages/de.xml');
        $formal = file_get_contents(dirname(__DIR__, 2) . '/languages/de-DE.xml');

        $this->assertNotFalse($informal);
        $this->assertNotFalse($formal);
        $this->assertNotSame($informal, $formal, 'the two German files are not the same file twice');
        $this->assertStringNotContainsString('Möchtest du', $formal);
        $this->assertStringNotContainsString('Willst du', $formal);
    }
}
