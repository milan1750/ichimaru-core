<?php
/**
 * Page-layout patterns + restore points: a frozen snapshot of each site
 * page's post_content, captured once as a static file under
 * includes/page-snapshots/, serving two purposes:
 *
 *  1. Registered as Block Patterns under a custom "Ichimaru Page Layouts"
 *     category, so any of these designs can be inserted into a brand new
 *     (or existing) page from the block inserter — a starting point to
 *     build a new page from, not tied to any specific page ID.
 *  2. Exposed via a REST endpoint the block editor calls to power a
 *     one-click "Restore original design" button (see the theme's
 *     assets/js/restore-point.js) — for when a live edit goes wrong on
 *     one of the pages below and an editor wants back to a known-good
 *     baseline.
 *
 * These are deliberately NOT live-synced to the current page content —
 * that's what makes them useful as a restore point. To refresh a
 * baseline after deliberately redesigning a page, re-export that page's
 * current post_content over the matching file in page-snapshots/.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot slug => the WordPress page slug (post_name) it's a restore
 * point for. Deliberately keyed by slug rather than a frozen post ID: IDs
 * don't survive a page being deleted and recreated (e.g. during an
 * import, or an editor accidentally trashing and rebuilding a page), but
 * as long as the replacement page keeps the same slug/URL, the restore
 * point still finds it. ichimaru_get_page_id_by_snapshot_slug() resolves
 * the live post ID from this slug at request time — nothing here is
 * pre-bound to any specific ID.
 *
 * Only pages in this map get the "Restore original design" button; the
 * block patterns are registered for every file in page-snapshots/
 * regardless (harmless to offer as a starting layout even without a
 * live page to restore).
 *
 * Most snapshot slugs match the page's actual slug 1:1 — 'privacy' is the
 * one exception, since WordPress creates its default Privacy Policy page
 * with the slug 'privacy-policy'.
 *
 * @return array<string,string>
 */
function ichimaru_page_snapshot_page_slugs() {
	return apply_filters(
		'ichimaru_page_snapshot_page_slugs',
		array(
			'home'                 => 'home',
			'our-story'            => 'our-story',
			'contact'              => 'contact',
			'menu'                 => 'menu',
			'locations'            => 'locations',
			'group-dining'         => 'group-dining',
			'careers'              => 'careers',
			'faqs'                 => 'faqs',
			'terms'                => 'terms',
			'privacy'              => 'privacy-policy',
			'allergen-information' => 'allergen-information',
		)
	);
}

/**
 * The live post ID for a snapshot slug, resolved by looking up its
 * current page slug — or 0 if no such page exists right now (e.g. it was
 * deleted and never recreated).
 */
function ichimaru_get_page_id_by_snapshot_slug( $snapshot_slug ) {
	$page_slugs = ichimaru_page_snapshot_page_slugs();
	if ( ! isset( $page_slugs[ $snapshot_slug ] ) ) {
		return 0;
	}
	$page = get_page_by_path( $page_slugs[ $snapshot_slug ], OBJECT, 'page' );
	return $page ? $page->ID : 0;
}

/**
 * The snapshot slug a given post ID currently corresponds to, resolved by
 * its live page slug — or false if that post isn't one of our known
 * restore-point pages. Deliberately looks up the CURRENT slug for this ID
 * rather than searching a frozen ID map, so a page that was deleted and
 * recreated (new ID, same slug) is still matched correctly.
 *
 * @return string|false
 */
function ichimaru_get_snapshot_slug_by_page_id( $post_id ) {
	$page_slug = get_post_field( 'post_name', $post_id );
	if ( ! $page_slug ) {
		return false;
	}
	return array_search( $page_slug, ichimaru_page_snapshot_page_slugs(), true );
}

/**
 * Human-readable label per snapshot slug, used for both the pattern title
 * and the editor button's confirmation text.
 *
 * @return array<string,string>
 */
function ichimaru_page_snapshot_labels() {
	return apply_filters(
		'ichimaru_page_snapshot_labels',
		array(
			'home'                 => __( 'Home Page', 'ichimaru-core' ),
			'our-story'            => __( 'Our Story', 'ichimaru-core' ),
			'contact'              => __( 'Contact', 'ichimaru-core' ),
			'menu'                 => __( 'Menu', 'ichimaru-core' ),
			'locations'            => __( 'Locations', 'ichimaru-core' ),
			'group-dining'         => __( 'Group Dining', 'ichimaru-core' ),
			'careers'              => __( 'Careers', 'ichimaru-core' ),
			'faqs'                 => __( 'FAQs', 'ichimaru-core' ),
			'terms'                => __( 'Terms & Conditions', 'ichimaru-core' ),
			'privacy'              => __( 'Privacy Policy', 'ichimaru-core' ),
			'allergen-information' => __( 'Allergen Information', 'ichimaru-core' ),
		)
	);
}

