<?php

namespace WPOrg_Learn\Form;

use WP_Error, WP_User;
use WordPressdotorg\Validator;
use function WordPressdotorg\Locales\get_locales_with_native_names;

defined( 'WPINC' ) || die();

/**
 * The schema for the workshop application fields.
 *
 * @return array
 */
function get_workshop_application_field_schema() {
	return array(
		'type'       => 'object',
		'label'      => 'submission',
		'properties' => array(
			'wporg-user-name'         => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'first-name'              => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'last-name'               => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'email'                   => array(
				'input_filters' => FILTER_SANITIZE_EMAIL,
				'type'          => 'string',
				'format'        => 'email',
				'required'      => true,
				'default'       => '',
			),
			'online-presence'         => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'workshop-title'          => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'description'             => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'description-short'       => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'learning-objectives'     => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'comprehension-questions' => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
			'audience'                => array(
				'input_filters' => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'type'          => 'array',
				'items'         => array(
					'type' => 'string',
				),
				'required'      => true,
				'default'       => '',
			),
			'experience-level'        => array(
				'input_filters' => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => FILTER_REQUIRE_ARRAY,
				),
				'type'          => 'array',
				'items'         => array(
					'type' => 'string',
				),
				'required'      => true,
				'default'       => '',
			),
			'language'                => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'enum'          => array_keys( get_locales_with_native_names() ),
				'required'      => true,
				'default'       => 'en_US',
			),
			'timezone'                => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => 'UTC-0',
			),
			'comments'                => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => false,
				'default'       => '',
			),
			'nonce'                   => array(
				'input_filters' => FILTER_SANITIZE_STRING,
				'type'          => 'string',
				'required'      => true,
				'default'       => '',
			),
		),
	);
}

/**
 * Parse a workshop application submission from an HTTP POST request.
 *
 * @return array
 */
function get_workshop_application_form_submission() {
	$schema = get_workshop_application_field_schema();

	$submission = filter_input_array(
		INPUT_POST,
		wp_list_pluck( $schema, 'input_filters' ),
		false
	);

	if ( empty( $submission ) ) {
		$submission = array();
	}

	$current_user = wp_get_current_user();

	if ( $current_user instanceof WP_User ) {
		$user = array(
			'wporg-user-name' => $current_user->user_login,
			'first-name'      => $current_user->user_firstname,
			'last-name'       => $current_user->user_lastname,
			'email'           => $current_user->user_email,
		);

		$submission = array_merge( $submission, $user );
	}

	return array_filter( $submission );
}

/**
 * Validate a workshop application submission.
 *
 * @param array|object $submission
 *
 * @return array|object|WP_Error
 */
function validate_workshop_application_form_submission( $submission ) {
	$validator  = new Validator( get_workshop_application_field_schema() );
	$submission = (object) $submission;

	return $validator->validate( $submission );
}
