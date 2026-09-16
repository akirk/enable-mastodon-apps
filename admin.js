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

	$(document).on( 'click', '.enable-mastodon-apps-sortable-table th[data-sort-type] button', function( event ) {
		event.preventDefault();

		const header = $( this ).closest( 'th' );
		const table = header.closest( 'table' );
		const tbody = table.find( 'tbody' ).first();
		const column = header.index();
		const sortType = header.data( 'sort-type' );
		const direction = header.hasClass( 'sort-asc' ) ? 'desc' : 'asc';
		const rows = tbody.find( 'tr' ).get();

		table.find( 'th' ).removeClass( 'sort-asc sort-desc' ).removeAttr( 'aria-sort' );
		header.addClass( 'asc' === direction ? 'sort-asc' : 'sort-desc' ).attr( 'aria-sort', 'asc' === direction ? 'ascending' : 'descending' );

		rows.sort( function( a, b ) {
			const aCell = $( a ).children( 'td' ).eq( column );
			const bCell = $( b ).children( 'td' ).eq( column );
			let aValue = aCell.data( 'sort' );
			let bValue = bCell.data( 'sort' );

			if ( typeof aValue === 'undefined' ) {
				aValue = aCell.text().trim().toLowerCase();
			}
			if ( typeof bValue === 'undefined' ) {
				bValue = bCell.text().trim().toLowerCase();
			}

			if ( 'number' === sortType ) {
				aValue = parseInt( aValue, 10 ) || 0;
				bValue = parseInt( bValue, 10 ) || 0;
			} else {
				aValue = aValue.toString().toLowerCase();
				bValue = bValue.toString().toLowerCase();
			}

			if ( aValue === bValue ) {
				return 0;
			}

			return ( aValue > bValue ? 1 : -1 ) * ( 'asc' === direction ? 1 : -1 );
		} );

		$.each( rows, function( index, row ) {
			$( row ).toggleClass( 'alternate', 0 === index % 2 );
			tbody.append( row );
		} );
	} );

	const iframe = $( '.enable-mastodon-apps-settings iframe');
	if ( iframe.length ) {
		setInterval( function() {
			iframe[0].style.height = ( iframe[0].contentWindow.document.body.scrollHeight + 50 ) + 'px';
		}, 1000 );
	}

	$(document).on( 'change', '.enable-mastodon-apps-settings .appformats', function( event, response ) {
		$( this ).closest('tr').find( '[name=save-app]' ).show();
	} );

	$(document).on( 'click', '.enable-mastodon-apps-settings button[data-confirm]', function( event, response ) {
		if ( ! confirm( this.dataset.confirm ) ) {
			event.preventDefault();
			return false;
		}
	} );
} );
