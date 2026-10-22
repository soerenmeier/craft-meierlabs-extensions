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

	// Generated image placeholders keep the asset's aspect ratio but are at
	// most this many pixels on the long side.
	private const MAX_IMAGE_SIZE = 2400;

	// Dummy files kept for reuse at the same time, see dummy().
	private const MAX_DUMMIES = 100;

	/** @var array<string, string> dummy files by key, oldest first */
	private array $dummies = [];

	/** @var string[] formats GD failed to write, they fall back to jpg */
	private array $brokenFormats = [];

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

				// With GD every image gets a placeholder of its own size and
				// format, so layouts keep their aspect ratios and nothing has
				// to be downloaded. Without it (Imagick only) all images get
				// the downloaded dummy.
				if (function_exists('imagecreatetruecolor')) {
					$this->mockAssetsFromQuery('image', $query, function(Asset $asset) {
						[$width, $height] = $this->placeholderSize($asset->getWidth(), $asset->getHeight());
						$format = $this->placeholderFormat($asset->getExtension());

						return $this->dummy(
							"image_{$width}x{$height}.{$format}",
							fn() => $this->generateImageDummy($width, $height, $format),
						);
					});
					continue;
				}
			}

			$this->mockAssetsFromQuery(
				$kind,
				$query,
				fn() => $this->dummy($kind, fn() => $this->downloadDummy($kind, $remoteUrl)),
			);
		}

		$this->mockAssetsFromQuery(
			'svg',
			Asset::find()->kind('image')->filename('*.svg'),
			fn() => $this->dummy('svg', fn() => $this->generateSvgDummy()),
		);

		return ExitCode::OK;
	}

	/**
	 * Replaces every asset matched by $query with the dummy file $dummyFor
	 * returns for it (see dummy()). When it returns null the dummy could not
	 * be made, and the remaining assets of the query are left as they are.
	 *
	 * @param callable(Asset): ?string $dummyFor
	 */
	private function mockAssetsFromQuery(string $label, AssetQuery $query, callable $dummyFor): void
	{
		$this->stdout("Replacing {$label} assets...\n");
		$successCount = 0;

		foreach ($query->each() as $asset) {
			try {
				if (!$this->force && $asset->getVolume()->getFs()->fileExists($asset->getPath())) {
					continue;
				}

				$tempPath = $dummyFor($asset);
				if ($tempPath === null) {
					$this->stderr("No dummy {$label} file, skipping the remaining {$label} assets.\n");
					break;
				}

				// replaceAssetFile() consumes (deletes) the file it's given, and it's
				// also what makes Craft refresh cached width/height/size and purge any
				// existing image transforms, so hand it a disposable copy per asset.
				$assetTempPath = $tempPath . '_' . $asset->id;
				if (!copy($tempPath, $assetTempPath)) {
					throw new \RuntimeException("Could not copy the dummy file to {$assetTempPath}");
				}

				Craft::$app->getAssets()->replaceAssetFile($asset, $assetTempPath, $asset->getFilename());

				$successCount++;

				if ($successCount % 100 === 0) {
					$this->stdout("Processed {$successCount} {$label}s...\n");
				}
			} catch (\Throwable $e) {
				$this->stderr("Failed to mock {$asset->getFilename()}: {$e->getMessage()}\n");
			}
		}

		$this->dropDummies();

		$this->stdout("Successfully mocked {$successCount} {$label} assets!\n\n");
	}

	/**
	 * The dummy file for $key, made by $make the first time and again if it
	 * disappeared mid-run (e.g. a concurrent invocation of this same command
	 * cleaning up after itself). At most MAX_DUMMIES files are kept, so a
	 * large run with many different image sizes doesn't fill the temp dir.
	 *
	 * @param callable(): ?string $make
	 */
	private function dummy(string $key, callable $make): ?string
	{
		if (isset($this->dummies[$key]) && is_file($this->dummies[$key])) {
			return $this->dummies[$key];
		}

		$path = $make();
		if ($path === null) {
			return null;
		}

		unset($this->dummies[$key]);
		$this->dummies[$key] = $path;

		if (count($this->dummies) > self::MAX_DUMMIES) {
			$oldest = array_key_first($this->dummies);
			@unlink($this->dummies[$oldest]);
			unset($this->dummies[$oldest]);
		}

		return $path;
	}

	private function dropDummies(): void
	{
		foreach ($this->dummies as $path) {
			@unlink($path);
		}

		$this->dummies = [];
	}

	/**
	 * The asset's size scaled down to MAX_IMAGE_SIZE on the long side, or a
	 * 4:3 default when the database doesn't know it.
	 *
	 * @return array{int, int}
	 */
	private function placeholderSize(?int $width, ?int $height): array
	{
		if (!$width || !$height) {
			return [1600, 1200];
		}

		$scale = min(1, self::MAX_IMAGE_SIZE / max($width, $height));

		return [max(1, (int)round($width * $scale)), max(1, (int)round($height * $scale))];
	}

	/**
	 * The format GD writes for a file extension. The image kinds GD can't
	 * write (heic, tiff, bmp, jp2, ...) get jpg data under their original
	 * file name, like the downloaded dummy; browsers and transforms only see
	 * the content.
	 */
	private function placeholderFormat(string $extension): string
	{
		$format = match (strtolower($extension)) {
			'png' => 'png',
			'gif' => 'gif',
			'webp' => function_exists('imagewebp') ? 'webp' : 'jpg',
			'avif' => function_exists('imageavif') ? 'avif' : 'jpg',
			default => 'jpg',
		};

		return in_array($format, $this->brokenFormats, true) ? 'jpg' : $format;
	}

	/**
	 * A grey placeholder with a cross and its size written in the middle,
	 * like the ones from dummyimage.com, generated with GD. When GD can't
	 * write the format (e.g. a broken avif encoder) it writes jpg instead;
	 * null when even that fails (e.g. a full disk).
	 */
	private function generateImageDummy(int $width, int $height, string $format): ?string
	{
		$image = imagecreatetruecolor($width, $height);
		imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 0xcc, 0xcc, 0xcc));

		$lineColor = imagecolorallocate($image, 0xb8, 0xb8, 0xb8);
		imagesetthickness($image, max(1, (int)(min($width, $height) / 200)));
		imageline($image, 0, 0, $width - 1, $height - 1, $lineColor);
		imageline($image, 0, $height - 1, $width - 1, 0, $lineColor);
		imagesetthickness($image, 1);

		// GD's built-in font is tiny: draw the label small on a transparent
		// background and scale it up to about a third of the image width.
		$label = "{$width} x {$height}";
		$font = 5;
		$labelWidth = imagefontwidth($font) * strlen($label);
		$labelHeight = imagefontheight($font);
		$labelImage = imagecreatetruecolor($labelWidth, $labelHeight);
		imagealphablending($labelImage, false);
		imagefilledrectangle($labelImage, 0, 0, $labelWidth - 1, $labelHeight - 1, imagecolorallocatealpha($labelImage, 0, 0, 0, 127));
		imagestring($labelImage, $font, 0, 0, $label, imagecolorallocate($labelImage, 0x55, 0x55, 0x55));

		$scale = min($width / 3 / $labelWidth, $height / 3 / $labelHeight);
		$targetWidth = max(1, (int)($labelWidth * $scale));
		$targetHeight = max(1, (int)($labelHeight * $scale));
		imagecopyresampled(
			$image,
			$labelImage,
			(int)(($width - $targetWidth) / 2),
			(int)(($height - $targetHeight) / 2),
			0,
			0,
			$targetWidth,
			$targetHeight,
			$labelWidth,
			$labelHeight,
		);
		imagedestroy($labelImage);

		$tempPath = Craft::$app->getPath()->getTempPath() . '/dummy_image_' . uniqid('', true);
		$written = $this->writeImage($image, $tempPath, $format);
		if (!$written && $format !== 'jpg') {
			@unlink($tempPath);
			$this->stderr("Could not write a {$format} placeholder, using jpg for {$format} images.\n");
			$this->brokenFormats[] = $format;
			$written = $this->writeImage($image, $tempPath, 'jpg');
		}
		imagedestroy($image);

		if (!$written) {
			@unlink($tempPath);
			$this->stderr("Could not write an image placeholder to {$tempPath}.\n");
			return null;
		}

		return $tempPath;
	}

	private function writeImage(\GdImage $image, string $path, string $format): bool
	{
		return match ($format) {
			'png' => imagepng($image, $path, 9),
			'gif' => imagegif($image, $path),
			'webp' => imagewebp($image, $path, 70),
			'avif' => imageavif($image, $path, 50),
			default => imagejpeg($image, $path, 70),
		} && is_file($path) && filesize($path) > 0;
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

	private function generateSvgDummy(): ?string
	{
		$this->stdout("Generating dummy svg placeholder...\n");

		$tempPath = Craft::$app->getPath()->getTempPath() . '/dummy_svg_' . uniqid('', true);
		if (file_put_contents($tempPath, self::DUMMY_SVG) === false) {
			$this->stderr("Could not write the svg placeholder to {$tempPath}.\n");
			return null;
		}

		return $tempPath;
	}
}
