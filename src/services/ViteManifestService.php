<?php

namespace soerenmeier\meierlabsextensions\services;

use Craft;
use craft\base\Component;
use craft\helpers\Json;
use soerenmeier\meierlabsextensions\assetbundles\Assets;

class ViteManifestService extends Component
{
	private ?array $_manifest = null;

	public function register(string $entry = 'src/main.js'): void
	{
		$view = Craft::$app->getView();

		// 1) Register bundle (publishes dist/)
		$bundle = $view->registerAssetBundle(Assets::class);

		// 2) Get published base URL
		$baseUrl = rtrim($bundle->baseUrl, '/');

		$manifest = $this->manifest();
		if (!isset($manifest[$entry])) {
			Craft::warning("Vite manifest entry not found: {$entry}", __METHOD__);
			return;
		}

		$item = $manifest[$entry];

		// 3) Register CSS
		foreach (($item['css'] ?? []) as $cssFile) {
			$view->registerCssFile($baseUrl . '/' . $cssFile);
		}

		// 4) Register JS (module)
		$view->registerJsFile(
			$baseUrl . '/' . $item['file'],
			['type' => 'module']
		);
	}

	private function manifest(): array
	{
		if ($this->_manifest !== null) {
			return $this->_manifest;
		}

		$distPath = Craft::getAlias('@soerenmeier/meierlabsextensions/dist');
		$manifestPath = $distPath . '/.vite/manifest.json';

		if (!is_file($manifestPath)) {
			Craft::warning("Vite manifest not found at {$manifestPath}", __METHOD__);
			return $this->_manifest = [];
		}

		return $this->_manifest = Json::decode(file_get_contents($manifestPath)) ?? [];
	}
}
