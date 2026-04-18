<?php
/**
 * Plugin Name:       Post Filter for Block Editor
 * Plugin URI:        https://nkpathan.github.io/
 * Description:       A Gutenberg block that displays a filterable list of posts by Difficulty Level custom field, with live REST API filtering on the frontend.
 * Version:           1.0.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Nawazkhan Pathan
 * Author URI:        https://nkpathan.github.io/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       post-filter-block-editor
 *
 * @package PostFilterBlockEditor
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Plugin constants
// ---------------------------------------------------------------------------

define( 'PFBE_VERSION',  '1.0.0' );
define( 'PFBE_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PFBE_URL',      plugin_dir_url( __FILE__ ) );
define( 'PFBE_BASENAME', plugin_basename( __FILE__ ) );

// ---------------------------------------------------------------------------
// 1. Register custom post meta field
// ---------------------------------------------------------------------------

add_action( 'init', 'pfbe_register_meta' );
/**
 * Register the difficulty_level post meta field.
 * Exposed to REST API so the block editor can read/write it.
 *
 * @return void
 */
function pfbe_register_meta() {
	register_post_meta(
		'post',
		'difficulty_level',
		array(
			'show_in_rest'      => true,
			'single'            => true,
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => 'pfbe_meta_auth_callback',
			'description'       => 'Difficulty level of the post: easy, medium, or hard.',
		)
	);
}

/**
 * Authorization callback for difficulty_level meta.
 *
 * @return bool
 */
function pfbe_meta_auth_callback() {
	return current_user_can( 'edit_posts' );
}

add_action( 'add_meta_boxes', 'pfbe_add_meta_box' );
/**
 * Add the difficulty level meta box to the post edit screen.
 *
 * @return void
 */
function pfbe_add_meta_box() {
	add_meta_box(
		'pfbe-difficulty-level',
		__( 'Difficulty Level', 'post-filter-block-editor' ),
		'pfbe_render_difficulty_meta_box',
		'post',
		'side',
		'default'
	);
}

/**
 * Render the difficulty level meta box HTML.
 *
 * @param WP_Post $post Current post object.
 * @return void
 */
function pfbe_render_difficulty_meta_box( $post ) {
	wp_nonce_field( 'pfbe_save_difficulty_meta', 'pfbe_difficulty_meta_nonce' );

	$current_value = get_post_meta( $post->ID, 'difficulty_level', true );
	$options       = pfbe_get_difficulty_options();

	echo '<label for="pfbe-difficulty-level-field" class="screen-reader-text">' . esc_html__( 'Select difficulty level for this post', 'post-filter-block-editor' ) . '</label>';
	echo '<select id="pfbe-difficulty-level-field" name="pfbe_difficulty_level" class="widefat">';

	foreach ( $options as $value => $label ) {
		echo sprintf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $current_value, $value, false ),
			esc_html( $label )
		);
	}

	echo '</select>';
}

add_action( 'save_post', 'pfbe_save_difficulty_meta' );
/**
 * Save the difficulty level post meta when the post is saved.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function pfbe_save_difficulty_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	$nonce = isset( $_POST['pfbe_difficulty_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['pfbe_difficulty_meta_nonce'] ) ) : '';

	if ( ! $nonce || ! wp_verify_nonce( $nonce, 'pfbe_save_difficulty_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['pfbe_difficulty_level'] ) ) {
		$difficulty = sanitize_text_field( wp_unslash( $_POST['pfbe_difficulty_level'] ) );

		if ( pfbe_validate_difficulty( $difficulty ) ) {
			update_post_meta( $post_id, 'difficulty_level', $difficulty );
		} else {
			delete_post_meta( $post_id, 'difficulty_level' );
		}
	}
}

// ---------------------------------------------------------------------------
// 2. Register the Gutenberg block
// ---------------------------------------------------------------------------

add_action( 'init', 'pfbe_register_block' );
/**
 * Register the block type using block.json for metadata.
 * Scripts and styles are enqueued via block metadata handles.
 *
 * @return void
 */
