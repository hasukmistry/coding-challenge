<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$markup          = '';
		$class_name      = ! empty( $attributes['className'] ) ? sanitize_html_class( esc_attr( $attributes['className'] ) ) : false;
		$tag             = 'foo';
		$category_name   = 'baz';
		$post_types      = get_post_types( [ 'public' => true ], 'objects' );
		$current_post_id = get_the_ID();

		$markup .= sprintf( '<div %s>', $class_name ? "class='{$class_name}'" : '' );

		$markup .= '<h2>Post Counts</h2>';

		$markup .= '<ul>';

		foreach ( $post_types as $post_type_object ) :
			$post_type_slug  = $post_type_object->name;
			$post_type_label = $post_type_object->labels->name;
			$post_count      = wp_count_posts( $post_type_slug );

			$markup .= sprintf( '<li> There are %s %s.</li>', $post_count->publish, $post_type_label );
		endforeach;

		$markup .= '</ul>';

		$markup .= sprintf( '<p>The current post ID is %s.</p>', $current_post_id );

		$markup .= $this->get_filtered_posts( $current_post_id, $tag, $category_name );

		$markup .= '<div>';

		return $markup;
	}

	/**
	 * Renders posts with matching tag and category. Also excludes given post_id.
	 * Also cache the results.
	 *
	 * @param int    $post_id       The given post id.
	 * @param string $tag           The tag associated with post, defaults to foo.
	 * @param string $category_name The category associated with post, defaults to baz.
	 * @return string The markup of the filtered post.
	 */
	private function get_filtered_posts( $post_id, $tag = 'foo', $category_name = 'baz' ) {
		// Check for the filtered_posts key in the 'site_counts' group.
		$filtered_posts_cached = wp_cache_get( 'filtered_posts', 'site_counts' );
		$markup                = '';

		// If nothing is found, build the object.
		if ( false === $filtered_posts_cached ) {
			$args  = [
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'any',
				'date_query'     => [
					[
						'hour'    => 9,
						'compare' => '>=',
					],
					[
						'hour'    => 17,
						'compare' => '<=',
					],
				],
				'tag'            => $tag,
				'category_name'  => $category_name,
				'posts_per_page' => 6, // 5 posts with the tag of foo and the category of baz. extra 1 for excluding current post.
			];
			$query = new \WP_Query( $args );

			if ( $query->found_posts && ! is_wp_error( $query ) ) :
				$filtered_posts = array_filter(
					$query->posts,
					function( $current_post ) use ( $post_id ) {
						return $post_id !== $current_post->ID;
					}
				);

				// Cache the whole WP_Query object in the cache and store it for 5 minutes (300 secs).
				wp_cache_set( 'filtered_posts', $filtered_posts, 'site_counts', 5 * 60 );

				// Retrieve cache value after setting.
				$filtered_posts_cached = wp_cache_get( 'filtered_posts', 'site_counts' );
			endif;
		}

		if ( ! $filtered_posts_cached ) {
			return $markup;
		}

		$markup .= sprintf( '<h2>%d posts with the tag of %s and the category of %s</h2>', $filtered_posts_cached, $tag, $category_name );

		$markup .= '<ul>';

		foreach ( $filtered_posts_cached as $post ) :
			$markup .= sprintf( '<li>%s</li>', $post->ID );
		endforeach;

		$markup .= '</ul>';

		return $markup;
	}
}
