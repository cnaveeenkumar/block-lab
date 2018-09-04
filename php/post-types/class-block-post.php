<?php
/**
 * Block Post Type.
 *
 * @package   Advanced_Custom_Blocks
 * @copyright Copyright(c) 2018, Advanced Custom Blocks
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 */

namespace Advanced_Custom_Blocks\Post_Types;

use Advanced_Custom_Blocks\Component_Abstract;
use Advanced_Custom_Blocks\Blocks\Block;
use Advanced_Custom_Blocks\Blocks\Field;
use Advanced_Custom_Blocks\Blocks\Controls;

/**
 * Class Block
 */
class Block_Post extends Component_Abstract {

	/**
	 * Slug used for the custom post type.
	 *
	 * @var string
	 */
	public $slug = 'acb_block';

	/**
	 * Registered controls.
	 *
	 * @var Controls\Control_Abstract[]
	 */
	public $controls = array();

	/**
	 * Register any hooks that this component needs.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_init', array( $this, 'add_caps' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_filter( 'enter_title_here', array( $this, 'post_title_placeholder' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_insert_post_data', array( $this, 'save_block' ), 10, 2 );

		// Clean up the list table.
		add_filter( 'disable_months_dropdown', '__return_true', 10, $this->slug );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'list_tables_style' ) );

		// AJAX Handlers.
		add_action( 'wp_ajax_fetch_field_settings', array( $this, 'ajax_field_settings' ) );
	}

	/**
	 * Initialise Block posts.
	 *
	 * @return void
	 */
	public function init() {
		$this->register_controls();
	}

	/**
	 * Register the controls.
	 *
	 * @return void
	 */
	public function register_controls() {
		$this->controls = apply_filters(
			'acb_controls', array(
				'text'     => new Controls\Text(),
				'textarea' => new Controls\Textarea(),
				'select'   => new Controls\Select(),
				'toggle'   => new Controls\Toggle(),
				'range'    => new Controls\Range(),
				'checkbox' => new Controls\Checkbox(),
				'radio'    => new Controls\Radio(),
			)
		);
	}

	/**
	 * Register the custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Custom Blocks', 'post type general name', 'advanced-custom-blocks' ),
			'singular_name'      => _x( 'Custom Block', 'post type singular name', 'advanced-custom-blocks' ),
			'menu_name'          => _x( 'Custom Blocks', 'admin menu', 'advanced-custom-blocks' ),
			'name_admin_bar'     => _x( 'Block', 'add new on admin bar', 'advanced-custom-blocks' ),
			'add_new'            => _x( 'Add New', 'block', 'advanced-custom-blocks' ),
			'add_new_item'       => __( 'Add New Block', 'advanced-custom-blocks' ),
			'new_item'           => __( 'New Block', 'advanced-custom-blocks' ),
			'edit_item'          => __( 'Edit Block', 'advanced-custom-blocks' ),
			'view_item'          => __( 'View Block', 'advanced-custom-blocks' ),
			'all_items'          => __( 'All Blocks', 'advanced-custom-blocks' ),
			'search_items'       => __( 'Search Blocks', 'advanced-custom-blocks' ),
			'parent_item_colon'  => __( 'Parent Blocks:', 'advanced-custom-blocks' ),
			'not_found'          => __( 'No blocks found.', 'advanced-custom-blocks' ),
			'not_found_in_trash' => __( 'No blocks found in Trash.', 'advanced-custom-blocks' ),
		);

		$args = array(
			'labels'        => $labels,
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 100,
			// This SVG icon is being included from the plugin directory, so using file_get_contents is okay.
			// @codingStandardsIgnoreStart
			'menu_icon'     => 'data:image/svg+xml;base64,' . base64_encode(
				file_get_contents(
					$this->plugin->get_assets_path( 'images/admin-menu-icon.svg' )
				)
			),
			// @codingStandardsIgnoreEnd
			'query_var'     => true,
			'rewrite'       => array( 'slug' => 'acb_block' ),
			'capabilities'  => array(
				'edit_post'          => 'acb_edit_block',
				'edit_posts'         => 'acb_edit_blocks',
				'edit_others_posts'  => 'acb_edit_others_blocks',
				'publish_posts'      => 'acb_publish_blocks',
				'read_post'          => 'acb_read_block',
				'read_private_posts' => 'acb_read_private_blocks',
				'delete_post'        => 'acb_delete_block',
			),
			'map_meta_cap'  => true,
			'supports'      => array( 'title' ),
		);

		register_post_type( $this->slug, $args );
	}

	/**
	 * Add custom capabilities
	 *
	 * @return void
	 */
	public function add_caps() {
		$admins = get_role( 'administrator' );

		$admins->add_cap( 'acb_edit_block' );
		$admins->add_cap( 'acb_edit_blocks' );
		$admins->add_cap( 'acb_edit_others_blocks' );
		$admins->add_cap( 'acb_publish_blocks' );
		$admins->add_cap( 'acb_read_block' );
		$admins->add_cap( 'acb_read_private_blocks' );
		$admins->add_cap( 'acb_delete_block' );
	}

