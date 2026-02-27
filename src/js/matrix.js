export function initUngroupMatrixButtons() {
	if (!Garnish || !Garnish.DisclosureMenu) return;

	// Hook disclosure creation so when Craft builds the menu, we can inline the Ungroup one.
	const origDisclosureInit = Garnish.DisclosureMenu.prototype.init;
	Garnish.DisclosureMenu.prototype.init = function (...args) {
		origDisclosureInit.apply(this, args);
		inlineOnlyUngroupMenu(this);
	};
}

function inlineOnlyUngroupMenu(disclosureMenu) {
	const $trigger = disclosureMenu.$trigger;
	const $container = disclosureMenu.$container;
	if (!$trigger || !$container) return;

	// Only Matrix "add" buttons live in ".matrix-field > .buttons"
	const $buttonsRow = $trigger.closest('.matrix-field > .buttons');
	if (!$buttonsRow.length) return;

	const triggerLabel = $trigger.find('.label').first().text().trim();
	// ignore triggers which don't have the label ungroup
	if (triggerLabel !== 'ungroup') return;

	// Prevent double-init
	const triggerEl = $trigger[0];
	if (triggerEl._matrixInlineUngroupInitialized) return;
	triggerEl._matrixInlineUngroupInitialized = true;

	// Menu items for entry types are buttons with data-type
	const $menuButtons = $container.find('button[data-type]');
	if (!$menuButtons.length) return;

	// Hide original menu trigger
	$trigger.hide();

	const triggerInBtnGroup = $trigger.closest('.btngroup').length > 0;

	// add a button group around the trigger
	if (!triggerInBtnGroup) {
		const $btnGroup = $('<div class="btngroup" />');
		$btnGroup.insertBefore($trigger);
		$btnGroup.append($trigger);
		$trigger.addClass('btngroup-btn-first');
	}

	const triggerWasFirst = $trigger.hasClass('btngroup-btn-first');

	const $proxyButtons = $menuButtons.map((idx, btn) => {
		const $orig = $(btn).clone();
		$orig.attr('id', $orig.attr('id') + '-clone');
		$orig.attr('class', 'btn dashed');

		console.log('idx', idx);

		if (idx === 0 && triggerWasFirst) {
			$orig.addClass('btngroup-btn-first icon add');
			$orig.find('.icon').remove();
		}

		if ($orig.find('.icon').length) {
			$orig.addClass('mle-btn-icon');
		}

		return $orig.get(0);
	});

	$($proxyButtons).insertAfter($trigger);
}
