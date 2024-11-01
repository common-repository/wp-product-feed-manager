var reviewAjaxNonce;

function wpprfm_getFeedAttributes( feedId, callback ) {

	jQuery.post(
		reviewAjaxNonce.ajaxurl,
		{
			action: 'myajax-get-product-review-feed-attributes',
			feedId: feedId,
			reviewFeedGetAttributesNonce: reviewAjaxNonce.reviewFeedGetAttributesNonce,
		},
		function( response ) {

			callback( wppfm_validateResponse( response ) );
		}
	);
}
