<?php
/**
 * Job Openings: custom post type so the Careers page's open positions can be
 * managed from wp-admin instead of hardcoded markup.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the "Job" post type.
 */
function ichimaru_register_job_type() {
	register_post_type(
		'job',
		array(
			'label'        => __( 'Jobs', 'ichimaru-core' ),
			'labels'       => array(
				'name'          => __( 'Jobs', 'ichimaru-core' ),
				'singular_name' => __( 'Job', 'ichimaru-core' ),
				'add_new_item'  => __( 'Add New Job', 'ichimaru-core' ),
				'edit_item'     => __( 'Edit Job', 'ichimaru-core' ),
				'all_items'     => __( 'All Jobs', 'ichimaru-core' ),
				'search_items'  => __( 'Search Jobs', 'ichimaru-core' ),
				'not_found'     => __( 'No jobs found.', 'ichimaru-core' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			// Not exposed via REST: same reasoning as the Location/Menu Item
			// post types — editing happens entirely through the classic meta
			// box below, and a non-public post type with show_in_rest=true is
			// still world-readable at /wp-json/wp/v2/job.
			'show_in_rest'       => false,
			'menu_icon'          => 'dashicons-businessperson',
			'supports'           => array( 'title', 'editor', 'page-attributes' ),
			'has_archive'        => false,
			'rewrite'            => false,
		)
	);
}
add_action( 'init', 'ichimaru_register_job_type' );

/**
 * Meta box: the three badge fields shown on a job card, matching the
 * original design's fixed location/type/department badge slots.
 */
function ichimaru_add_job_meta_box() {
	add_meta_box(
		'ichimaru_job_details',
		__( 'Job Details', 'ichimaru-core' ),
		'ichimaru_render_job_meta_box',
		'job',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_job', 'ichimaru_add_job_meta_box' );

function ichimaru_render_job_meta_box( $post ) {
	wp_nonce_field( 'ichimaru_save_job', 'ichimaru_job_nonce' );

	$location = get_post_meta( $post->ID, '_ichimaru_job_location', true );
	$type     = get_post_meta( $post->ID, '_ichimaru_job_type', true );
	$dept     = get_post_meta( $post->ID, '_ichimaru_job_dept', true );
	?>
	<p class="description"><?php esc_html_e( 'Title: the job title, e.g. "Kitchen Assistant". Description: type it in the main content area above — shown as plain text on the card.', 'ichimaru-core' ); ?></p>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="ichimaru-job-location"><?php esc_html_e( 'Location badge', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-job-location" name="ichimaru_job_location" value="<?php echo esc_attr( $location ); ?>" placeholder="All Locations" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-job-type"><?php esc_html_e( 'Type badge', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-job-type" name="ichimaru_job_type" value="<?php echo esc_attr( $type ); ?>" placeholder="Full &amp; Part-time" class="regular-text" /></td>
		</tr>
		<tr>
			<th><label for="ichimaru-job-dept"><?php esc_html_e( 'Department badge', 'ichimaru-core' ); ?></label></th>
			<td><input type="text" id="ichimaru-job-dept" name="ichimaru_job_dept" value="<?php echo esc_attr( $dept ); ?>" placeholder="Kitchen" class="regular-text" /></td>
		</tr>
	</table>
	<p class="description"><?php esc_html_e( 'Order on the page: use "Order" in Page Attributes (side panel).', 'ichimaru-core' ); ?></p>
	<?php
}

function ichimaru_save_job_meta( $post_id ) {
	if ( ! isset( $_POST['ichimaru_job_nonce'] ) || ! wp_verify_nonce( $_POST['ichimaru_job_nonce'], 'ichimaru_save_job' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'_ichimaru_job_location' => 'ichimaru_job_location',
		'_ichimaru_job_type'     => 'ichimaru_job_type',
		'_ichimaru_job_dept'     => 'ichimaru_job_dept',
	);
	foreach ( $fields as $meta_key => $field_name ) {
		update_post_meta( $post_id, $meta_key, isset( $_POST[ $field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) : '' );
	}
}
add_action( 'save_post_job', 'ichimaru_save_job_meta' );

/**
 * ---- Read-side helpers used by the theme. ----
 */

/**
 * All jobs in display order (Page Attributes "Order").
 *
 * @return WP_Post[]
 */
function ichimaru_get_jobs() {
	$query = new WP_Query(
		array(
			'post_type'      => 'job',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		)
	);
	return $query->posts;
}

/**
 * Job fields as a flat array, for card rendering.
 *
 * @return array
 */
function ichimaru_job_fields( $post ) {
	return array(
		'title'    => $post->post_title,
		'location' => get_post_meta( $post->ID, '_ichimaru_job_location', true ),
		'type'     => get_post_meta( $post->ID, '_ichimaru_job_type', true ),
		'dept'     => get_post_meta( $post->ID, '_ichimaru_job_dept', true ),
		'desc'     => trim( wp_strip_all_tags( $post->post_content ) ),
	);
}
