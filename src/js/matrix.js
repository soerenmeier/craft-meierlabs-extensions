export function initUngroupMatrixButtons() {
	if (!Garnish || !Garnish.DisclosureMenu) return;

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

	const $buttonsRow = $trigger.closest('.matrix-field > .buttons');
	if (!$buttonsRow.length) return;

	const triggerLabel = $trigger.find('.label').first().text().trim();
	// ignore triggers which don't have the label ungroup
	if (triggerLabel !== 'ungroup') return;

	// Prevent double-init
	const triggerEl = $trigger[0];
	if (triggerEl._matrixInlineUngroupInitialized) return;
	triggerEl._matrixInlineUngroupInitialized = true;

	// if the trigger belongs to a btnGroup which is collapse
	// that is the mobile version, we ignore it
	if ($trigger.parent().hasClass('expandable-button--collapsed')) return;

	const $menuButtons = $container.find('button.menu-item[data-type]');
	if (!$menuButtons.length) return;

	// Hide original menu trigger
	$trigger.hide();

	// add a btngroup around the trigger
	const triggerInBtnGroup = $trigger.closest('.btngroup').length > 0;
	if (!triggerInBtnGroup) {
		const $btnGroup = $('<div class="btngroup" />');
		$btnGroup.insertBefore($trigger);
		$btnGroup.append($trigger);
		$trigger.addClass('btngroup-btn-first');
	}

	const triggerWasFirst = $trigger.hasClass('btngroup-btn-first');

	const $newButtons = $menuButtons.map((idx, btn) => {
		const $orig = $(btn);
		const $new = $orig.clone();
		$new.attr('id', $new.attr('id') + '-clone');
		$new.attr('class', 'btn dashed');

		console.log('idx', idx);

		if (idx === 0 && triggerWasFirst) {
			$new.addClass('btngroup-btn-first icon add');
			$new.find('.icon').remove();
		}

		if ($new.find('.icon').length) {
			$new.addClass('mle-btn-icon');
		}

		// https://github.com/craftcms/cms/blob/d3273e2967e3fc5368ef2821e0b317a2ba73985c/src/web/assets/matrix/src/MatrixInput.js#L127
		// because of that line, we can't just use the native
		// handler but have to trigger a custom activate event
		$new.on('activate', ev => {
			ev.preventDefault();
			ev.stopPropagation();
			ev.stopImmediatePropagation();

			$orig.trigger('activate');
		});

		return $new.get(0);
	});

	$($newButtons).insertAfter($trigger);
}
