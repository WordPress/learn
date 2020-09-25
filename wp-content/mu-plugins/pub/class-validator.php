<?php

namespace WordPressdotorg;

use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class Validator
 *
 * A generic class for validating a set of optionally nested values such as from an HTTP POST request or a JSON string,
 * given a schema that describes the requirements for those values.
 *
 * This will work best if the provided schema more or less follows the schema for schemas. Though note that this class
 * does not exhaustively implement all schema parameters.
 * See https://json-schema.org/understanding-json-schema/reference/index.html
 *
 * @package WordPressdotorg
 */
class Validator {
	/**
	 * @var WP_Error
	 */
	protected $errors;

	/**
	 * @var array
	 */
	protected $schema;

	/**
	 * Validator constructor.
	 *
	 * @param array $schema {
	 *     See the schema reference for a list of parameters that will work with each type.
	 *     https://json-schema.org/understanding-json-schema/reference/index.html
	 *
	 *     @type string|array $type  Required. The type of data being validated.
	 *                               Valid values are 'string', 'boolean', 'integer', 'number', 'array', and 'object'.
	 *     @type string       $label Optional. The label for the data being validated. Used in error messages.
	 * }
	 */
	public function __construct( array $schema ) {
		$this->errors = new WP_Error();
		$this->schema = $schema;
	}

	/**
	 * A getter for the schema for the data set.
	 *
	 * @return array
	 */
	public function get_schema() {
		return $this->schema;
	}

	/**
	 * Validate a data set.
	 *
	 * @param object|WP_Error $data
	 *
	 * @return bool|WP_Error
	 */
	public function validate( $data ) {
		$schema = $this->get_schema();

		if ( ! isset( $schema['type'] ) ) {
			return new WP_Error(
				'error',
				'The schema does not define the data type.'
			);
		}

		$prop = $schema['label'] ?: 'data';

		$valid = $this->route_validation_for_type(
			$schema['type'],
			$data,
			$prop,
			$schema
		);

		if ( ! $valid ) {
			return $this->errors;
		}

		return $data;
	}

