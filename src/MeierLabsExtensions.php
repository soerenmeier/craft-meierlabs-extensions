<?php

namespace soerenmeier\meierlabsextensions;

use Craft;
use yii\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\web\View;
use soerenmeier\meierlabsextensions\models\Settings;
use soerenmeier\meierlabsextensions\services\ViteManifestService;

class MeierLabsExtensions extends Plugin
{
	public static $plugin;

	public string $schemaVersion = '1.0.0';
	public bool $hasCpSettings = true;

	public function init(): void
	{
		parent::init();
		self::$plugin = $this;

		// Register services
		$this->setComponents([
			'vite' => ViteManifestService::class,
		]);

		// Defer most setup tasks until Craft is fully initialized
		Craft::$app->onInit($this->attachEventHandlers(...));
	}

	private function attachEventHandlers(): void
	{
		Event::on(
			View::class,
			View::EVENT_BEFORE_RENDER_TEMPLATE,
			function (Event $event) {
				$request = Craft::$app->request;

				if (!$request->getIsCpRequest() || Craft::$app->user->getIsGuest()) {
					return;
				}

				$view = Craft::$app->getView();

				$setts = $this->getSettings();
				$settings = [
					'ckeHiddenList' => (bool)$setts->ckeHiddenList,
					'ckeFontSizes' => (bool)$setts->ckeFontSizes,
					'linkFieldCompact' => (bool)$setts->linkFieldCompact,
					'ungroupMatrixButtons' => (bool)$setts->ungroupMatrixButtons,
				];

				$view->registerJsVar('meierlabsExtensions', $settings, View::POS_HEAD);

				// Vite entry
				$this->vite->register('src/main.js');
			}
		);
	}

	protected function createSettingsModel(): ?Model
	{
		return new Settings();
	}

	protected function settingsHtml(): ?string
	{
		return Craft::$app->getView()->renderTemplate(
			'meierlabs-extensions/settings',
			[
				'settings' => $this->getSettings()
			]
		);
	}
}
