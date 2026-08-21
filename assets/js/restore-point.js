/**
 * "Restore Original Design" panel — shown in the block editor sidebar on
 * every Page (see ichimaru_enqueue_restore_point_editor_script() in
 * includes/patterns.php). Matched by the current page's SLUG, not its post
 * ID (see ichimaru_page_snapshot_page_slugs()) — an ID goes stale if the
 * page is ever deleted and recreated, but the slug is what actually
 * identifies "the same page" to a restore point. If the current slug isn't
 * one of the known restore points, the panel says so instead of offering
 * a button.
 *
 * Clicking the button opens a confirmation modal styled with the Ichimaru
 * theme's own palette (see assets/css/restore-point.css) rather than a
 * plain browser confirm() — confirming loads that page's frozen snapshot
 * into the editor as unsaved changes. The editor still has to review and
 * click Update themselves, so a stray click can't silently overwrite a
 * live page.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	function RestorePointPanel() {
		var postId = wp.data.select( 'core/editor' ).getCurrentPostId();
		var postSlug = wp.data.select( 'core/editor' ).getCurrentPost().slug;
		var pageSlugs = ( window.ichimaruRestorePoints && window.ichimaruRestorePoints.pageSlugs ) || {};
		var labels = ( window.ichimaruRestorePoints && window.ichimaruRestorePoints.labels ) || {};

		var slug = null;
		for ( var key in pageSlugs ) {
			if ( pageSlugs[ key ] === postSlug ) {
				slug = key;
				break;
			}
		}
		var label = slug ? ( labels[ slug ] || slug ) : null;

		var confirmState = wp.element.useState( false );
		var isConfirmOpen = confirmState[ 0 ];
		var setConfirmOpen = confirmState[ 1 ];

		var busy = wp.element.useState( false );
		var isBusy = busy[ 0 ];
		var setBusy = busy[ 1 ];

		function doRestore() {
			setConfirmOpen( false );
			setBusy( true );
			wp.apiFetch( { path: '/ichimaru/v1/restore-point/' + postId } )
				.then( function ( response ) {
					var blocks = wp.blocks.parse( response.content );
					wp.data.dispatch( 'core/block-editor' ).resetBlocks( blocks );
					wp.data.dispatch( 'core/notices' ).createSuccessNotice(
						__( 'Original design loaded. Review it, then click Update to save.', 'ichimaru-core' ),
						{ type: 'snackbar' }
					);
				} )
				.catch( function ( error ) {
					wp.data.dispatch( 'core/notices' ).createErrorNotice(
						error && error.message
							? error.message
							: __( 'Could not load the restore point.', 'ichimaru-core' )
					);
				} )
				.then( function () {
					setBusy( false );
				} );
		}

		if ( ! slug ) {
			return el(
				wp.editPost.PluginDocumentSettingPanel,
				{ name: 'ichimaru-restore-point', title: __( 'Ichimaru Restore Point', 'ichimaru-core' ) },
				el(
					'p',
					{ style: { fontSize: '12px', color: '#757575', marginTop: 0, marginBottom: 0 } },
					__( 'Snapshot not found for this page.', 'ichimaru-core' )
				)
			);
		}

		return el(
			wp.editPost.PluginDocumentSettingPanel,
			{ name: 'ichimaru-restore-point', title: __( 'Ichimaru Restore Point', 'ichimaru-core' ) },
			el(
				'p',
				{ style: { fontSize: '12px', color: '#757575', marginTop: 0 } },
				__( 'Reset this page back to its original design if edits here go wrong.', 'ichimaru-core' )
			),
			el(
				wp.components.Button,
				{
					variant: 'secondary',
					isBusy: isBusy,
					disabled: isBusy,
					onClick: function () {
						setConfirmOpen( true );
					},
				},
				isBusy
					? __( 'Restoring…', 'ichimaru-core' )
					: __( 'Restore Original Design', 'ichimaru-core' )
			),
			el(
				'p',
				{ style: { fontSize: '11px', color: '#757575', marginBottom: 0 } },
				label
			),
			isConfirmOpen
				? el(
					wp.components.Modal,
					{
						className: 'ichimaru-restore-modal',
						title: __( 'Restore Original Design?', 'ichimaru-core' ),
						onRequestClose: function () {
							setConfirmOpen( false );
						},
					},
					el(
						'span',
						{ className: 'ichimaru-restore-modal__label' },
						label
					),
					el(
						'p',
						{ className: 'ichimaru-restore-modal__body' },
						__(
							'This will replace everything currently in the editor with the original design for this page. Your existing edits will be lost unless you Undo — nothing is saved until you click Update.',
							'ichimaru-core'
						)
					),
					el(
						'div',
						{ className: 'ichimaru-restore-modal__actions' },
						el(
							wp.components.Button,
							{
								variant: 'secondary',
								onClick: function () {
									setConfirmOpen( false );
								},
							},
							__( 'Cancel', 'ichimaru-core' )
						),
						el(
							wp.components.Button,
							{
								variant: 'primary',
								onClick: doRestore,
							},
							__( 'Restore', 'ichimaru-core' )
						)
					)
				)
				: null
		);
	}

	wp.plugins.registerPlugin( 'ichimaru-restore-point', {
		render: RestorePointPanel,
	} );
} )( window.wp );
