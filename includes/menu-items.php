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
			'show_in_rest'       => true,
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
			'show_in_rest'      => true,
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
			'show_in_rest'      => true,
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
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_term_meta(
		'menu_category',
		'display_style',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
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
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
		)
	);
}
add_action( 'init', 'ichimaru_register_menu_category_meta' );

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
	<?php
}
add_action( 'menu_category_add_form_fields', 'ichimaru_menu_category_add_fields' );

/**
 * Extra fields on the "Edit Menu Category" screen.
 */
function ichimaru_menu_category_edit_fields( $term ) {
	$jp_label      = get_term_meta( $term->term_id, 'jp_label', true );
	$display_style = get_term_meta( $term->term_id, 'display_style', true ) ?: 'card';
	$sort_order    = get_term_meta( $term->term_id, 'sort_order', true );
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
	<?php
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
}
add_action( 'created_menu_category', 'ichimaru_save_menu_category_meta' );
add_action( 'edited_menu_category', 'ichimaru_save_menu_category_meta' );

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
 * ---- Read-side helpers used by the theme to render the menu page. ----
 */

/**
 * Menu categories in display order.
 *
 * @return WP_Term[]
 */
function ichimaru_get_menu_categories() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'menu_category',
			'hide_empty' => true,
			'meta_key'   => 'sort_order',
			'orderby'    => 'meta_value_num',
			'order'      => 'ASC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
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
 * Visible dietary badges for a menu item as [slug, label] pairs, honouring
 * the "opt." wording and the rule that a Vegan badge supersedes Veggie.
 *
 * @return array<int, array{0:string,1:string}>
 */
function ichimaru_menu_item_badges( $post_id ) {
	$filters = ichimaru_menu_item_filters( $post_id );
	$badges  = array();

	if ( in_array( 'vegan', $filters, true ) ) {
		$badges[] = array( 'vegan', get_post_meta( $post_id, '_ichimaru_vegan_opt', true ) ? 'Vegan opt.' : 'Vegan' );
	} elseif ( in_array( 'veg', $filters, true ) ) {
		$badges[] = array( 'veg', 'Veggie' );
	}

	if ( in_array( 'halal', $filters, true ) ) {
		$badges[] = array( 'halal', get_post_meta( $post_id, '_ichimaru_halal_opt', true ) ? 'Halal opt.' : 'Halal' );
	}

	if ( in_array( 'spicy', $filters, true ) ) {
		$badges[] = array( 'spicy', 'Spicy' );
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
