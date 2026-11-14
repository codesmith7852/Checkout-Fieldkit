/**
 * Classic checkout and My Account address form: locale overrides after a
 * country change, and the enhanced multi-select dropdown.
 *
 * Loaded after WooCommerce's address-i18n script, so its handler for
 * country_to_state_changing runs after WooCommerce's.
 */
import {
	applyLocaleOverrides,
	type AddressI18nParams,
	type PriorityStore,
	type ScriptData,
} from './locale';
import './index.css';

declare global {
	interface Window {
		jQuery?: any;
		checkoutFieldkitClassic?: ScriptData;
		wc_address_i18n_params?: AddressI18nParams;
	}
}

const $ = window.jQuery;

if ( $ ) {
	// WooCommerce's script keeps row priorities in jQuery's data store.
	const priorities: PriorityStore = {
		get: ( row ) => {
			const value = parseInt( $( row ).data( 'priority' ), 10 );
			return Number.isNaN( value ) ? undefined : value;
		},
		set: ( row, priority ) => {
			$( row ).data( 'priority', priority );
		},
	};

	$( () => {
		$( document.body ).on(
			'country_to_state_changing',
			( _event: unknown, country: string, wrapper: any ) => {
				const data = window.checkoutFieldkitClassic;
				const params = window.wc_address_i18n_params;
				const element =
					wrapper && wrapper.get ? wrapper.get( 0 ) : null;
				if (
					data &&
					params &&
					element &&
					typeof country === 'string'
				) {
					applyLocaleOverrides(
						element,
						country,
						data,
						params,
						priorities
					);
				}
			}
		);

		if ( $.fn && $.fn.selectWoo ) {
			$( 'select.cfk-enhanced-select' ).each( function (
				this: HTMLSelectElement
			) {
				$( this ).selectWoo( {
					width: '100%',
					placeholder: $( this ).data( 'placeholder' ) || '',
				} );
			} );
		}
	} );
}
