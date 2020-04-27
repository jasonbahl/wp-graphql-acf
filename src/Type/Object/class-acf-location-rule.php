<?php
namespace WPGraphQL\ACF\Type\Object;

/**
 * Class ACF_Location_Rule
 *
 * @package WPGraphQL\ACF\Type\Object
 */
class ACF_Location_Rule {

	/**
	 * Register the AcfLocationRule Type
	 */
	public static function register_type() {

		register_graphql_object_type( 'AcfLocationRule', [
			'fields' => [
				'param' => [
					'type' => 'String',
					'description' => __( 'The Location Rule paramater to evaluate with the value', 'wp-graphql-acf' ),
				],
				'operator' => [
					'type' => 'String',
					'description' => __( 'The Location Rule operator to evaluate the paramater with the value', 'wp-graphql-acf' ),
				],
				'value' => [
					'type' => 'String',
					'description' => __( 'The Location Rule value to evaluate with the paramater', 'wp-graphql-acf' ),
				],
			]
		]);

	}
}
