function split( val ) {
    return val.split( /,\s*/ );
}
function extractLast( term ) {
    return split( term ).pop();
}
jQuery(document).ready(function($) {
    $('#recipient')
    .autocomplete({
        minLength: 2,
        delay: 600,
        source: function(request, response) {
            var	data = {
                action  : 'pm4wp_get_users',
                term    : request.term
            };
            $.post(ajaxurl, data, function(r) {
                response(r);
            }, 'json');
        },
        focus: function() {
            // prevent value inserted on focus
            return false;
        },
        select: function( event, ui ) {
            var terms = split( this.value );
            // remove the current input
            terms.pop();
            // add the selected item
            terms.push( ui.item.value );
            // add placeholder to get the comma-and-space at the end
            terms.push( "" );
            this.value = terms.join( "," );
            return false;
        }
    });
});