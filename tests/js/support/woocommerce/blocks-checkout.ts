/**
 * Test double for `@woocommerce/blocks-checkout` (WooCommerce's
 * `wc.blocksCheckout`): records registrations and checks US and GB postcodes.
 */
export const registerCheckoutBlock = jest.fn();

export const isPostcode = ( {
	postcode,
	country,
}: {
	postcode: string;
	country: string;
} ): boolean => {
	if ( country === 'US' ) {
		return /^\d{5}(-\d{4})?$/.test( postcode );
	}
	if ( country === 'GB' ) {
		return /^[A-Z]{1,2}\d[A-Z\d]? ?\d[A-Z]{2}$/i.test( postcode );
	}

	return true;
};
