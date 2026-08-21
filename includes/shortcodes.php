<?php
/**
 * Front-end shortcodes rendering the CPT-driven Locations and Menu page
 * bodies, so the theme's page content can be plain post_content + a
 * shortcode instead of a template pattern doing the query/loop.
 *
 * @package IchimaruCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * [ichimaru_locations_layout] — the Locations page's map + cards layout.
 * The map itself (#map-locations) is drawn by assets/js/locations-page-map.js
 * in the theme, which reads its coordinates from the ichimaruLocations JS
 * global (see ichimaru_get_locations_map_data() and its enqueue in the
 * theme's functions.php).
 */
function ichimaru_locations_layout_shortcode() {
	$locations = ichimaru_get_locations();

	ob_start();
	?>
	<div class="locations-page-layout">

		<div class="location-cards-col">
			<div style="margin-bottom:2rem;">
				<p style="font-size:0.82rem; color:var(--text-light);">Click a location to zoom on the map. Open in Google Maps for directions.</p>
			</div>

			<?php foreach ( $locations as $ichimaru_i => $ichimaru_post ) :
				$ichimaru_loc  = ichimaru_location_fields( $ichimaru_post );
				$ichimaru_slug = ichimaru_location_slug( $ichimaru_loc['name'] );
				?>
			<div class="loc-card<?php echo 0 === $ichimaru_i ? ' active' : ''; ?>" id="<?php echo esc_attr( $ichimaru_slug ); ?>" onclick="selectLoc(<?php echo esc_attr( $ichimaru_i ); ?>)">
				<div class="loc-card-head">
					<div class="loc-card-head-left">
						<div class="loc-pin"></div>
						<div>
							<h3><?php echo esc_html( $ichimaru_loc['name'] ); ?></h3>
							<div class="loc-area"><?php echo esc_html( $ichimaru_loc['area'] ); ?></div>
						</div>
					</div>
				</div>
				<div class="loc-card-body">
					<?php if ( $ichimaru_loc['address'] ) : ?>
					<div class="loc-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
						<?php echo esc_html( $ichimaru_loc['address'] ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $ichimaru_loc['hours'] ) : ?>
					<div class="loc-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
						<?php echo esc_html( $ichimaru_loc['hours'] ); ?>
					</div>
					<?php endif; ?>
					<?php if ( $ichimaru_loc['phone'] ) : ?>
					<div class="loc-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.57 3.63 2 2 0 0 1 3.54 1.45h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6 6l1.72-1.72a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
						<a href="tel:<?php echo esc_attr( $ichimaru_loc['tel'] ); ?>" style="color:var(--navy);"><?php echo esc_html( $ichimaru_loc['phone'] ); ?></a>
					</div>
					<?php endif; ?>
					<?php if ( $ichimaru_loc['email'] ) : ?>
					<div class="loc-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
						<a href="mailto:<?php echo esc_attr( $ichimaru_loc['email'] ); ?>" style="color:var(--navy);"><?php echo esc_html( $ichimaru_loc['email'] ); ?></a>
					</div>
					<?php endif; ?>
				</div>
				<div class="loc-card-actions">
					<?php if ( $ichimaru_loc['maps'] ) : ?>
					<a href="<?php echo esc_url( $ichimaru_loc['maps'] ); ?>" target="_blank" rel="noopener" class="btn btn-navy">Get Directions</a>
					<?php endif; ?>
					<a href="/menu/?location=<?php echo esc_attr( $ichimaru_post->ID ); ?>" class="btn btn-outline-navy">View Menu</a>
				</div>
			</div>
			<?php endforeach; ?>

		</div>

		<div class="map-full-wrap">
			<div id="map-locations"></div>
		</div>

	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_locations_layout', 'ichimaru_locations_layout_shortcode' );

/**
 * ---- [ichimaru_menu_catalog] — the Menu page's filter bar + full catalog. ----
 * The category/dietary filtering itself is client-side JS (assets/js/menu-filter.js
 * in the theme) driven by the data-cat/data-tags attributes rendered below.
 */

/**
 * Placeholder tile markup for dishes awaiting photography.
 */
function ichimaru_menu_placeholder_svg( $name ) {
	?>
	<div class="menu-card-img is-placeholder" role="img" aria-label="<?php echo esc_attr( $name . ' — photograph coming soon' ); ?>">
		<svg viewBox="0 0 100 100" fill="none" aria-hidden="true"><circle cx="50" cy="50" r="34" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="196 40" transform="rotate(-25 50 50)"/><rect x="30" y="46" width="40" height="8" rx="4" fill="currentColor"/></svg>
	</div>
	<?php
}

/**
 * Filter-chip icon for a dietary tag. The four tags from the original
 * design get their hand-picked glyph; any new tag an editor adds falls
 * back to a plain dot so the chip still renders sensibly.
 */
function ichimaru_menu_diet_icon( $slug ) {
	$icons = array(
		'vegan' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline;margin-right:3px;vertical-align:middle;" aria-hidden="true"><path d="M12 2a10 10 0 0 1 0 20 10 10 0 0 1 0-20m-2 5c-3 4-2 8 2 10 4-2 5-6 2-10"/></svg>',
		'veg'   => '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline;margin-right:3px;vertical-align:middle;" aria-hidden="true"><path d="M4 20c0-8 6-14 16-15C19 15 13 20 4 20z"/><path d="M4 20c3-4 7-6 11-7"/></svg>',
		'halal' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="display:inline;margin-right:3px;vertical-align:middle;" aria-hidden="true"><path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-5h2v2h-2zm0-8h2v6h-2z"/></svg>',
		'spicy' => '<svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor" style="display:inline;margin-right:3px;vertical-align:middle;" aria-hidden="true"><path d="M8.5 2.5c.7.5 1.5 1.5 1.5 3 0 2-2 3-2 5s2 3.5 6 3.5 6-1.5 6-3.5-2-3-2-5c0-1.5.8-2.5 1.5-3C17 4 14 3 12 3s-5 1-3.5-.5z"/></svg>',
	);
	return $icons[ $slug ] ?? '<svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor" style="display:inline;margin-right:4px;vertical-align:middle;" aria-hidden="true"><circle cx="12" cy="12" r="9"/></svg>';
}

/**
 * A single dish card inside a .menu-grid, rendered from a menu_item post.
 */
function ichimaru_menu_card( $cat_slug, $post ) {
	$filters   = esc_attr( implode( ' ', ichimaru_menu_item_filters( $post->ID ) ) );
	$locations = esc_attr( implode( ' ', ichimaru_menu_item_location_ids( $post->ID ) ) );
	$price     = ichimaru_menu_item_price( $post->ID );
	$desc      = ichimaru_menu_item_desc( $post );
	?>
	<div class="menu-card" data-cat="<?php echo esc_attr( $cat_slug ); ?>" data-tags="<?php echo $filters; ?>" data-locations="<?php echo $locations; ?>">
		<?php if ( has_post_thumbnail( $post ) ) : ?>
			<div class="menu-card-img"><?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'loading' => 'lazy', 'width' => 500, 'height' => 375 ) ); ?></div>
		<?php else : ?>
			<?php ichimaru_menu_placeholder_svg( $post->post_title ); ?>
		<?php endif; ?>
		<div class="menu-card-body">
			<div class="menu-card-name"><?php echo esc_html( $post->post_title ); ?></div>
			<?php if ( $desc ) : ?>
				<div class="menu-card-desc"><?php echo esc_html( $desc ); ?></div>
			<?php endif; ?>
			<div class="menu-card-footer">
				<?php if ( $price['tba'] ) : ?>
					<span class="menu-card-price price-tba">Ask in store</span>
				<?php else : ?>
					<span class="menu-card-price"><?php echo esc_html( $price['text'] ); ?></span>
				<?php endif; ?>
				<div class="menu-tags">
					<?php foreach ( ichimaru_menu_item_badges( $post->ID ) as $badge ) : ?>
						<span class="tag tag-<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * A text-only row inside a .menu-list (sides/toppings), rendered from a menu_item post.
 */
