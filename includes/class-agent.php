<?php
/**
 * Awesome Support Agent.
 *
 * @package   Awesome Support/Agent
 * @author    Julien Liabeuf <julien@liabeuf.fr>
 * @license   GPL-2.0+
 * @link      http://themeavenue.net
 * @copyright 2014 ThemeAvenue
 */

class WPAS_Agent {

	/**
	 * ID of the agent
	 *
	 * @var integer
	 */
	public $agent_id;

	/**
	 * User object
	 *
	 * @var
	 */
	protected $user;

	public function __construct( $agent_id ) {

		$this->agent_id = (int) $agent_id;
		$this->user     = new WP_User( $this->agent_id );

	}

	/**
	 * Check if a user exists
	 *
	 * This function is just a wrapper for WP_User::exists()
	 *
	 * @since 3.2
	 * @return bool
	 */
	public function exists() {
		return $this->user->exists;
	}

	/**
	 * Check if the user had agent capability
	 *
	 * @since 3.2
	 * @return bool|WP_Error
	 */
	public function is_agent() {

		if ( false === $this->exists() ) {
			return new WP_Error( 'user_not_exists', sprintf( __( 'The user with ID %d does not exist', 'wpas' ), $this->agent_id ) );
		}

		if ( false === $this->user->has_cap( 'edit_ticket' ) ) {
			return new WP_Error( 'user_not_agent', __( 'The user exists but is not a support agent', 'wpas' ) );
		}

		return true;

	}

	/**
	 * Check if the agent can be assigned to new tickets
	 *
	 * @since 3.2
	 * @return bool
	 */
	public function can_be_assigned() {

		$can = esc_attr( get_the_author_meta( 'wpas_can_be_assigned', $this->agent_id ) );

		return empty( $can ) ? false : true;
	}

	/**
	 * Count the number of open tickets for this agent
	 *
	 * @since 3.2
	 * @return int
	 */
	public function open_tickets() {

		$posts_args = array(
			'post_type'              => 'ticket',
			'post_status'            => 'any',
			'posts_per_page'         => - 1,
			'no_found_rows'          => true,
			'cache_results'          => false,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => '_wpas_status',
					'value'   => 'open',
					'type'    => 'CHAR',
					'compare' => '='
				),
				array(
					'key'     => '_wpas_assignee',
					'value'   => $this->agent_id,
					'type'    => 'NUMERIC',
					'compare' => '='
				),
			)
		);

		$open_tickets = new WP_Query( $posts_args );

		return count( $open_tickets->posts ); // Total number of open tickets for this agent

	}

}