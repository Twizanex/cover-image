jQuery(document).ready(function() {

	var jq = jQuery,
		loc = window.bp_cover_photo_l10n || false;

	if ( loc ) {
		jq( window ).load( function(){
			var $imageToCrop = jq( '#cover-image-to-crop' ),
				scaledSize = {
					width:  $imageToCrop.width(),
					height: $imageToCrop.height()
				},
				scale = loc.width / scaledSize.width;

			if ( scale < 1 ) {
				scale = 1;
			}

			$imageToCrop.Jcrop({
				onSelect: updateCoords,
				aspectRatio: parseFloat( loc.aspectRatio, 10 ),
				setSelect: [ 0, 0, scaledSize.width, scaledSize.height ]
			});
			updateCoords({x: 0, y: 0, w: scaledSize.width, h: scaledSize.height });

			function updateCoords(c) {
				console.log(c);

				jq('#x').val( c.x * scale );
				jq('#y').val( c.y * scale );
				jq('#w').val( c.w * scale );
				jq('#h').val( c.h * scale );
			}
		} );
	}

	jq('#cover-photo-change').on('click', '#cover-photo-delete', function() {

		var $this = jq(this);

		jq.post( ajaxurl,
			{
				action: 'delete_cover_photo', cookie:encodeURIComponent(document.cookie), _wpnonce:jq( $this.parents('form').get(0) ).find('#_wpnonce').val()
			},

			function(response) {

				// Remove the current image
				jq('div#message').remove();
				$this.parent().before( jq( "<div id='message' class='update'>" + response + "</div>" ) );
				$this.prev('cover-photo').fadeOut(100);//hide current image
				$this.parent().remove();//remove from dom the delete link
				jq('body').removeClass('is-user-profile');
				location.reload();
			}
		);

		return false;

	});

	jq('#cover-photo-change').on('click', '#cover-photo-cancel', function() {

		var $this = jq(this);

		jq.post( ajaxurl,
			{
				action: 'cancel_cover_photo', cookie:encodeURIComponent(document.cookie), _wpnonce:jq( $this.parents('form').get(0) ).find('#_wpnonce').val()
			},

			function(response) {

				// Remove the current image
				jq('div#message').remove();
				$this.parent().before( jq( "<div id='message' class='update'>" + response + "</div>" ) );
				$this.prev('cover-photo').fadeOut(100);//hide current image
				$this.parent().remove();//remove from dom the delete link
				jq('body').removeClass('is-user-profile');
				location.reload();
			}
		);

		return false;

	});

});


