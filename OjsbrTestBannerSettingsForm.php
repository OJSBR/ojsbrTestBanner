<?php

/**
 * @file plugins/generic/ojsbrTestBanner/OjsbrTestBannerSettingsForm.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OjsbrTestBannerSettingsForm
 *
 * @brief The message of the band, per language, for the whole installation.
 */

namespace APP\plugins\generic\ojsbrTestBanner;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;

class OjsbrTestBannerSettingsForm extends Form
{
    public function __construct(private OjsbrTestBannerPlugin $plugin)
    {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $this->addCheck(new FormValidatorCustom(
            $this,
            'message',
            'optional',
            'plugins.generic.ojsbrTestBanner.settings.message.tooLong',
            fn ($messages) => self::fitsTheBand((array) $messages)
        ));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /** No message longer than the band takes. */
    public static function fitsTheBand(array $messages): bool
    {
        foreach ($messages as $message) {
            if (mb_strlen(OjsbrTestBannerPlugin::cleanMessage((string) $message)) > OjsbrTestBannerPlugin::MAX_LENGTH) {
                return false;
            }
        }

        return true;
    }

    public function initData(): void
    {
        $this->_data = ['message' => $this->plugin->getMessages()];
    }

    public function readInputData(): void
    {
        $this->readUserVars(['message']);
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pluginName' => $this->plugin->getName(),
            'maxLength' => OjsbrTestBannerPlugin::MAX_LENGTH,
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $messages = array_filter(
            array_map(fn ($message) => OjsbrTestBannerPlugin::cleanMessage((string) $message), (array) $this->getData('message')),
            'strlen'
        );
        $this->plugin->updateSetting(Application::SITE_CONTEXT_ID, 'message', json_encode($messages, JSON_UNESCAPED_UNICODE), 'string');

        return parent::execute(...$functionArgs);
    }
}
