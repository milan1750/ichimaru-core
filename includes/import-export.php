<?php
/**
 * Import / Export: bundle Menu Items (with categories, dietary tags and dish
 * photos) and Locations into a single portable .zip, and re-import it on
 * another install.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu entry.
 */
function ichimaru_register_import_export_page() {
	add_menu_page(
		__( 'Ichimaru Import / Export', 'ichimaru-core' ),
		__( 'Import / Export', 'ichimaru-core' ),
		'manage_options',
		'ichimaru-import-export',
		'ichimaru_render_import_export_page',
		'dashicons-migrate',
		58
	);
}
add_action( 'admin_menu', 'ichimaru_register_import_export_page' );

/**
 * Admin page: export button + import form, plus a results notice after
 * either action redirects back here.
 */
function ichimaru_render_import_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ichimaru Import / Export', 'ichimaru-core' ); ?></h1>

		<?php ichimaru_render_import_export_notice(); ?>

		<h2><?php esc_html_e( 'Export', 'ichimaru-core' ); ?></h2>
		<p><?php esc_html_e( 'Downloads a .zip containing all Menu Categories, Dietary Tags, Menu Items (with dish photos) and Locations.', 'ichimaru-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ichimaru_export', 'ichimaru_export_nonce' ); ?>
			<input type="hidden" name="action" value="ichimaru_export" />
			<?php submit_button( __( 'Download Export (.zip)', 'ichimaru-core' ), 'primary', 'submit', false ); ?>
		</form>

		<hr/>

		<h2><?php esc_html_e( 'Import', 'ichimaru-core' ); ?></h2>
		<p><?php esc_html_e( 'Upload a .zip created by the Export button above (from this site or another Ichimaru site). Matches existing items by name and updates them; anything not found is created. Nothing is deleted.', 'ichimaru-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'ichimaru_import', 'ichimaru_import_nonce' ); ?>
			<input type="hidden" name="action" value="ichimaru_import" />
			<input type="file" name="ichimaru_import_file" accept=".zip" required />
			<?php submit_button( __( 'Upload & Import', 'ichimaru-core' ), 'primary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/**
 * Render the result notice after a redirect back from export/import.
 */
function ichimaru_render_import_export_notice() {
	if ( ! isset( $_GET['ichimaru_ie'] ) ) {
		return;
	}
	$status = sanitize_key( wp_unslash( $_GET['ichimaru_ie'] ) );

	if ( 'import_success' === $status ) {
		$created = isset( $_GET['created'] ) ? absint( $_GET['created'] ) : 0;
		$updated = isset( $_GET['updated'] ) ? absint( $_GET['updated'] ) : 0;
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: created count, 2: updated count */
					__( 'Import complete — %1$d item(s) created, %2$d item(s) updated.', 'ichimaru-core' ),
					$created,
					$updated
				)
			)
		);
	} elseif ( 'import_error' === $status ) {
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'Import failed.', 'ichimaru-core' );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	} elseif ( 'export_error' === $status ) {
		$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'Export failed.', 'ichimaru-core' );
		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
	}
}

/**
 * ---- Export ----
 */

/**
 * Build the export .zip (data.json + images/) on disk and return its path.
 * Pure data/file logic, no HTTP output — kept separate from
 * ichimaru_handle_export() so it can be unit-tested directly.
 *
 * @return string|WP_Error Path to the generated .zip, or WP_Error on failure.
 */
