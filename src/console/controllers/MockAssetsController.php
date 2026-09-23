<?php

namespace soerenmeier\meierlabsextensions\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use yii\console\ExitCode;

/**
 * Replaces asset files with small, reliable dummy files.
 *
 * Useful for staging/testing environments where you want the database
 * structure of assets without the (large, potentially sensitive) real files.
 */
class MockAssetsController extends Controller
{
	public $defaultAction = 'index';

	public bool $force = false;

	private const DUMMY_URLS = [
		'image' => 'https://dummyimage.com/4000x4000.jpg',
		'pdf' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
		'video' => 'https://www.w3schools.com/html/mov_bbb.mp4',
		'audio' => 'https://www.w3schools.com/html/horse.mp3',
	];

	// Most SVGs on a Craft site are logos, so rather than downloading someone
	// else's (potentially trademarked) logo, generate a generic placeholder
	// badge locally. This also avoids relying on an extra external host.
	private const DUMMY_SVG = <<<SVG
	<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
		<circle cx="256" cy="256" r="240" fill="#e0e0e0" stroke="#999999" stroke-width="8"/>
		<path d="M256 120 L340 360 L256 310 L172 360 Z" fill="#999999"/>
		<text x="256" y="440" text-anchor="middle" font-family="sans-serif" font-size="40" font-weight="bold" fill="#666666">LOGO</text>
	</svg>
	SVG;

	public function options($actionID): array
	{
		return array_merge(parent::options($actionID), ['force']);
	}

	/**
	 * Replaces every image, pdf, video, audio, and svg asset with a dummy file.
	 *
	 * Assets whose file already exists on their volume are left untouched,
	 * unless --force is passed.
	 */
	public function actionIndex(): int
	{
		foreach (self::DUMMY_URLS as $kind => $remoteUrl) {
			$query = Asset::find()->kind($kind);

			// SVGs are grouped under the "image" kind by Craft, but they get
			// their own generated placeholder below instead of the raster dummy.
			if ($kind === 'image') {
				$query->filename('not *.svg');
			}

			$this->mockAssetsFromQuery($kind, $query, fn() => $this->downloadDummy($kind, $remoteUrl));
		}

		$this->mockAssetsFromQuery(
			'svg',
			Asset::find()->kind('image')->filename('*.svg'),
			fn() => $this->generateSvgDummy(),
		);

		return ExitCode::OK;
	}

	/**
	 * Replaces every asset matched by $query with a dummy file produced by
	 * $prepareDummy, which is called once up front and again if the dummy
	 * file unexpectedly disappears mid-run (e.g. a concurrent invocation of
	 * this same command cleaning up after itself).
	 *
	 * @param callable(): ?string $prepareDummy
	 */
	private function mockAssetsFromQuery(string $label, AssetQuery $query, callable $prepareDummy): void
	{
		$tempPath = $prepareDummy();
		if ($tempPath === null) {
			return;
		}

		$this->stdout("Replacing {$label} assets...\n");
		$successCount = 0;

		foreach ($query->each() as $asset) {
			try {
				if (!$this->force && $asset->getVolume()->getFs()->fileExists($asset->getPath())) {
					continue;
				}

				if (!is_file($tempPath)) {
					$tempPath = $prepareDummy();
					if ($tempPath === null) {
						break;
					}
				}

				// replaceAssetFile() consumes (deletes) the file it's given, and it's
				// also what makes Craft refresh cached width/height/size and purge any
				// existing image transforms, so hand it a disposable copy per asset.
				$assetTempPath = $tempPath . '_' . $asset->id;
				copy($tempPath, $assetTempPath);

				Craft::$app->getAssets()->replaceAssetFile($asset, $assetTempPath, $asset->getFilename());

				$successCount++;

				if ($successCount % 100 === 0) {
					$this->stdout("Processed {$successCount} {$label}s...\n");
				}
			} catch (\Throwable $e) {
				$this->stderr("Failed to mock {$asset->getFilename()}: {$e->getMessage()}\n");
			}
		}

		@unlink($tempPath);

		$this->stdout("Successfully mocked {$successCount} {$label} assets!\n\n");
	}

	private function downloadDummy(string $kind, string $remoteUrl): ?string
	{
		$this->stdout("Downloading dummy {$kind} from {$remoteUrl}...\n");

		$tempPath = Craft::$app->getPath()->getTempPath() . '/dummy_' . $kind . '_' . uniqid('', true);

		try {
			Craft::createGuzzleClient()->request('GET', $remoteUrl, ['sink' => $tempPath]);
		} catch (\Throwable $e) {
			$this->stderr("Failed to download dummy {$kind}: {$e->getMessage()}\n");
			return null;
		}

		return $tempPath;
	}

	private function generateSvgDummy(): string
	{
		$this->stdout("Generating dummy svg placeholder...\n");

		$tempPath = Craft::$app->getPath()->getTempPath() . '/dummy_svg_' . uniqid('', true);
		file_put_contents($tempPath, self::DUMMY_SVG);

		return $tempPath;
	}
}
