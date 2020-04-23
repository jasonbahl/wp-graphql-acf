<?php

namespace WPGraphQL\ACF\Type\Object;

use WPGraphQL\ACF\ACF_Type_Registry;
use WPGraphQL\ACF\Registry\FieldRegistry\ACF_Field_Registry;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class ACF_Field_Groups
 *
 * @package WPGraphQL\ACF\Type\Object
 */
class ACF_Field_Groups {

	/**
	 * @param TypeRegistry      $type_registry     The WPGraphQL Type Registry
	 * @param ACF_Type_Registry $acf_type_registry The WPGraphQL for ACF Type Registry
	 */
	public static function register_types( TypeRegistry $type_registry, ACF_Type_Registry $acf_type_registry ) {

		if ( empty( $acf_type_registry->graphql_field_groups ) || ! is_array( $acf_type_registry->graphql_field_groups ) ) {
			return;
		}

		foreach ( $acf_type_registry->graphql_field_groups as $field_group ) {
			if ( ! empty( $field_group['graphql_types'] ) && ! empty( $field_group['graphql_field_name'] ) ) {
				foreach ( $field_group['graphql_types'] as $graphql_type ) {

					$field_group_type_name           = isset( $field_group['graphql_field_name'] ) ?? $field_group['graphql_field_name'];
					$field_group_description_context = sprintf( __( 'The "%s" ACF Field Group.', 'wp-graphql-acf' ), $field_group['title'] );
					$field_group_description         = ! empty( $field_group['description'] ) ? $field_group_description_context . ' ' . $field_group['description'] : $field_group_description_context;

					$field_registry = new ACF_Field_Registry( $field_group );
					$field_registry->register_fields();

					/**
					 * Register the Field Group Object type
					 */
					register_graphql_object_type( $field_group_type_name, [
						'description' => $field_group_description,
						'fields'      => [
							'config' => [
								'type'        => 'ACF_Field_Group_Config',
								'description' => __( 'Configuration details for the ACF Field Group', 'wp-graphql-acf' ),
								'resolve'     => function( $field_group ) {
									$can_admin = function_exists( 'acf_current_user_can_admin' ) && true === acf_current_user_can_admin() ? true : false;

									return $can_admin ? $field_group : null;
								}
							],
						]
					] );

					/**
					 * Register the field that exposes the Field Group Object Type
					 */
//					register_graphql_field( $graphql_type, lcfirst( $field_group['graphql_field_name'] ), [
//						'type'        => $field_group_type_name,
//						'description' => $field_group_description,
//						'resolve'     => function() use ( $field_group ) {
//							return $field_group;
//						}
//					] );

					register_graphql_interface_type( $field_group['graphql_field_name'], [
						'fields' => [
							lcfirst( $field_group['graphql_field_name'] ) => [
								'type'        => $field_group_type_name,
								'description' => $field_group_description,
								'resolve'     => function() use ( $field_group ) {
									return $field_group;
								}
							],
						],
					]);

				}
			}
		}

	}

}
