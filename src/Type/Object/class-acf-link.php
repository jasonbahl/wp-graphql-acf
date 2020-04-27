<?php
namespace WPGraphQL\ACF\Type\Object;

/**
 * Class ACF_Link
 *
 * @package WPGraphQL\ACF\Type\Object
 */
class ACF_Link {

	/**
	 * Register the AcfLink Type
	 */
	public static function register_type() {

		register_graphql_object_type( 'AcfLink', [
			'description' => __( 'A link to another entity.', 'wp-graphql-acf' ),
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
		] );

	}
}