/**
 * Directory holding the frozen snapshot .html files.
 */
function ichimaru_page_snapshot_dir() {
	return ICHIMARU_CORE_DIR . 'includes/page-snapshots/';
}

/**
 * A snapshot's raw block markup, or '' if the slug has no snapshot file.
 */
function ichimaru_get_page_snapshot( $slug ) {
	$slug = sanitize_file_name( $slug );
	$file = ichimaru_page_snapshot_dir() . $slug . '.html';
	if ( ! file_exists( $file ) ) {
		return '';
	}
	return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents
}

/**
 * ---- 1. Block patterns (inserter reuse) ----
 */

function ichimaru_register_page_snapshot_patterns() {
	if ( ! function_exists( 'register_block_pattern_category' ) ) {
		return;
	}

	register_block_pattern_category(
		'ichimaru-pages',
		array( 'label' => __( 'Ichimaru Page Layouts', 'ichimaru-core' ) )
	);

	$labels = ichimaru_page_snapshot_labels();
	$dir    = ichimaru_page_snapshot_dir();
	if ( ! is_dir( $dir ) ) {
		return;
	}

	foreach ( glob( $dir . '*.html' ) as $file ) {
		$slug    = basename( $file, '.html' );
		$content = ichimaru_get_page_snapshot( $slug );
		if ( ! $content ) {
			continue;
		}

		register_block_pattern(
			'ichimaru/page-' . $slug,
			array(
				'title'      => $labels[ $slug ] ?? ucwords( str_replace( '-', ' ', $slug ) ),
				/* translators: %s: page name, e.g. "Careers". */
				'description' => sprintf( __( 'Full page layout, as it currently looks on the %s page.', 'ichimaru-core' ), $labels[ $slug ] ?? $slug ),
				'categories' => array( 'ichimaru-pages' ),
				'content'    => $content,
			)
		);
	}
}
add_action( 'init', 'ichimaru_register_page_snapshot_patterns', 20 );

/**
 * ---- 2. REST endpoint (restore-point button) ----
 */

function ichimaru_register_restore_point_routes() {
	register_rest_route(
		'ichimaru/v1',
		'/restore-point/(?P<post_id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'ichimaru_rest_get_restore_point',
			'permission_callback' => function ( $request ) {
				return current_user_can( 'edit_post', (int) $request['post_id'] );
			},
			'args'                => array(
				'post_id' => array(
					'validate_callback' => function ( $value ) {
						return is_numeric( $value );
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'ichimaru_register_restore_point_routes' );

/**
 * REST callback: the restore-point snapshot content for a given post ID,
 * if that post's current slug matches one of our known restore-point
 * pages (see ichimaru_get_snapshot_slug_by_page_id()).
 */
function ichimaru_rest_get_restore_point( $request ) {
	$post_id = (int) $request['post_id'];
	$slug    = ichimaru_get_snapshot_slug_by_page_id( $post_id );

	if ( false === $slug ) {
		return new WP_Error(
			'ichimaru_no_restore_point',
			__( 'This page has no restore point.', 'ichimaru-core' ),
			array( 'status' => 404 )
		);
	}

	$content = ichimaru_get_page_snapshot( $slug );
	if ( ! $content ) {
		return new WP_Error(
			'ichimaru_snapshot_missing',
			__( 'The restore point file is missing.', 'ichimaru-core' ),
			array( 'status' => 404 )
		);
	}

	$labels = ichimaru_page_snapshot_labels();

	return array(
		'slug'    => $slug,
		'label'   => $labels[ $slug ] ?? $slug,
		'content' => $content,
	);
}

/**
 * ---- 3. Editor sidebar button ----
 */

function ichimaru_enqueue_restore_point_editor_script() {
	$post = get_post();
	if ( ! $post || 'page' !== $post->post_type ) {
		return;
	}

	wp_enqueue_script(
		'ichimaru-restore-point',
		ICHIMARU_CORE_URL . 'assets/js/restore-point.js',
		array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-i18n', 'wp-api-fetch' ),
		ICHIMARU_CORE_VERSION,
		true
	);

	wp_enqueue_style(
		'ichimaru-restore-point',
		ICHIMARU_CORE_URL . 'assets/css/restore-point.css',
		array(),
		ICHIMARU_CORE_VERSION
	);

	wp_localize_script(
		'ichimaru-restore-point',
		'ichimaruRestorePoints',
		array(
			'pageSlugs' => ichimaru_page_snapshot_page_slugs(),
			'labels'    => ichimaru_page_snapshot_labels(),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'ichimaru_enqueue_restore_point_editor_script' );
