<?php

namespace soerenmeier\meierlabsextensions\models;

use craft\base\Model;

class Settings extends Model
{
	public $ckeHiddenList = false;
	public $ckeFontSizes = false;
	public $linkFieldCompact = false;
	public $ungroupMatrixButtons = false;

	public function defineRules(): array
	{
		return [
			[['ckeHiddenList', 'ckeFontSizes', 'linkFieldCompact', 'ungroupMatrixButtons'], 'boolean']
		];
	}
}
