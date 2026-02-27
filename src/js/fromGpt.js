/**
 * Craft CP Matrix: inline ONLY the add-menu group button titled "Ungroup".
 * Hardcoded, minimal, safe against double-add (no data-type on proxies).
 */
(function (window) {
	const { Craft, Garnish, $ } = window;
	if (
		!Craft ||
		!Garnish ||
		!Garnish.DisclosureMenu ||
		!Craft.MatrixInput ||
		!$
	)
		return;

	const TARGET_GROUP_LABEL = 'ungroup';

	function getTriggerLabelText($trigger) {
		return (
			$trigger.find('.label').first().text().trim() ||
			$trigger.text().trim()
		);
	}

	function inlineOnlyUngroupMenu(disclosureMenu) {
		const $trigger = disclosureMenu.$trigger;
		const $container = disclosureMenu.$container;
		if (!$trigger || !$container) return;

		// Only Matrix "add" buttons live in ".matrix-field > .buttons"
		const $buttonsRow = $trigger.closest('.matrix-field > .buttons');
		if (!$buttonsRow.length) return;

		// Only act on the group button whose title/label matches TARGET_GROUP_LABEL
		if (getTriggerLabelText($trigger) !== TARGET_GROUP_LABEL) return;

		// Prevent double-init
		const triggerEl = $trigger[0];
		if (triggerEl._matrixInlineUngroupInitialized) return;
		triggerEl._matrixInlineUngroupInitialized = true;

		// Menu items for entry types are buttons with data-type
		const $menuButtons = $container.find('button[data-type]');
		if (!$menuButtons.length) return;

		// Hide original menu trigger
		$trigger.hide();

		// Inline container next to it
		const $inline = $('<div class="matrix-inline-ungroup"></div>');
		$inline.insertAfter($trigger);

		// Create proxy buttons WITHOUT data-type to avoid Craft delegated handlers (prevents double-add)
		const $proxyButtons = $menuButtons.map((_, btn) => {
			const $orig = $(btn);
			const type = $orig.data('type');
			const label = $orig.text().trim();

			const $proxy = $('<button/>', {
				type: 'button',
				class: 'btn dashed',
				text: label,
			});

			$proxy.data('matrixType', type);

			$proxy.on('click', ev => {
				ev.preventDefault();
				ev.stopPropagation();
				ev.stopImmediatePropagation();
				// Use Craft's native behavior by activating the original menu item
				$orig.trigger('activate');
			});

			return $proxy.get(0);
		});

		const $group = $('<div class="btngroup"></div>');
		$group.append($proxyButtons);
		$inline.append($group);

		// --- Icon rule: only ONE "add icon" in the whole buttons bar ---
		// If Craft already has an add-icon button visible (e.g. first group like "Bilder"),
		// then our inline ungroup must NOT show add icon.
		const $buttonsRoot = $inline
			.closest('.matrix-field')
			.find('> .buttons')
			.first();

		// Visible native "add icon" buttons (group triggers / collapsed single button)
		const hasVisibleNativeAddIcon =
			$buttonsRoot.find(
				'button[data-disclosure-trigger="true"].add.icon:visible',
			).length > 0;

		// Default: no add icon on our inline proxies
		$proxyButtons.first().removeClass('add icon btngroup-btn-first');

		// Only add it if there is no other visible native add-icon button
		if (!hasVisibleNativeAddIcon) {
			$proxyButtons.first().addClass('add icon btngroup-btn-first');
		}

		// Store for later enable/disable sync
		triggerEl._matrixInlineUngroupButtons = $proxyButtons;
	}

	// Hook disclosure creation so when Craft builds the menu, we can inline the Ungroup one.
	const origDisclosureInit = Garnish.DisclosureMenu.prototype.init;
	Garnish.DisclosureMenu.prototype.init = function (...args) {
		origDisclosureInit.apply(this, args);
		inlineOnlyUngroupMenu(this);
	};

	// Keep inline buttons enabled/disabled in sync with maxEntries, etc.
	const origUpdateAddEntryBtn = Craft.MatrixInput.prototype.updateAddEntryBtn;
	Craft.MatrixInput.prototype.updateAddEntryBtn = function (...args) {
		origUpdateAddEntryBtn.apply(this, args);

		const canAdd =
			typeof this.canAddMoreEntries === 'function'
				? this.canAddMoreEntries()
				: true;

		// Find the (now hidden) Ungroup trigger we inlined
		const $trigger = this.$container
			.find(
				'> .buttons button, > .buttons .menubtn, > .buttons .action-btn',
			)
			.filter(function () {
				return (
					this._matrixInlineUngroupInitialized &&
					this._matrixInlineUngroupButtons &&
					this._matrixInlineUngroupButtons.length
				);
			})
			.first();

		if (!$trigger.length) return;

		const btns = $trigger[0]._matrixInlineUngroupButtons;
		btns.disable();
		btns.parent().attr('title', '');

		if (!canAdd) {
			btns.parent().attr(
				'title',
				Craft.t('app', 'No more entries can be added.'),
			);
			return;
		}

		btns.enable();
	};

	// tiny optional style (safe)
	if (!document.getElementById('matrix-inline-ungroup-style')) {
		const style = document.createElement('style');
		style.id = 'matrix-inline-ungroup-style';
		style.textContent = `
      .matrix-inline-ungroup { display: inline-flex; gap: 6px; flex-wrap: wrap; }
      .matrix-inline-ungroup .btngroup { margin: 0; }
    `;
		document.head.appendChild(style);
	}
})(window);
