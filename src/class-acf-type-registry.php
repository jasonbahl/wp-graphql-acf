<?php
namespace WPGraphQL\ACF;

use WPGraphQL\ACF\Type\Object\ACF_Field_Group_Config;
use WPGraphQL\ACF\Type\Object\ACF_Location_Rule;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Utils\Utils;

/**
 * Class ACF_Type_Registry
 *
 * @package WPGraphQL\ACF
 */
class ACF_Type_Registry {

	/**
	 * All ACF Field Groups
	 * @var array
	 */
	public $acf_field_groups;

	/**
	 * The ACF Field Groups to show in GraphQL
	 *
	 * @var array
	 */
	public $graphql_field_groups;

	/**
	 * The WPGraphQL TypeRegistry
	 *
	 * @var TypeRegistry
	 */
	public $type_registry;

	/**
	 * Initialize the ACF Type Registry
	 */
	public function init() {

		// Get ALL ACF Field Groups
		$this->acf_field_groups = acf_get_field_groups();

		// Get All field groups that should show in GraphQL
		$this->graphql_field_groups = $this->get_graphql_field_groups();

		// Register the ACF Field Groups to the Schema
		add_action( 'graphql_register_types', function( TypeRegistry $type_registry ) {

			// Iterate over the field groups.
			$field_groups = $this->graphql_field_groups;
			if ( ! empty( $field_groups ) && is_array( $field_groups ) ) {
				foreach ( $field_groups as $field_group ) {

					$graphql_types = $field_group['graphql_types'] ?? null;

					if ( empty( $graphql_types ) ) {
						return;
					}

					// Prepare the fields for the field group
					$object_fields = $this->prepare_fields( $field_group );

					if ( empty( $object_fields ) ) {
						return;
					}

					$interface_name = 'Has' . ucfirst($field_group['graphql_field_name']);

					// Register an Interface for the Field Group. This interface will be applied to
					// the types the ACF Field Group is assigned
					register_graphql_interface_type( $interface_name, [
						'description' => sprintf( __( 'Node that supports the ACF Field Group %s', 'wp-graphql-acf' ), $field_group['graphql_field_name'] ),
						'fields' => [
							Utils::format_field_name( $field_group['graphql_field_name'] ) => [
								'type' => Utils::format_type_name( $field_group['graphql_field_name'] ),
								'resolve' => function( $root ) use ( $field_group ) {
									return $root;
								}
							],
						],
					]);

					// Register an Object Type with the $object_fields
					register_graphql_object_type( $field_group['graphql_field_name'], [
						'fields' => $object_fields
					]);

					// Register the interfaces to the graphql types
					register_graphql_interfaces_to_types( [ $interface_name ], $field_group['graphql_types'] );

				}
			}

		} );

	}

	/**
	 * Get the value of an ACF Field
	 *
	 * @param mixed $source The source being resolved.
	 * @param array $acf_field The ACF Field to resolve.
	 * @param boolean $format Whether ACF should apply formatting to the field. Default false.
	 *
	 * @return mixed
	 */
	protected function get_acf_field_value( $source, $acf_field, $format = false ) {

		$value = null;
		$id = null;

		if ( is_array( $source ) && ! ( ! empty( $source['type'] ) && 'options_page' === $source['type'] ) ) {

			if ( isset( $root[ $acf_field['key'] ] ) ) {
				$value = $source[ $acf_field['key'] ];

				if ( 'wysiwyg' === $acf_field['type'] ) {
					$value = apply_filters( 'the_content', $value );
				}

			}
		} else {

			// Determines the source to know how to ask for the field from ACF.
			switch ( true ) {
				case $source instanceof Term:
					$id = acf_get_term_post_id( $source->taxonomyName, $source->term_id );
					break;
				case $source instanceof Post:
					$id = absint( $source->ID );
					break;
				case $source instanceof MenuItem:
					$id = absint( $source->menuItemId );
					break;
				case $source instanceof Menu:
					$id = acf_get_term_post_id( 'nav_menu', $source->menuId );
					break;
				case $source instanceof User:
					$id = 'user_' . absint( $source->userId );
					break;
				case $source instanceof Comment:
					$id = 'comment_' . absint( $source->comment_ID );
					break;
				case is_array( $source ) && ! empty( $source['type'] ) && 'options_page' === $source['type']:
					$id = $source['post_id'];
					break;
				default:
					$id = null;
					break;
			}

			/**
			 * Filters the root ID, allowing additional Models the ability to provide a way to resolve their ID
			 *
			 * @param int   $id    The ID of the object. Default null
			 * @param mixed $root  The Root object being resolved. The ID is typically a property of this object.
			 */
			$id = apply_filters( 'graphql_acf_get_root_id', $id, $source );

			if ( empty( $id ) ) {
				return null;
			}

			$format = false;

			if ( 'wysiwyg' === $acf_field['type'] ) {
				$format = true;
			}

			// Check if cloned field and retrieve the key accordingly.
			if ( ! empty( $acf_field['_clone'] ) ) {
				$key = $acf_field['__key'];
			} else {
				$key = $acf_field['key'];
			}

			$field_value = get_field( $key, $id, $format );

			$value = ! empty( $field_value ) ? $field_value : null;
		}

		/**
		 * Filters the returned ACF field value
		 *
		 * @param mixed $value     The resolved ACF field value
		 * @param array $acf_field The ACF field config
		 * @param mixed $source    The Root object being resolved. The ID is typically a property of this object.
		 * @param int   $id        The ID of the object
		 */
		return apply_filters( 'graphql_acf_field_value', $value, $acf_field, $source, $id );

	}

