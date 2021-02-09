<?php

namespace WPOrg_Learn\Events;

use DateTime, Exception, Error;
use wpdb, WP_REST_Request, WP_REST_Response, WP_REST_Server;
use function WPOrg_Learn\{ get_build_path, get_build_url };

defined( 'WPINC' ) || die();

/**
 * Actions and filters.
 */
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_routes' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_event_assets' );

/**
 * Retrieve current and upcoming Learn discussion group events using caching mechanisms.
 *
 * @return array
 */
function get_discussion_events( array $args = array() ) {
	wp_cache_add_global_groups( array( 'learn-events' ) );
	$cache_expiration = HOUR_IN_SECONDS * 6;

	$events = wp_cache_get( 'discussion-events', 'learn-events' );

	if ( false === $events ) {
		try {
			$start_date = new DateTime( '@' . strtotime( '-1 hour' ) );
			$end_date = new DateTime( '@' . strtotime( '1 month' ) );
		} catch ( Exception $exception ) {
			return array();
		}

		$raw_events = get_discussion_events_from_db( $start_date, $end_date );

		$event_fields_to_keep = array( 'title', 'url', 'description', 'date_utc', 'date_utc_offset' );
		$events = array_map(
			function( $event ) use ( $event_fields_to_keep ) {
				return array_intersect_key(
					$event,
					array_fill_keys( $event_fields_to_keep, '' )
				);
			},
			$raw_events
		);

		wp_cache_set( 'discussion-events', $events, 'learn-events', $cache_expiration );
	}

	if ( isset( $args['number'] ) ) {
		$events = array_slice( $events, 0, absint( $args['number'] ) );
	}

	return $events;
}

/**
 * Retrieve current and upcoming Learn discussion group events directly from the database.
 *
 * This function should probably not be called directly. Use `get_discussion_events()` instead.
 *
 * @global wpdb $wpdb;
 *
 * @param DateTime $start_date
 * @param DateTime $end_date
 *
 * @return array
 */
function get_discussion_events_from_db( DateTime $start_date, DateTime $end_date ) {
	global $wpdb;

	$sql = "
		SELECT *
		FROM wporg_events
		WHERE meetup_url = 'https://www.meetup.com/learn-wordpress-discussions/'
		AND status = 'scheduled'
		AND date_utc BETWEEN %s AND %s
		ORDER BY date_utc
		LIMIT 100
	";

	$results = $wpdb->get_results(
		$wpdb->prepare(
			$sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
			$start_date->format( 'Y-m-d H:i:s' ),
			$end_date->format( 'Y-m-d H:i:s' )
		),
		ARRAY_A
	);

	return $results ?: array();
}

/**
 * REST API routes related to events.
 *
 * @return void
 */
function register_rest_routes() {
	register_rest_route(
		'wporg-learn/v1',
		'upcoming-discussion-groups',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\rest_upcoming_discussion_groups_callback',
			'permission_callback' => '__return_true',
			'args'                => array(
				'number' => array(
					'description' => __( 'The number of upcoming events to display.', 'wporg-learn' ),
					'type'        => 'integer',
					'default'     => 3,
				),
			),
			'schema'              => array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'upcoming-discussion-groups',
				'type'       => 'array',
				'items'      => array(
					'type'       => 'object',
					'properties' => array(
						'title'           => array(
							'description' => 'The title of the event.',
							'type'        => 'string',
						),
						'url'             => array(
							'description' => 'URL to the event page.',
							'type'        => 'string',
							'format'      => 'uri',
						),
						'description'     => array(
							'description' => 'HTML-formatted details about the event.',
							'type'        => 'string',
						),
						'start_timestamp' => array(
							'description' => 'Unix timestamp for the start of the event.',
							'type'        => 'integer',
						),
					),
				),
			),
		)
	);
}

/**
 * Process a request to upcoming-discussion-groups and return a REST response.
 *
 * @param WP_REST_Request $request
 *
 * @return WP_REST_Response
 */
function rest_upcoming_discussion_groups_callback( $request ) {
	$args = array(
		'number' => $request['number'],
	);

	$events = get_discussion_events( $args );

	$events = array_map(
		function( $event ) {
			$event['start_timestamp'] = strtotime( $event['date_utc'] ) - (int) $event['date_utc_offset'];
			unset( $event['date_utc'], $event['date_utc_offset'] );

			return $event;
		},
		$events
	);

	return rest_ensure_response( $events );
}

/**
 * Enqueue scripts and stylesheets for event functionality.
 *
 * @throws Error If the build files are not found.
 */
function enqueue_event_assets() {
	$script_asset_path = get_build_path() . 'event.asset.php';
	if ( ! file_exists( $script_asset_path ) ) {
		throw new Error(
			'You need to run `npm start` or `npm run build` for event.js first.'
		);
	}

	$script_asset = require $script_asset_path;
	wp_register_script(
		'wporg-learn-event',
		get_build_url() . 'event.js',
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);
}
