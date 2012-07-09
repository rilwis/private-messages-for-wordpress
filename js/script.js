jQuery(document).ready(function($) {
	$('#send-form').submit(function() {
		$('<input type="hidden" name="recipient" value="' + $('.as-values').val() + '" />').appendTo($(this));
	});
});
