<?php
/**
 * Creates CMB2 objects/fields endpoint for WordPres REST API.
 * Allows access to fields registered to a specific post type and more.
 *
 * @todo  Add better documentation.
 * @todo  Research proper schema.
 *
 * @since 2.2.0
 *
 * @category  WordPress_Plugin
 * @package   CMB2
 * @author    WebDevStudios
 * @license   GPL-2.0+
 * @link      http://webdevstudios.com
 */
class CMB2_REST_Controller_Boxes extends CMB2_REST_Controller {

	/**
	 * CMB2 Instance
	 *
	 * @var CMB2
	 */
	protected $cmb;

	/**
	 * Register the routes for the objects of the controller.
	 *
	 * @since 2.2.0
	 */
	public function register_routes() {
		// Returns all boxes data.
		register_rest_route( CMB2_REST::BASE, '/boxes/', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );

		// Returns specific box's data.
		register_rest_route( CMB2_REST::BASE, '/boxes/(?P<cmb_id>[\w-]+)', array(
			array(
				'methods'         => WP_REST_Server::READABLE,
				'callback'        => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Get all public fields
	 *
	 * @since 2.2.0
	 *
	 * @param WP_REST_Request $request The API request object.
	 * @return array
	 */
	public function get_items( $request ) {
		$this->initiate_request( $request, 'boxes_read' );

		$boxes = CMB2_Boxes::get_by_property( 'show_in_rest', false );

		if ( empty( $boxes ) ) {
			return $this->prepare_item( array( 'error' => __( 'No boxes found.', 'cmb2' ) ), $this->request );
		}

		$boxes_data = array();
		// Loop boxes and get specific field.
		foreach ( $boxes as $key => $cmb ) {
			$boxes_data[ $cmb->cmb_id ] = $this->get_rest_box( $cmb );
		}

		return $this->prepare_item( $boxes_data );
	}

	/**
	 * Get all public fields
	 *
	 * @since 2.2.0
	 *
	 * @param WP_REST_Request $request The API request object.
	 * @return array
	 */
	public function get_item( $request ) {
		$this->initiate_request( $request );

		$cmb_id = $this->request->get_param( 'cmb_id' );

		if ( $cmb_id && ( $cmb = cmb2_get_metabox( $cmb_id, $this->object_id, $this->object_type ) ) ) {
			return $this->prepare_item( $this->get_rest_box( $cmb ) );
		}

		return $this->prepare_item( array( 'error' => __( 'No box found by that id.', 'cmb2' ) ) );
	}

	/**
	 * Get a CMB2 box prepared for REST
	 *
	 * @since 2.2.0
	 *
	 * @param CMB2 $cmb
	 * @return array
	 */
	public function get_rest_box( $cmb ) {
		$cmb->object_type( $this->object_id );
		$cmb->object_id( $this->object_type );

		$boxes_data = $cmb->meta_box;

		if ( isset( $this->request['_rendered'] ) ) {
			$boxes_data['form_open'] = $this->get_cb_results( array( $cmb, 'render_form_open' ) );
			$boxes_data['form_close'] = $this->get_cb_results( array( $cmb, 'render_form_close' ) );

			global $wp_scripts, $wp_styles;
			$before_css = $wp_styles->queue;
			$before_js = $wp_scripts->queue;

			CMB2_JS::enqueue();

			$boxes_data['js_dependencies'] = array_values( array_diff( $wp_scripts->queue, $before_js ) );
			$boxes_data['css_dependencies'] = array_values( array_diff( $wp_styles->queue, $before_css ) );
		}

		// TODO: look into 'embed' parameter.
		// http://demo.wp-api.org/wp-json/wp/v2/posts?_embed
		unset( $boxes_data['fields'] );
		// Handle callable properties.
		unset( $boxes_data['show_on_cb'] );

		$response = rest_ensure_response( $boxes_data );

		$response->add_links( $this->prepare_links( $cmb ) );

		return $response;
	}

	public function prepare_links( $cmb ) {
		$base = CMB2_REST::BASE . '/boxes';
		$boxbase = $base . '/' . $cmb->cmb_id;

		return array(
			'self' => array(
				'href' => rest_url( $boxbase ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'fields' => array(
				'href' => rest_url( trailingslashit( $boxbase ) . 'fields' ),
				'embeddable' => true,
			),
		);
	}

}