function pfbe_register_block() {
	wp_register_script(
		'pfbe-editor',
		PFBE_URL . 'editor.js',
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
			'wp-i18n',
		),
		PFBE_VERSION,
		true
	);

	wp_register_style(
		'pfbe-style',
		PFBE_URL . 'style.css',
		array(),
		PFBE_VERSION
	);

	register_block_type(
		PFBE_PATH . 'block.json',
		array(
			'editor_script' => 'pfbe-editor',
			'editor_style'  => 'pfbe-style',
			'style'         => 'pfbe-style',
			'render_callback' => 'pfbe_render_block',
		)
	);
}

// ---------------------------------------------------------------------------
// 3. Server-side render callback
// ---------------------------------------------------------------------------

/**
 * Render callback for the Posts Filter block.
 *
 * @param  array  $attributes Block attributes.
 * @return string             Rendered HTML.
 */
function pfbe_render_block( $attributes ) {
	$selected_difficulty = isset( $attributes['selectedDifficulty'] )
		? sanitize_text_field( $attributes['selectedDifficulty'] )
		: '';

	// Generate unique IDs so multiple block instances on the same page
	// never share the same DOM IDs.
	$uid      = wp_unique_id( 'pfbe-' );
	$select_id = $uid . '-filter';
	$list_id   = $uid . '-list';

	$posts = pfbe_query_posts( $selected_difficulty );

	$difficulty_options = pfbe_get_difficulty_options();

	// Build select options markup.
	$options_html = '';
	foreach ( $difficulty_options as $value => $label ) {
		$options_html .= sprintf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $value ),
			selected( $selected_difficulty, $value, false ),
			esc_html( $label )
		);
	}

	$wrapper_attributes = get_block_wrapper_attributes(
		array( 'class' => 'pfbe-block' )
	);
	
	$posts_html = pfbe_build_posts_html( $posts );
	$html  = sprintf( '<div %s>', $wrapper_attributes );
	$html .= sprintf( '<div id="%s" class="pfbe-posts-list">%s</div>', esc_attr( $list_id ), $posts_html );
	$html .= '</div>';

	return $html;
}

// ---------------------------------------------------------------------------
// 4. Query helper
// ---------------------------------------------------------------------------

/**
 * Query published posts, optionally filtered by difficulty_level meta.
 *
 * @param  string $difficulty  Meta value to filter by. Empty = all posts.
 * @return WP_Post[]           Array of post objects.
 */
function pfbe_query_posts( $difficulty = '' ) {
	$args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);

	if ( ! empty( $difficulty ) ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'difficulty_level',
				'value'   => $difficulty,
				'compare' => '=',
			),
		);
	}

	$query = new WP_Query( $args );

	return $query->posts;
}

// ---------------------------------------------------------------------------
// 5. Post list HTML builder (shared by SSR + REST response)
// ---------------------------------------------------------------------------

/**
 * Build the inner HTML for a list of post cards.
 *
 * @param  WP_Post[] $posts Array of post objects.
 * @return string           HTML string.
 */
function pfbe_build_posts_html( $posts ) {
	if ( empty( $posts ) ) {
		return '<p class="pfbe-no-posts">' .
			esc_html__( 'No posts found for this difficulty level.', 'post-filter-block-editor' ) .
			'</p>';
	}

	$labels = pfbe_get_difficulty_labels();
	$html   = '';

	foreach ( $posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}

		$difficulty = get_post_meta( $post->ID, 'difficulty_level', true );
		$difficulty = sanitize_text_field( $difficulty );

		$label   = isset( $labels[ $difficulty ] ) ? $labels[ $difficulty ] : '';
		$excerpt = get_the_excerpt( $post );

		$html .= '<article class="pfbe-post-card">';

		$html .= '<header class="pfbe-post-header">';
		$html .= sprintf(
			'<h3 class="pfbe-post-title"><a href="%1$s">%2$s</a></h3>',
			esc_url( get_permalink( $post ) ),
			esc_html( get_the_title( $post ) )
		);

		if ( $label ) {
			$html .= sprintf(
				'<span class="pfbe-badge pfbe-badge--%1$s">%2$s</span>',
				esc_attr( $difficulty ),
				esc_html( $label )
			);
		}

		$html .= '</header>';

		if ( $excerpt ) {
			$html .= sprintf(
				'<p class="pfbe-post-excerpt">%s</p>',
				esc_html( $excerpt )
			);
		}

		$html .= sprintf(
			'<footer class="pfbe-post-footer"><a class="pfbe-read-more" href="%1$s">%2$s</a></footer>',
			esc_url( get_permalink( $post ) ),
			esc_html__( 'Read more', 'post-filter-block-editor' )
		);

		$html .= '</article>';
	}

	return $html;
}