function ichimaru_menu_list_row( $cat_slug, $post ) {
	$filters   = esc_attr( implode( ' ', ichimaru_menu_item_filters( $post->ID ) ) );
	$locations = esc_attr( implode( ' ', ichimaru_menu_item_location_ids( $post->ID ) ) );
	$price     = ichimaru_menu_item_price( $post->ID );
	$desc      = ichimaru_menu_item_desc( $post );
	?>
	<li class="menu-list-row" data-cat="<?php echo esc_attr( $cat_slug ); ?>" data-tags="<?php echo $filters; ?>" data-locations="<?php echo $locations; ?>">
		<div class="menu-list-main">
			<span class="menu-list-name"><?php echo esc_html( $post->post_title ); ?></span>
			<div class="menu-tags">
				<?php foreach ( ichimaru_menu_item_badges( $post->ID ) as $badge ) : ?>
					<span class="tag tag-<?php echo esc_attr( $badge[0] ); ?>"><?php echo esc_html( $badge[1] ); ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php if ( $desc ) : ?>
			<span class="menu-list-desc"><?php echo esc_html( $desc ); ?></span>
		<?php endif; ?>
		<span class="menu-list-price"><?php echo esc_html( $price['tba'] ? 'Ask in store' : $price['text'] ); ?></span>
	</li>
	<?php
}

