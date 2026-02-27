import './style.scss';

import { initCkeFontSizes, initCkeHiddenList } from './js/cke';
import { initLinkFieldCompact } from './js/linkfield';
import { initUngroupMatrixButtons } from './js/matrix';

async function main() {
	const settings = window.meierlabsExtensions;
	if (settings?.ckeHiddenList) initCkeHiddenList();
	if (settings?.ckeFontSizes) initCkeFontSizes();
	if (settings?.linkFieldCompact) initLinkFieldCompact();
	if (settings?.ungroupMatrixButtons) initUngroupMatrixButtons();
}
main();
