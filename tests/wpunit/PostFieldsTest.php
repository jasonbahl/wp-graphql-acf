<?php
class PostFieldsTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;
	public $post_id;

	public function setUp() {
		parent::setUp();
		WPGraphQL::clear_schema();
		$this->group_key = __CLASS__;

		$this->register_field_group();

		$this->post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_content' => 'test',
		]);
	}

	public function tearDown() {
		parent::tearDown();
	}

	public function register_field_group( $config = [] ) {

		$defaults = [
			'key'                   => $this->group_key,
			'title'                 => 'Post Object Fields',
			'fields'                => [],
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					],
				],
			],
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_graphql'       => 1,
			'graphql_field_name'    => 'postFields',
			'graphql_types' => [
				'Post',
			]
		];

		acf_add_local_field_group( array_merge( $defaults, $config ));

	}

	public function register_acf_field( $config = [] ) {

		$defaults = [
			'parent'            => $this->group_key,
			'key'               => 'field_5d7812fd000a4',
			'label'             => 'Text',
			'name'              => 'text',
			'type'              => 'text',
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
			'show_in_graphql'   => 1,
			'default_value'     => '',
			'placeholder'       => '',
			'prepend'           => '',
			'append'            => '',
			'maxlength'         => '',
		];

		acf_add_local_field( array_merge( $defaults, $config ) );
	}

	/**
	 * @throws Exception
	 */
	public function testBasicQuery() {
		$query = '{ posts { nodes { id } } }';
		$actual = graphql(['query' => $query ]);
		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * @throws Exception
	 */
	public function testTextFieldOnPost() {
		WPGraphQL::clear_schema();
		$this->register_acf_field([
			'name'              => 'text_field',
			'type'              => 'text',
		]);

		$expected_text_1 = 'Some Text';

		update_field( 'text_field', $expected_text_1, $this->post_id );

		$query = '
		query getPostById( $postId: Int ) {
			postBy( postId: $postId ) {
				id
				postFields {
					textField
				}
			}
		}
		';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_text_1, $actual['data']['postBy']['postFields']['textField'] );
	}

}
