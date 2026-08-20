<?php
/**
 * Menu Items: custom post type, taxonomies and admin fields so the
 * restaurant menu can be managed from wp-admin instead of hardcoded markup.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the "Menu Item" post type and its two taxonomies.
 */
function ichimaru_register_menu_item_type() {
	register_post_type(
		'menu_item',
		array(
			'label'        => __( 'Menu Items', 'ichimaru-core' ),
			'labels'       => array(
				'name'               => __( 'Menu Items', 'ichimaru-core' ),
				'singular_name'      => __( 'Menu Item', 'ichimaru-core' ),
				'add_new_item'       => __( 'Add New Menu Item', 'ichimaru-core' ),
				'edit_item'          => __( 'Edit Menu Item', 'ichimaru-core' ),
				'all_items'          => __( 'All Menu Items', 'ichimaru-core' ),
				'search_items'       => __( 'Search Menu Items', 'ichimaru-core' ),
				'not_found'          => __( 'No menu items found.', 'ichimaru-core' ),
				'featured_image'     => __( 'Dish Photo', 'ichimaru-core' ),
				'set_featured_image' => __( 'Set dish photo', 'ichimaru-core' ),
			),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			// Deliberately not exposed via REST: the description field is rendered
			// as plain text on the front end (see ichimaru_menu_item_desc()), so
			// there's no block-editor benefit, and it would otherwise make every
			// dish's data readable by anyone at /wp-json/wp/v2/menu_item.
			'show_in_rest'       => false,
			'menu_icon'          => 'dashicons-carrot',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'taxonomies'         => array( 'menu_category', 'menu_diet' ),
		)
	);

	register_taxonomy(
		'menu_category',
		'menu_item',
		array(
			'label'             => __( 'Menu Categories', 'ichimaru-core' ),
			'labels'            => array(
				'name'          => __( 'Menu Categories', 'ichimaru-core' ),
				'singular_name' => __( 'Menu Category', 'ichimaru-core' ),
			),
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'rewrite'           => false,
		)
	);

	register_taxonomy(
		'menu_diet',
		'menu_item',
		array(
			'label'             => __( 'Dietary Tags', 'ichimaru-core' ),
			'labels'            => array(
				'name'          => __( 'Dietary Tags', 'ichimaru-core' ),
				'singular_name' => __( 'Dietary Tag', 'ichimaru-core' ),
			),
			// Hierarchical purely to get the checkbox-list admin UI for this small, fixed vocabulary.
			'hierarchical'      => true,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => false,
			'rewrite'           => false,
		)
	);
}
add_action( 'init', 'ichimaru_register_menu_item_type' );

/**
 * Term meta for menu_category: a Japanese label under the section heading,
 * a display style (card grid vs. plain list), and the order categories
 * appear in the filter bar / page body.
 */
function ichimaru_register_menu_category_meta() {
	register_term_meta(
		'menu_category',
		'jp_label',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_term_meta(
		'menu_category',
		'display_style',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => function ( $value ) {
				return 'list' === $value ? 'list' : 'card';
			},
		)
	);
	register_term_meta(
		'menu_category',
		'sort_order',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		)
	);
	register_term_meta(
		'menu_category',
		'show_on_home',
		array(
			'type'              => 'boolean',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);
	register_term_meta(
		'menu_category',
		'category_image_id',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		)
	);
}
add_action( 'init', 'ichimaru_register_menu_category_meta' );

/**
 * Load the WP media uploader on the Menu Category add/edit screens only.
 */
function ichimaru_enqueue_menu_category_media( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'menu_category' !== $screen->taxonomy ) {
		return;
	}
	wp_enqueue_media();
}
add_action( 'admin_enqueue_scripts', 'ichimaru_enqueue_menu_category_media' );

/**
 * Inline JS driving the "Category image" media-uploader field, shared by
 * the add and edit screens (they never render on the same page).
 */