function ichimaru_build_export_zip() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ichimaru_no_zip', __( 'The PHP zip extension is not available on this server.', 'ichimaru-core' ) );
	}

	$data = array(
		'generated_at'    => gmdate( 'c' ),
		'site'            => home_url(),
		'menu_categories' => array(),
		'menu_diets'      => array(),
		'menu_items'      => array(),
		'locations'       => array(),
	);

	foreach ( get_terms( array( 'taxonomy' => 'menu_category', 'hide_empty' => false ) ) as $term ) {
		$data['menu_categories'][] = array(
			'slug'          => $term->slug,
			'name'          => $term->name,
			'description'   => $term->description,
			'jp_label'      => get_term_meta( $term->term_id, 'jp_label', true ),
			'display_style' => get_term_meta( $term->term_id, 'display_style', true ),
			'sort_order'    => (int) get_term_meta( $term->term_id, 'sort_order', true ),
		);
	}

	foreach ( get_terms( array( 'taxonomy' => 'menu_diet', 'hide_empty' => false ) ) as $term ) {
		$data['menu_diets'][] = array(
			'slug'       => $term->slug,
			'name'       => $term->name,
			'sort_order' => (int) get_term_meta( $term->term_id, 'sort_order', true ),
		);
	}

	$tmp_dir    = get_temp_dir() . 'ichimaru-export-' . wp_generate_password( 8, false ) . '/';
	$images_dir = $tmp_dir . 'images/';
	wp_mkdir_p( $images_dir );

	$menu_items = get_posts(
		array(
			'post_type'      => 'menu_item',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);
	foreach ( $menu_items as $post ) {
		$cats  = wp_get_post_terms( $post->ID, 'menu_category', array( 'fields' => 'slugs' ) );
		$diets = wp_get_post_terms( $post->ID, 'menu_diet', array( 'fields' => 'slugs' ) );

		$image_filename = null;
		if ( has_post_thumbnail( $post ) ) {
			$attachment_id = get_post_thumbnail_id( $post );
			$file_path     = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$image_filename = $attachment_id . '-' . wp_basename( $file_path );
				copy( $file_path, $images_dir . $image_filename );
			}
		}

		$data['menu_items'][] = array(
			'title'       => $post->post_title,
			'content'     => $post->post_content,
			'menu_order'  => (int) $post->menu_order,
			'category'    => $cats ? $cats[0] : '',
			'diets'       => $diets,
			'price'       => get_post_meta( $post->ID, '_ichimaru_price', true ),
			'price_tba'   => (bool) get_post_meta( $post->ID, '_ichimaru_price_tba', true ),
			'vegan_opt'   => (bool) get_post_meta( $post->ID, '_ichimaru_vegan_opt', true ),
			'halal_opt'   => (bool) get_post_meta( $post->ID, '_ichimaru_halal_opt', true ),
			'group_label' => get_post_meta( $post->ID, '_ichimaru_group_label', true ),
			'image'       => $image_filename,
		);
	}

	$locations = get_posts(
		array(
			'post_type'      => 'location',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		)
	);
	foreach ( $locations as $post ) {
		$data['locations'][] = array(
			'title'      => $post->post_title,
			'menu_order' => (int) $post->menu_order,
			'area'       => get_post_meta( $post->ID, '_ichimaru_area', true ),
			'address'    => get_post_meta( $post->ID, '_ichimaru_address', true ),
			'hours'      => get_post_meta( $post->ID, '_ichimaru_hours', true ),
			'phone'      => get_post_meta( $post->ID, '_ichimaru_phone_display', true ),
			'tel'        => get_post_meta( $post->ID, '_ichimaru_phone_tel', true ),
			'email'      => get_post_meta( $post->ID, '_ichimaru_email', true ),
			'maps'       => get_post_meta( $post->ID, '_ichimaru_maps_url', true ),
			'lat'        => (float) get_post_meta( $post->ID, '_ichimaru_lat', true ),
			'lng'        => (float) get_post_meta( $post->ID, '_ichimaru_lng', true ),
		);
	}

	file_put_contents( $tmp_dir . 'data.json', wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

	$zip_path = get_temp_dir() . 'ichimaru-export-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.zip';
	$zip      = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		ichimaru_rrmdir( $tmp_dir );
		return new WP_Error( 'ichimaru_zip_open_failed', __( 'Could not create the export archive.', 'ichimaru-core' ) );
	}
	$zip->addFile( $tmp_dir . 'data.json', 'data.json' );
	foreach ( glob( $images_dir . '*' ) as $image_file ) {
		$zip->addFile( $image_file, 'images/' . wp_basename( $image_file ) );
	}
	$zip->close();

	ichimaru_rrmdir( $tmp_dir );

	return $zip_path;
}

/**
 * admin-post.php handler: build the export zip and stream it to the browser.
 */
function ichimaru_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ichimaru-core' ) );
	}
	check_admin_referer( 'ichimaru_export', 'ichimaru_export_nonce' );

	$zip_path = ichimaru_build_export_zip();
	if ( is_wp_error( $zip_path ) ) {
		ichimaru_redirect_ie_notice( 'export_error', array( 'message' => $zip_path->get_error_message() ) );
	}

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . basename( $zip_path ) . '"' );
	header( 'Content-Length: ' . filesize( $zip_path ) );
	readfile( $zip_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
	unlink( $zip_path );
	exit;
}
add_action( 'admin_post_ichimaru_export', 'ichimaru_handle_export' );

/**
 * ---- Import ----
 */
/**
 * Import a .zip built by ichimaru_build_export_zip() (or the Export button).
 * Pure data logic, no $_FILES/HTTP handling — kept separate from
 * ichimaru_handle_import() so it can be unit-tested directly.
 *
 * @param string $tmp_zip Path to the .zip file to import.
 * @return array{created:int,updated:int}|WP_Error
 */
