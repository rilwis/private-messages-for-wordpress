jQuery( document ).ready( function ( $ )
{
	$( '#recipient' ).autocomplete( {
		source: function ( request, response )
		{
			var data = {
				action: 'rwpm_get_users',
				term  : request.term
			};
			$.post( ajaxurl, data, function ( r )
			{
				response( r );
			}, 'json' );
		},
		focus : function ()
		{
			// prevent value inserted on focus
			return false;
		},
		select: function ( event, ui )
		{
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
	} );

    /**
     * Split string into multiple values, separated by commas
     *
     * @param val
     *
     * @return array
     */
	function split( val )
	{
		return val.split( /,\s*/ );
	}
} );