function ichimaru_menu_category_image_field_script() {
	?>
	<script>
	( function( $ ) {
		function ichimaruBindCategoryImagePicker( $scope ) {
			var $button  = $scope.find( '#ichimaru-category-image-button' );
			var $remove  = $scope.find( '#ichimaru-category-image-remove' );
			var $input   = $scope.find( '#ichimaru-category-image-id' );
			var $preview = $scope.find( '#ichimaru-category-image-preview' );
			var frame;

			$button.on( 'click', function( e ) {
				e.preventDefault();
				if ( frame ) {
					frame.open();
					return;
				}
				frame = wp.media( {
					title: '<?php echo esc_js( __( 'Select category image', 'ichimaru-core' ) ); ?>',
					multiple: false,
					library: { type: 'image' }
				} );
				frame.on( 'select', function() {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
					$input.val( attachment.id );
					$preview.html( '<img src="' + url + '" style="max-width:150px;height:auto;display:block;" />' );
					$remove.show();
				} );
				frame.open();
			} );

			$remove.on( 'click', function( e ) {
				e.preventDefault();
				$input.val( '' );
				$preview.empty();
				$remove.hide();
			} );
		}
		$( function() {
			ichimaruBindCategoryImagePicker( $( document ) );
		} );
	} )( jQuery );
	</script>
	<?php
}

/**
 * Term meta for menu_diet: the order dietary tags appear as filter chips
 * (and as badges on a dish card, left to right).
 */
function ichimaru_register_menu_diet_meta() {
	register_term_meta(
		'menu_diet',
		'sort_order',
		array(
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'absint',
		)
	);
}
add_action( 'init', 'ichimaru_register_menu_diet_meta' );

/**
 * Extra fields on the "Add Menu Category" screen.
 */
function ichimaru_menu_category_add_fields() {
	?>
	<div class="form-field">
		<label for="ichimaru-jp-label"><?php esc_html_e( 'Japanese label', 'ichimaru-core' ); ?></label>
		<input type="text" name="ichimaru_jp_label" id="ichimaru-jp-label" value="" />
		<p><?php esc_html_e( 'Shown next to the English heading, e.g. うどん.', 'ichimaru-core' ); ?></p>
	</div>
	<div class="form-field">
		<label for="ichimaru-display-style"><?php esc_html_e( 'Display style', 'ichimaru-core' ); ?></label>
		<select name="ichimaru_display_style" id="ichimaru-display-style">
			<option value="card"><?php esc_html_e( 'Photo cards (grid)', 'ichimaru-core' ); ?></option>
			<option value="list"><?php esc_html_e( 'Text list (e.g. sides & toppings)', 'ichimaru-core' ); ?></option>
		</select>
	</div>
	<div class="form-field">
		<label for="ichimaru-sort-order"><?php esc_html_e( 'Order', 'ichimaru-core' ); ?></label>
		<input type="number" name="ichimaru_sort_order" id="ichimaru-sort-order" value="0" />
		<p><?php esc_html_e( 'Lower numbers appear first in the filter bar and page.', 'ichimaru-core' ); ?></p>
	</div>
	<div class="form-field">
		<label>
			<input type="checkbox" name="ichimaru_show_on_home" id="ichimaru-show-on-home" value="1" />
			<?php esc_html_e( 'Show on homepage "Our Menu" section', 'ichimaru-core' ); ?>
		</label>
	</div>
	<div class="form-field">
		<label for="ichimaru-category-image-button"><?php esc_html_e( 'Category image', 'ichimaru-core' ); ?></label>
		<input type="hidden" name="ichimaru_category_image_id" id="ichimaru-category-image-id" value="" />
		<div id="ichimaru-category-image-preview" style="margin-bottom:8px;"></div>
		<button type="button" class="button" id="ichimaru-category-image-button"><?php esc_html_e( 'Select image', 'ichimaru-core' ); ?></button>
		<button type="button" class="button" id="ichimaru-category-image-remove" style="display:none;"><?php esc_html_e( 'Remove', 'ichimaru-core' ); ?></button>
		<p><?php esc_html_e( 'Optional. Used as the photo on the homepage "Our Menu" card. If not set, the first dish photo in this category is used instead.', 'ichimaru-core' ); ?></p>
	</div>
	<?php
	ichimaru_menu_category_image_field_script();
}
add_action( 'menu_category_add_form_fields', 'ichimaru_menu_category_add_fields' );

