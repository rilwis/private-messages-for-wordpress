jQuery( document ).ready( function( $ )
{
	// Add more file
	$( '.rwpm-attach-file' ).click( function()
	{
		var $this = $( this ), $first = $this.parent().find( '.attach_files:first' );

		$first.clone().insertBefore( $this );

		return false;
	} );
	//Remove file
	$( '.rwpm-remove-file' ).live('click', function()
	{
		$(this).parent().fadeOut(500, function(){ $(this).remove(); });
	} );
} );