	/**
	 * Validate an object and its properties.
	 *
	 * @param object $object The value to validate as an object.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_object( $object, $prop, $schema ) {
		if ( is_array( $object ) && ! wp_is_numeric_array( $object ) ) {
			// Convert an associative array to an object.
			$object = (object) $object;
		}

		if ( ! is_object( $object ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain an object value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		$results = array();

		if ( ! isset( $schema['required'] ) && isset( $schema['properties'] ) ) {
			// Some schemas use individual 'required' parameters for each property instead of an array of property names.
			$required = array_filter(
				$schema['properties'],
				function( $property ) {
					return isset( $property['required'] ) && true === $property['required'];
				}
			);

			if ( ! empty( $required ) ) {
				$schema['required'] = array_keys( $required );
			}
		}

		if ( isset( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_prop ) {
				if ( ! property_exists( $object, $required_prop ) ) {
					$this->errors->add(
						'error',
						sprintf(
							__( 'The %1$s property is required in the %2$s object.', 'wporg' ),
							'<code>' . $required_prop . '</code>',
							'<code>' . $prop . '</code>'
						)
					);
					$this->append_error_data( "$prop:$required_prop", 'error' );
					$results[] = false;
				}
			}
		}

		if ( isset( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $subprop => $subschema ) {
				if ( ! isset( $object->$subprop ) ) {
					continue;
				}

				if ( isset( $subschema['type'] ) ) {
					$results[] = $this->route_validation_for_type(
						$subschema['type'],
						$object->$subprop,
						"$prop:$subprop",
						$subschema
					);
				}
			}
		}

		if ( isset( $schema['additionalProperties'] ) ) {
			if ( false === $schema['additionalProperties'] ) {
				foreach ( array_keys( get_object_vars( $object ) ) as $key ) {
					if ( ! isset( $schema['properties'][ $key ] ) ) {
						$this->errors->add(
							'error',
							sprintf(
								__( '%1$s is not a valid property in the %2$s object.', 'wporg' ),
								'<code>' . $key . '</code>',
								'<code>' . $prop . '</code>'
							)
						);
						$this->append_error_data( "$prop:$key", 'error' );
						$results[] = false;
						continue;
					}
				}
			} elseif ( isset( $schema['additionalProperties']['type'] ) ) {
				foreach ( $object as $subprop => $subvalue ) {
					$results[] = $this->route_validation_for_type(
						$schema['additionalProperties']['type'],
						$subvalue,
						"$prop:$subprop",
						$schema['additionalProperties']
					);
				}
			}
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Validate an array and its items.
	 *
	 * @param array  $array  The value to validate as an array.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_array( $array, $prop, $schema ) {
		if ( ! wp_is_numeric_array( $array ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain an array value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		if ( isset( $schema['items']['type'] ) ) {
			$results = array();
			$index   = 0;

			foreach ( $array as $item ) {
				$results[] = $this->route_validation_for_type(
					$schema['items']['type'],
					$item,
					$prop . "[$index]",
					$schema['items']
				);
				$index ++;
			}

			return ! in_array( false, $results, true );
		}

		return true;
	}

	/**
	 * Validate a string.
	 *
	 * @param string $string The value to validate as a string.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_string( $string, $prop, $schema ) {
		if ( ! is_string( $string ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain a string value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		if ( isset( $schema['enum'] ) ) {
			if ( ! in_array( $string, $schema['enum'], true ) ) {
				$this->errors->add(
					'error',
					sprintf(
						__( '"%1$s" is not a valid value for the %2$s property.', 'wporg' ),
						esc_html( $string ),
						'<code>' . $prop . '</code>'
					)
				);
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		if ( isset( $schema['pattern'] ) ) {
			if ( ! preg_match( '#' . $schema['pattern'] . '#', $string ) ) {
				$pattern_description = $this->get_human_readable_pattern_description( $schema['pattern'] );
				if ( $pattern_description ) {
					$message = sprintf(
						$pattern_description,
						'<code>' . $prop . '</code>'
					);
				} else {
					$message = sprintf(
						__( 'The value of %s does not match the required pattern.', 'wporg' ),
						'<code>' . $prop . '</code>'
					);
				}

				$this->errors->add( 'error', $message );
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		if ( isset( $schema['format'] ) ) {
			switch ( $schema['format'] ) {
				case 'email':
					if ( ! is_email( $string ) ) {
						$this->errors->add(
							'error',
							sprintf(
								__( '%s must contain a valid email address.', 'wporg' ),
								'<code>' . $prop . '</code>'
							)
						);
						$this->append_error_data( $prop, 'error' );

						return false;
					}
					break;
			}
		}

		if ( isset( $schema['minLength'] ) ) {
			if ( strlen( $string ) < $schema['minLength'] ) {
				$this->errors->add(
					'error',
					sprintf(
						__( '%1$s must be at least %2$d characters long.', 'wporg' ),
						'<code>' . $prop . '</code>',
						absint( $schema['minLength'] )
					)
				);
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		if ( isset( $schema['maxLength'] ) ) {
			if ( strlen( $string ) > $schema['maxLength'] ) {
				$this->errors->add(
					'error',
					sprintf(
						__( '%1$s must be at most %2$d characters long.', 'wporg' ),
						'<code>' . $prop . '</code>',
						absint( $schema['maxLength'] )
					)
				);
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a number.
	 *
	 * @param string $string The value to validate as a number.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_number( $number, $prop, $schema ) {
		if ( ! is_numeric( $number ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain a numeric value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		if ( isset( $schema['minimum'] ) ) {
			$precision = $this->get_number_precision( $schema['minimum'] );
			if ( round( floatval( $number ), $precision ) < floatval( $schema['minimum'] ) ) {
				$this->errors->add(
					'error',
					sprintf(
						__( 'The value of %1$s must be at least %2$f.', 'wporg' ),
						'<code>' . $prop . '</code>',
						floatval( $schema['minimum'] )
					)
				);
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		if ( isset( $schema['maximum'] ) ) {
			$precision = $this->get_number_precision( $schema['maximum'] );
			if ( round( floatval( $number ), $precision ) > floatval( $schema['maximum'] ) ) {
				$this->errors->add(
					'error',
					sprintf(
						__( 'The value of %1$s must be at most %2$f.', 'wporg' ),
						'<code>' . $prop . '</code>',
						floatval( $schema['maximum'] )
					)
				);
				$this->append_error_data( $prop, 'error' );

				return false;
			}
		}

		return true;
	}

	/**
	 * Validate an integer.
	 *
	 * @param string $string The value to validate as an integer.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_integer( $number, $prop, $schema ) {
		if ( ! is_int( $number ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain an integer value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		return $this->validate_number( $number, $prop, $schema );
	}


	/**
	 * Validate a boolean.
	 *
	 * @param bool   $boolean The value to validate as a boolean.
	 * @param string $prop    The name of the property, used in error reporting.
	 *
	 * @return bool
	 */
	protected function validate_boolean( $boolean, $prop ) {
		if ( ! is_bool( $boolean ) ) {
			$this->errors->add(
				'error',
				sprintf(
					__( '%s must contain a boolean value.', 'wporg' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Send a property value to the correct validator depending on which type(s) it can be.
	 *
	 * @param string|array $valid_types
	 * @param mixed        $value
	 * @param string       $prop
	 * @param array        $schema
	 *
	 * @return bool
	 */
	protected function route_validation_for_type( $valid_types, $value, $prop, $schema ) {
		// There is a single valid type.
		if ( is_string( $valid_types ) ) {
			$method = "validate_$valid_types";
			return $this->$method( $value, $prop, $schema );
		}

		// There are multiple valid types in an array.
		foreach ( $valid_types as $type ) {
			switch ( $type ) {
				case 'boolean':
					$check = 'is_bool';
					break;
				case 'number':
					$check = 'is_numeric';
					break;
				default:
					$check = "is_$type";
					break;
			}

			if ( $check( $value ) ) {
				$method = "validate_$type";
				return $this->$method( $value, $prop, $schema );
			}
		}

		// Made it this far, it's none of the valid types.
		$this->errors->add(
			'error',
			sprintf(
				__( '%1$s must contain a value that is one of these types: %2$s', 'wporg' ),
				'<code>' . $prop . '</code>',
				// translators: used between list items, there is a space after the comma.
				'<code>' . implode( '</code>' . __( ', ', 'wporg' ) . '<code>', $valid_types ) . '</code>'
			)
		);
		$this->append_error_data( $prop, 'error' );

		return false;
	}

	/**
	 * Add more data to an error code.
	 *
	 * The `add_data` method in WP_Error replaces data with each subsequent call with the same error code.
	 *
	 * @param mixed  $new_data   The data to append.
	 * @param string $error_code The error code to assign the data to.
	 *
	 * @return void
	 */
	protected function append_error_data( $new_data, $error_code ) {
		$data   = $this->errors->get_error_data( $error_code ) ?: array();
		$data[] = $new_data;
		$this->errors->add_data( $data, $error_code );
	}

	/**
	 * Get a description of a regex pattern that can be understood by humans.
	 *
	 * @param string $pattern A regex pattern.
	 *
	 * @return string
	 */
	protected function get_human_readable_pattern_description( $pattern ) {
		/**
		 * Filter: provide a human readable description of a regular expression.
		 *
		 * The description is intended to be used in an error message, so it should be phrased
		 * as an imperative.
		 *
		 * Example:
		 *     - Pattern: \.css$
		 *     - Description: The value must end in ".css".
		 *
		 * @param string $description The description.
		 * @param string $pattern     The regular expression.
		 */
		return apply_filters( 'wporg_validator_human_readable_pattern_description', '', $pattern );
	}

	/**
	 * Get the number of decimal places of a number.
	 *
	 * @param int|float $number
	 *
	 * @return int
	 */
	protected function get_number_precision( $number ) {
		$decimals = substr(
			(string) $number,
			strpos( (string) $number, '.' ) + 1
		);

		if ( ! $decimals ) {
			return 0;
		}

		return strlen( $decimals );
	}
}