	/**
	 * Enqueue scripts and styles used by the Block post type.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! is_object( $screen ) ) {
			return;
		}

		// Enqueue scripts and styles on the edit screen of the Block post type.
		if ( $this->slug === $screen->post_type && 'post' === $screen->base ) {
			wp_enqueue_style(
				'block-post',
				$this->plugin->get_url( 'css/admin.block-post.css' ),
				array(),
				filemtime( $this->plugin->get_path( 'css/admin.block-post.css' ) )
			);
			wp_enqueue_script(
				'block-post',
				$this->plugin->get_url( 'js/admin.block-post.js' ),
				array( 'jquery', 'jquery-ui-sortable', 'wp-util', 'wp-blocks' ),
				filemtime( $this->plugin->get_path( 'js/admin.block-post.js' ) ),
				false
			);
			wp_localize_script(
				'block-post',
				'advancedCustomBlocks',
				array(
					'fieldSettingsNonce' => wp_create_nonce( 'acb_field_settings_nonce' ),
				)
			);
		}
	}

	/**
	 * Add meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'acb_block_properties',
			__( 'Block Properties', 'advanced-custom-blocks' ),
			array( $this, 'render_properties_meta_box' ),
			$this->slug,
			'normal',
			'high'
		);

		add_meta_box(
			'acb_block_fields',
			__( 'Block Fields', 'advanced-custom-blocks' ),
			array( $this, 'render_fields_meta_box' ),
			$this->slug,
			'normal',
			'high'
		);

		add_meta_box(
			'acb_block_template',
			__( 'Template', 'advanced-custom-blocks' ),
			array( $this, 'render_template_meta_box' ),
			$this->slug,
			'side',
			'default'
		);
	}

	/**
	 * Render the Block Fields meta box.
	 *
	 * @return void
	 */
	public function render_properties_meta_box() {
		global $post;
		$block = new Block( $post->ID );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="acb-properties-category">
						<?php esc_html_e( 'Category', 'advanced-custom-blocks' ); ?>
					</label>
				</th>
				<td>
					<p>
						<select name="acb-properties-category" id="acb-properties-category">
						</select>
						<input type="hidden" id="acb-properties-category-saved" value="<?php echo esc_attr( $block->category ); ?>" />
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="acb-properties-icon">
						<?php esc_html_e( 'Icon', 'advanced-custom-blocks' ); ?>
					</label>
				</th>
				<td>
					<input
						name="acb-properties-icon"
						type="hidden"
						id="acb-properties-icon"
						value="<?php echo esc_attr( $block->icon ); ?>">
					<div class="acb-properties-icons">
						<?php
						foreach ( acb_get_icons() as $name => $icon ) {
							$selected = $icon['value'] === $block->icon ? 'selected' : '';
							switch ( $icon['type'] ) {
								case 'dashicons':
									printf(
										'<span class="dashicons %s %s" data-value="%s"></span>',
										esc_attr( $name ),
										esc_attr( $selected ),
										esc_attr( $icon['value'] )
									);
									break;
								case 'svg':
									printf(
										'<span class="svg %s" data-value="%s">%s</span>',
										esc_attr( $selected ),
										esc_attr( $name ),
										wp_kses(
											$icon['value'],
											array( 'svg' => array( 'preserveAspectRatio', 'viewbox', 'xmlns' ) )
										)
									);
									break;
							}
						}
						?>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="acb-properties-keywords">
						<?php esc_html_e( 'Keywords', 'advanced-custom-blocks' ); ?>
					</label>
					<p class="description" id="acb-properties-keywords-description">
						<?php
						esc_html_e(
							'A comma separated list of keywords, used when searching. Maximum of 3 keywords.',
							'advanced-custom-blocks'
						);
						?>
					</p>
				</th>
				<td>
					<p>
						<input
							name="acb-properties-keywords"
							type="text"
							id="acb-properties-keywords"
							value="<?php echo esc_attr( implode( ', ', $block->keywords ) ); ?>"
							class="regular-text">
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="acb-properties-description">
						<?php esc_html_e( 'Description', 'advanced-custom-blocks' ); ?>
					</label>
				</th>
				<td>
					<p>
						<textarea
							name="acb-properties-description"
							id="acb-properties-description"
							class="large-text"
							rows="3"><?php echo esc_textarea( $block->description ); ?></textarea>
					</p>
				</td>
			</tr>
		</table>
		<?php
		wp_nonce_field( 'acb_save_properties', 'acb_properties_nonce' );
	}

