<?php
namespace WPGraphQL\ACF\Type\Object;

use GraphQLRelay\Relay;

/**
 * Class ACF_Field_Group_Config
 *
 * @package WPGraphQL\ACF\Type\Object
 */
class ACF_Field_Group_Config {

	/**
	 * Register the AcfFieldGroupConfig Type
	 */
	public static function register_type() {

		register_graphql_object_type( 'AcfFieldGroupConfig', [
			'description' => __( 'Configuration fields for ACF Field Groups', 'wp-graphql-acf' ),
			'interfaces' => [ 'Node' ],
			'fields' => [
				'id' => [
					'type' => 'ID',
					'description' => __( 'Unique Identifier for the ACF Field Group', 'wp-graphql' ),
					'resolve' => function( $field_group ) {
						return isset( $field_group['ID'] ) ? Relay::toGlobalId( 'fieldGroup', absint( $field_group['ID'] ) ) : null;
					},
				],
				'databaseId' => [
					'type' => 'Int',
					'description' => __( 'The database ID for the ACF Field Group', 'wp-graphql-acf' ),
					'resolve' => function( $field_group ) {
						return isset( $field_group['ID'] ) ? absint( $field_group['ID'] ) : null;
					}
				],
				'key' => [
					'type' => 'ID',
					'description' => __( 'A unique key used by ACF to identify field groups', 'wp-graphql-acf' ),
				],
				'title' => [
					'type' => 'String',
					'description' => __( 'The title of the ACF Field Group', 'wp-graphql-acf' ),
					'resolve' => function( $root ) {
						return $root['title'];
					}
				],
				'fields' => [
					'type' => [ 'list_of' => 'String' ],
					'description' => __( 'The fields configured within the ACF Field Group', 'wp-graphql-acf' ),
				],
				'locationRules' => [
					'type' => [ 'list_of' => 'AcfLocationRule' ],
					'description' => __( 'The location rules configured for the ACF Field Group', 'wp-graphql-acf' ),
					'resolve' => function( $config ) {
						return ! empty( $config['location'][0] ) ? $config['location'][0] : null;
					},
				],
				'menuOrder' => [
					'type' => 'Int',
					'description' => __( 'The menu order assigned to the ACF Field Group', 'wp-graphql-acf' ),
					'resolve' => function( $config ) {
						return ! empty( $config['menu_order'] ) ? absint( $config['menu_order'] ) : null;
					}
				],
				'position' => [
					'type' => 'String', // Enum?
					'description' => __( 'The position of the ACF Field Group on the Admin screen', 'wp-graphql-acf' ),
				],
				'style' => [
					'type' => 'String', // Enum?
					'description' => __( 'The style to display the ACF Field Group with in the Admin', 'wp-graphql-acf' ),
				],
				'labelPlacement' => [
					'type' => 'String',
					'description' => __( 'The label placement for the ACF FIeld Group', 'wp-graphql-acf' ),
					'resolve' => function( $config ) {
						return ! empty( $config['label_placement'] ) ? $config['label_placement'] : null;
					}
				],
				'instructionPlacement' => [
					'type' => 'String',
					'description' => __( 'The instruction placement for the ACF Field Group', 'wp-graphql-acf' ),
					'resolve' => function( $config ) {
						return ! empty( $config['instruction_placement'] ) ? $config['instruction_placement'] : null;
					}
				],
				'shouldHideOnScreen' => [
					'type' => 'Boolean',
					'description' => __( 'Whether to hide the ACF Field Group on the Admin screen', 'wp-graphql-acf' ),
					'resolve' => function( $config ) {
						return isset( $config['hide_on_screen'] ) ? (bool) $config['instruction_placement'] : null;
					}
				],
				'active' => [
					'type' => 'Boolean',
					'description' => __( 'Whether the field group is active', 'wp-graphql-acf' ),
				],
				'description' => [
					'type' => 'String',
					'description' => __( 'Description of intended use for the ACF Field Group', 'wp-graphql-acf' ),
				],
				'showInGraphQL' => [
					'type' => 'Boolean',
					'description' => __( 'Whether to show the ACF Field Group in the GraphQL Schema', 'wp-graphql' ),
					'resolve' => function( $config ) {
						return isset( $config['show_in_graphql'] ) ? (bool) $config['show_in_graphql'] : null;
					}
				],
				'graphqlFieldName' => [
					'type' => 'String',
					'description' => __( 'The name to use to refer to the ACF Field Group in the GraphQL Schema', 'wp-graphql' ),
					'resolve' => function( $config ) {
						return isset( $config['graphql_field_name'] ) ? (bool) $config['graphql_field_name'] : null;
					}
				],
				'graphqlTypeNames' => [
					'type' => [
						'list_of' => 'String',
					],
					'description' => __( 'The name of the GraphQL Types the field group should show on in the GraphQL Schema', 'wp-graphql' ),
					'resolve' => function( $field_group ) {
						return $field_group['graphql_types'] ?? null;
					}
				],
			]
		]);

	}

}
