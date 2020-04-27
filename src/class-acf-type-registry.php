<?php

namespace WPGraphQL\ACF;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\ACF\Type\Object\ACF_Field_Group_Config;
use WPGraphQL\ACF\Type\Object\ACF_Link;
use WPGraphQL\ACF\Type\Object\ACF_Location_Rule;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
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
	 *
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
	public function init( TypeRegistry $type_registry ) {

		// Set the type_registry on the class.
		$this->type_registry = $type_registry;

		// Get ALL ACF Field Groups.
		$this->acf_field_groups = acf_get_field_groups();

		// Get All field groups that should show in GraphQL.
		$this->graphql_field_groups = $this->get_graphql_field_groups();

		ACF_Link::register_type();
		ACF_Field_Group_Config::register_type();
		ACF_Location_Rule::register_type();

		// Iterate over the field groups.
		$field_groups = $this->graphql_field_groups;
		if ( ! empty( $field_groups ) && is_array( $field_groups ) ) {
			foreach ( $field_groups as $field_group ) {
				$this->add_field_group_to_schema( $field_group );
			}
		}
	}

	/**
	 * Given a Field Group, this applies it to the WPGraphQL Schema
	 *
	 * @param $field_group
	 *
	 * @return string The name of the ObjectType registered
	 */
	public function add_field_group_to_schema( $field_group ) {

		$graphql_types = $field_group['graphql_types'] ?? null;

		if ( empty( $graphql_types ) ) {
			return null;
		}

		$field_group_graphql_type_name     = isset( $field_group['graphql_field_name'] ) ? Utils::format_type_name( $field_group['graphql_field_name'] ) : $field_group['title'];
		$field_group_graphql_field_name    = Utils::format_field_name( $field_group_graphql_type_name );

		if ( $field_group['sub_fields'] ) {
			$field_group_graphql_type_name = Utils::format_type_name( $field_group['graphql_parent_type'] . $field_group_graphql_type_name );
		}

		$field_group['graphql_field_name'] = $field_group_graphql_field_name;
		$field_group['graphql_type_name']  = $field_group_graphql_type_name;

		// Prepare the fields for the field group
		$object_fields = $this->prepare_fields( $field_group );

		if ( empty( $object_fields ) ) {
			return null;
		}

		$interface_name = 'Has' . ucfirst( $field_group['graphql_field_name'] );

		// Register an Interface for the Field Group. This interface will be applied to
		// the types the ACF Field Group is assigned
		register_graphql_interface_type( $interface_name, [
			'description' => sprintf( __( 'Node that supports the ACF Field Group %s', 'wp-graphql-acf' ), $field_group['graphql_field_name'] ),
			'fields'      => [
				$field_group_graphql_field_name => [
					'type'        => $field_group_graphql_type_name,
					'description' => sprintf( __( 'The %s field group registered by ACF. %2$s', 'wp-graphql-acf' ), $field_group['title'], $field_group['description'] ),
					'resolve'     => function( $root ) use ( $field_group ) {
						return $root;
					}
				],
			],
		] );

		// Register an Object Type to represent the field group with the Field Groups fields
		register_graphql_object_type( $field_group_graphql_type_name, [
			'description' => sprintf( __( 'The %1$s field group registered by ACF. %2$s', 'wp-graphql-acf' ), $field_group['title'], $field_group['description'] ),
			'fields'      => array_merge( $object_fields, [
				'fieldGroupConfig' => [
					'type'        => 'AcfFieldGroupConfig',
					'description' => sprintf( __( 'The config for the %s ACF Field Group', 'wp-graphql-acf' ), $field_group_graphql_type_name ),
					'resolve'     => function() use ( $field_group ) {
						return $field_group;
					}
				],
			], $object_fields ),
		] );

		// Register the interfaces to the graphql types
		register_graphql_interfaces_to_types( [ $interface_name ], $field_group['graphql_types'] );

		return $field_group_graphql_type_name;

	}

	/**
	 * Get the value of an ACF Field
	 *
	 * @param mixed   $source    The source being resolved.
	 * @param array   $acf_field The ACF Field to resolve.
	 * @param boolean $format    Whether ACF should apply formatting to the field. Default false.
	 *
	 * @return mixed
	 */
	protected function get_acf_field_value( $source, $acf_field, $format = false ) {

		$value = null;
		$id    = null;

		if ( is_array( $source ) && ! ( ! empty( $source['type'] ) && 'options_page' === $source['type'] ) ) {

			if ( ! empty( $root[ $acf_field['key'] ] ) ) {
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
			 * @param int   $id   The ID of the object. Default null
			 * @param mixed $root The Root object being resolved. The ID is typically a property of this object.
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
		$fields = ! empty( $field_group['sub_fields'] ) ? $field_group['sub_fields'] : acf_get_fields( $field_group );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $prepared_fields;
		}

		foreach ( $fields as $field ) {

			// If the field is set to NOT show in GraphQL, leave it out.
			if ( false === (bool) $field['show_in_graphql'] ) {
				continue;
			}

			$field_name                  = ! empty( $field['graphql_field_name'] ) ? Utils::format_field_name( $field['graphql_field_name'] ) : Utils::format_field_name( $field['name'] );
			$field['graphql_field_name'] = $field_name;
			$prepared_field              = $this->prepare_field( $field, $field_group );

			/**
			 * Filter the prepared field.
			 *
			 * @param array             $field_config The config for the field to be registered
			 * @param array             $field        The ACF Field config
			 * @param array             $field_group  The ACF Field Group the field belongs to
			 * @param ACF_Type_Registry $this         The instance of the ACF_Type_Registry
			 */
			$prepared_field                 = apply_filters( 'graphql_acf_prepared_field', $prepared_field, $field, $field_group, $this );
			$prepared_fields[ $field_name ] = $prepared_field;
		}

		return $prepared_fields;

	}

	/**
	 * Given an acf_field config, prepare the field for the WPGraphQL Schema
	 *
	 * @param array $acf_field       The ACF Field to prepare
	 * @param array $acf_field_group The ACF Field Group the ACF Field belongs too
	 *
	 * @return array
	 */
	public function prepare_field( $acf_field, $acf_field_group ) {

		$prepared_field = [];

		$acf_field_type = ! empty( $acf_field['type'] ) ? $acf_field['type'] : null;
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
			case 'radio':
				break;
			case 'select':
				$prepared_field = $this->prepare_select_field( $acf_field, $acf_field_group );
				break;
			case 'range':
				$prepared_field['type'] = 'Integer';
				break;
			case 'number':
				$prepared_field['type'] = 'Float';
				break;
			case 'true_false':
				$prepared_field['type'] = 'Boolean';
				break;
			case 'textarea':
				$prepared_field = $this->prepare_textarea_field( $acf_field, $acf_field_group );
				break;
			case 'date_picker':
			case 'time_picker':
			case 'date_time_picker':
				$prepared_field = $this->prepare_date_field( $acf_field, $acf_field_group );
				break;
			case 'relationship':
				$prepared_field = $this->prepare_relationship_field( $acf_field, $acf_field_group );
				break;
			case 'page_link':
			case 'post_object':
				$prepared_field = $this->prepare_post_object_field( $acf_field, $acf_field_group );
				break;
			case 'link':
				$prepared_field = $this->prepare_link_field( $acf_field, $acf_field_group );
				break;
			case 'image':
			case 'file':
				$prepared_field = $this->prepare_media_field( $acf_field, $acf_field_group );
				break;
			case 'checkbox':
				$prepared_field = $this->prepare_checkbox_field( $acf_field, $acf_field_group );
				break;
			case 'gallery':
				$prepared_field = $this->prepare_gallery_field( $acf_field, $acf_field_group );
				break;
			case 'user':
				$prepared_field = $this->prepare_user_field( $acf_field, $acf_field_group );
				break;
			case 'taxonomy':
				$prepared_field = $this->prepare_taxonomy_field( $acf_field, $acf_field_group );
				break;
			// Accordions are not represented in the GraphQL Schema.
			case 'accordion':
				$field_config = null;
				break;
			case 'group':
				$this->prepare_group_field( $acf_field, $acf_field_group );
				break;
			default:
				break;
		}

		$default_field_config = [
			'type'        => 'String',
			'description' => sprintf( __( 'The "%1$s" field registered to the "%2$s" ACF Field Group. %3$s', 'wp-graphql' ), $acf_field['label'], $acf_field_group['title'], $acf_field['instructions'] ),
			'resolve'     => function( $root ) use ( $acf_field ) {
				return $this->get_acf_field_value( $root, $acf_field, false );
			}
		];

		$final = array_merge( $default_field_config, $prepared_field );

		// Return the prepared field
		return $final;

	}

	public function prepare_group_field( $acf_field, $acf_field_group ) {

		$acf_field['graphql_types'][] = $acf_field_group['graphql_type_name'];
		$acf_field['graphql_parent_type'] = $acf_field_group['graphql_type_name'];
		$object_type = $this->add_field_group_to_schema( $acf_field );
		return [ 'type' => $object_type ];

//		$field_type_name = $type_name . '_' . ucfirst( self::camel_case( $acf_field['name'] ) );
//		if ( $this->type_registry->get_type( $field_type_name ) ) {
//			$field_config['type'] = $field_type_name;
//			break;
//		}
//
//		register_graphql_object_type(
//			$field_type_name,
//			[
//				'description' => __( 'Field Group', 'wp-graphql-acf' ),
//				'fields'      => [
//					'fieldGroupName' => [
//						'type'    => 'String',
//						'resolve' => function( $source ) use ( $acf_field ) {
//							return ! empty( $acf_field['name'] ) ? $acf_field['name'] : null;
//						},
//					],
//				],
//			]
//		);

//		$field_config['type'] = $field_type_name;
	}

	/**
	 * Given a User ACF Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	public function prepare_taxonomy_field( $acf_field, $acf_field_group ) {
		$type = 'TermObjectUnion';

		if ( ! empty( $acf_field['taxonomy'] ) ) {
			$tax_object = get_taxonomy( $acf_field['taxonomy'] );
			if ( isset( $tax_object->graphql_single_name ) ) {
				$type = $tax_object->graphql_single_name;
			}
		}

		return [
			'type'    => [ 'list_of' => $type ],
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );
				$terms = [];
				if ( ! empty( $value ) && is_array( $value ) ) {
					foreach ( $value as $term ) {
						$terms[] = DataSource::resolve_term_object( (int) $term, $context );
					}
				}

				return $terms;
			},
		];
	}

	/**
	 * Given a User ACF Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	public function prepare_user_field( $acf_field, $acf_field_group ) {

		$type = 'User';

		if ( isset( $acf_field['multiple'] ) && 1 === $acf_field['multiple'] ) {
			$type = [ 'list_of' => $type ];
		}

		return [
			'type'    => $type,
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );

				$return = [];
				if ( ! empty( $value ) ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $id ) {
							$user = get_user_by( 'id', $id );
							if ( ! empty( $user ) ) {
								$user = new User( $user );
								if ( 'private' !== $user->get_visibility() ) {
									$return[] = $user;
								}
							}
						}
					} else {
						$user = get_user_by( 'id', absint( $value ) );
						if ( ! empty( $user ) ) {
							$user = new User( $user );
							if ( 'private' !== $user->get_visibility() ) {
								$return[] = $user;
							}
						}
					}
				}

				// If the field is allowed to be a multi select
				if ( 0 !== $acf_field['multiple'] ) {
					$return = ! empty( $return ) ? $return : null;
				} else {
					$return = ! empty( $return[0] ) ? $return[0] : null;
				}

				return $return;
			},
		];
	}

	/**
	 * Given a Gallery ACF Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_gallery_field( $acf_field, $acf_field_group ) {
		return [
			'type'    => [ 'list_of' => 'MediaItem' ],
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value   = $this->get_acf_field_value( $root, $acf_field );
				$gallery = [];
				if ( ! empty( $value ) && is_array( $value ) ) {
					foreach ( $value as $image ) {
						$post_object = get_post( (int) $image );
						if ( $post_object instanceof \WP_Post ) {
							$post_model = new Post( $post_object );
							$gallery[]  = $post_model;
						}
					}
				}

				return isset( $value ) ? $gallery : null;
			},
		];
	}

	/**
	 * Given a Checkbox ACF Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_checkbox_field( $acf_field, $acf_field_group ) {
		return [
			'type'    => [ 'list_of' => 'String' ],
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );

				return is_array( $value ) ? $value : null;
			},
		];
	}

	/**
	 * Given an ACF Field config that returns Media files, and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_media_field( $acf_field, $acf_field_group ) {
		return [
			'type'    => 'MediaItem',
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );

				return DataSource::resolve_post_object( (int) $value, $context );
			},
		];
	}

	/**
	 * Given an ACF Link Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_link_field( $acf_field, $acf_field_group ) {

		$prepared_field = [];

		$field_type_name = 'ACF_Link';
		if ( $this->type_registry->get_type( $field_type_name ) == $field_type_name ) {
			$prepared_field['type'] = $field_type_name;

			return $prepared_field;
		}

		register_graphql_object_type(
			$field_type_name,
			[
				'description' => __( 'ACF Link field', 'wp-graphql-acf' ),
				'fields'      => [
					'url'    => [
						'type'        => 'String',
						'description' => __( 'The url of the link', 'wp-graphql-acf' ),
					],
					'title'  => [
						'type'        => 'String',
						'description' => __( 'The title of the link', 'wp-graphql-acf' ),
					],
					'target' => [
						'type'        => 'String',
						'description' => __( 'The target of the link (_blank, etc)', 'wp-graphql-acf' ),
					],
				],
			]
		);
		$prepared_field['type'] = $field_type_name;

		return $prepared_field;
	}

	/**
	 * Given an ACF Post Object Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_post_object_field( $acf_field, $acf_field_group ) {

		$type           = 'PostObjectUnion';
		$prepared_field = [];

		if ( isset( $acf_field['post_type'] ) && is_array( $acf_field['post_type'] ) ) {

			$field_type_name = Utils::format_type_name( $acf_field_group['graphql_field_name'] . '_' . $acf_field['graphql_field_name'] );

			if ( $this->type_registry->get_type( $field_type_name ) == $field_type_name ) {
				$type = $field_type_name;
			} else {
				$type_names = [];
				foreach ( $acf_field['post_type'] as $post_type ) {
					if ( in_array( $post_type, \get_post_types( [ 'show_in_graphql' => true ] ), true ) ) {
						$type_names[ $post_type ] = get_post_type_object( $post_type )->graphql_single_name;
					}
				}

				if ( empty( $type_names ) ) {
					$prepared_field['type'] = null;

					return $prepared_field;
				}

				register_graphql_union_type( $field_type_name, [
					'typeNames'   => $type_names,
					'resolveType' => function( $value ) use ( $type_names ) {
						$post_type_object = get_post_type_object( $value->post_type );

						return ! empty( $post_type_object->graphql_single_name ) ? $this->type_registry->get_type( $post_type_object->graphql_single_name ) : null;
					}
				] );

				$type = $field_type_name;
			}
		}

		// If the field is allowed to be a multi select
		if ( 0 !== $acf_field['multiple'] ) {
			$type = [ 'list_of' => $type ];
		}

		$prepared_field = [
			'type'    => $type,
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );

				$return = [];
				if ( ! empty( $value ) ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $id ) {
							$post = get_post( $id );
							if ( ! empty( $post ) ) {
								$return[] = new Post( $post );
							}
						}
					} else {
						$post = get_post( absint( $value ) );
						if ( ! empty( $post ) ) {
							$return[] = new Post( $post );
						}
					}
				}

				// If the field is allowed to be a multi select
				if ( 0 !== $acf_field['multiple'] ) {
					$return = ! empty( $return ) ? $return : null;
				} else {
					$return = ! empty( $return[0] ) ? $return[0] : null;
				}

				/**
				 * This hooks allows for filtering of the post object source. In case an non-core defined
				 * post-type is being targeted.
				 *
				 * @param mixed|null  $source  GraphQL Type source.
				 * @param mixed|null  $value   Root ACF field value.
				 * @param AppContext  $context AppContext instance.
				 * @param ResolveInfo $info    ResolveInfo instance.
				 */
				return apply_filters(
					'graphql_acf_post_object_source',
					$return,
					$value,
					$context,
					$info
				);

			},
		];

		return $prepared_field;

	}

	/**
	 * Given an ACF Select Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_select_field( $acf_field, $acf_field_group ) {

		$prepared_field = [];

		/**
		 * If the select field is configured to not allow multiple values
		 * the field will return a string, but if it is configured to allow
		 * multiple values it will return a list of strings, and an empty array
		 * if no values are set.
		 *
		 * @see: https://github.com/wp-graphql/wp-graphql-acf/issues/25
		 */
		if ( empty( $acf_field['multiple'] ) ) {
			$prepared_field['type'] = 'String';
		} else {
			$prepared_field['type']    = [ 'list_of' => 'String' ];
			$prepared_field['resolve'] = function( $root ) use ( $acf_field ) {
				$value = $this->get_acf_field_value( $root, $acf_field );

				return ! empty( $value ) && is_array( $value ) ? $value : [];
			};
		}

		return $prepared_field;
	}

	/**
	 * Given an ACF Date / Time / DateTime Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_date_field( $acf_field, $acf_field_group ) {
		return [
			'type'    => 'String',
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {

				$value = $this->get_acf_field_value( $root, $acf_field, true );

				if ( ! empty( $value ) && ! empty( $acf_field['return_format'] ) ) {
					$value = date( $acf_field['return_format'], strtotime( $value ) );
				}

				return ! empty( $value ) ? $value : null;
			},
		];
	}

	/**
	 * Given an ACF Text Area Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_textarea_field( $acf_field, $acf_field_group ) {
		return [
			'type'    => 'String',
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
	}

	/**
	 * Given an ACF Relationship Field config and its field group, this returns
	 * a prepared field for use in the WPGraphQL Schema
	 *
	 * @param array $acf_field       ACF Field Config
	 * @param array $acf_field_group ACF Field Group Config
	 *
	 * @return array
	 */
	protected function prepare_relationship_field( $acf_field, $acf_field_group ) {

		$type = 'PostObjectUnion';

		if ( isset( $acf_field['post_type'] ) && is_array( $acf_field['post_type'] ) ) {

			$field_type_name = Utils::format_type_name( $acf_field_group['graphql_field_name'] . '_' . $acf_field['graphql_field_name'] );

			if ( $this->type_registry->get_type( $field_type_name ) == $field_type_name ) {
				$type = $field_type_name;
			} else {
				$type_names = [];
				foreach ( $acf_field['post_type'] as $post_type ) {
					if ( in_array( $post_type, get_post_types( [ 'show_in_graphql' => true ] ), true ) ) {
						$type_names[ $post_type ] = get_post_type_object( $post_type )->graphql_single_name;
					}
				}

				if ( empty( $type_names ) ) {
					$type = 'PostObjectUnion';
				} else {
					register_graphql_union_type( $field_type_name, [
						'typeNames'   => $type_names,
						'resolveType' => function( $value ) use ( $type_names ) {
							$post_type_object = get_post_type_object( $value->post_type );

							return ! empty( $post_type_object->graphql_single_name ) ? $this->type_registry->get_type( $post_type_object->graphql_single_name ) : null;
						}
					] );

					$type = $field_type_name;
				}
			}
		}

		return [
			'type'    => [ 'list_of' => $type ],
			'resolve' => function( $root, $args, $context, $info ) use ( $acf_field ) {
				$relationship = [];
				$value        = $this->get_acf_field_value( $root, $acf_field );

				if ( ! empty( $value ) && is_array( $value ) ) {
					foreach ( $value as $post_id ) {
						$post_object = get_post( $post_id );
						if ( $post_object instanceof \WP_Post ) {
							$post_model     = new Post( $post_object );
							$relationship[] = $post_model;
						}
					}
				}

				return isset( $value ) ? $relationship : null;

			},
		];

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
		if ( ! empty( $this->acf_field_groups ) && is_array( $this->acf_field_groups ) ) {
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
