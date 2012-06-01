jQuery(document).ready(function($) {
	$('#recipient').autoSuggest(data);
	
	$('#send-form').submit(function() {
		$('<input type="hidden" name="recipient" value="' + $('.as-values').val() + '" />').appendTo($(this));
	});
});
