<?php
namespace WPGraphQL\ACF\Type\Object;

class ACF_Location_Rule {

	public static function register_type() {

		register_graphql_object_type( 'ACF_Location_Rule', [
			'fields' => [
				'param' => [
					'type' => 'String',
				],
				'operator' => [
					'type' => 'String',
				],
				'value' => [
					'type' => 'String',
				],
			]
		]);

	}
}
