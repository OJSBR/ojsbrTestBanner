<?php

/**
 * @file plugins/generic/ojsbrTestBanner/OjsbrTestBannerPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrTestBannerPlugin
 *
 * @brief Marks a non-production installation with a permanent red band, so nobody
 *        mistakes a test or staging portal for the live one.
 *
 *        The band belongs to the installation, not to a journal: the plugin is
 *        site-wide, enabled once by the site administrator, and shows on the
 *        site, on every journal and in the editorial back end. Its message can be
 *        written per language in the settings; an empty message falls back to
 *        the translated default. Full-page galley viewers are left alone, so the
 *        band never covers a reader.
 */

namespace APP\plugins\generic\ojsbrTestBanner;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;

class OjsbrTestBannerPlugin extends GenericPlugin
{
    /** Plugins whose template takes over the whole page to show a galley. */
    public const VIEWERS = [
        'pdfJsViewer',
        'epubJsViewer',
        'htmlArticleGalley',
        'lensGalley',
    ];

    /** The longest message the settings accept, in characters. */
    public const MAX_LENGTH = 200;

    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        if (Application::isUnderMaintenance()) {
            return $success;
        }
        // Only web pages show the band, so the hook is added only when the
        // plugin is on (for a site-wide plugin, the setting of the site).
        if ($success && $this->getEnabled()) {
            Hook::add('TemplateManager::display', $this->addBand(...));
        }

        return $success;
    }

    /** Enabled and configured for the whole installation, by the site administrator. */
    public function isSitePlugin(): bool
    {
        return true;
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.ojsbrTestBanner.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.ojsbrTestBanner.description');
    }

    public function getActions($request, $actionArgs): array
    {
        if (!$this->getEnabled()) {
            return parent::getActions($request, $actionArgs);
        }
        $router = $request->getRouter();

        return array_merge(
            [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url($request, null, null, 'manage', null, array_merge($actionArgs, ['verb' => 'settings'])),
                        $this->getDisplayName()
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ],
            parent::getActions($request, $actionArgs)
        );
    }

    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        // The core lets only the site administrator manage a site-wide plugin.
        $form = new OjsbrTestBannerSettingsForm($this);
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }
        $form->execute();

        return new JSONMessage(true);
    }

    /**
     * The messages written in the settings, by locale.
     *
     * @return array<string,string>
     */
    public function getMessages(): array
    {
        $messages = json_decode((string) $this->getSetting(Application::SITE_CONTEXT_ID, 'message'), true);

        return is_array($messages) ? array_filter(array_map('strval', $messages), 'strlen') : [];
    }

    /** The text of the band in the language of the page. */
    public function getMessage(): string
    {
        $site = Application::get()->getRequest()->getSite();

        return self::pickMessage(
            $this->getMessages(),
            Locale::getLocale(),
            $site ? $site->getPrimaryLocale() : null,
            __('plugins.generic.ojsbrTestBanner.message')
        );
    }

    /**
     * The message of the locale, else the one of the site's primary locale, else
     * any message written, else the translated default: a portal that wrote its
     * warning in one language shows that warning to every reader.
     *
     * @param array<string,string> $messages
     */
    public static function pickMessage(array $messages, string $locale, ?string $primaryLocale, string $default): string
    {
        foreach ([$locale, $primaryLocale] as $candidate) {
            if ($candidate !== null && trim($messages[$candidate] ?? '') !== '') {
                return trim($messages[$candidate]);
            }
        }
        foreach ($messages as $message) {
            if (trim($message) !== '') {
                return trim($message);
            }
        }

        return $default;
    }

    /** Plain text on a single line, as the band shows it. */
    public static function cleanMessage(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
    }

    /**
     * A CSS string with the text: every character that is not a letter, a digit
     * or a space is written as a CSS escape, so no text can close the string or
     * the <style> element it goes in.
     */
    public static function cssString(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $char) {
            $out .= preg_match('/^[\p{L}\p{N} ]$/u', $char) ? $char : '\\' . dechex(mb_ord($char)) . ' ';
        }

        return '"' . $out . '"';
    }

    /** Whether the template is the page of a full-page galley viewer. */
    public static function isViewerTemplate(string $template): bool
    {
        // A plugin template arrives as a Smarty resource id, with hyphens for slashes:
        // plugins-23-plugins-generic-epubJsViewer-generic-epubJsViewer:display.tpl
        return (bool) preg_match('/-(?:' . implode('|', self::VIEWERS) . ')[-:]/', $template);
    }

    /**
     * Adds the band to every page except the full-page galley viewers.
     *
     * @param array $args [TemplateManager, string $template, ...]
     */
    public function addBand(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        if (self::isViewerTemplate((string) ($args[1] ?? ''))) {
            return Hook::CONTINUE;
        }

        $templateMgr->addStyleSheet(
            'ojsbrTestBanner',
            self::css($this->getMessage()),
            [
                'inline' => true,
                'contexts' => ['frontend', 'backend'],
                'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
            ]
        );

        return Hook::CONTINUE;
    }

    /** The style of the band, fixed to the bottom of the window and kept off print. */
    public static function css(string $message): string
    {
        $content = self::cssString($message);

        return <<<CSS
            body::after {
                content: {$content};
                position: fixed;
                left: 0; right: 0; bottom: 0;
                z-index: 99999;
                display: block;
                box-sizing: border-box;
                padding: .45rem 1rem;
                background: #c0392b;
                color: #fff;
                font: 700 13px/1.3 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                letter-spacing: .04em;
                text-align: center;
                text-transform: uppercase;
                box-shadow: 0 -2px 6px rgba(0,0,0,.25);
                pointer-events: none;
            }
            body { padding-bottom: 3.5rem !important; }
            @media print { body::after { display: none; } body { padding-bottom: 0 !important; } }
            CSS;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\ojsbrTestBanner\OjsbrTestBannerPlugin', '\OjsbrTestBannerPlugin');
}
