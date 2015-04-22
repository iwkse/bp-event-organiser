<?php

/**
 * `BP_Component` implementation.
 */
class BPEO_Component extends BP_Component {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::start(
			'bpeo',
			__( 'Event Organiser', 'bp-event-organiser' ),
			BPEO_PATH,
			array( 'adminbar_myaccount_order' => 36 )
		);
	}

	/**
	 * Set up globals.
	 */
	public function setup_globals( $args = array() ) {
		parent::setup_globals( array(
			'slug' => bpeo_get_events_slug(),
			'has_directory' => false,
		) );

		$this->setup_single_event_screen();
	}

	/**
	 * Set up navigation.
	 */
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {
		$name = bp_is_my_profile() ? __( 'My Events', 'bp-event-organiser' ) : __( 'Events', 'bp-event-organiser' );

		$main_nav = array(
			'name' => $name,
			'slug' => $this->slug,
			'position' => 62,
			'show_for_displayed_user' => false,
			'screen_function' => array( $this, 'template_loader' ),
			'default_subnav_slug' => 'calendar',
		);

		$sub_nav[] = array(
			'name' => __( 'Calendar', 'bp-event-organiser' ),
			'slug' => 'calendar', // @todo better l10n
			'parent_url' => bp_displayed_user_domain() . trailingslashit( $this->slug ),
			'parent_slug' => $this->slug,
			'user_has_access' => bp_core_can_edit_settings(),
			'screen_function' => array( $this, 'template_loader' ),
		);

		$sub_nav[] = array(
			'name' => __( 'New Event', 'bp-event-organizer' ),
			'slug' => bpeo_get_events_new_slug(),
			'parent_url' => bp_displayed_user_domain() . trailingslashit( $this->slug ),
			'parent_slug' => $this->slug,
			'user_has_access' => bp_core_can_edit_settings() && current_user_can( 'publish_events' ),
			'screen_function' => array( $this, 'template_loader' ),
		);

		parent::setup_nav( $main_nav, $sub_nav );
	}

	/**
	 * Set up admin bar links.
	 */
	public function setup_admin_bar( $wp_admin_nav = array() ) {
		$bp = buddypress();

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Add the "My Account" sub menus
		$wp_admin_nav[] = array(
			'parent' => $bp->my_account_menu_id,
			'id'     => 'my-account-events',
			'title'  => __( 'Events', 'bp-event-organiser' ),
			'href'   => bp_loggedin_user_domain() . bpeo_get_events_slug(),
		);

		$wp_admin_nav[] = array(
			'parent' => 'my-account-events',
			'id'     => 'my-account-events-calendar',
			'title'  => __( 'Calendar', 'bp-event-organiser' ),
			'href'   => trailingslashit( bp_loggedin_user_domain() . bpeo_get_events_slug() ),
		);

		$wp_admin_nav[] = array(
			'parent' => 'my-account-events',
			'id'     => 'my-account-events-new',
			'title'  => __( 'New Event', 'bp-event-organiser' ),
			'href'   => trailingslashit( bp_loggedin_user_domain() . bpeo_get_events_slug() . '/' . bpeo_get_events_new_slug() ),
		);

		parent::setup_admin_bar( $wp_admin_nav );
	}

	/**
	 * Set up single event screen.
	 */
	protected function setup_single_event_screen() {
		if ( ! bp_is_user() ) {
			return;
		}

		if ( ! bp_is_current_component( bpeo_get_events_slug() ) ) {
			return;
		}

		if ( bp_is_current_action( bpeo_get_events_new_slug() ) ) {
			return;
		}

		// This is not a single event.
		if ( ! bp_current_action() ) {
			return;
		}

		// query for the event
		$event = eo_get_events( array(
			'name' => bp_current_action()
		) );

		// check if event exists
		if ( empty( $event ) ) {
			bp_core_add_message( __( 'Event does not exist.', 'bp-event-organiser' ), 'error' );
			bp_core_redirect( trailingslashit( bp_displayed_user_domain() . bpeo_get_events_slug() ) );
			die();
		}

		// save queried event as property
		$this->queried_event = $event[0];

		// add our screen hook
		add_action( 'bp_screens', array( $this, 'template_loader' ) );
	}

	public function template_loader() {
		// new event
		if ( bp_is_current_action( bpeo_get_events_new_slug() ) ) {
			// magic admin screen code!
			require BPEO_PATH . '/includes/class.bpeo_frontend_admin_screen.php';

			$this->create_event = new BPEO_Frontend_Admin_Screen( array(
				'type' => 'new',
				'redirect_root'  => trailingslashit( bp_displayed_user_domain() . $this->slug )
			) );

			add_action( 'bp_template_content', array( $this->create_event, 'display' ) );

		// single event
		} elseif ( false === bp_is_current_action( 'calendar' ) ) {
			$this->single_event_screen();
			add_action( 'bp_template_content', array( $this, 'display_single_event' ) );

		// user calendar
		} else {
			add_action( 'bp_template_content', array( $this, 'select_template' ) );
		}

		bp_core_load_template( 'members/single/plugins' );
	}

	/**
	 * Utility function for selecting the correct Docs template to be loaded in the component
	 */
	public function select_template() {

		$args = array(
			'bp_displayed_user_id' => bp_displayed_user_id(),
		);

		echo eo_get_event_fullcalendar( $args );
	}

	/**
	 * Single event screen handler.
	 */
	protected function single_event_screen() {
		if ( empty( $this->queried_event ) ) {
			return;
		}

		// edit single event logic
		if ( bp_is_action_variable( 'edit' ) ) {
			// check if user has access
			if ( false === current_user_can( 'edit_event', $this->queried_event->ID ) ) {
				bp_core_add_message( __( 'You do not have access to edit this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( trailingslashit( bp_displayed_user_domain() . $this->slug ) . "{$this->queried_event->post_name}/" );
				die();
			}

			// magic admin screen code!
			require BPEO_PATH . '/includes/class.bpeo_frontend_admin_screen.php';

			$this->edit_event = new BPEO_Frontend_Admin_Screen( array(
				'queried_post'   => $this->queried_event,
				'redirect_root'  => trailingslashit( bp_displayed_user_domain() . $this->slug )
			) );

		// delete single event logic
		} elseif ( bp_is_action_variable( 'delete' ) ) {
			// check if user has access
			if ( false === current_user_can( 'delete_event', $this->queried_event->ID ) ) {
				bp_core_add_message( __( 'You do not have permission to delete this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( trailingslashit( bp_displayed_user_domain() . $this->slug ) . "{$this->queried_event->post_name}/" );
				die();
			}

			// verify nonce
			if ( false === bp_action_variable( 1 ) || ! wp_verify_nonce( bp_action_variable( 1 ), "bpeo_delete_event_{$this->queried_event->ID}" ) ) {
				bp_core_add_message( __( 'You do not have permission to delete this event.', 'bp-event-organiser' ), 'error' );
				bp_core_redirect( trailingslashit( bp_displayed_user_domain() . $this->slug ) . "{$this->queried_event->post_name}/" );
				die();
			}

			// delete event
			$delete = wp_delete_post( $this->queried_event->ID, true );
			if ( false === $delete ) {
				bp_core_add_message( __( 'There was a problem deleting the event.', 'bp-event-organiser' ), 'error' );
			} else {
				bp_core_add_message( __( 'Event deleted.', 'bp-event-organiser' ) );
			}

			bp_core_redirect( trailingslashit( bp_displayed_user_domain() . $this->slug ) );
			die();
		}
	}

	/**
	 * Display a single event within a user's profile.
	 *
	 * @todo Move part of this functionality into a template part so theme devs can customize.
	 * @todo Merge single event display logic for users and groups
	 */
	public function display_single_event() {
		if ( empty( $this->queried_event ) ) {
			return;
		}

		// save $post global temporarily
		global $post;
		$_post = false;
		if ( ! empty( $post ) ) {
			$_post = $post;
		}

		// override the $post global so EO can use its functions
		$post = $this->queried_event;

		// edit screen has its own display method
		if ( bp_is_action_variable( 'edit' ) ) {
			$this->edit_event->display();

			// revert $post global
			if ( ! empty( $_post ) ) {
				$post = $_post;
			}
			return;
		}

		// output single event content
		bpeo_the_single_event_content( $post );

		// revert $post global
		if ( ! empty( $_post ) ) {
			$post = $_post;
		}
	}

}

buddypress()->bpeo = new BPEO_Component();
