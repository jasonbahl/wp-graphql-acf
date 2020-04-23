<?php

namespace WPGraphQL\ACF\Registry\FieldRegistry;

/**
 * Class ACF_Field_Registry
 *
 * @package WPGraphQL\ACF\Registry\FieldRegistry
 */
class ACF_Field_Registry {

	/**
	 * @var array
	 */
	public $field_group;

	/**
	 * @var mixed
	 */
	public $field_group_type_name;

	/**
	 * @var string
	 */
	public $field_group_description;

	/**
	 * @var array
	 */
	public $fields;

	/**
	 * @var array
	 */
	public $field;

	/**
	 * ACF_Field_Registry constructor.
	 *
	 * @param array $field_group
	 */
	public function __construct( array $field_group ) {

		$this->field_group               = $field_group;
		$this->field_group_type_name     = $this->field_group['graphql_field_name'];
		$field_group_description_context = sprintf( __( 'The "%s" ACF Field Group.', 'wp-graphql-acf' ), $this->field_group['title'] );
		$this->field_group_description   = ! empty( $this->field_group['description'] ) ? $field_group_description_context . ' ' . $this->field_group['description'] : $field_group_description_context;
		$this->fields                    = acf_get_fields( $field_group );

	}

	/**
	 * @return mixed|void
	 */
	public function register_fields() {

		/**
		 * Iterate over each field
		 */
		foreach ( $this->fields as $field ) {

			/**
			 * Set the value of the field at the class level
			 */
			$this->set_field( $field );

			/**
			 * Filter the field registry. This allows for additional ACF Field Types that are not
			 * part of the core ACF Plugin to be registered.
			 *
			 * @param null $register_field The field to register
			 * @param ACF_Field_Registry $this Instance of the Field Registry
			 *
			 * Example:
			 *
			 * add_filter( 'graphql_acf_register_field_to_field_group', function( $register_field, $field_registry ) {
			 *
			 *   if ( isset( $field_registry->field['type'] ) && 'my_custom_type' === $field_registry->field['type'] ) {
			 *      register_graphql_field( $this->field_group_type_name, 'my_field', [
			 *         'type' => 'String'
			 *      ] );
			 *   }
			 *
			 * }, 10, 2 );
			 */
			$register_field = apply_filters( 'graphql_acf_register_field_to_field_group', null, $this );
			if ( null === $register_field && true === method_exists( $this, $field['type'] ) ) {
				$this->{$field['type']}( $field );
			}

			/**
			 * Fire action after fields have been registered to ACF Field Groups
			 *
			 * This is a great place for plugins to add fields to ALL field groups in the schema, or
			 * many field groups that meet a certain, broad criteria
			 *
			 * @param ACF_Field_Registry $this Instance of the Field Registry
			 */
			do_action( 'graphql_acf_after_register_field_to_fields', $this  );
		}

	}

	public function set_field( array $field ) {
		$this->field = $field;
	}

	public function register_field( $args ) {
		$default_args = [
			'type' => 'String',
			'description' => sprintf( __( 'The %1$s field. Registered to the ACF Field Group "%2$s". %3$s', 'wp-graphql-acf' ), $this->field['tiitle'], $this->field_group['title'], $this->field['description'] ),
		];
		$args = array_merge( $default_args, $args );
		$field_name = $this->field['name'];
		register_graphql_field( $this->field_group_type_name, $field_name, $args );
	}

	public function text() {
		$this->register_field( [
			'type'    => 'String',
			'resolve' => function() {
				return 'goo';
			}
		] );
	}

	public function textarea() {
		$this->register_field( [
			'type'    => 'String',
			'resolve' => function() {
				return 'goo';
			}
		] );
	}

	public function number() {
		$this->register_field( [
			'type'    => 'String',
			'resolve' => function() {
				return 'goo';
			}
		] );
	}

	public function flexible_content() {
		$this->register_field( [
			'type' => 'String',
			'resolve' => function( $root ) {
				return get_field( $this->field['name'] );
			}
		] );
	}

}
