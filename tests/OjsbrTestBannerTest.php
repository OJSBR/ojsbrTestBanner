<?php

/**
 * @file plugins/generic/ojsbrTestBanner/tests/OjsbrTestBannerTest.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrTestBannerTest
 *
 * @brief The band as a page gets it: the message saved by the settings form,
 *        read back from the database and printed in the style of the page, in
 *        the language of the reader; never on a galley viewer; and a message
 *        that cannot break out of the style it goes in.
 *
 *        The hook is run the way the core runs it. The test puts back the
 *        message the site had.
 */

namespace APP\plugins\generic\ojsbrTestBanner\tests;

use APP\core\Application;
use APP\core\PageRouter;
use APP\plugins\generic\ojsbrTestBanner\OjsbrTestBannerPlugin;
use APP\plugins\generic\ojsbrTestBanner\OjsbrTestBannerSettingsForm;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PKP\context\Context;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\plugins\PluginSettingsDAO;
use PKP\tests\PKPTestCase;

#[CoversClass(OjsbrTestBannerPlugin::class)]
#[CoversClass(OjsbrTestBannerSettingsForm::class)]
class OjsbrTestBannerTest extends PKPTestCase
{
    private ?OjsbrTestBannerPlugin $plugin = null;
    private ?string $savedMessage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pinTheSite();
        PluginRegistry::loadCategory('generic', false, Application::SITE_CONTEXT_ID);
        $this->plugin = PluginRegistry::getPlugin('generic', 'ojsbrtestbannerplugin');
        if (!$this->plugin) {
            $this->markTestSkipped('the plugin is not installed here');
        }
        $this->savedMessage = $this->plugin->getSetting(Application::SITE_CONTEXT_ID, 'message');
    }

    protected function tearDown(): void
    {
        if ($this->plugin) {
            if ($this->savedMessage === null) {
                $this->forgetMessage();
            } else {
                $this->plugin->updateSetting(Application::SITE_CONTEXT_ID, 'message', $this->savedMessage, 'string');
            }
        }
        parent::tearDown();
    }

    public function testThePluginBelongsToTheSite(): void
    {
        $this->assertTrue($this->plugin->isSitePlugin(), 'enabled and set up once, by the site administrator');
    }

    public function testTheMessageWrittenInTheSettingsIsWhatThePageShows(): void
    {
        $locale = Locale::getLocale();
        $written = 'Homologação da revista "X" — use <b>revistas.example.org</b>';
        $this->saveThroughTheForm([$locale => "  {$written}\n "]);

        // Read back from the database, not from the form.
        $this->assertSame([$locale => 'Homologação da revista "X" — use revistas.example.org'], $this->storedMessages());

        $style = $this->styleOfAPage('frontend/pages/indexJournal.tpl');
        $this->assertNotNull($style, 'the page got the band');
        $this->assertSame('Homologação da revista "X" — use revistas.example.org', $this->contentOf($style));
    }

    public function testWithNoMessageThePageShowsTheTranslatedDefault(): void
    {
        $this->saveThroughTheForm([Locale::getLocale() => '   ']);

        $this->assertSame([], $this->storedMessages(), 'an empty message is not kept');
        $style = $this->styleOfAPage('frontend/pages/indexSite.tpl');
        $this->assertSame(__('plugins.generic.ojsbrTestBanner.message'), $this->contentOf($style));
    }

    public function testTheBackEndGetsTheBandToo(): void
    {
        $this->saveThroughTheForm([Locale::getLocale() => 'Ambiente de homologação']);

        $this->assertSame('Ambiente de homologação', $this->contentOf($this->styleOfAPage('dashboard/index.tpl', 'backend')));
    }

    public function testAGalleyViewerGetsNoBand(): void
    {
        $this->saveThroughTheForm([Locale::getLocale() => 'Ambiente de homologação']);

        foreach ([
            'plugins-23-plugins-generic-pdfJsViewer-generic-pdfJsViewer:display.tpl',
            'plugins-23-plugins-generic-epubJsViewer-generic-epubJsViewer:display.tpl',
            'plugins-7-plugins-generic-htmlArticleGalley-generic-htmlArticleGalley:display.tpl',
        ] as $template) {
            $this->assertNull($this->styleOfAPage($template), $template);
        }
    }

    public function testATooLongMessageIsRefused(): void
    {
        $limit = OjsbrTestBannerPlugin::MAX_LENGTH;
        $this->assertTrue(OjsbrTestBannerSettingsForm::fitsTheBand(['en' => str_repeat('é', $limit), 'pt_BR' => '']));
        $this->assertTrue(OjsbrTestBannerSettingsForm::fitsTheBand(['en' => str_repeat('a', $limit) . "\n\n  "]), 'spaces around do not count');
        $this->assertFalse(OjsbrTestBannerSettingsForm::fitsTheBand(['en' => 'ok', 'pt_BR' => str_repeat('a', $limit + 1)]));
    }

    public static function viewerTemplates(): array
    {
        return [
            ['plugins-23-plugins-generic-pdfJsViewer-generic-pdfJsViewer:display.tpl', true],
            ['plugins-23-plugins-generic-epubJsViewer-generic-epubJsViewer:display.tpl', true],
            ['plugins-7-plugins-generic-htmlArticleGalley-generic-htmlArticleGalley:display.tpl', true],
            ['plugins-9-plugins-generic-lensGalley-generic-lensGalley:display.tpl', true],
            ['frontend/pages/article.tpl', false],
            ['frontend/pages/issue.tpl', false],
            ['dashboard/index.tpl', false],
            ['plugins-30-plugins-generic-ojsbrTestBanner-generic-ojsbrTestBanner:settingsForm.tpl', false],
            ['plugins-31-plugins-generic-pdfJsViewerTools-generic-pdfJsViewerTools:display.tpl', false],
        ];
    }

    #[DataProvider('viewerTemplates')]
    public function testTheViewersAreKnownByTheTemplateTheCoreGives(string $template, bool $viewer): void
    {
        $this->assertSame($viewer, OjsbrTestBannerPlugin::isViewerTemplate($template));
    }

    public function testTheMessageOfTheReaderLanguageWinsThenThePrimaryOneThenAnyThenTheDefault(): void
    {
        $messages = ['pt_BR' => 'Homologação', 'en' => 'Staging'];
        $this->assertSame('Staging', OjsbrTestBannerPlugin::pickMessage($messages, 'en', 'pt_BR', 'default'));
        $this->assertSame('Homologação', OjsbrTestBannerPlugin::pickMessage($messages, 'es', 'pt_BR', 'default'));
        $this->assertSame('Staging', OjsbrTestBannerPlugin::pickMessage(['en' => 'Staging'], 'es', 'pt_BR', 'default'));
        $this->assertSame('default', OjsbrTestBannerPlugin::pickMessage([], 'es', 'pt_BR', 'default'));
        $this->assertSame('default', OjsbrTestBannerPlugin::pickMessage(['es' => '  '], 'es', null, 'default'));
    }

    public function testTheMessageIsPlainTextOnOneLine(): void
    {
        $this->assertSame('Use o portal oficial', OjsbrTestBannerPlugin::cleanMessage("  Use o <a href=\"x\">portal</a>\n\toficial "));
    }

    public static function hostileMessages(): array
    {
        return [
            'double quote' => ['a"b'],
            'single quote' => ["a'b"],
            'backslash' => ['a\\b'],
            'end of the style element' => ['</style><script>alert(1)</script>'],
            'comment' => ['/* x */'],
            'new line' => ["a\nb"],
            'braces' => ['} body { display: none'],
            'accents and dash' => ['Ambiente de testes — não oficial'],
            'other scripts' => ['テスト環境 — بيئة اختبار'],
            'emoji' => ['⚠️ teste'],
        ];
    }

    #[DataProvider('hostileMessages')]
    public function testNoMessageCanBreakOutOfTheStyle(string $message): void
    {
        $css = OjsbrTestBannerPlugin::css($message);

        $this->assertStringNotContainsStringIgnoringCase('</style', $css);
        $this->assertSame(1, preg_match('/content: ("(?:[^"\\\\\n]|\\\\[0-9a-f]{1,6} )*");/', $css, $match), 'one closed CSS string');
        $this->assertSame($message, $this->unescape($match[1]), 'and it says exactly the message');
        $this->assertSame(substr_count(OjsbrTestBannerPlugin::css('x'), '{'), substr_count($css, '{'), 'no rule added');
    }

    /** Saves as the administrator's form does: the form reads the posted values and executes. */
    private function saveThroughTheForm(array $messages): void
    {
        $form = new OjsbrTestBannerSettingsForm($this->plugin);
        $form->setData('message', $messages);
        $this->assertTrue(OjsbrTestBannerSettingsForm::fitsTheBand($messages));
        $form->execute();
    }

    private function storedMessages(): array
    {
        $raw = DB::table('plugin_settings')
            ->where('plugin_name', 'ojsbrtestbannerplugin')
            ->whereNull('context_id')
            ->where('setting_name', 'message')
            ->value('setting_value');

        return (array) json_decode((string) $raw, true);
    }

    /** The band style of a page with this template, as the core prints it, or null. */
    private function styleOfAPage(string $template, string $context = 'frontend'): ?string
    {
        // A template manager of its own, so no style of another page is counted.
        $templateMgr = new TemplateManager();

        // Only this plugin on the hook, as the core calls it; the others are put back.
        $hooks = &Hook::getHooks();
        $saved = $hooks['TemplateManager::display'] ?? null;
        Hook::clear('TemplateManager::display');
        Hook::add('TemplateManager::display', $this->plugin->addBand(...));
        try {
            $output = '';
            Hook::call('TemplateManager::display', [$templateMgr, &$template, &$output]);
        } finally {
            $hooks = &Hook::getHooks();
            unset($hooks['TemplateManager::display']);
            if ($saved !== null) {
                $hooks['TemplateManager::display'] = $saved;
            }
        }

        $html = $templateMgr->smartyLoadStylesheet(['context' => $context], $templateMgr);
        foreach (explode('<style type="text/css">', $html) as $chunk) {
            if (str_contains($chunk, 'body::after')) {
                return strstr($chunk, '</style>', true) ?: $chunk;
            }
        }

        return null;
    }

    private function contentOf(?string $style): ?string
    {
        if ($style === null || !preg_match('/content: ("(?:[^"\\\\\n]|\\\\[0-9a-f]{1,6} )*");/', $style, $match)) {
            return null;
        }

        return $this->unescape($match[1]);
    }

    /** Reads a CSS string the way a browser does. */
    private function unescape(string $string): string
    {
        return preg_replace_callback('/\\\\([0-9a-f]{1,6}) /', fn ($m) => mb_chr(hexdec($m[1])), substr($string, 1, -1));
    }

    private function forgetMessage(): void
    {
        $settings = new class () extends PluginSettingsDAO {
            public function forget(string $pluginName): void
            {
                $pluginName = static::_normalizePluginName($pluginName);
                DB::table('plugin_settings')
                    ->where('plugin_name', $pluginName)
                    ->whereNull('context_id')
                    ->where('setting_name', 'message')
                    ->delete();
                Cache::forget($this->_getCacheId(Application::SITE_CONTEXT_ID, $pluginName, true));
            }
        };
        $settings->forget($this->plugin->getName());
    }

    /** There is no URL on the command line: a router is set that answers "no journal", as the site does. */
    private function pinTheSite(): void
    {
        $request = Application::get()->getRequest();
        $router = new class () extends PageRouter {
            public function getContext(PKPRequest $request, bool $forceReload = false): ?Context
            {
                return null;
            }
        };
        $router->setApplication(Application::get());
        $request->setRouter($router);
    }
}
