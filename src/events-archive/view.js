const initEventsLoadMore = () => {
	const buttons = document.querySelectorAll( '.js-carkeek-events-load-more' );

	if ( ! buttons.length ) {
		return;
	}

	buttons.forEach( ( button ) => {
		if ( button.dataset.ckInit === '1' ) {
			return;
		}

		button.dataset.ckInit = '1';

		button.addEventListener( 'click', async () => {
			if ( button.disabled ) {
				return;
			}

			const container = button.closest( '.carkeek-events-archive-block' );
			const list = container?.querySelector( '.carkeek-events-archive__list' );

			if ( ! container || ! list ) {
				return;
			}

			const currentPage  = parseInt( button.dataset.currentPage || '1', 10 );
			const defaultLabel = button.dataset.defaultLabel || button.textContent.trim();
			const loadingLabel = button.dataset.loadingLabel || 'Loading\u2026';
			const errorLabel   = button.dataset.errorLabel   || 'Unable to load more events.';
			const statusNode   = container.querySelector( '.js-carkeek-events-load-more-status' );

			button.disabled = true;
			button.classList.add( 'is-loading' );
			button.textContent = loadingLabel;
			button.setAttribute( 'aria-busy', 'true' );

			if ( statusNode ) {
				statusNode.textContent = '';
			}

			const formData = new FormData();
			formData.append( 'action',     'carkeek_events_load_more' );
			formData.append( 'nonce',      button.dataset.nonce );
			formData.append( 'attributes', button.dataset.attributes );
			formData.append( 'page',       String( currentPage + 1 ) );

			try {
				const response = await fetch( button.dataset.ajaxUrl, {
					method:      'POST',
					credentials: 'same-origin',
					body:        formData,
				} );

				const payload = await response.json();

				if ( ! payload?.success || ! payload?.data ) {
					throw new Error( 'Invalid response' );
				}

				if ( payload.data.itemsHtml ) {
					list.insertAdjacentHTML( 'beforeend', payload.data.itemsHtml );
				}

				button.dataset.currentPage = String( payload.data.nextPage || currentPage + 1 );

				if ( ! payload.data.hasMore ) {
					const wrap = button.closest( '.carkeek-events-archive__load-more-wrap' );
					( wrap || button ).remove();
					return;
				}
			} catch {
				button.disabled = false;
				button.classList.remove( 'is-loading' );
				button.removeAttribute( 'aria-busy' );
				button.textContent = defaultLabel;

				if ( statusNode ) {
					statusNode.textContent = errorLabel;
				}

				return;
			}

			button.disabled = false;
			button.classList.remove( 'is-loading' );
			button.removeAttribute( 'aria-busy' );
			button.textContent = defaultLabel;
		} );
	} );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initEventsLoadMore );
} else {
	initEventsLoadMore();
}
