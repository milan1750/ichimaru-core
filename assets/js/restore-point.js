/**
 * "Restore Original Design" panel — shown in the block editor sidebar only
 * on pages that have a registered restore point (see
 * ichimaru_enqueue_restore_point_editor_script() in includes/patterns.php).
 *
 * Clicking the button loads that page's frozen snapshot into the editor
 * as unsaved changes — the editor still has to review and click Update
 * themselves, so a stray click can't silently overwrite a live page.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	function RestorePointPanel() {
		var postId = wp.data.select( 'core/editor' ).getCurrentPostId();
		var ids = ( window.ichimaruRestorePoints && window.ichimaruRestorePoints.ids ) || {};
		var labels = ( window.ichimaruRestorePoints && window.ichimaruRestorePoints.labels ) || {};

		var slug = null;
		for ( var key in ids ) {
			if ( ids[ key ] === postId ) {
				slug = key;
				break;
			}
		}
		if ( ! slug ) {
			return null;
		}

		var label = labels[ slug ] || slug;
		var busy = wp.element.useState( false );
		var isBusy = busy[ 0 ];
		var setBusy = busy[ 1 ];

		function restore() {
			var confirmed = window.confirm(
				__(
					'This will replace everything currently in the editor with the original design for this page. Your existing edits will be lost unless you Undo — nothing is saved until you click Update. Continue?',
					'ichimaru-core'
				)
			);
			if ( ! confirmed ) {
				return;
			}

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
					onClick: restore,
				},
				isBusy
					? __( 'Restoring…', 'ichimaru-core' )
					: __( 'Restore Original Design', 'ichimaru-core' )
			),
			el(
				'p',
				{ style: { fontSize: '11px', color: '#757575', marginBottom: 0 } },
				label
			)
		);
	}

	wp.plugins.registerPlugin( 'ichimaru-restore-point', {
		render: RestorePointPanel,
	} );
} )( window.wp );
