{**
 * plugins/generic/ojsbrTestBanner/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Settings of the test environment banner.
 *}
<script type="text/javascript">
	$(function() {ldelim}
		$('#ojsbrTestBannerSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form class="pkp_form" id="ojsbrTestBannerSettingsForm" method="post" action="{url router=PKP\core\PKPApplication::ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="ojsbrTestBannerFormNotification"}

	<p>{translate key="plugins.generic.ojsbrTestBanner.settings.intro"}</p>

	{fbvFormArea id="ojsbrTestBannerMessage"}
		{fbvFormSection for="message" description="plugins.generic.ojsbrTestBanner.settings.message.description"}
			{fbvElement type="text" label="plugins.generic.ojsbrTestBanner.settings.message" id="message" value=$message multilingual=true maxlength=$maxLength}
		{/fbvFormSection}
		<p>{translate key="plugins.generic.ojsbrTestBanner.settings.default"} <em>{translate key="plugins.generic.ojsbrTestBanner.message"}</em></p>
	{/fbvFormArea}

	{fbvFormButtons}
</form>
