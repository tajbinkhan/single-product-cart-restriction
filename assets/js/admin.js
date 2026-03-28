(function ($) {
	'use strict';

	$(function () {
		var $mode = $('#spcr_mode');
		if (!$mode.length) {
			return;
		}

		var $noticeRow = $('#spcr_custom_notice').closest('tr');
		var $forceRow = $('#spcr_force_quantity_one').closest('tr');

		function updateModeHints() {
			var isReplace = 'replace' === $mode.val();

			$noticeRow.toggleClass('spcr-mode-replace', isReplace);
			$forceRow.find('.description').toggleClass('spcr-muted', isReplace);
		}

		updateModeHints();
		$mode.on('change', updateModeHints);
	});
})(jQuery);