/**
 * Extra fields on the "Edit Menu Category" screen.
 */
function ichimaru_menu_category_edit_fields( $term ) {
	$jp_label      = get_term_meta( $term->term_id, 'jp_label', true );
	$display_style = get_term_meta( $term->term_id, 'display_style', true ) ?: 'card';
	$sort_order    = get_term_meta( $term->term_id, 'sort_order', true );
	$show_on_home  = get_term_meta( $term->term_id, 'show_on_home', true );
	$image_id      = (int) get_term_meta( $term->term_id, 'category_image_id', true );
	$image_url     = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-jp-label"><?php esc_html_e( 'Japanese label', 'ichimaru-core' ); ?></label></th>
		<td>
			<input type="text" name="ichimaru_jp_label" id="ichimaru-jp-label" value="<?php echo esc_attr( $jp_label ); ?>" />
			<p class="description"><?php esc_html_e( 'Shown next to the English heading, e.g. うどん.', 'ichimaru-core' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-display-style"><?php esc_html_e( 'Display style', 'ichimaru-core' ); ?></label></th>
		<td>
			<select name="ichimaru_display_style" id="ichimaru-display-style">
				<option value="card" <?php selected( $display_style, 'card' ); ?>><?php esc_html_e( 'Photo cards (grid)', 'ichimaru-core' ); ?></option>
				<option value="list" <?php selected( $display_style, 'list' ); ?>><?php esc_html_e( 'Text list (e.g. sides & toppings)', 'ichimaru-core' ); ?></option>
			</select>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-sort-order"><?php esc_html_e( 'Order', 'ichimaru-core' ); ?></label></th>
		<td>
			<input type="number" name="ichimaru_sort_order" id="ichimaru-sort-order" value="<?php echo esc_attr( $sort_order ); ?>" />
			<p class="description"><?php esc_html_e( 'Lower numbers appear first in the filter bar and page.', 'ichimaru-core' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-show-on-home"><?php esc_html_e( 'Homepage', 'ichimaru-core' ); ?></label></th>
		<td>
			<label>
				<input type="checkbox" name="ichimaru_show_on_home" id="ichimaru-show-on-home" value="1" <?php checked( $show_on_home ); ?> />
				<?php esc_html_e( 'Show on homepage "Our Menu" section', 'ichimaru-core' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Uses the first dish in this category (in Order) that has a photo.', 'ichimaru-core' ); ?></p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-category-image-button"><?php esc_html_e( 'Category image', 'ichimaru-core' ); ?></label></th>
		<td>
			<input type="hidden" name="ichimaru_category_image_id" id="ichimaru-category-image-id" value="<?php echo esc_attr( $image_id ); ?>" />
			<div id="ichimaru-category-image-preview" style="margin-bottom:8px;">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:150px;height:auto;display:block;" />
				<?php endif; ?>
			</div>
			<button type="button" class="button" id="ichimaru-category-image-button"><?php esc_html_e( 'Select image', 'ichimaru-core' ); ?></button>
			<button type="button" class="button" id="ichimaru-category-image-remove" style="<?php echo $image_url ? '' : 'display:none;'; ?>"><?php esc_html_e( 'Remove', 'ichimaru-core' ); ?></button>
			<p class="description"><?php esc_html_e( 'Optional. Used as the photo on the homepage "Our Menu" card. If not set, the first dish photo in this category is used instead.', 'ichimaru-core' ); ?></p>
		</td>
	</tr>
	<?php
	ichimaru_menu_category_image_field_script();
}
add_action( 'menu_category_edit_form_fields', 'ichimaru_menu_category_edit_fields' );

/**
 * Save the menu_category term meta fields.
 */
function ichimaru_save_menu_category_meta( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( isset( $_POST['ichimaru_jp_label'] ) ) {
		update_term_meta( $term_id, 'jp_label', sanitize_text_field( wp_unslash( $_POST['ichimaru_jp_label'] ) ) );
	}
	if ( isset( $_POST['ichimaru_display_style'] ) ) {
		$style = 'list' === $_POST['ichimaru_display_style'] ? 'list' : 'card';
		update_term_meta( $term_id, 'display_style', $style );
	}
	if ( isset( $_POST['ichimaru_sort_order'] ) ) {
		update_term_meta( $term_id, 'sort_order', absint( $_POST['ichimaru_sort_order'] ) );
	}
	update_term_meta( $term_id, 'show_on_home', isset( $_POST['ichimaru_show_on_home'] ) ? 1 : 0 );
	if ( isset( $_POST['ichimaru_category_image_id'] ) ) {
		update_term_meta( $term_id, 'category_image_id', absint( $_POST['ichimaru_category_image_id'] ) );
	}
}
add_action( 'created_menu_category', 'ichimaru_save_menu_category_meta' );
add_action( 'edited_menu_category', 'ichimaru_save_menu_category_meta' );

/**
 * Order field on the "Add Dietary Tag" screen.
 */
function ichimaru_menu_diet_add_fields() {
	?>
	<div class="form-field">
		<label for="ichimaru-diet-sort-order"><?php esc_html_e( 'Order', 'ichimaru-core' ); ?></label>
		<input type="number" name="ichimaru_sort_order" id="ichimaru-diet-sort-order" value="0" />
		<p><?php esc_html_e( 'Lower numbers appear first among the filter chips and dish badges.', 'ichimaru-core' ); ?></p>
	</div>
	<?php
}
add_action( 'menu_diet_add_form_fields', 'ichimaru_menu_diet_add_fields' );

/**
 * Order field on the "Edit Dietary Tag" screen.
 */
function ichimaru_menu_diet_edit_fields( $term ) {
	$sort_order = get_term_meta( $term->term_id, 'sort_order', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="ichimaru-diet-sort-order"><?php esc_html_e( 'Order', 'ichimaru-core' ); ?></label></th>
		<td>
			<input type="number" name="ichimaru_sort_order" id="ichimaru-diet-sort-order" value="<?php echo esc_attr( $sort_order ); ?>" />
			<p class="description"><?php esc_html_e( 'Lower numbers appear first among the filter chips and dish badges.', 'ichimaru-core' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'menu_diet_edit_form_fields', 'ichimaru_menu_diet_edit_fields' );

/**
 * Save the menu_diet term meta field.
 */
function ichimaru_save_menu_diet_meta( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( isset( $_POST['ichimaru_sort_order'] ) ) {
		update_term_meta( $term_id, 'sort_order', absint( $_POST['ichimaru_sort_order'] ) );
	}
}
add_action( 'created_menu_diet', 'ichimaru_save_menu_diet_meta' );
add_action( 'edited_menu_diet', 'ichimaru_save_menu_diet_meta' );

/**
 * Meta box: dish details (price, "ask in store", dietary badge wording, extras grouping).
 */
function ichimaru_add_menu_item_meta_box() {
	add_meta_box(
		'ichimaru_menu_item_details',
		__( 'Dish Details', 'ichimaru-core' ),
		'ichimaru_render_menu_item_meta_box',
		'menu_item',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_menu_item', 'ichimaru_add_menu_item_meta_box' );

function ichimaru_render_menu_item_meta_box( $post ) {
	wp_nonce_field( 'ichimaru_save_menu_item', 'ichimaru_menu_item_nonce' );

	$price       = get_post_meta( $post->ID, '_ichimaru_price', true );
	$price_tba   = (bool) get_post_meta( $post->ID, '_ichimaru_price_tba', true );
	$vegan_opt   = (bool) get_post_meta( $post->ID, '_ichimaru_vegan_opt', true );
	$halal_opt   = (bool) get_post_meta( $post->ID, '_ichimaru_halal_opt', true );
	$group_label = get_post_meta( $post->ID, '_ichimaru_group_label', true );
	?>
	<p>
		<label for="ichimaru-price"><strong><?php esc_html_e( 'Price', 'ichimaru-core' ); ?></strong></label><br/>
		<input type="text" id="ichimaru-price" name="ichimaru_price" value="<?php echo esc_attr( $price ); ?>" placeholder="£5.90" style="width:100%;" />
	</p>
	<p>
		<label>
			<input type="checkbox" name="ichimaru_price_tba" value="1" <?php checked( $price_tba ); ?> />
			<?php esc_html_e( 'Show "Ask in store" instead of a price', 'ichimaru-core' ); ?>
		</label>
	</p>
	<hr/>
	<p>
		<label>
			<input type="checkbox" name="ichimaru_vegan_opt" value="1" <?php checked( $vegan_opt ); ?> />
			<?php esc_html_e( 'Vegan badge reads "Vegan opt." (available on request)', 'ichimaru-core' ); ?>
		</label>
	</p>
	<p>
		<label>
			<input type="checkbox" name="ichimaru_halal_opt" value="1" <?php checked( $halal_opt ); ?> />
			<?php esc_html_e( 'Halal badge reads "Halal opt." (available on request)', 'ichimaru-core' ); ?>
		</label>
	</p>
	<p class="description"><?php esc_html_e( 'Requires the matching Dietary Tag to be checked below.', 'ichimaru-core' ); ?></p>
	<hr/>
	<p>
		<label for="ichimaru-group-label"><strong><?php esc_html_e( 'List group (list-style categories only)', 'ichimaru-core' ); ?></strong></label><br/>
		<input type="text" id="ichimaru-group-label" name="ichimaru_group_label" value="<?php echo esc_attr( $group_label ); ?>" placeholder="e.g. Cold Sides & Grab-and-Go" style="width:100%;" />
		<span class="description"><?php esc_html_e( 'Items with the same group text are listed under one sub-heading.', 'ichimaru-core' ); ?></span>
	</p>
	<hr/>
	<p class="description"><?php esc_html_e( 'Dish photo: set it under "Dish Photo" below. Leave unset to show a "photo coming soon" tile.', 'ichimaru-core' ); ?></p>
	<p class="description"><?php esc_html_e( 'Description: type it in the main content area above. Order within a category: use "Order" in Page Attributes.', 'ichimaru-core' ); ?></p>
	<?php
}

function ichimaru_save_menu_item_meta( $post_id ) {
	if ( ! isset( $_POST['ichimaru_menu_item_nonce'] ) || ! wp_verify_nonce( $_POST['ichimaru_menu_item_nonce'], 'ichimaru_save_menu_item' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_ichimaru_price', isset( $_POST['ichimaru_price'] ) ? sanitize_text_field( wp_unslash( $_POST['ichimaru_price'] ) ) : '' );
	update_post_meta( $post_id, '_ichimaru_price_tba', isset( $_POST['ichimaru_price_tba'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_ichimaru_vegan_opt', isset( $_POST['ichimaru_vegan_opt'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_ichimaru_halal_opt', isset( $_POST['ichimaru_halal_opt'] ) ? 1 : 0 );
	update_post_meta( $post_id, '_ichimaru_group_label', isset( $_POST['ichimaru_group_label'] ) ? sanitize_text_field( wp_unslash( $_POST['ichimaru_group_label'] ) ) : '' );
}
add_action( 'save_post_menu_item', 'ichimaru_save_menu_item_meta' );

/**
 * Meta box: which Locations this dish is available at, feeding the Menu
 * page's Location filter (see ichimaru_menu_item_location_ids() below).
 */
function ichimaru_add_menu_item_locations_meta_box() {
	add_meta_box(
		'ichimaru_menu_item_locations',
		__( 'Available At', 'ichimaru-core' ),
		'ichimaru_render_menu_item_locations_meta_box',
		'menu_item',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_menu_item', 'ichimaru_add_menu_item_locations_meta_box' );

function ichimaru_render_menu_item_locations_meta_box( $post ) {
	wp_nonce_field( 'ichimaru_save_menu_item_locations', 'ichimaru_menu_item_locations_nonce' );

	$locations = ichimaru_get_locations();
	if ( ! $locations ) {
		echo '<p class="description">' . esc_html__( 'No locations yet — add one under Locations.', 'ichimaru-core' ) . '</p>';
		return;
	}

	// A dish with no saved selection yet (new, or created before this field
	// existed) defaults to every current location, so nothing silently
	// disappears from a location's menu the first time this box is seen.
	// Once saved, whatever the editor actually checked is what's stored.
	$has_saved = metadata_exists( 'post', $post->ID, '_ichimaru_location_ids' );
	$selected  = $has_saved ? ichimaru_menu_item_location_ids( $post->ID ) : wp_list_pluck( $locations, 'ID' );

	foreach ( $locations as $location ) {
		?>
		<label style="display:block;margin-bottom:6px;">
			<input type="checkbox" name="ichimaru_locations[]" value="<?php echo esc_attr( $location->ID ); ?>" <?php checked( in_array( $location->ID, $selected, true ) ); ?> />
			<?php echo esc_html( $location->post_title ); ?>
		</label>
		<?php
	}
}

function ichimaru_save_menu_item_locations_meta( $post_id ) {
	if ( ! isset( $_POST['ichimaru_menu_item_locations_nonce'] ) || ! wp_verify_nonce( $_POST['ichimaru_menu_item_locations_nonce'], 'ichimaru_save_menu_item_locations' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$ids = isset( $_POST['ichimaru_locations'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ichimaru_locations'] ) ) : array();
	update_post_meta( $post_id, '_ichimaru_location_ids', $ids );
}
add_action( 'save_post_menu_item', 'ichimaru_save_menu_item_locations_meta' );

/**
 * ---- Read-side helpers used by the theme to render the menu page. ----
 */

/**
 * Fetch a taxonomy's terms sorted by their "sort_order" term meta, ASC.
 * Terms with no order set yet (e.g. just added via the quick "+ Add"
 * sidebar box, which skips the custom fields) sort to the end instead of
 * being excluded — a plain get_terms( 'meta_key' => 'sort_order', ... )
 * would inner-join on that meta and silently drop them.
 *
 * @return WP_Term[]
 */
function ichimaru_get_ordered_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	usort(
		$terms,
		function ( $a, $b ) {
			$order_a = get_term_meta( $a->term_id, 'sort_order', true );
			$order_b = get_term_meta( $b->term_id, 'sort_order', true );
			$order_a = ( '' === $order_a ) ? PHP_INT_MAX : (int) $order_a;
			$order_b = ( '' === $order_b ) ? PHP_INT_MAX : (int) $order_b;
			return $order_a <=> $order_b ?: strcasecmp( $a->name, $b->name );
		}
	);
	return $terms;
}

/**
 * Menu categories in display order.
 *
 * @return WP_Term[]
 */
function ichimaru_get_menu_categories() {
	return ichimaru_get_ordered_terms( 'menu_category' );
}

/**
 * Menu categories flagged "Show on homepage" (edit a category under Menu
 * Items → Menu Categories to toggle it), in the same display order.
 *
 * @return WP_Term[]
 */
function ichimaru_get_homepage_menu_categories() {
	return array_values(
		array_filter(
			ichimaru_get_menu_categories(),
			function ( $term ) {
				return (bool) get_term_meta( $term->term_id, 'show_on_home', true );
			}
		)
	);
}

/**
 * The first menu_item in a category (in Order) that has a dish photo, for
 * use as that category's homepage teaser image.
 *
 * @return int Attachment ID, or 0 if no item in the category has a photo.
 */
function ichimaru_get_category_thumbnail_id( $term_id ) {
	foreach ( ichimaru_get_menu_items( $term_id ) as $post ) {
		$thumbnail_id = get_post_thumbnail_id( $post );
		if ( $thumbnail_id ) {
			return (int) $thumbnail_id;
		}
	}
	return 0;
}

/**
 * The image to use for a category's homepage teaser: the explicit
 * "Category image" set on the term if there is one, otherwise the first
 * dish photo (see ichimaru_get_category_thumbnail_id()).
 *
 * @return int Attachment ID, or 0 if neither is set.
 */
function ichimaru_get_category_image_id( $term_id ) {
	$image_id = (int) get_term_meta( $term_id, 'category_image_id', true );
	return $image_id ? $image_id : ichimaru_get_category_thumbnail_id( $term_id );
}

/**
 * Dietary tags in display order (filter chips and, per item, badge order).
 *
 * @return WP_Term[]
 */
function ichimaru_get_menu_diets() {
	return ichimaru_get_ordered_terms( 'menu_diet' );
}

/**
 * Menu items belonging to a category, in menu_order.
 *
 * @return WP_Post[]
 */
function ichimaru_get_menu_items( $term_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'menu_item',
			'posts_per_page' => -1,
			'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
			'tax_query'      => array(
				array(
					'taxonomy' => 'menu_category',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		)
	);
	return $query->posts;
}

/**
 * Dietary filter keys (taxonomy term slugs) assigned to a menu item —
 * feeds the card's data-tags attribute used by the filter script.
 *
 * @return string[]
 */
function ichimaru_menu_item_filters( $post_id ) {
	$terms = get_the_terms( $post_id, 'menu_diet' );
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return array();
	}
	return wp_list_pluck( $terms, 'slug' );
}

/**
 * Location post IDs a menu item is available at (set via the "Available At"
 * meta box), feeding the Menu page's Location filter data-locations
 * attribute. An empty result means no location data has been saved for
 * this item yet — the "All" filter still shows it, but it won't match a
 * specific location filter until "Available At" is set.
 *
 * @return int[]
 */
function ichimaru_menu_item_location_ids( $post_id ) {
	$ids = get_post_meta( $post_id, '_ichimaru_location_ids', true );
	return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
}

/**
 * Visible dietary badges for a menu item as [slug, label] pairs, in the
 * same order as the filter chips. Honours the "opt." wording on Vegan/Halal,
 * and skips a separate Vegetarian badge when Vegan is already shown. Any
 * other dietary tag an editor adds is rendered with its own term name.
 *
 * @return array<int, array{0:string,1:string}>
 */
function ichimaru_menu_item_badges( $post_id ) {
	$filters = ichimaru_menu_item_filters( $post_id );
	if ( empty( $filters ) ) {
		return array();
	}

	$vegan_opt = (bool) get_post_meta( $post_id, '_ichimaru_vegan_opt', true );
	$halal_opt = (bool) get_post_meta( $post_id, '_ichimaru_halal_opt', true );
	$badges    = array();

	foreach ( ichimaru_get_menu_diets() as $diet ) {
		if ( ! in_array( $diet->slug, $filters, true ) ) {
			continue;
		}
		if ( 'veg' === $diet->slug && in_array( 'vegan', $filters, true ) ) {
			continue; // Vegan already implies vegetarian; don't show both badges.
		}

		switch ( $diet->slug ) {
			case 'vegan':
				$label = $vegan_opt ? 'Vegan opt.' : 'Vegan';
				break;
			case 'veg':
				$label = 'Veggie';
				break;
			case 'halal':
				$label = $halal_opt ? 'Halal opt.' : 'Halal';
				break;
			default:
				$label = $diet->name;
		}

		$badges[] = array( $diet->slug, $label );
	}

	return $badges;
}

/**
 * Price markup data for a menu item.
 *
 * @return array{text:string,tba:bool}
 */
function ichimaru_menu_item_price( $post_id ) {
	if ( get_post_meta( $post_id, '_ichimaru_price_tba', true ) ) {
		return array( 'text' => 'Ask in store', 'tba' => true );
	}
	return array( 'text' => (string) get_post_meta( $post_id, '_ichimaru_price', true ), 'tba' => false );
}

/**
 * Plain-text dish description from the post content.
 */
function ichimaru_menu_item_desc( $post ) {
	return trim( wp_strip_all_tags( $post->post_content ) );
}
