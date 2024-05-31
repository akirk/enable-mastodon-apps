jQuery( function( $ ) {
	// Accordion handling in various areas.
	$( '.enable-mastodon-apps-settings-accordion' ).on( 'click', '.enable-mastodon-apps-settings-accordion-trigger', function() {
		var isExpanded = ( 'true' === $( this ).attr( 'aria-expanded' ) );

		if ( isExpanded ) {
			$( this ).attr( 'aria-expanded', 'false' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', true );
		} else {
			$( this ).attr( 'aria-expanded', 'true' );
			$( '#' + $( this ).attr( 'aria-controls' ) ).attr( 'hidden', false );
		}
	} );

	$(document).on( 'wp-plugin-install-success', function( event, response ) {
		setTimeout( function() {
			$( '.activate-now' ).removeClass( 'thickbox open-plugin-details-modal' );
		}, 1200 );
	} );

	$(document).on( 'click', '.enable-mastodon-apps-settings .copyable', function( event, response ) {
		this.select();
	} );

	$(document).on( 'click', '.enable-mastodon-apps-registered-apps-page thead', function( event, response ) {
		$( this ).parent().find( 'tbody' ).toggle();
	} );

	const iframe = $( '.enable-mastodon-apps-settings iframe');
	if ( iframe.length ) {
		setInterval( function() {
			iframe[0].style.height = ( iframe[0].contentWindow.document.body.scrollHeight + 50 ) + 'px';
		}, 1000 );
	}

} );
