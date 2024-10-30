<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class GamiPress_CPT_Rules_Engine {

    /**
     * @var GamiPress_CPT_Rules_Engine
     */
    private static $instance;

    /**
     * Main GamiPress_CPT_Rules_Engine Instance
     *
     * Insures that only one instance of GamiPress_CPT_Rules_Engine exists in memory at
     * any one time. Also prevents needing to define globals all over the place.
     *
     * @since GamiPress_CPT_Rules_Engine (0.0.3)
     *
     * @staticvar array $instance
     *
     * @return GamiPress_CPT_Rules_Engine
     */
    public static function instance( ) {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new GamiPress_CPT_Rules_Engine;
            self::$instance->setup_filters();
            self::$instance->setup_actions();
        }

        return self::$instance;
    }

    /**
     * A dummy constructor to prevent loading more than one instance
     *
     * @since GamiPress_CPT_Rules_Engine (0.0.1)
     */
    private function __construct() { /* Do nothing here */
    }

    /**
     * Setup the actions
     *
     * @since GamiPress_CPT_Rules_Engine (0.0.1)
     * @access private
     *
     * @uses remove_action() To remove various actions
     * @uses add_action() To add various actions
     */
    private function setup_actions() {
        add_action( 'comment_post', array( $this, 'maybe_trigger_publish_comment' ), 8, 2 );
        add_action( 'transition_comment_status', array( $this, 'check_comment_approved' ), 10, 3);
    }

    /**
     * Setup the filters
     *
     * @since GamiPress_CPT_Rules_Engine (0.0.1)
     * @access private
     *
     * @uses remove_filter() To remove various filters
     * @uses add_filter() To add various filters
     */
    private function setup_filters() {

		add_filter( 'gamipress_activity_triggers',  array( $this, 'add_triggers') );
    }


    function get_post_types() {
        $skipped_types = array( 'post', 'page' );

        // bbpress post types are handled elsewhere
        foreach ( array( 'bbp_get_topic_post_type', 'bbp_get_reply_post_type', 'bbp_get_forum_post_type' ) as $func ) {
            if ( function_exists( $func ) )
                $skipped_types[] = call_user_func( $func );
        }

        // do not include gamipress achievement types
        $skipped_types = array_merge($skipped_types, gamipress_get_achievement_types_slugs() );

        return array_diff( get_post_types( array( 'public' => true ) ), $skipped_types );
    }

    function get_triggers() {
        $triggers = array();

        $post_types = $this->get_post_types();

        foreach( $post_types as $post_type ) {
            $info = get_post_type_object( $post_type );

            $triggers["gamipress_publish_{$post_type}"] = sprintf( __( 'Publish a new %s', 'gamipress-cpt' ), $info->labels->singular_name );
			//$triggers["gamipress_delete_{$post_type}"] = sprintf( __( 'Delete a %s', 'gamipress-cpt' ), $info->labels->singular_name );
            $triggers["gamipress_cpt_comment_$post_type"] = sprintf( __( 'Comment on a %s',  'gamipress-cpt' ), $info->labels->singular_name );
        }

        return array( __( 'Custom Post Type Events', 'gamipress' ) => $triggers );
    }

    function add_triggers( $triggers ) {
        return array_merge( $triggers, $this->get_triggers() );
    }

    /**
     * Trigger comment action when a comment is approved
     *
     * @since 1.0.0
     *
     */
    function check_comment_approved( $new_status, $old_status, $comment ) {

        // If comment is not approved, don't trigger
        if ( $new_status != 'approved' )
            return;

        $this->maybe_trigger_publish_comment( $comment->comment_ID, 1, $comment );
    }

    /**
     * Trigger comment action when a post is commented
     *
     * @since 1.0.0
     *
     */
    function maybe_trigger_publish_comment( $comment_ID, $comment_approved, $comment = null ) {

        // If comment is not approved, don't trigger
        if ( $comment_approved !== 1 )
            return;

        if ( ! $comment )
            $comment = get_comment( $comment_ID );

        // If comment is not from a registered user, don't trigger
        if ( ! $comment->user_id )
            return;

        $post = get_post( $comment->comment_post_ID );

		gamipress_trigger_event( array(
			'event' => "gamipress_cpt_comment_{$post->post_type}",
			'user_id' => $comment->user_id,
			'post_id' => $post->ID
		) );
    }

}

GamiPress_CPT_Rules_Engine::instance();
