<?php
/**
 * Category Featured Image admin screen.
 *
 * @package Greenberry
 */

namespace Greenberry\CategoryFeaturedImage;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves Category Featured Image settings.
 */
class Admin {
	/**
	 * Settings store.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings store.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_greenberry_category_featured_image_save', array( $this, 'save' ) );
	}

	/**
	 * Registers Category Featured Image submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'greenberry',
			__( 'Category Featured Image', 'greenberry' ),
			__( 'Category Featured Image', 'greenberry' ),
			'manage_options',
			'greenberry-category-featured-image',
			array( $this, 'render' )
		);
	}

	/**
	 * Loads media picker helpers.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'greenberry-category-featured-image' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'greenberry-category-featured-image-admin',
			GREENBERRY_PLUGIN_URL . 'CategoryFeaturedImage/admin.js',
			array( 'media-editor', 'media-views' ),
			GREENBERRY_VERSION,
			true
		);
		wp_localize_script(
			'greenberry-category-featured-image-admin',
			'greenberryCategoryFeaturedImage',
			array(
				'frameTitle'  => __( 'Select default featured image', 'greenberry' ),
				'frameButton' => __( 'Use this image', 'greenberry' ),
				'emptyText'   => __( 'No image selected', 'greenberry' ),
			)
		);
	}

	/**
	 * Saves settings.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Greenberry Category Featured Image.', 'greenberry' ) );
		}

		check_admin_referer( 'greenberry_category_featured_image_save' );

		$data = isset( $_POST['greenberry_category_featured_image'] ) && is_array( $_POST['greenberry_category_featured_image'] )
			? wp_unslash( $_POST['greenberry_category_featured_image'] )
			: array();

		$this->settings->save( $data );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'greenberry-category-featured-image',
					'greenberry_notice' => 'settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Renders the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = $this->settings->get();
		$post_types = $this->settings->get_assignable_post_types();
		$taxonomies = $this->settings->get_assignable_taxonomies();

		\Greenberry\Admin_UI::open(
			__( 'Category Featured Image', 'greenberry' ),
			__( 'Assign a fallback featured image only when content is saved without one. Term defaults win, then post type, then the global default.', 'greenberry' ),
			'greenberry-category-featured-image-admin'
		);
		$this->render_notice();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="greenberry_category_featured_image_save">
			<?php wp_nonce_field( 'greenberry_category_featured_image_save' ); ?>

			<div data-greenberry-tabs>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Featured image sections', 'greenberry' ); ?>">
					<button type="button" class="nav-tab nav-tab-active" data-greenberry-tab="global"><?php esc_html_e( 'Global Default', 'greenberry' ); ?></button>
					<button type="button" class="nav-tab" data-greenberry-tab="post-types"><?php esc_html_e( 'Post Types', 'greenberry' ); ?></button>
					<button type="button" class="nav-tab" data-greenberry-tab="terms"><?php esc_html_e( 'Taxonomy Terms', 'greenberry' ); ?></button>
				</nav>

				<div class="greenberry-tab-panel" data-greenberry-panel="global">
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Global Default', 'greenberry' ); ?></h2>
						<?php
						\Greenberry\Admin_UI::toggle(
							array(
								'name'    => 'greenberry_category_featured_image[enabled]',
								'checked' => ! empty( $settings['enabled'] ),
								'label'   => __( 'Enable automatic featured image defaults', 'greenberry' ),
								'help'    => __( 'Existing featured images are always left untouched.', 'greenberry' ),
							)
						);
						?>

						<div class="greenberry-field">
							<label for="greenberry-category-featured-image-global"><?php esc_html_e( 'Fallback image', 'greenberry' ); ?></label>
							<?php $this->render_image_picker( 'greenberry_category_featured_image[global_image_id]', 'greenberry-category-featured-image-global', $settings['global_image_id'] ); ?>
							<p class="description"><?php esc_html_e( 'Used when no matching taxonomy term or post type default is configured.', 'greenberry' ); ?></p>
						</div>
					</section>
				</div>

				<div class="greenberry-tab-panel" data-greenberry-panel="post-types" hidden>
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Post Type Defaults', 'greenberry' ); ?></h2>
						<?php $this->render_post_type_defaults( $post_types, $settings ); ?>
					</section>
				</div>

				<div class="greenberry-tab-panel" data-greenberry-panel="terms" hidden>
					<section class="greenberry-panel">
						<h2><?php esc_html_e( 'Taxonomy Term Defaults', 'greenberry' ); ?></h2>
						<p class="greenberry-muted"><?php esc_html_e( 'Term defaults take priority over post type and global defaults.', 'greenberry' ); ?></p>
						<?php $this->render_taxonomy_defaults( $taxonomies, $settings ); ?>
					</section>
				</div>
			</div>

			<?php submit_button( __( 'Save Changes', 'greenberry' ) ); ?>
		</form>
		<?php
		\Greenberry\Admin_UI::close();
	}

	/**
	 * Renders post type default settings.
	 *
	 * @param array $post_types Assignable post types.
	 * @param array $settings   Settings.
	 * @return void
	 */
	private function render_post_type_defaults( $post_types, $settings ) {
		if ( empty( $post_types ) ) {
			echo '<p class="greenberry-muted">' . esc_html__( 'No public post types with featured image support were found.', 'greenberry' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped greenberry-featured-image-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post type', 'greenberry' ); ?></th>
					<th><?php esc_html_e( 'Default featured image', 'greenberry' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $post_types as $post_type => $object ) : ?>
					<?php
					$field_id = 'greenberry-category-featured-image-post-type-' . sanitize_html_class( $post_type );
					$image_id = isset( $settings['post_type_defaults'][ $post_type ] ) ? absint( $settings['post_type_defaults'][ $post_type ] ) : 0;
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $object->labels->singular_name ); ?></strong>
							<div class="greenberry-muted"><code><?php echo esc_html( $post_type ); ?></code></div>
						</td>
						<td><?php $this->render_image_picker( 'greenberry_category_featured_image[post_type_defaults][' . $post_type . ']', $field_id, $image_id ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders taxonomy term default settings.
	 *
	 * @param array $taxonomies Assignable taxonomies.
	 * @param array $settings   Settings.
	 * @return void
	 */
	private function render_taxonomy_defaults( $taxonomies, $settings ) {
		if ( empty( $taxonomies ) ) {
			echo '<p class="greenberry-muted">' . esc_html__( 'No public taxonomies are attached to supported post types.', 'greenberry' ) . '</p>';
			return;
		}
		?>
		<div class="greenberry-term-defaults">
			<?php foreach ( $taxonomies as $taxonomy => $object ) : ?>
				<?php $terms = $this->settings->get_terms_for_taxonomy( $taxonomy ); ?>
				<details class="greenberry-term-defaults__group" <?php echo 'category' === $taxonomy ? 'open' : ''; ?>>
					<summary>
						<span>
							<strong><?php echo esc_html( $object->labels->name ); ?></strong>
							<code><?php echo esc_html( $taxonomy ); ?></code>
						</span>
						<span class="greenberry-status"><?php echo esc_html( sprintf( _n( '%s term', '%s terms', count( $terms ), 'greenberry' ), number_format_i18n( count( $terms ) ) ) ); ?></span>
					</summary>

					<?php if ( empty( $terms ) ) : ?>
						<p class="greenberry-muted"><?php esc_html_e( 'No terms found.', 'greenberry' ); ?></p>
					<?php else : ?>
						<table class="widefat striped greenberry-featured-image-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Term', 'greenberry' ); ?></th>
									<th><?php esc_html_e( 'Default featured image', 'greenberry' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $terms as $term ) : ?>
									<?php
									$field_id = 'greenberry-category-featured-image-term-' . sanitize_html_class( $taxonomy ) . '-' . absint( $term->term_id );
									$image_id = isset( $settings['term_defaults'][ $taxonomy ][ $term->term_id ] ) ? absint( $settings['term_defaults'][ $taxonomy ][ $term->term_id ] ) : 0;
									?>
									<tr>
										<td>
											<strong><?php echo esc_html( $term->name ); ?></strong>
											<div class="greenberry-muted"><code><?php echo esc_html( $term->slug ); ?></code></div>
										</td>
										<td><?php $this->render_image_picker( 'greenberry_category_featured_image[term_defaults][' . $taxonomy . '][' . absint( $term->term_id ) . ']', $field_id, $image_id ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</details>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Renders one media image picker.
	 *
	 * @param string $name     Field name.
	 * @param string $field_id Field ID.
	 * @param int    $image_id Attachment ID.
	 * @return void
	 */
	private function render_image_picker( $name, $field_id, $image_id ) {
		$image_id  = absint( $image_id );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
		$has_image = ! empty( $image_url );
		?>
		<div class="greenberry-image-picker <?php echo esc_attr( $has_image ? 'has-image' : '' ); ?>" data-greenberry-image-field>
			<input id="<?php echo esc_attr( $field_id ); ?>" type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $has_image ? $image_id : 0 ); ?>" data-greenberry-image-input>
			<div class="greenberry-image-picker__preview">
				<img src="<?php echo esc_url( $has_image ? $image_url : '' ); ?>" alt="" data-greenberry-image-preview>
				<span data-greenberry-image-empty><?php esc_html_e( 'No image selected', 'greenberry' ); ?></span>
			</div>
			<div class="greenberry-image-picker__actions">
				<button type="button" class="button button-secondary" data-greenberry-image-choose><?php esc_html_e( 'Choose image', 'greenberry' ); ?></button>
				<button type="button" class="button button-link-delete" data-greenberry-image-remove><?php esc_html_e( 'Remove', 'greenberry' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders admin notices.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['greenberry_notice'] ) ) {
			return;
		}

		$notice   = sanitize_key( $_GET['greenberry_notice'] );
		$messages = array(
			'settings_saved' => __( 'Featured image defaults saved.', 'greenberry' ),
		);

		if ( empty( $messages[ $notice ] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( $messages[ $notice ] ); ?></p>
		</div>
		<?php
	}
}