	/**
	 * Render the Block Fields meta box.
	 *
	 * @return void
	 */
	public function render_fields_meta_box() {
		global $post;
		$block = new Block( $post->ID );
		?>
		<div class="acb-fields-list">
			<table class="widefat">
				<thead>
					<tr>
						<th class="acb-fields-sort"></th>
						<th class="acb-fields-label">
							<?php esc_html_e( 'Field Label', 'advanced-custom-blocks' ); ?>
						</th>
						<th class="acb-fields-name">
							<?php esc_html_e( 'Field Name', 'advanced-custom-blocks' ); ?>
						</th>
						<th class="acb-fields-control">
							<?php esc_html_e( 'Field Type', 'advanced-custom-blocks' ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td colspan="4" class="acb-fields-rows">
							<p class="acb-no-fields">
								<?php
								echo wp_kses_post(
									sprintf(
										// Translators: Placeholders are for <strong> HTML tags.
										__( 'Click the %1$s+ Add Field%2$s button below to add your first field.' ),
										'<strong>',
										'</strong>'
									)
								);
								?>
							</p>
							<?php
							if ( count( $block->fields ) > 0 ) {
								foreach ( $block->fields as $field ) {
									$this->render_fields_meta_box_row( $field, uniqid() );
								}
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div class="acb-fields-actions-add-field">
			<input
				name="add-field"
				type="button"
				class="button button-primary button-large"
				id="acb-add-field"
				value="<?php esc_attr_e( '+ Add Field', 'advanced-custom-blocks' ); ?>" />

			<script type="text/html" id="tmpl-field-repeater">
				<?php
				$this->render_fields_meta_box_row( new Field() );
				?>
			</script>
		</div>
		<?php
		wp_nonce_field( 'acb_save_fields', 'acb_fields_nonce' );
	}

	/**
	 * Render a single Field as a row.
	 *
	 * @param Field $field The Field containing the options to render.
	 * @param mixed $uid   A unique ID to used to unify the HTML name, for, and id attributes.
	 *
	 * @return void
	 */
	public function render_fields_meta_box_row( $field, $uid = false ) {
		// Use a template placeholder if no UID provided.
		if ( ! $uid ) {
			$uid = '{{ data.uid }}';
		}
		?>
		<div class="acb-fields-row" data-uid="<?php echo esc_attr( $uid ); ?>">
			<div class="acb-fields-sort">
				<span class="acb-fields-sort-handle"></span>
			</div>
			<div class="acb-fields-label">
				<a class="row-title" href="javascript:" id="acb-fields-label_<?php echo esc_attr( $uid ); ?>">
					<?php echo esc_html( $field->label ); ?>
				</a>
				<div class="acb-fields-actions">
					<a class="acb-fields-actions-edit" href="javascript:">
						<?php esc_html_e( 'Edit', 'advanced-custom-blocks' ); ?>
					</a>
					&nbsp;|&nbsp;
					<a class="acb-fields-actions-delete" href="javascript:">
						<?php esc_html_e( 'Delete', 'advanced-custom-blocks' ); ?>
					</a>
				</div>
			</div>
			<div class="acb-fields-name" id="acb-fields-name_<?php echo esc_attr( $uid ); ?>">
				<?php echo esc_html( $field->name ); ?>
			</div>
			<div class="acb-fields-control" id="acb-fields-control_<?php echo esc_attr( $uid ); ?>">
				<?php echo esc_html( $field->control ); ?>
			</div>
			<div class="acb-fields-edit">
				<table class="widefat">
					<tr class="acb-fields-edit-label">
						<td class="spacer"></td>
						<th scope="row">
							<label for="acb-fields-edit-label-input_<?php echo esc_attr( $uid ); ?>">
								<?php esc_html_e( 'Field Label', 'advanced-custom-blocks' ); ?>
							</label>
							<p class="description" id="acb-fields-edit-label-description">
								<?php
								esc_html_e(
									'A label describing your block\'s custom field.',
									'advanced-custom-blocks'
								);
								?>
							</p>
						</th>
						<td>
							<input
								name="acb-fields-label[<?php echo esc_attr( $uid ); ?>]"
								type="text"
								id="acb-fields-edit-label-input_<?php echo esc_attr( $uid ); ?>"
								class="regular-text"
								value="<?php echo esc_attr( $field->label ); ?>"
								data-sync="acb-fields-label_<?php echo esc_attr( $uid ); ?>" />
						</td>
					</tr>
					<tr class="acb-fields-edit-name">
						<td class="spacer"></td>
						<th scope="row">
							<label for="acb-fields-edit-name-input_<?php echo esc_attr( $uid ); ?>">
								<?php esc_html_e( 'Field Name', 'advanced-custom-blocks' ); ?>
							</label>
							<p class="description" id="acb-fields-edit-name-description">
								<?php esc_html_e( 'Single word, no spaces.', 'advanced-custom-blocks' ); ?>
							</p>
						</th>
						<td>
							<input
								name="acb-fields-name[<?php echo esc_attr( $uid ); ?>]"
								type="text"
								id="acb-fields-edit-name-input_<?php echo esc_attr( $uid ); ?>"
								class="regular-text"
								value="<?php echo esc_attr( $field->name ); ?>"
								data-sync="acb-fields-name_<?php echo esc_attr( $uid ); ?>" />
						</td>
					</tr>
					<tr class="acb-fields-edit-control">
						<td class="spacer"></td>
						<th scope="row">
							<label for="acb-fields-edit-control-input_<?php echo esc_attr( $uid ); ?>">
								<?php esc_html_e( 'Field Type', 'advanced-custom-blocks' ); ?>
							</label>
						</th>
						<td>
							<select
								name="acb-fields-control[<?php echo esc_attr( $uid ); ?>]"
								id="acb-fields-edit-control-input_<?php echo esc_attr( $uid ); ?>"
								data-sync="acb-fields-control_<?php echo esc_attr( $uid ); ?>" >
								<?php foreach ( $this->controls as $control ) : ?>
									<option
										value="<?php echo esc_attr( $control->name ); ?>"
										<?php selected( $field->control, $control->name ); ?>>
										<?php echo esc_html( $control->label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php $this->render_field_settings( $field, $uid ); ?>
					<tr class="acb-fields-edit-actions-close">
						<td class="spacer"></td>
						<th scope="row">
						</th>
						<td>
							<a class="button" title="<?php esc_attr_e( 'Close Field', 'advanced-custom-blocks' ); ?>" href="javascript:">
								<?php esc_html_e( 'Close Field', 'advanced-custom-blocks' ); ?>
							</a>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Block Template meta box.
	 *
	 * @TODO: Change this so that it uses a built-in template fallback method
	 *
	 * @return void
	 */
	public function render_template_meta_box() {
		global $post;

		if ( ! isset( $post->post_name ) || empty( $post->post_name ) ) {
			?>
			<div class="template-notice template-warning">
				<p>
					<?php esc_html_e( 'The template path will be available after publishing this block.', 'advanced-custom-blocks' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		if ( ! file_exists( get_theme_file_path( 'blocks/block-' . $post->post_name . '.php' ) ) ) {
			?>
			<div class="template-notice template-warning">
				<p>
					<strong><?php esc_html_e( 'Template not found.', 'advanced-custom-blocks' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'To display this block, ACB will look for one of these templates:', 'advanced-custom-blocks' ); ?>
				</p>
				<?php
				$child_template  = str_replace( get_theme_root(), '', get_stylesheet_directory() ) . '/blocks/block-' . $post->post_name . '.php';
				$parent_template = str_replace( get_theme_root(), '', get_template_directory() ) . '/blocks/block-' . $post->post_name . '.php';
				if ( $child_template !== $parent_template ) {
					?>
					<p><code><?php echo esc_html( $child_template ); ?></code></p>
					<?php
				}
				?>
				<p><code><?php echo esc_html( $parent_template ); ?></code></p>
			</div>
			<?php
			return;
		}

		?>
		<div class="template-notice template-success">
			<p>
				<strong><?php esc_html_e( 'Template found.', 'advanced-custom-blocks' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'This block uses the following template:', 'advanced-custom-blocks' ); ?>
			</p>
			<?php
			$child_template  = str_replace( get_theme_root(), '', get_stylesheet_directory() ) . '/blocks/block-' . $post->post_name . '.php';
			$parent_template = str_replace( get_theme_root(), '', get_template_directory() ) . '/blocks/block-' . $post->post_name . '.php';
			if ( $child_template !== $parent_template ) {
				?>
				<p><code><?php echo esc_html( $child_template ); ?></code></p>
				<?php
			} else {
				?>
				<p><code><?php echo esc_html( $parent_template ); ?></code></p>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render the Block Template meta box.
	 *
	 * @param Field  $field The Field containing the options to render.
	 * @param string $uid   A unique ID to used to unify the HTML name, for, and id attributes.
	 *
	 * @return void
	 */
	public function render_field_settings( $field, $uid ) {
		if ( isset( $this->controls[ $field->control ] ) ) {
			$this->controls[ $field->control ]->render_settings( $field, $uid );
		}
	}

	/**
	 * Ajax response for fetching field settings.
	 *
	 * @return void
	 */
	public function ajax_field_settings() {
		wp_verify_nonce( 'acb_field_options_nonce' );

		if ( ! isset( $_POST['control'] ) || ! isset( $_POST['uid'] ) ) {
			wp_send_json_error();
			return;
		}

		$control = sanitize_key( $_POST['control'] );
		$uid     = sanitize_key( $_POST['uid'] );

		ob_start();
		$field = new Field( array( 'control' => $control ) );
		$this->render_field_settings( $field, $uid );
		$data['html'] = ob_get_clean();

		if ( '' === $data['html'] ) {
			wp_send_json_error();
		}

		wp_send_json_success( $data );
	}

	/**
	 * Save block meta boxes as a json blob in post content.
	 *
	 * @param array $data An array of slashed post data.
	 *
	 * @return array
	 */
	public function save_block( $data ) {
		if ( ! isset( $_POST['post_ID'] ) ) {
			return $data;
		}

		$post_id = sanitize_key( $_POST['post_ID'] );

		// Exits script depending on save status.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return $data;
		}

		check_admin_referer( 'acb_save_fields', 'acb_fields_nonce' );
		check_admin_referer( 'acb_save_properties', 'acb_properties_nonce' );

		$block = new Block();

		// Block name.
		$block->name = sanitize_key( $data['post_name'] );
		if ( '' === $block->name ) {
			$block->name = $post_id;
		}

		// Block title.
		$block->title = sanitize_text_field( $data['post_title'] );
		if ( '' === $block->title ) {
			$block->title = $post_id;
		}

		// Block category.
		if ( isset( $_POST['acb-properties-category'] ) ) {
			$block->category = sanitize_key( $_POST['acb-properties-category'] );
		}

		// Block icon.
		if ( isset( $_POST['acb-properties-icon'] ) ) {
			$block->icon = sanitize_text_field(
				wp_unslash( $_POST['acb-properties-icon'] )
			);
		}

		// Block keywords.
		if ( isset( $_POST['acb-properties-keywords'] ) ) {
			$keywords = sanitize_text_field(
				wp_unslash( $_POST['acb-properties-keywords'] )
			);
			$keywords = explode( ',', $keywords );
			$keywords = array_map( 'trim', $keywords );
			$keywords = array_slice( $keywords, 0, 3 );

			$block->keywords = $keywords;
		}

		// Block description.
		if ( isset( $_POST['acb-properties-description'] ) ) {
			$block->description = sanitize_textarea_field(
				wp_unslash( $_POST['acb-properties-description'] )
			);
		}

		// Block fields.
		if ( isset( $_POST['acb-fields-name'] ) && is_array( $_POST['acb-fields-name'] ) ) {
			$order = 0;

			// We loop through this array and sanitize its content according to the content type.
			$fields = wp_unslash( $_POST['acb-fields-name'] ); // Sanitization okay.
			foreach ( $fields as $key => $name ) {
				// Field name and order.
				$field_config = array(
					'name'  => sanitize_key( $name ),
					'order' => $order,
				);

				// Field label.
				if ( isset( $_POST['acb-fields-label'][ $key ] ) ) {
					$field_config['label'] = sanitize_text_field(
						wp_unslash( $_POST['acb-fields-label'][ $key ] )
					);
				}

				// Field control.
				if ( isset( $_POST['acb-fields-control'][ $key ] ) ) {
					$field_config['control'] = sanitize_text_field(
						wp_unslash( $_POST['acb-fields-control'][ $key ] )
					);
				}

				// Field settings.
				if ( isset( $this->controls[ $field_config['control'] ] ) ) {
					$control = $this->controls[ $field_config['control'] ];
					foreach ( $control->settings as $setting ) {
						if ( isset( $_POST['acb-fields-settings'][ $key ][ $setting->name ] ) ) {
							// Sanitize the field options according to their type.
							if ( is_callable( $setting->sanitize ) ) {
								$field_config['settings'][ $setting->name ] = call_user_func(
									$setting->sanitize,
									$_POST['acb-fields-settings'][ $key ][ $setting->name ] // Sanitization okay.
								);
							}
						}
					}
				}

				$field = new Field( $field_config );

				$block->fields[ $name ] = $field;
				$order++;
			}
		}

		$data['post_content'] = $block->to_json();
		return $data;
	}

	/**
	 * Change the default "Enter Title Here" placeholder on the edit post screen.
	 *
	 * @param string $title Placeholder text. Default 'Enter title here'.
	 *
	 * @return string
	 */
	public function post_title_placeholder( $title ) {
		$screen = get_current_screen();

		// Enqueue scripts and styles on the edit screen of the Block post type.
		if ( is_object( $screen ) && $this->slug === $screen->post_type ) {
			$title = __( 'Enter block name here', 'advanced-custom-blocks' );
		}

		return $title;
	}

	/**
	 * Hide the search box and top pagination.
	 *
	 * @return void
	 */
	public function list_tables_style() {
		$custom_css  = '.post-type-acb_block .tablenav.top { display: none; }';
		$custom_css .= '.post-type-acb_block .search-box { display: none; }';
		wp_add_inline_style( 'list-tables', $custom_css );
	}

	/**
	 * Hide the Quick Edit row action.
	 *
	 * @param array $actions An array of row action links.
	 *
	 * @return array
	 */
	public function post_row_actions( $actions = array() ) {
		global $post;

		// Abort if the post type is incorrect.
		if ( $post->post_type !== $this->slug ) {
			return $actions;
		}

		// Remove the Quick Edit link.
		if ( isset( $actions['inline hide-if-no-js'] ) ) {
			unset( $actions['inline hide-if-no-js'] );
		}

		// Return the set of links without Quick Edit.
		return $actions;
	}
}