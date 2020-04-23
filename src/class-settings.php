<?php

namespace WPGraphQL\ACF;

/**
 * Class Settings
 *
 * @package WPGraphQL\ACF
 */
class Settings {

	/**
	 * Initialize ACF Settings
	 */
	public function init() {

		/**
		 * Add settings to ACF field groups to allow each field granular control
		 * over how it's shown in the GraphQL Schema
		 */
		add_action( 'acf/render_field_group_settings', [
			$this,
			'add_field_group_settings'
		], 10, 1 );

		/**
		 * Add settings to individual fields to allow each field granular control
		 * over how it's shown in the GraphQL Schema
		 */
		add_action( 'acf/render_field_settings', [
			$this,
			'add_field_settings'
		], 10, 1 );

	}

	/**
	 * Adds settings fields to ACF Fields to provide more granular control over how the fields are
	 * exposed to the Schema.
	 *
	 * @param $field
	 */
	public function add_field_settings( $field ) {

		/**
		 * Render the "show_in_graphql" setting for the field.
		 */
		acf_render_field_setting(
			$field,
			[
				'label'         => __( 'GraphQL: Show in Schema', 'wp-graphql-acf' ),
				'instructions'  => __( 'Whether the field should be queryable via GraphQL. If set to false, the field will not be exposed to the WPGraphQL Schema.', 'wp-graphql-acf' ),
				'name'          => 'show_in_graphql',
				'type'          => 'true_false',
				'ui'            => 1,
				'default_value' => 1,
				'value'         => isset( $field['show_in_graphql'] ) ? (bool) $field['show_in_graphql'] : true,
			],
			true
		);

		/**
		 * Render the "show_in_graphql" setting for the field.
		 */
		acf_render_field_setting(
			$field,
			[
				'label'         => __( 'GraphQL: Field Name', 'wp-graphql-acf' ),
				'instructions'  => __( 'The name of the field in the WPGraphQL Schema. Snake case recommended. ex: yourFieldName', 'wp-graphql-acf' ),
				'name'          => 'graphql_field_name',
				'type'          => 'text',
				'ui'            => true,
				'default_value' => '',
				'required'      => false,
				'value'         => isset( $field['graphql_field_name'] ) ? $field['graphql_field_name'] : '',
			],
			true
		);

	}

	/**
	 * This adds a setting to the ACF Field groups to activate a field group in GraphQL.
	 *
	 * If a field group is set to active and is set to "show_in_graphql", the fields in the field
	 * group will be exposed to the GraphQL Schema based on the matching location rules.
	 *
	 * @param array $field_group The field group to add settings to.
	 */
	public function add_field_group_settings( $field_group ) {

		/**
		 * Render a field in the Field Group settings to allow for a Field Group to be shown in GraphQL.
		 */
		acf_render_field_wrap(
			[
				'label'        => __( 'GraphQL: Show in Schema', 'acf' ),
				'instructions' => __( 'If the field group is active, and this is set to show, the fields in this group will be available in the WPGraphQL Schema based on the respective Location rules.' ),
				'type'         => 'true_false',
				'name'         => 'show_in_graphql',
				'prefix'       => 'acf_field_group',
				'value'        => isset( $field_group['show_in_graphql'] ) ? (bool) $field_group['show_in_graphql'] : false,
				'ui'           => true,
			]
		);

		/**
		 * Render a field in the Field Group settings to allow for a Field Group to be shown in GraphQL.
		 */
		acf_render_field_wrap(
			[
				'label'        => __( 'GraphQL: Field Name', 'acf' ),
				'instructions' => __( 'The name of the field group in the GraphQL Schema.', 'wp-graphql-acf' ),
				'type'         => 'text',
				'prefix'       => 'acf_field_group',
				'name'         => 'graphql_field_name',
				'placeholder'  => ! empty( $field_group['graphql_field_name'] ) ? $field_group['graphql_field_name'] : null,
				'value'        => ! empty( $field_group['graphql_field_name'] ) ? $field_group['graphql_field_name'] : null,
			]
		);

		$choices = [
			'User'     => 'User',
			'MenuItem' => 'MenuItem',
			'Menu'     => 'Menu',
			'Comment'  => 'Comment',
		];

		$post_types = get_post_types( [ 'show_in_graphql' => true ], 'objects' );

		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			foreach ( $post_types as $post_type ) {
				$name = isset( $post_type->graphql_single_name ) ? ucfirst( $post_type->graphql_single_name ) : null;
				if ( $name ) {
					$choices[ $name ] = $name . ' (Post Type)';
				}
			}
		}

		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ], 'objects' );

		if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$name = isset( $taxonomy->graphql_single_name ) ? ucfirst( $taxonomy->graphql_single_name ) : null;
				if ( $name ) {
					$choices[ $name ] = $name . ' (Taxonomy)';
				}
			}
		}

		global $acf_options_page;

		if ( ! isset( $acf_options_page ) ) {
			return;
		}

		/**
		 * Get a list of post types that have been registered to show in graphql
		 */
		$graphql_options_pages = ACF::get_graphql_options_pages();

		if ( ! empty( $graphql_options_pages ) && is_array( $graphql_options_pages ) ) {
			foreach ( $graphql_options_pages as $key => $value ) {
				$name = ucfirst( $key );
				if ( $name ) {
					$choices[ $name ] = ucfirst( $name ) . ' (ACF Options Page)';
				}
			}
		}

		$registered_page_templates = wp_get_theme()->get_post_templates();

		if ( ! empty( $registered_page_templates ) && is_array( $registered_page_templates ) ) {

			$page_templates['default'] = 'Default';
			foreach ( $registered_page_templates as $post_type => $post_type_templates ) {
				foreach ( $post_type_templates as $file => $name ) {
					$name                           = ucwords( $name );
					$name                           = preg_replace( '/[^\w]/', '', $name );
					$template_type_name             = $name . 'Template';
					$choices[ $template_type_name ] = $template_type_name . ' (' . ucfirst( $post_type ) . ' Template)';
				}
			}
		}

		asort( $choices );

		acf_render_field_wrap( [
			'label'        => __( 'GraphQL: Assign Field Group to Types', 'wp-graphql-acf' ),
			'instructions' => __( 'Select the Types in the WPGraphQL Schema to expose the ACF Field Group on.', 'wp-graphql' ),
			'type'         => 'checkbox',
			'choices'      => $choices,
			'prefix'       => 'acf_field_group',
			'name'         => 'graphql_types',
			'required'     => false,
			'placeholder'  => ! empty( $field_group['graphql_types'] ) ? $field_group['graphql_types'] : null,
			'value'        => ! empty( $field_group['graphql_types'] ) ? $field_group['graphql_types'] : null,
		] );

	}

}
