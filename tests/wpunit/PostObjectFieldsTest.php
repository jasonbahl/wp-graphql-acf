<?php
class PostObjectFieldsTest extends \Codeception\TestCase\WPTestCase {

	public $group_key;
	public $post_id;
	public $test_image;

	public function setUp(): void {


		parent::setUp(); // TODO: Change the autogenerated stub

		$this->group_key = __CLASS__;
		WPGraphQL::clear_schema();
		$this->register_acf_field_group();

		$this->post_id = $this->factory()->post->create([
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_title' => 'Test',
			'post_content' => 'test',
		]);

		$this->test_image = dirname( __FILE__, 2 ) . '/_data/images/test.png';



	}
	public function tearDown(): void {

		$this->deregister_acf_field_group();
		WPGraphQL::clear_schema();
		wp_delete_post( $this->post_id, true );
		parent::tearDown(); // TODO: Change the autogenerated stub
	}

	public function deregister_acf_field_group() {
		acf_remove_local_field_group( $this->group_key );
	}

	public function register_acf_field_group( $config = [] ) {

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
			'graphql_types'		    => ['Post']
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
	public function testAcfTextField() {

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
					fieldGroupName
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

	/**
	 * @throws Exception
	 */
	public function testQuerytTextAreaField() {

		$this->register_acf_field([
			'type' => 'textarea',
			'name' => 'text_area_field'
		]);

		$expexted = 'expected test value';

		update_field( 'text_area_field', $expexted, $this->post_id );

		$query = '
		query getPostById( $postId: Int ) {
			postBy( postId: $postId ) {
				id
				postFields {
					fieldGroupName
					textAreaField
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
		$this->assertSame( $expexted, $actual['data']['postBy']['postFields']['textAreaField'] );

	}

	/**
	 * Test querying a number field
	 *
	 * @throws Exception
	 */
	public function testQueryNumberField() {

		$this->register_acf_field([
			'type' => 'number',
			'name' => 'number_field'
		]);

		$expected = absint(55 );
		update_field( 'number_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      numberField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( (float) $expected, $actual['data']['postBy']['postFields']['numberField'] );

	}

	/**
	 * Test querying a range field
	 *
	 * @throws Exception
	 */
	public function testQueryRangeField() {

		$this->register_acf_field([
			'type' => 'range',
			'name' => 'range_field',
		]);

		$expected = floatval(66 );
		update_field( 'range_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      rangeField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['rangeField'] );

	}

	/**
	 * Test querying a email field
	 *
	 * @throws Exception
	 */
	public function testQueryEmailField() {

		$this->register_acf_field([
			'type' => 'email',
			'name' => 'email_field'
		]);

		$expected = 'test@test.com';
		update_field( 'email_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      emailField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['emailField'] );

	}

	/**
	 * Test querying a url field
	 *
	 * @throws Exception
	 */
	public function testQueryUrlField() {

		$this->register_acf_field([
			'type' => 'url',
			'name' => 'url_field',
		]);

		$expected = 'https://site.com';
		update_field( 'url_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      urlField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['urlField'] );

	}

	/**
	 * Test querying a password field
	 *
	 * @throws Exception
	 */
	public function testQueryPasswordField() {

		$this->register_acf_field([
			'type' => 'password',
			'name' => 'password_field',
		]);

		$expected = 'aserw3fgwv5467#$%$%^$';
		update_field( 'password_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      passwordField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['passwordField'] );

	}

	/**
	 * Test querying a image field
	 *
	 * @throws Exception
	 */
	public function testQueryImageField() {

		$this->register_acf_field([
			'type' => 'image',
			'name' => 'image_field',
		]);

		$filename      = ( $this->test_image );
		$img_id = $this->factory()->attachment->create_upload_object( $filename );
		update_field( 'image_field', $img_id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      imageField {
		        node {
			      mediaItemId
			      thumbnail: sourceUrl(size: THUMBNAIL)
				  medium: sourceUrl(size: MEDIUM)
				  full: sourceUrl(size: LARGE)
				  sourceUrl
				}
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( [
			'mediaItemId' => $img_id,
			'thumbnail' => wp_get_attachment_image_src( $img_id, 'thumbnail' )[0],
			'medium' => wp_get_attachment_image_src( $img_id, 'medium' )[0],
			'full' => wp_get_attachment_image_src( $img_id, 'full' )[0],
			'sourceUrl' => wp_get_attachment_image_src( $img_id, 'full' )[0]
		], $actual['data']['postBy']['postFields']['imageField']['node'] );

	}

	/**
	 * Test querying a File field
	 *
	 * @throws Exception
	 */
	public function testQueryFileField() {

		$this->register_acf_field([
			'type' => 'file',
			'name' => 'file_field',
		]);

		$filename      = ( $this->test_image );
		$img_id = $this->factory()->attachment->create_upload_object( $filename );
		update_field( 'file_field', $img_id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      fileField {
		        node {
			      mediaItemId
				  sourceUrl
				}
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( [
			'mediaItemId' => $img_id,
			'sourceUrl' => wp_get_attachment_image_src( $img_id, 'full' )[0]
		], $actual['data']['postBy']['postFields']['fileField']['node'] );

	}

	/**
	 * Test querying a Wysiwyg field
	 *
	 * @throws Exception
	 */
	public function testQueryWysiwygField() {

		$this->register_acf_field([
			'type' => 'wysiwyg',
			'name' => 'wysiwyg_field',
		]);

		$text = 'some text';
		update_field( 'wysiwyg_field', $text, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      wysiwygField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		$expected = get_field( 'wysiwyg_field', $this->post_id, true );

		codecept_debug( $actual );
		codecept_debug( $expected );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['wysiwygField'] );


	}

	/**
	 * Test querying a Wysiwyg field
	 *
	 * @throws Exception
	 */
	public function testQueryOembedField() {

		$this->register_acf_field([
			'type' => 'oembed',
			'name' => 'oembed_field',
		]);

		$expected = 'https://twitter.com/wpgraphql/status/1115652591705190400';
		update_field( 'oembed_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      oembedField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['oembedField'] );

	}

	/**
	 * Test querying a Wysiwyg field
	 *
	 * @throws Exception
	 */
	public function testQueryGalleryField() {

		$this->register_acf_field([
			'type' => 'gallery',
			'name' => 'gallery_field',
		]);

		/**
		 * Save Image IDs as the value for the gallery field
		 */
		$filename      = ( $this->test_image );
		$img_id_1 = $this->factory()->attachment->create_upload_object( $filename );
		$img_id_2 = $this->factory()->attachment->create_upload_object( $filename );
		$img_ids = [ $img_id_1, $img_id_2 ];
		update_field( 'gallery_field', $img_ids, $this->post_id );

		/**
		 * Query for the gallery
		 */
		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      galleryField {
		        nodes {
		          mediaItemId
		          sourceUrl
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			[
				'mediaItemId' => $img_id_1,
				'sourceUrl' => wp_get_attachment_image_src( $img_id_1, 'full' )[0]
			],
			[
				'mediaItemId' => $img_id_2,
				'sourceUrl' => wp_get_attachment_image_src( $img_id_2, 'full' )[0]
			],
		], $actual['data']['postBy']['postFields']['galleryField']['nodes'] );

		$img_ids = [ $img_id_2, $img_id_1 ];
		update_field( 'gallery_field', $img_ids, $this->post_id );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			[
				'mediaItemId' => $img_id_2,
				'sourceUrl' => wp_get_attachment_image_src( $img_id_2, 'full' )[0]
			],
			[
				'mediaItemId' => $img_id_1,
				'sourceUrl' => wp_get_attachment_image_src( $img_id_1, 'full' )[0]
			],
		], $actual['data']['postBy']['postFields']['galleryField']['nodes'] );


	}

	/**
	 * Test querying a Select field
	 *
	 * @throws Exception
	 */
	public function testQuerySelectField() {

		$this->register_acf_field([
			'type' => 'select',
			'name' => 'select_field',
		]);

		$expected = 'one';
		update_field( 'select_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      selectField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['selectField'] );

	}

	/**
	 * Test querying a Checkbox field
	 *
	 * @throws Exception
	 */
	public function testQueryCheckboxField() {

		$this->register_acf_field([
			'type' => 'checkbox',
			'name' => 'checkbox_field'
		]);

		$expected = ['one'];
		update_field( 'checkbox_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      checkboxField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['checkboxField'] );

	}

	/**
	 * Test querying a Radio field
	 *
	 * @throws Exception
	 */
	public function testQueryRadioButtonField() {

		$this->register_acf_field([
			'type' => 'radio',
			'name' => 'radio_field',
		]);

		$expected = 'two';
		update_field( 'radio_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      radioField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['radioField'] );

	}

	/**
	 * Test querying a Button Group field
	 *
	 * @throws Exception
	 */
	public function testQueryButtonGroupField() {

		$this->register_acf_field([
			'type' => 'button_group',
			'name' => 'button_group_field',
		]);

		$expected = 'one';
		update_field( 'button_group_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      buttonGroupField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['buttonGroupField'] );

	}

	/**
	 * Test querying a True/False field
	 *
	 * @throws Exception
	 */
	public function testQueryTrueFalseField() {

		$this->register_acf_field([
			'type' => 'true_false',
			'name' => 'true_false_field',
		]);

		$expected = true;
		update_field( 'true_false_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      trueFalseField
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['trueFalseField'] );

	}

	/**
	 * Test querying a Link field
	 *
	 * @throws Exception
	 */
	public function testQueryLinkField() {

		$this->register_acf_field([
			'type' => 'link',
			'name' => 'link_field',
		]);

		$expected = [
			'title' => 'Some Link',
			'url' => 'https://github.com/wp-graphql/wp-graphql',
			'target' => '_blank'
		];

		update_field( 'link_field', $expected, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      linkField {
		        title
		        url
		        target
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['postBy']['postFields']['linkField'] );

	}

	/**
	 * Test querying a Post Object field
	 *
	 * @throws Exception
	 */
	public function testQueryPostObjectField() {

		$this->register_acf_field([
			'type' => 'post_object',
			'name' => 'post_object_field',
			'post_type'          => [
				'post',
				'page'
			],
			'taxonomy'           => '',
		]);

		$post_id = $this->post_id;

		update_field( 'post_object_field', $post_id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      postObjectField {
		        node {
		          __typename
		          ...on Post {
		            postId
		          }
		          ...on Page {
		            pageId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			'__typename' => 'Post',
			'postId' => $post_id,
		], $actual['data']['postBy']['postFields']['postObjectField']['node'] );

	}

	/**
	 * Test querying a Post Object field
	 *
	 * @throws Exception
	 */
	public function testQueryPostObjectFieldWithPage() {

		$page_id = $this->factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Test Page',
		]);

		update_field( 'post_object_field', $page_id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      postObjectField {
		        node {
		          __typename
		          ...on Post {
		            postId
		          }
		          ...on Page {
		            pageId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			'__typename' => 'Page',
			'pageId' => $page_id,
		], $actual['data']['postBy']['postFields']['postObjectField']['node'] );

	}

	/**
	 * Test querying a Page Link field
	 *
	 * @throws Exception
	 */
	public function testQueryPageLinkField() {

		$this->register_acf_field([
			'type' => 'page_link',
			'name' => 'page_link_field',
			'post_type'         => [
				'post',
			],
		]);

		$id = $this->post_id;

		update_field( 'page_link_field', $id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      pageLinkField {
		        node {
		          __typename
		          ...on Post {
		            postId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			'__typename' => 'Post',
			'postId' => $id,
		], $actual['data']['postBy']['postFields']['pageLinkField']['node'] );

	}

	/**
	 * Test querying a Page Link field
	 *
	 * @throws Exception
	 */
	public function testQueryPageLinkFieldWithError() {

		$this->register_acf_field([
			'name' => 'page_link_field',
			'type' => 'page_link',
			'post_type'         => [
				'post',
			],
		]);

		$id = $this->post_id;

		update_field( 'page_link_field', $id, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      pageLinkField {
		        __typename
		        ...on Post {
		          postId
		        }
		        ...on Page {
		          pageId
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		/**
		 * Since the page_link_field is configured with just "post_type => [ 'post' ]",
		 * the union to return is just the "Post" type, so querying for
		 * a Page should throw an error here.
		 *
		 * Should see an error such as:
		 *
		 * Fragment cannot be spread here as objects of type "Post_PostFields_PageLinkField" can never be of type "Page".
		 */
		$this->assertArrayHasKey( 'errors', $actual );

	}

	/**
	 * Test query the select field with multiple values selected
	 *
	 * @throws Exception
	 */
	public function testQueryMultipleSelectField() {

		$this->register_acf_field([
			'name' => 'select_field_multiple',
			'type' => 'select',
			'show_in_graphql'    => 1,
			'graphql_field_name' => 'selectMultiple',
			'choices'            => [
				'one' => 'One',
				'two' => 'Two',
			],
			'default_value'      => [],
			'allow_null'         => 0,
			'multiple'           => 1,
		]);

		$expected_value = [ 'one', 'two' ];
		update_field( 'select_field_multiple', $expected_value, $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      selectMultiple
		    }
		  }
		}';



		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected_value, $actual['data']['postBy']['postFields']['selectMultiple'] );


	}

	/**
	 * Test query the select field with no values set
	 *
	 * @throws Exception
	 */
	public function testQueryMultipleSelectFieldWithNoValueSet() {

		$this->register_acf_field([
			'name' => 'select_field_multiple',
			'type' => 'select',
			'show_in_graphql'    => true,
			'graphql_field_name' => 'selectMultiple',
			'choices'            => [
				'one' => 'One',
				'two' => 'Two',
			],
			'default_value'      => [],
			'allow_null'         => 0,
			'multiple'           => 1,
		]);

		delete_post_meta( $this->post_id, 'select_field_multiple' );

		codecept_debug( 'goo' );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      selectMultiple
		    }
		  }
		}';



		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertSame( null, $actual['data']['postBy']['postFields']['selectMultiple'] );

	}

	/**
	 * Test field on Custom Post Type
	 * @throws Exception
	 */
	public function testQueryFieldOnCustomPostType() {

		register_post_type( 'acf_test', [
			'show_ui' => true,
			'show_in_graphql' => 'true',
			'graphql_single_name' => 'acfTest',
			'graphql_plural_name' => 'acfTests'
		] );

		$group_key = uniqid();

		$this->register_acf_field_group([
			'key' =>  $group_key . 'acf_test_group',
			'title'                 => 'ACF Test Fields',
			'location'              => [
				[
					[
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'acf_test',
					],
				],
			],
			'graphql_field_name'    => 'acfTestFields',
			'graphql_types' => [ 'acfTest' ]
		]);



		$this->register_acf_field([
			'parent' => $group_key . 'acf_test_group',
			'type' => 'text',
			'name' => 'acf_text_field',
		]);

		$id = $this->factory()->post->create([
			'post_type' => 'acf_test',
			'post_status' => 'publish',
			'post_title' => 'ACF Test',
		]);

		$query = '
		{
		  __type( name: "AcfTestFields" ) {
		    name
		    description
		    fields {
		      name
		    }
		  }
		  acfTest: __type( name: "AcfTest" ) {
		    name
		    description
		    fields {
		      name
		    }
		  }
		}
		';

		$debug = graphql([
			'query' => $query,
		]);

		codecept_debug( $debug );

		$expected_text_1 = 'test value';

		update_field( 'acf_text_field', $expected_text_1, $id );

		$query = '
		query GET_CUSTOM_POST_TYPE_WITH_ACF_FIELD( $testId: ID! ) {
		  acfTest( id: $testId idType: DATABASE_ID ) {
		    __typename
		    id
		    title
		    acfTestFields {
		        fieldGroupName
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'testId' => $id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( 'acfTestFields', $actual['data']['acfTest']['acfTestFields']['fieldGroupName'] );

		acf_remove_local_field_group( $group_key . 'acf_test_group' );
	}

	/**
	 * Test querying a Relationship field
	 *
	 * @throws Exception
	 */
	public function testQueryRelationshipField() {

		$this->register_acf_field([
			'type' => 'relationship',
			'name' => 'relationship_field',
			'post_type'          => [
				'post',
				'page',
				'attachment'
			],
		]);

		$post_id = $this->post_id;
		$page_id = $this->factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Test Page',
		]);

		update_field( 'relationship_field', [ $post_id, $page_id ], $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      relationshipField {
		        nodes {
		          __typename
		          ...on Post {
		            postId
		          }
		          ...on Page {
		            pageId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( [
			[
				'__typename' => 'Post',
				'postId' => $post_id,
			],
			[
				'__typename' => 'Page',
				'pageId' => $page_id,
			],
		], $actual['data']['postBy']['postFields']['relationshipField']['nodes'] );

	}

	public function test_relationship_field_with_draft_post_doesnt_cause_error() {

		$this->register_acf_field([
			'type' => 'relationship',
			'name' => 'relationship_field',
			'post_type'          => [
				'post',
				'page',
				'attachment'
			],
		]);

		$page_id = $this->factory()->post->create([
			'post_type' => 'page',
			'post_status' => 'draft',
			'post_title' => 'Test Page',
		]);

		// Set the value of the field to a draft post
		update_field( 'relationship_field', [ $page_id ], $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      relationshipField {
		        nodes {
		          __typename
		          ...on Post {
		            postId
		          }
		          ...on Page {
		            pageId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Since the related post is a draft, we shouldn't get any node returned, and should
		// also get no errors
		$this->assertSame( [], $actual['data']['postBy']['postFields']['relationshipField']['nodes'] );

		// Update the relationship to have one published Post ID and one Draft Page ID
		update_field( 'relationship_field', [ $this->post_id, $page_id ], $this->post_id );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			],
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// Since the related post is published, but the related page is draft, we should
		// get the post returned, but the draft page should not be in the response
		$this->assertSame( [
			[
				'__typename' => 'Post',
				'postId' => $this->post_id,
			],
		], $actual['data']['postBy']['postFields']['relationshipField']['nodes'] );

	}

	public function test_taxonomy_field_in_repeater_returns_terms() {

		$this->register_acf_field([
			'type' => 'repeater',
			'name' => 'repeater_field',
			'sub_fields' => [
				[
					'key' => 'field_609d76ed7dc3e',
					'label' => 'category',
					'name' => 'category',
					'type' => 'taxonomy',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'show_in_graphql' => 1,
					'taxonomy' => 'category',
					'field_type' => 'checkbox',
					'add_term' => 1,
					'save_terms' => 0,
					'load_terms' => 0,
					'return_format' => 'id',
					'multiple' => 0,
					'allow_null' => 0,
				],
			],
		]);

		$category_1 = $this->factory()->category->create([
			'name' => 'test one'
		]);
		$category_2 = $this->factory()->category->create([
			'name' => 'test two',
			'parent' => $category_1,
		]);

		update_field( 'repeater_field', [
			[
				'field_609d76ed7dc3e' => [ $category_1 ]
			],
			[
				'field_609d76ed7dc3e' => [ $category_2, $category_1 ]
			],
			[
				'field_609d76ed7dc3e' => [ $category_2 ]
			]
		], $this->post_id );

		codecept_debug( get_post_custom( $this->post_id ) );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      repeaterField {
		        category {
		          nodes {
		            __typename
		            databaseId
		          }
		        }
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		// First repeater has just the parent category
		$this->assertSame( [
			[
				'__typename' => 'Category',
				'databaseId' => $category_1,
			],
		], $actual['data']['postBy']['postFields']['repeaterField'][0]['category']['nodes'] );

		// Next repeater has parent and child category, ordered with the child first, parent 2nd
		$this->assertSame( [
			[
				'__typename' => 'Category',
				'databaseId' => $category_2,
			],
			[
				'__typename' => 'Category',
				'databaseId' => $category_1,
			],
		], $actual['data']['postBy']['postFields']['repeaterField'][1]['category']['nodes'] );

		// Next repeater has just child category
		$this->assertSame( [
			[
				'__typename' => 'Category',
				'databaseId' => $category_2,
			],
		], $actual['data']['postBy']['postFields']['repeaterField'][2]['category']['nodes'] );


	}

	public function test_repeater_field_with_no_values_returns_empty_array() {

		$this->register_acf_field([
			'type' => 'repeater',
			'name' => 'repeater_test',
			'sub_fields' => [
				[
					'key' => 'field_609d76easdfere',
					'label' => 'text',
					'name' => 'text',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => [
						'width' => '',
						'class' => '',
						'id' => '',
					],
					'show_in_graphql' => 1,
					'add_term' => 1,
					'save_terms' => 0,
					'load_terms' => 0,
					'multiple' => 0,
					'allow_null' => 0,
				],
			],
		]);

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      repeaterTest {
		        text
		      }
		    }
		  }
		}';

		update_field( 'repeater_test', [], $this->post_id );

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEmpty( $actual['data']['postBy']['postFields']['repeaterTest'] );

		update_field( 'repeater_test', [
			[
				'field_609d76easdfere' => 'text one'
			],
			[
				'field_609d76easdfere' => 'text two'
			],
			[
				'field_609d76easdfere' => 'text three'
			]
		], $this->post_id );

		$query = '
		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
		  postBy( postId: $postId ) {
		    id
		    title
		    postFields {
		      repeaterTest {
		        text
		      }
		    }
		  }
		}';

		$actual = graphql([
			'query' => $query,
			'variables' => [
				'postId' => $this->post_id,
			]
		]);

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( 'text one', $actual['data']['postBy']['postFields']['repeaterTest'][0]['text'] );
		$this->assertSame( 'text two', $actual['data']['postBy']['postFields']['repeaterTest'][1]['text'] );
		$this->assertSame( 'text three', $actual['data']['postBy']['postFields']['repeaterTest'][2]['text'] );

	}

	public function test_flex_field_with_empty_layout_does_not_return_errors() {

		// @todo: Write this test!!!
		// Register Flex Field
		// Query flex field, with no data saved to it
		// Assert no errors
		// Update field with empty layout
		// Query field
		// Assert no errors
		// Update field with layout data
		// Query Field
		// Assert no errors

//		$this->register_acf_field([
//			'key' => 'field_60a2eba592eca',
//			'label' => 'FlexFieldWithEmptyLayout',
//			'name' => 'flex_field_with_empty_layout',
//			'type' => 'flexible_content',
//			'instructions' => '',
//			'required' => 0,
//			'conditional_logic' => 0,
//			'wrapper' => array(
//				'width' => '',
//				'class' => '',
//				'id' => '',
//			),
//			'show_in_graphql' => 1,
//			'layouts' => array(
//				'layout_60a2ebb0ddd96' => array(
//					'key' => 'layout_60a2ebb0ddd96',
//					'name' => 'layout_one',
//					'label' => 'Layout One',
//					'display' => 'block',
//					'sub_fields' => array(
//						// No subfields in the layout, intentionally
//					),
//					'min' => '',
//					'max' => '',
//				),
//			),
//			'button_label' => 'Add Row',
//			'min' => '',
//			'max' => '',
//		]);
//
//		$query = '
//		query GET_POST_WITH_ACF_FIELD( $postId: Int! ) {
//		  postBy( postId: $postId ) {
//		    id
//		    title
//		    postFields {
//		      flexFieldWithEmptyLayout {
//		        __typename
//		      }
//		    }
//		  }
//		}';
//
//		$actual = graphql([
//			'query' => $query,
//			'variables' => [
//				'postId' => $this->post_id,
//			]
//		]);
//
//		codecept_debug( $actual );
//
//
//		$this->assertArrayNotHasKey( 'errors', $actual );
//		$this->assertEmpty( $actual['data']['postBy']['postFields']['flexFieldWithEmptyLayout']);


	}

	public function test_flex_field_preview() {
		// @todo: test that previewing flex fields work
	}

	public function test_repeater_field_preview() {
		// @todo: test that previewing repeater fields work
	}
}