function ichimaru_import_zip_file( $tmp_zip ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ichimaru_no_zip', __( 'The PHP zip extension is not available on this server.', 'ichimaru-core' ) );
	}

	$extract_dir = get_temp_dir() . 'ichimaru-import-' . wp_generate_password( 8, false ) . '/';
	wp_mkdir_p( $extract_dir );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp_zip ) ) {
		ichimaru_rrmdir( $extract_dir );
		return new WP_Error( 'ichimaru_zip_open_failed', __( 'Could not open the uploaded .zip file.', 'ichimaru-core' ) );
	}
	if ( ! ichimaru_safe_extract_zip( $zip, $extract_dir ) ) {
		$zip->close();
		ichimaru_rrmdir( $extract_dir );
		return new WP_Error( 'ichimaru_unsafe_zip', __( 'The .zip file contains an invalid entry and was not imported.', 'ichimaru-core' ) );
	}
	$zip->close();

	$json_path = $extract_dir . 'data.json';
	if ( ! file_exists( $json_path ) ) {
		ichimaru_rrmdir( $extract_dir );
		return new WP_Error( 'ichimaru_no_data_json', __( 'The .zip file does not contain a data.json — it was not created by this plugin\'s Export.', 'ichimaru-core' ) );
	}

	$data = json_decode( file_get_contents( $json_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents
	if ( ! is_array( $data ) ) {
		ichimaru_rrmdir( $extract_dir );
		return new WP_Error( 'ichimaru_invalid_json', __( 'data.json in the .zip is not valid.', 'ichimaru-core' ) );
	}

	$created = 0;
	$updated = 0;

	// Categories and dietary tags first, so menu items can be assigned to them.
	$cat_term_ids = array();
	foreach ( (array) ( $data['menu_categories'] ?? array() ) as $cat ) {
		$term = get_term_by( 'slug', $cat['slug'], 'menu_category' );
		if ( ! $term ) {
			$result = wp_insert_term( $cat['name'], 'menu_category', array( 'slug' => $cat['slug'], 'description' => $cat['description'] ?? '' ) );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_id = $result['term_id'];
			$created++;
		} else {
			$term_id = $term->term_id;
			wp_update_term( $term_id, 'menu_category', array( 'name' => $cat['name'], 'description' => $cat['description'] ?? '' ) );
			$updated++;
		}
		update_term_meta( $term_id, 'jp_label', $cat['jp_label'] ?? '' );
		update_term_meta( $term_id, 'display_style', ( 'list' === ( $cat['display_style'] ?? '' ) ) ? 'list' : 'card' );
		update_term_meta( $term_id, 'sort_order', (int) ( $cat['sort_order'] ?? 0 ) );
		$cat_term_ids[ $cat['slug'] ] = $term_id;
	}

	$diet_term_ids = array();
	foreach ( (array) ( $data['menu_diets'] ?? array() ) as $diet ) {
		$term = get_term_by( 'slug', $diet['slug'], 'menu_diet' );
		if ( ! $term ) {
			$result = wp_insert_term( $diet['name'], 'menu_diet', array( 'slug' => $diet['slug'] ) );
			if ( is_wp_error( $result ) ) {
				continue;
			}
			$term_id = $result['term_id'];
			$created++;
		} else {
			$term_id = $term->term_id;
			$updated++;
		}
		update_term_meta( $term_id, 'sort_order', (int) ( $diet['sort_order'] ?? 0 ) );
		$diet_term_ids[ $diet['slug'] ] = $term_id;
	}

	global $wpdb;

	foreach ( (array) ( $data['menu_items'] ?? array() ) as $item ) {
		// Match on title *and* category, not title alone: the same dish name
		// can legitimately appear in two categories (e.g. "Wakame Seaweed" is
		// both a udon topping and an udon bowl) — title-only matching would
		// silently merge two different items into one.
		$existing_id = ichimaru_find_menu_item_by_title_and_category( $item['title'], $item['category'] ?? '' );

		$post_args = array(
			'post_type'    => 'menu_item',
			'post_title'   => $item['title'],
			'post_content' => $item['content'] ?? '',
			'post_status'  => 'publish',
			'menu_order'   => (int) ( $item['menu_order'] ?? 0 ),
		);

		if ( $existing_id ) {
			$post_args['ID'] = $existing_id;
			wp_update_post( $post_args );
			$post_id = $existing_id;
			$updated++;
		} else {
			$post_id = wp_insert_post( $post_args );
			$created++;
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		if ( ! empty( $item['category'] ) && isset( $cat_term_ids[ $item['category'] ] ) ) {
			wp_set_object_terms( $post_id, array( $cat_term_ids[ $item['category'] ] ), 'menu_category' );
		}
		$item_diet_ids = array();
		foreach ( (array) ( $item['diets'] ?? array() ) as $diet_slug ) {
			if ( isset( $diet_term_ids[ $diet_slug ] ) ) {
				$item_diet_ids[] = $diet_term_ids[ $diet_slug ];
			}
		}
		wp_set_object_terms( $post_id, $item_diet_ids, 'menu_diet' );

		update_post_meta( $post_id, '_ichimaru_price', $item['price'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_price_tba', ! empty( $item['price_tba'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ichimaru_vegan_opt', ! empty( $item['vegan_opt'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ichimaru_halal_opt', ! empty( $item['halal_opt'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_ichimaru_group_label', $item['group_label'] ?? '' );

		if ( ! empty( $item['image'] ) && ! has_post_thumbnail( $post_id ) ) {
			$image_path = $extract_dir . 'images/' . $item['image'];
			if ( file_exists( $image_path ) ) {
				ichimaru_attach_image_to_post( $post_id, $image_path, $item['title'] );
			}
		}
	}

	foreach ( (array) ( $data['locations'] ?? array() ) as $loc ) {
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'location' AND post_title = %s AND post_status != 'trash' LIMIT 1",
				$loc['title']
			)
		);

		$post_args = array(
			'post_type'   => 'location',
			'post_title'  => $loc['title'],
			'post_status' => 'publish',
			'menu_order'  => (int) ( $loc['menu_order'] ?? 0 ),
		);

		if ( $existing_id ) {
			$post_args['ID'] = $existing_id;
			wp_update_post( $post_args );
			$post_id = $existing_id;
			$updated++;
		} else {
			$post_id = wp_insert_post( $post_args );
			$created++;
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			continue;
		}

		update_post_meta( $post_id, '_ichimaru_area', $loc['area'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_address', $loc['address'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_hours', $loc['hours'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_phone_display', $loc['phone'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_phone_tel', $loc['tel'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_email', $loc['email'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_maps_url', $loc['maps'] ?? '' );
		update_post_meta( $post_id, '_ichimaru_lat', (float) ( $loc['lat'] ?? 0 ) );
		update_post_meta( $post_id, '_ichimaru_lng', (float) ( $loc['lng'] ?? 0 ) );
	}

	ichimaru_rrmdir( $extract_dir );

	return array( 'created' => $created, 'updated' => $updated );
}

/**
 * admin-post.php handler: validate the upload, import it, and redirect
 * back to the admin page with a result notice.
 */
function ichimaru_handle_import() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ichimaru-core' ) );
	}
	check_admin_referer( 'ichimaru_import', 'ichimaru_import_nonce' );

	if ( empty( $_FILES['ichimaru_import_file']['tmp_name'] ) || UPLOAD_ERR_OK !== $_FILES['ichimaru_import_file']['error'] ) {
		ichimaru_redirect_ie_notice( 'import_error', array( 'message' => __( 'No file uploaded, or the upload failed.', 'ichimaru-core' ) ) );
	}

	$uploaded_name = isset( $_FILES['ichimaru_import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['ichimaru_import_file']['name'] ) ) : '';
	if ( 'zip' !== strtolower( pathinfo( $uploaded_name, PATHINFO_EXTENSION ) ) ) {
		ichimaru_redirect_ie_notice( 'import_error', array( 'message' => __( 'Please upload a .zip file created by the Export button.', 'ichimaru-core' ) ) );
	}

	$tmp_zip = $_FILES['ichimaru_import_file']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

	// Extension checks are trivially spoofed; confirm the actual file content
	// is a zip (magic number "PK\x03\x04" or an empty-archive variant) before
	// handing it to ZipArchive.
	$handle = fopen( $tmp_zip, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
	$magic  = $handle ? fread( $handle, 4 ) : '';
	if ( $handle ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
	}
	if ( ! in_array( $magic, array( "PK\x03\x04", "PK\x05\x06", "PK\x07\x08" ), true ) ) {
		ichimaru_redirect_ie_notice( 'import_error', array( 'message' => __( 'That file is not a valid .zip archive.', 'ichimaru-core' ) ) );
	}

	$result = ichimaru_import_zip_file( $tmp_zip );
	if ( is_wp_error( $result ) ) {
		ichimaru_redirect_ie_notice( 'import_error', array( 'message' => $result->get_error_message() ) );
	}

	ichimaru_redirect_ie_notice( 'import_success', $result );
}
add_action( 'admin_post_ichimaru_import', 'ichimaru_handle_import' );

/**
 * Find an existing menu_item to update, disambiguating same-titled items by
 * their category (e.g. "Wakame Seaweed" the udon topping vs. the udon bowl).
 * If several posts share the title and none match the target category, this
 * treats the item as new rather than guessing — a caller can still create a
 * duplicate title deliberately.
 *
 * @return int Post ID, or 0 if nothing matches.
 */
function ichimaru_find_menu_item_by_title_and_category( $title, $category_slug ) {
	global $wpdb;

	$candidate_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'menu_item' AND post_title = %s AND post_status != 'trash'",
			$title
		)
	);
	if ( ! $candidate_ids ) {
		return 0;
	}
	if ( 1 === count( $candidate_ids ) && ! $category_slug ) {
		return (int) $candidate_ids[0];
	}

	foreach ( $candidate_ids as $candidate_id ) {
		$terms = wp_get_post_terms( $candidate_id, 'menu_category', array( 'fields' => 'slugs' ) );
		if ( in_array( $category_slug, $terms, true ) ) {
			return (int) $candidate_id;
		}
	}

	// No candidate is in the right category. If there was only one candidate
	// to begin with, it's still the best match (e.g. the item had no category
	// assigned yet); otherwise refuse to guess between ambiguous same-titled items.
	return 1 === count( $candidate_ids ) ? (int) $candidate_ids[0] : 0;
}

/**
 * Sideload a local image file and set it as a post's featured image.
 */
function ichimaru_attach_image_to_post( $post_id, $file_path, $title ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$filename = preg_replace( '/^\d+-/', '', wp_basename( $file_path ) ); // strip the "{attachment_id}-" export prefix.
	$upload   = wp_upload_bits( $filename, null, file_get_contents( $file_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents
	if ( $upload['error'] ) {
		return;
	}
	$filetype   = wp_check_filetype( $upload['file'] );
	$attachment = array(
		'post_mime_type' => $filetype['type'],
		'post_title'     => sanitize_file_name( basename( $upload['file'] ) ),
		'post_content'   => '',
		'post_status'    => 'inherit',
	);
	$attach_id   = wp_insert_attachment( $attachment, $upload['file'], $post_id );
	$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
	wp_update_attachment_metadata( $attach_id, $attach_data );
	set_post_thumbnail( $post_id, $attach_id );
	update_post_meta( $attach_id, '_wp_attachment_image_alt', $title );
}

/**
 * Redirect back to the admin page with a result notice in the query string.
 */
function ichimaru_redirect_ie_notice( $status, $extra = array() ) {
	$url = add_query_arg(
		array_merge( array( 'page' => 'ichimaru-import-export', 'ichimaru_ie' => $status ), $extra ),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $url );
	exit;
}

/**
 * Extract a ZipArchive one entry at a time, rejecting any entry whose name
 * would resolve outside the target directory (path traversal / "zip slip",
 * e.g. an entry named "../../wp-config.php"). ZipArchive::extractTo() alone
 * doesn't guarantee protection against this across all PHP/libzip builds.
 *
 * @return bool True if every entry was safe and extracted.
 */
function ichimaru_safe_extract_zip( ZipArchive $zip, $target_dir ) {
	$target_dir = wp_normalize_path( trailingslashit( realpath( $target_dir ) ) );

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( false === $name || '' === $name ) {
			continue;
		}
		// Reject absolute paths, drive letters, and any ".." segment outright.
		if ( '/' === $name[0] || preg_match( '#^[A-Za-z]:#', $name ) || false !== strpos( $name, '..' ) ) {
			return false;
		}

		$dest = $target_dir . ltrim( wp_normalize_path( $name ), '/' );

		// Belt-and-braces: confirm the resolved path still sits under $target_dir.
		if ( 0 !== strpos( $dest, $target_dir ) ) {
			return false;
		}

		if ( '/' === substr( $name, -1 ) ) {
			wp_mkdir_p( $dest );
			continue;
		}

		wp_mkdir_p( dirname( $dest ) );
		$contents = $zip->getFromIndex( $i );
		if ( false === $contents || false === file_put_contents( $dest, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}
	}
	return true;
}

/**
 * Recursively remove a temp directory this plugin created.
 */
function ichimaru_rrmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			ichimaru_rrmdir( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}