// ---------------------------------------------------------------------------
// 6. Shared data helpers
// ---------------------------------------------------------------------------

/**
 * Get the dropdown options array.
 *
 * @return array<string,string>
 */
function pfbe_get_difficulty_options() {
	return array(
		''       => __( 'All Levels', 'post-filter-block-editor' ),
		'easy'   => __( 'Easy',       'post-filter-block-editor' ),
		'medium' => __( 'Medium',     'post-filter-block-editor' ),
		'hard'   => __( 'Hard',       'post-filter-block-editor' ),
	);
}

/**
 * Get the badge label map (excludes the "All" option).
 *
 * @return array<string,string>
 */
function pfbe_get_difficulty_labels() {
	return array(
		'easy'   => __( 'Easy',   'post-filter-block-editor' ),
		'medium' => __( 'Medium', 'post-filter-block-editor' ),
		'hard'   => __( 'Hard',   'post-filter-block-editor' ),
	);
}

// ---------------------------------------------------------------------------
// 7. REST API endpoint
// ---------------------------------------------------------------------------

add_action( 'rest_api_init', 'pfbe_register_rest_route' );
/**
 * Register REST API route for AJAX filtering.
 *
 * @return void
 */
function pfbe_register_rest_route() {
	register_rest_route(
		'pfbe/v1',
		'/posts',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'pfbe_rest_callback',
			'permission_callback' => '__return_true',
			'args'                => array(
				'difficulty' => array(
					'type'              => 'string',
					'default'           => '',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => 'pfbe_validate_difficulty',
				),
			),
		)
	);
}

/**
 * Validate the difficulty parameter value.
 *
 * @param  mixed $value The supplied value.
 * @return bool
 */
function pfbe_validate_difficulty( $value ) {
	$allowed = array( '', 'easy', 'medium', 'hard' );
	return in_array( $value, $allowed, true );
}

/**
 * REST API callback — returns post list HTML as JSON.
 *
 * @param  WP_REST_Request $request Full REST request.
 * @return WP_REST_Response
 */
function pfbe_rest_callback( WP_REST_Request $request ) {
	$difficulty = $request->get_param( 'difficulty' );
	$posts      = pfbe_query_posts( $difficulty );
	$html       = pfbe_build_posts_html( $posts );

	return rest_ensure_response( array( 'html' => $html ) );
}

// ---------------------------------------------------------------------------
// 8. Enqueue frontend assets
// ---------------------------------------------------------------------------
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', 'pfbe_enqueue_frontend_assets' );
/**
 * Enqueue frontend script and style only on pages that contain the block.
 *
 * @return void
 */
function pfbe_enqueue_frontend_assets() {
	if ( ! is_singular() ) {
		return;
	}

	global $post;

	if ( ! ( $post instanceof WP_Post ) ) {
		return;
	}

	if ( ! has_block( 'post-filter-block-editor/posts-filter', $post ) ) {
		return;
	}

	wp_register_script(
		'pfbe-frontend',
		PFBE_URL . 'frontend.js',
		array(),
		PFBE_VERSION,
		true
	);

	wp_localize_script(
		'pfbe-frontend',
		'pfbeData',
		array(
			'restUrl' => esc_url_raw( rest_url( 'pfbe/v1/posts' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'i18n'    => array(
				'loadError' => esc_html__( 'Could not load posts. Please try again.', 'post-filter-block-editor' ),
			),
		)
	);

	wp_enqueue_script( 'pfbe-frontend' );
}
