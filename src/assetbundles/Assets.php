<?php

namespace soerenmeier\meierlabsextensions\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class Assets extends AssetBundle
{
	public function init(): void
	{
		parent::init();

		// Define the path that your JS files are located
		$this->sourcePath = '@soerenmeier/meierlabsextensions/dist';

		$this->depends = [
			CpAsset::class,
		];
	}
}