function ichimaru_menu_catalog_shortcode() {
	$ichimaru_categories = ichimaru_get_menu_categories();
	$ichimaru_diets      = ichimaru_get_menu_diets();
	$ichimaru_locations  = ichimaru_get_locations();

	ob_start();
	?>
	<div class="filter-section">
		<div class="filter-section-inner">
			<div class="filter-group">
				<span class="filter-label">Location</span>
				<select class="filter-select" id="locationFilter">
					<option value="all">All Locations</option>
					<?php foreach ( $ichimaru_locations as $ichimaru_loc_post ) : ?>
						<option value="<?php echo esc_attr( $ichimaru_loc_post->ID ); ?>"><?php echo esc_html( $ichimaru_loc_post->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="filter-group">
				<span class="filter-label">Category</span>
				<div class="cat-btn-row">
					<button class="cat-btn active" data-cat="all">All</button>
					<?php foreach ( $ichimaru_categories as $ichimaru_cat ) : ?>
						<button class="cat-btn" data-cat="<?php echo esc_attr( $ichimaru_cat->slug ); ?>"><?php echo esc_html( $ichimaru_cat->name ); ?></button>
					<?php endforeach; ?>
				</div>
				<select class="filter-select cat-select" id="categoryFilter">
					<option value="all">All Categories</option>
					<?php foreach ( $ichimaru_categories as $ichimaru_cat ) : ?>
						<option value="<?php echo esc_attr( $ichimaru_cat->slug ); ?>"><?php echo esc_html( $ichimaru_cat->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="filter-group">
				<span class="filter-label">Dietary</span>
				<div class="diet-chip-row">
					<?php foreach ( $ichimaru_diets as $ichimaru_diet ) : ?>
						<button class="filter-chip chip-<?php echo esc_attr( $ichimaru_diet->slug ); ?>" data-diet="<?php echo esc_attr( $ichimaru_diet->slug ); ?>" title="<?php echo esc_attr( $ichimaru_diet->name ); ?>">
							<?php echo ichimaru_menu_diet_icon( $ichimaru_diet->slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed, hardcoded SVG markup. ?>
							<?php echo esc_html( $ichimaru_diet->name ); ?>
						</button>
					<?php endforeach; ?>
				</div>
				<select class="filter-select diet-select" id="dietaryFilter">
					<option value="">All Dietary</option>
					<?php foreach ( $ichimaru_diets as $ichimaru_diet ) : ?>
						<option value="<?php echo esc_attr( $ichimaru_diet->slug ); ?>"><?php echo esc_html( $ichimaru_diet->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="filter-chip clear-filters-btn" id="clearFilters" style="opacity:0.6;">Clear Filters</button>
			</div>
		</div>
	</div>

	<div class="menu-body">
		<div id="noResults" class="no-results">
			<p style="font-size:1.2rem; color:var(--navy); margin-bottom:0.5rem;">No items match your filters.</p>
			<p>Try removing a filter to see more dishes.</p>
		</div>

		<?php foreach ( $ichimaru_categories as $ichimaru_cat ) :
			$ichimaru_items = ichimaru_get_menu_items( $ichimaru_cat->term_id );
			if ( ! $ichimaru_items ) {
				continue;
			}
			$ichimaru_jp    = get_term_meta( $ichimaru_cat->term_id, 'jp_label', true );
			$ichimaru_style = get_term_meta( $ichimaru_cat->term_id, 'display_style', true ) ?: 'card';
			?>
		<div class="menu-section" data-category="<?php echo esc_attr( $ichimaru_cat->slug ); ?>" id="sec-<?php echo esc_attr( $ichimaru_cat->slug ); ?>">
			<div class="menu-section-heading">
				<h3><?php echo esc_html( $ichimaru_cat->name ); ?></h3>
				<?php if ( $ichimaru_jp ) : ?>
					<span class="jp-label"><?php echo esc_html( $ichimaru_jp ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( 'list' === $ichimaru_style ) : ?>
				<?php
				$ichimaru_groups = array();
				foreach ( $ichimaru_items as $ichimaru_post ) {
					$ichimaru_group = get_post_meta( $ichimaru_post->ID, '_ichimaru_group_label', true );
					$ichimaru_groups[ $ichimaru_group ][] = $ichimaru_post;
				}
				foreach ( $ichimaru_groups as $ichimaru_group_title => $ichimaru_rows ) :
					?>
					<?php if ( $ichimaru_group_title ) : ?>
						<h4 class="menu-list-title"><?php echo esc_html( $ichimaru_group_title ); ?></h4>
					<?php endif; ?>
					<ul class="menu-list">
						<?php foreach ( $ichimaru_rows as $ichimaru_post ) : ?>
							<?php ichimaru_menu_list_row( $ichimaru_cat->slug, $ichimaru_post ); ?>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			<?php else : ?>
				<div class="menu-grid">
					<?php foreach ( $ichimaru_items as $ichimaru_post ) : ?>
						<?php ichimaru_menu_card( $ichimaru_cat->slug, $ichimaru_post ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	</div>

	<div class="menu-footnotes">
		<p><strong>Vegan opt.</strong> — a vegan version of this dish is available on request; our standard broth is dashi-based. <strong>Halal opt.</strong> — a halal version is available; please ask the team when ordering. Takoyaki contains octopus, which some sects consider not halal.</p>
		<p>Prices shown are guide prices and may vary by location. Items marked <strong>Ask in store</strong> are newly launched — pricing is confirmed in restaurant. Our Ealing Broadway branch serves a dedicated halal menu with some different dishes.</p>
		<p>Allergen information for every dish is available on our <a href="/faqs/#allergens" style="color:var(--navy); font-weight:600;">FAQs &amp; Allergens</a> page. Recipes change seasonally — if you have a severe allergy or intolerance, please always check with staff on the day of your visit.</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_menu_catalog', 'ichimaru_menu_catalog_shortcode' );

/**
 * [ichimaru_home_locations_preview] — the homepage's compact locations
 * sidebar + mini map. The map (#map) is drawn by assets/js/locations-map.js
 * in the theme, reading the ichimaruLocations JS global.
 */
function ichimaru_home_locations_preview_shortcode() {
	$locations = ichimaru_get_locations();

	ob_start();
	?>
	<div class="locations-layout animate-in">
		<div class="locations-sidebar" id="locationsSidebar">
			<?php foreach ( $locations as $ichimaru_index => $ichimaru_post ) :
				$ichimaru_loc        = ichimaru_location_fields( $ichimaru_post );
				$ichimaru_short_name = ichimaru_location_short_name( $ichimaru_loc['name'] );
				?>
			<div class="location-entry<?php echo 0 === $ichimaru_index ? ' active' : ''; ?>" onclick="flyTo(<?php echo (int) $ichimaru_index; ?>)" tabindex="0">
				<div class="location-entry-dot"></div>
				<div>
					<div class="location-entry-name"><?php echo esc_html( $ichimaru_short_name ); ?></div>
					<div class="location-entry-area"><?php echo esc_html( $ichimaru_loc['area'] ); ?></div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<div id="map"></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_home_locations_preview', 'ichimaru_home_locations_preview_shortcode' );

/**
 * [ichimaru_footer_locations] — the footer's "Locations" link list. Links
 * to /locations/#{slug} (e.g. #st-pancras) rather than a plain /locations/,
 * so clicking a location in the footer lands on and centers that location's
 * card there (see window.selectLoc() in assets/js/locations-page-map.js,
 * which reads that hash on page load). The slug comes from
 * ichimaru_location_slug(), the same helper the Locations page's own
 * [ichimaru_locations_layout] shortcode uses for each card's id, so the two
 * always match.
 */
function ichimaru_footer_locations_shortcode() {
	$locations = ichimaru_get_locations();

	ob_start();
	?>
	<ul class="wp-block-list">
		<?php foreach ( $locations as $ichimaru_post ) :
			$ichimaru_loc        = ichimaru_location_fields( $ichimaru_post );
			$ichimaru_short_name = ichimaru_location_short_name( $ichimaru_loc['name'] );
			$ichimaru_slug       = ichimaru_location_slug( $ichimaru_loc['name'] );
			?>
		<li><a href="/locations/#<?php echo esc_attr( $ichimaru_slug ); ?>"><?php echo esc_html( $ichimaru_short_name ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_footer_locations', 'ichimaru_footer_locations_shortcode' );

/**
 * [ichimaru_home_menu_preview] — the homepage's "Our Menu" card grid,
 * showing whichever Menu Categories are flagged "Show on homepage" (edit a
 * category under wp-admin: Menu Items → Menu Categories), each using the
 * photo of the first dish in that category that has one.
 */
function ichimaru_home_menu_preview_shortcode() {
	$home_categories = ichimaru_get_homepage_menu_categories();
	if ( ! $home_categories ) {
		return '';
	}

	ob_start();
	?>
	<div class="menu-preview-grid">
		<?php foreach ( $home_categories as $ichimaru_i => $ichimaru_cat ) :
			$ichimaru_thumb_id = ichimaru_get_category_image_id( $ichimaru_cat->term_id );
			$ichimaru_thumb    = $ichimaru_thumb_id ? wp_get_attachment_image_url( $ichimaru_thumb_id, 'medium_large' ) : '';
			$ichimaru_desc     = trim( wp_strip_all_tags( $ichimaru_cat->description ) );
			$ichimaru_delay    = $ichimaru_i > 0 ? 'transition-delay:' . ( $ichimaru_i * 0.15 ) . 's;' : '';
			?>
		<a href="/menu/#sec-<?php echo esc_attr( $ichimaru_cat->slug ); ?>" class="menu-preview-card animate-in" style="<?php echo esc_attr( $ichimaru_delay ); ?>">
			<div class="menu-preview-card-img">
				<?php if ( $ichimaru_thumb ) : ?>
					<img src="<?php echo esc_url( $ichimaru_thumb ); ?>" alt="<?php echo esc_attr( $ichimaru_cat->name ); ?>" loading="lazy"/>
				<?php endif; ?>
			</div>
			<div class="menu-preview-card-body">
				<h3 class="menu-preview-card-name"><?php echo esc_html( $ichimaru_cat->name ); ?></h3>
				<?php if ( $ichimaru_desc ) : ?>
					<p class="menu-preview-card-line"><?php echo esc_html( $ichimaru_desc ); ?></p>
				<?php endif; ?>
			</div>
		</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_home_menu_preview', 'ichimaru_home_menu_preview_shortcode' );

/**
 * [ichimaru_job_cards] — the Careers page's "Open Positions" card grid,
 * rendered from Job posts (wp-admin: Jobs). Markup/classes match the
 * core/group + core/button block output this replaced, so no CSS changes
 * were needed.
 */
function ichimaru_job_cards_shortcode() {
	$jobs = ichimaru_get_jobs();
	if ( ! $jobs ) {
		return '';
	}

	ob_start();
	?>
	<div class="wp-block-group job-cards">
		<?php foreach ( $jobs as $ichimaru_post ) :
			$ichimaru_job = ichimaru_job_fields( $ichimaru_post );
			?>
		<div class="wp-block-group job-card animate-in has-text-align-left">
			<p class="job-card-title wp-block-paragraph"><?php echo esc_html( $ichimaru_job['title'] ); ?></p>
			<div class="job-card-meta">
				<?php if ( $ichimaru_job['location'] ) : ?><span class="job-badge badge-location"><?php echo esc_html( $ichimaru_job['location'] ); ?></span><?php endif; ?>
				<?php if ( $ichimaru_job['type'] ) : ?><span class="job-badge badge-type"><?php echo esc_html( $ichimaru_job['type'] ); ?></span><?php endif; ?>
				<?php if ( $ichimaru_job['dept'] ) : ?><span class="job-badge badge-dept"><?php echo esc_html( $ichimaru_job['dept'] ); ?></span><?php endif; ?>
			</div>
			<?php if ( $ichimaru_job['desc'] ) : ?>
				<p class="job-card-desc wp-block-paragraph"><?php echo esc_html( $ichimaru_job['desc'] ); ?></p>
			<?php endif; ?>
			<div class="wp-block-buttons job-card-actions">
				<div class="wp-block-button"><a class="wp-block-button__link has-white-color has-navy-background-color has-text-color has-background has-border-color has-custom-font-size wp-element-button" href="#apply" style="border-color:#0d2044;border-width:2px;border-radius:4px;padding-top:0.5rem;padding-right:1.1rem;padding-bottom:0.5rem;padding-left:1.1rem;font-size:0.72rem;font-weight:600;letter-spacing:0.12em;text-transform:uppercase">Apply Now</a></div>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ichimaru_job_cards', 'ichimaru_job_cards_shortcode' );

/**
 * [allergen_information] — a button that opens a modal asking the visitor
 * to pick their Location, then opens that location's Allergen PDF (set per
 * location under wp-admin: Locations → Allergen PDF) in a new tab.
 *
 * Attributes:
 *   label — button text. Default "Allergen Information".
 *
 * Only Locations with a PDF actually set are offered in the picker; if no
 * Location has one yet, the shortcode renders nothing rather than a picker
 * with only "Select a location" and no usable options. JS behaviour lives
 * in the theme's assets/js/allergen-modal.js, enqueued in functions.php
 * only on pages where this shortcode is actually used.
 */
function ichimaru_allergen_information_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'label' => __( 'Allergen Information', 'ichimaru-core' ),
		),
		$atts,
		'allergen_information'
	);

	$locations_with_pdf = array_values(
		array_filter(
			array_map( 'ichimaru_location_fields', ichimaru_get_locations() ),
			function ( $loc ) {
				return ! empty( $loc['allergen_pdf'] );
			}
		)
	);
	if ( ! $locations_with_pdf ) {
		return '';
	}

	$modal_id = 'allergen-modal-' . wp_unique_id();

	ob_start();
	?>
	<div class="allergen-information">
		<button type="button" class="btn btn-navy allergen-modal-trigger" data-modal="<?php echo esc_attr( $modal_id ); ?>">
			<?php echo esc_html( $atts['label'] ); ?>
		</button>

		<div class="allergen-modal-overlay" id="<?php echo esc_attr( $modal_id ); ?>" hidden>
			<div class="allergen-modal" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $modal_id ); ?>-title">
				<button type="button" class="allergen-modal-close" aria-label="<?php esc_attr_e( 'Close', 'ichimaru-core' ); ?>">&times;</button>
				<p class="section-label" style="justify-content:center;"><?php esc_html_e( 'Allergen Information', 'ichimaru-core' ); ?></p>
				<h3 id="<?php echo esc_attr( $modal_id ); ?>-title"><?php esc_html_e( 'Select Your Location', 'ichimaru-core' ); ?></h3>
				<p class="allergen-modal-intro"><?php esc_html_e( 'Choose a location below to open its allergen PDF in a new tab.', 'ichimaru-core' ); ?></p>
				<select class="allergen-modal-select">
					<option value=""><?php esc_html_e( 'Select a location…', 'ichimaru-core' ); ?></option>
					<?php foreach ( $locations_with_pdf as $ichimaru_loc ) : ?>
						<option value="<?php echo esc_url( $ichimaru_loc['allergen_pdf'] ); ?>"><?php echo esc_html( $ichimaru_loc['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'allergen_information', 'ichimaru_allergen_information_shortcode' );