	/**
	 * Given an ACF Field Group, this prepares the fields to be registered to the WPGraphQL Schema
	 *
	 * @param array $field_group The ACF Field Group to prepare fields for the WPGraphQL Schema
	 *
	 * @return array
	 */
	public function prepare_fields( array $field_group ) {

		$prepared_fields = [];

		// Get the fields of the group.
		$fields = acf_get_fields( $field_group );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $prepared_fields;
		}

		foreach ( $fields as $field ) {

			// If the field is set to NOT show in GraphQL, leave it out.
			if ( false === (bool) $field['show_in_graphql'] ) {
				continue;
			}

			$default_field_config = [
				'type' => 'String',
				'description' => sprintf( __( 'The "%1$s" field registered to the "%2$s" ACF Field Group. %3$s', 'wp-graphql' ), $field['label'], $field_group['title'], $field['instructions'] ),
				'resolve' => function( $root ) use ( $field ) {
					return $this->get_acf_field_value( $root, $field, false );
				}
			];

			$field_config = $this->prepare_field( $field, $field_group );
			$prepared_field = array_merge( $default_field_config, $field_config );
			$field_name = isset( $field['graphql_single_name'] ) ? Utils::format_field_name( $field['graphql_single_name'] ) : Utils::format_field_name( $field['name'] );

			/**
			 * Filter the prepared field.
			 *
			 * @param array $field_config The config for the field to be registered
			 * @param array $field The ACF Field config
			 * @param array $field_group The ACF Field Group the field belongs to
			 * @param ACF_Type_Registry $this The instance of the ACF_Type_Registry
			 */
			$prepared_field = apply_filters( 'graphql_acf_prepared_field', $prepared_field, $field, $field_group, $this );
			$prepared_fields[ $field_name ] = $prepared_field;
		}

		return $prepared_fields;

	}

	/**
	 * Given an acf_field config, prepare the field for the WPGraphQL Schema
	 *
	 * @param array $acf_field The ACF Field to prepare
	 * @param array $acf_field_group The ACF Field Group the ACF Field belongs too
	 *
	 * @return array
	 */
	public function prepare_field( $acf_field, $acf_field_group ) {

		$prepared_field = [];

		$acf_field_type  = isset( $acf_field['type'] ) ? $acf_field['type'] : null;
		if ( empty( $acf_field_type ) ) {
			return $prepared_field;
		}

		$custom_prepared_field = apply_filters( 'graphql_acf_register_graphql_field', null, $acf_field );

		if ( ! empty( $custom_prepared_field ) && is_array( $custom_prepared_field ) ) {
			return $custom_prepared_field;
		}

		switch ( $acf_field_type ) {
			case 'button_group':
			case 'color_picker':
			case 'email':
			case 'text':
			case 'message':
			case 'oembed':
			case 'password':
			case 'wysiwyg':
			case 'url':
				break;
			case 'textarea':
				$prepared_field = [
					'type' => 'String',
					'resolve' => function( $root ) use ( $acf_field ) {
						$value = $this->get_acf_field_value( $root, $acf_field );
						if ( ! empty( $acf_field['new_lines'] ) ) {
							if ( 'wpautop' === $acf_field['new_lines'] ) {
								$value = wpautop( $value );
							}
							if ( 'br' === $acf_field['new_lines'] ) {
								$value = nl2br( $value );
							}
						}
						return $value;
					}
				];
				break;
			default:
				break;
		}

		// Return the prepared field
		return $prepared_field;

	}

	/**
	 * Register the Types
	 */
	public function register_types() {
		ACF_Location_Rule::register_type();
		ACF_Field_Group_Config::register_type();
	}

	/**
	 * Determines whether a field group should be exposed to the GraphQL Schema. By default, field
	 * groups will not be exposed to GraphQL.
	 *
	 * @param array $field_group The ACF Field Group.
	 *
	 * @return bool
	 */
	public function should_field_group_show_in_graphql( $field_group ) {

		// By default, field groups will NOT be exposed to GraphQL.
		$show = false;

		// Determine if the field group is set to NOT show in GraphQL
		if ( isset( $field_group['show_in_graphql'] ) && true === (bool) $field_group['show_in_graphql'] ) {
			$show = true;
		}

		 // Determine conditions where the GraphQL Schema should NOT be shown in GraphQL for
		 // root groups, not nested groups with parent.
		if ( ! isset( $field_group['parent'] ) ) {
			if (
				( isset( $field_group['active'] ) && true != $field_group['active'] ) ||
				( empty( $field_group['location'] ) || ! is_array( $field_group['location'] ) )
			) {
				$show = false;
			}
		}

		/**
		 * Whether a field group should show in GraphQL.
		 *
		 * @var boolean $show        Whether the field group should show in the GraphQL Schema
		 * @var array   $field_group The ACF Field Group
		 * @var Config  $this        The Config for the ACF Plugin
		 */
		return apply_filters( 'graphql_acf_should_field_group_show_in_graphql', $show, $field_group, $this );

	}

	/**
	 * Get field groups that are set to show_in_graphql and have a graphql field name set.
	 *
	 * @return array
	 */
	public function get_graphql_field_groups() {
		$field_groups = [];
		if ( ! empty( $this->acf_field_groups ) && is_array( $this->acf_field_groups ) )  {
			foreach ( $this->acf_field_groups as $field_group ) {
				if ( $this->should_field_group_show_in_graphql( $field_group ) ) {
					$type_name                  = $field_group['graphql_field_name'];
					$field_groups[ $type_name ] = $field_group;
				}
			}
		}
		return $field_groups;
	}



}
