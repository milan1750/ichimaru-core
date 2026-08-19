<?php
/**
 * Restaurant Locations: custom post type so branches can be managed from
 * wp-admin instead of hardcoded markup.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the "Location" post type.
 */
function ichimaru_register_location_type() {
	register_post_type(
		'location',
		array(
			'label'        => __( 'Locations', 'ichimaru-core' ),
			'labels'       => array(
				'name'          => __( 'Locations', 'ichimaru-core' ),
				'singular_name' => __( 'Location', 'ichimaru-core' ),
				'add_new_item'  => __( 'Add New Location', 'ichimaru-core' ),
				'edit_item'     => __( 'Edit Location', 'ichimaru-core' ),
				'all_items'     => __( 'All Locations', 'ichimaru-core' ),
				'search_items'  => __( 'Search Locations', 'ichimaru-core' ),
				'not_found'     => __( 'No locations found.', 'ichimaru-core' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			// Not exposed via REST: all editing happens through the classic
			// "Location Details" meta box below, and a non-public post type with
			// show_in_rest=true is still world-readable at /wp-json/wp/v2/location.
			'show_in_rest'       => false,
			'menu_icon'          => 'dashicons-location',
			'supports'           => array( 'title', 'page-attributes' ),
			'has_archive'        => false,
			'rewrite'            => false,
		)
	);
}
add_action( 'init', 'ichimaru_register_location_type' );

/**
 * Meta box: address, hours, contact details and map coordinates.
 */
function ichimaru_add_location_meta_box() {
	add_meta_box(
		'ichimaru_location_details',
		__( 'Location Details', 'ichimaru-core' ),
		'ichimaru_render_location_meta_box',
		'location',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_location', 'ichimaru_add_location_meta_box' );

function ichimaru_render_location_meta_box( $post ) {
	wp_nonce_field( 'ichimaru_save_location', 'ichimaru_location_nonce' );

	$area         = get_post_meta( $post->ID, '_ichimaru_area', true );
	$address      = get_post_meta( $post->ID, '_ichimaru_address', true );
	$hours        = get_post_meta( $post->ID, '_ichimaru_hours', true );
	$phone        = get_post_meta( $post->ID, '_ichimaru_phone_display', true );
	$tel          = get_post_meta( $post->ID, '_ichimaru_phone_tel', true );
	$email        = get_post_meta( $post->ID, '_ichimaru_email', true );
	$maps_url     = get_post_meta( $post->ID, '_ichimaru_maps_url', true );
	$lat          = get_post_meta( $post->ID, '_ichimaru_lat', true );
	$lng          = get_post_meta( $post->ID, '_ichimaru_lng', true );
	?>
	<p class="description"><?php esc_html_e( 'Title: how the location appears, e.g. "Ichimaru — Islington".', 'ichimaru-core' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="ichimaru-loc-area"><?php esc_html_e( 'Area', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-area" name="ichimaru_area" value="<?php echo esc_attr( $area ); ?>" placeholder="London · N1" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-address"><?php esc_html_e( 'Address', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-address" name="ichimaru_address" value="<?php echo esc_attr( $address ); ?>" placeholder="Islington, London N1" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-hours"><?php esc_html_e( 'Opening hours', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-hours" name="ichimaru_hours" value="<?php echo esc_attr( $hours ); ?>" placeholder="Mon&ndash;Fri 11am&ndash;9pm &middot; Sat 11am&ndash;10pm &middot; Sun 12pm&ndash;8pm" class="large-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-phone"><?php esc_html_e( 'Phone (display)', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-phone" name="ichimaru_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+44 (0)20 1234 5678" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-tel"><?php esc_html_e( 'Phone (tel: link)', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-tel" name="ichimaru_tel" value="<?php echo esc_attr( $tel ); ?>" placeholder="+442012345678" class="regular-text" />
			<p class="description"><?php esc_html_e( 'Digits only (with leading +), no spaces.', 'ichimaru-core' ); ?></p></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-email"><?php esc_html_e( 'Email (optional)', 'ichimaru-core' ); ?></label></th>
			<td><input type="email" id="ichimaru-loc-email" name="ichimaru_email" value="<?php echo esc_attr( $email ); ?>" placeholder="islington@ichimaruudon.com" class="regular-text" />
			<p class="description"><?php esc_html_e( 'Leave blank to hide the email row on this location\'s card.', 'ichimaru-core' ); ?></p></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-maps"><?php esc_html_e( 'Google Maps link', 'ichimaru-core' ); ?></label></th>
			<td><input type="url" id="ichimaru-loc-maps" name="ichimaru_maps_url" value="<?php echo esc_attr( $maps_url ); ?>" placeholder="https://maps.app.goo.gl/..." class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-lat"><?php esc_html_e( 'Latitude', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-lat" name="ichimaru_lat" value="<?php echo esc_attr( $lat ); ?>" placeholder="51.5322686" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-loc-lng"><?php esc_html_e( 'Longitude', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-loc-lng" name="ichimaru_lng" value="<?php echo esc_attr( $lng ); ?>" placeholder="-0.126483" class="regular-text" /></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Order on the page: use "Order" in Page Attributes (side panel).', 'ichimaru-core' ); ?></p>
	<?php
}

function ichimaru_save_location_meta( $post_id ) {
	if ( ! isset( $_POST['ichimaru_location_nonce'] ) || ! wp_verify_nonce( $_POST['ichimaru_location_nonce'], 'ichimaru_save_location' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'_ichimaru_area'          => 'ichimaru_area',
		'_ichimaru_address'       => 'ichimaru_address',
		'_ichimaru_hours'         => 'ichimaru_hours',
		'_ichimaru_phone_display' => 'ichimaru_phone',
		'_ichimaru_phone_tel'     => 'ichimaru_tel',
		'_ichimaru_email'         => 'ichimaru_email',
	);
	foreach ( $fields as $meta_key => $field_name ) {
		update_post_meta( $post_id, $meta_key, isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '' );
	}

	update_post_meta( $post_id, '_ichimaru_maps_url', isset( $_POST['ichimaru_maps_url'] ) ? esc_url_raw( wp_unslash( $_POST['ichimaru_maps_url'] ) ) : '' );
	update_post_meta( $post_id, '_ichimaru_lat', isset( $_POST['ichimaru_lat'] ) ? (float) $_POST['ichimaru_lat'] : 0 );
	update_post_meta( $post_id, '_ichimaru_lng', isset( $_POST['ichimaru_lng'] ) ? (float) $_POST['ichimaru_lng'] : 0 );
}
add_action( 'save_post_location', 'ichimaru_save_location_meta' );

/**
 * ---- Read-side helpers used by the theme. ----
 */

/**
 * All locations in display order (Page Attributes "Order").
 *
 * @return WP_Post[]
 */
function ichimaru_get_locations() {
	$query = new WP_Query(
		array(
			'post_type'      => 'location',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	return $query->posts;
}

/**
 * Location fields as a flat array, for card rendering.
 *
 * @return array
 */
function ichimaru_location_fields( $post ) {
	return array(
		'name'    => $post->post_title,
		'area'    => get_post_meta( $post->ID, '_ichimaru_area', true ),
		'address' => get_post_meta( $post->ID, '_ichimaru_address', true ),
		'hours'   => get_post_meta( $post->ID, '_ichimaru_hours', true ),
		'phone'   => get_post_meta( $post->ID, '_ichimaru_phone_display', true ),
		'tel'     => get_post_meta( $post->ID, '_ichimaru_phone_tel', true ),
		'email'   => get_post_meta( $post->ID, '_ichimaru_email', true ),
		'maps'    => get_post_meta( $post->ID, '_ichimaru_maps_url', true ),
		'lat'     => (float) get_post_meta( $post->ID, '_ichimaru_lat', true ),
		'lng'     => (float) get_post_meta( $post->ID, '_ichimaru_lng', true ),
	);
}

/**
 * All locations' map data (lat/lng/name/area/mapsUrl), in display order —
 * fed to the front-end map scripts via wp_add_inline_script().
 *
 * @return array<int, array{lat:float,lng:float,name:string,area:string,mapsUrl:string}>
 */
function ichimaru_get_locations_map_data() {
	$data = array();
	foreach ( ichimaru_get_locations() as $post ) {
		$fields = ichimaru_location_fields( $post );
		if ( ! $fields['lat'] || ! $fields['lng'] ) {
			continue;
		}
		$data[] = array(
			'lat'     => $fields['lat'],
			'lng'     => $fields['lng'],
			'name'    => $fields['name'],
			'area'    => $fields['area'],
			'mapsUrl' => $fields['maps'],
		);
	}
	return $data;
}
