<?php
/**
 * Plugin Name:       FF Signature Field
 * Plugin URI:        https://designare.at/ff-signature-field
 * Description:       Adds a digital signature field to Fluent Forms. Users can sign directly in the form using mouse, touch, or stylus.
 * Version:           2.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Michael Kanda
 * Author URI:        https://designare.at
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ff-signature-field
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FFSIG_VERSION', '2.0.0' );
define( 'FFSIG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FFSIG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 *
 * Registers a signature field component for Fluent Forms, renders
 * the canvas-based drawing UI, validates submissions, and stores
 * the resulting PNG images in the uploads directory.
 *
 * @since 1.0.0
 */
final class FF_Signature_Field {

	/**
	 * Singleton instance.
	 *
	 * @var FF_Signature_Field|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return FF_Signature_Field
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – registers all hooks.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialise the plugin after all plugins have loaded.
	 *
	 * Checks that Fluent Forms is active before registering the
	 * signature field component and its associated hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! defined( 'FLUENTFORM' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_dependency_notice' ) );
			return;
		}

		// Editor integration.
		add_filter( 'fluentform/editor_components', array( $this, 'register_component' ) );
		add_filter( 'fluentform/editor_element_search_tags', array( $this, 'register_search_tags' ) );
		add_filter( 'fluentform/editor_element_settings_placement', array( $this, 'register_settings_placement' ) );

		// Front-end rendering.
		add_action( 'fluentform/render_item_signature', array( $this, 'render_field' ), 10, 2 );

		// Validation and data handling.
		add_filter( 'fluentform/validate_input_item_signature', array( $this, 'validate_field' ), 10, 5 );
		add_filter( 'fluentform/input_data_signature', array( $this, 'handle_input_data' ), 10, 3 );

		// Post-submission image storage.
		add_action( 'fluentform/submission_inserted', array( $this, 'save_signature_image' ), 10, 3 );
	}

	/**
	 * Show an admin notice when Fluent Forms is not active.
	 *
	 * @return void
	 */
	public function missing_dependency_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'FF Signature Field requires Fluent Forms to be installed and activated.',
				'ff-signature-field'
			)
		);
	}

	/**
	 * Register the signature component in the Fluent Forms editor.
	 *
	 * @param array $components Existing editor components.
	 * @return array Modified components.
	 */
	public function register_component( $components ) {
		$components['advanced'][] = array(
			'index'      => 20,
			'element'    => 'signature',
			'attributes' => array(
				'name'  => 'signature',
				'class' => '',
				'value' => '',
				'type'  => 'signature',
			),
			'settings'   => array(
				'container_class'    => '',
				'label'              => __( 'Signature', 'ff-signature-field' ),
				'admin_field_label'  => __( 'Signature', 'ff-signature-field' ),
				'label_placement'    => '',
				'help_message'       => '',
				'validation_rules'   => array(
					'required' => array(
						'value'   => false,
						'message' => __( 'Please provide a signature.', 'ff-signature-field' ),
					),
				),
				'conditional_logics' => array(),
			),
			'editor_options' => array(
				'title'      => __( 'Signature', 'ff-signature-field' ),
				'icon_class' => 'ff-edit-textarea',
				'template'   => 'signature',
			),
		);

		return $components;
	}

	/**
	 * Register search tags so the field is discoverable in the editor.
	 *
	 * @param array $tags Existing search tags.
	 * @return array Modified tags.
	 */
	public function register_search_tags( $tags ) {
		$tags['signature'] = array( 'signature', 'sign', 'unterschrift' );
		return $tags;
	}

	/**
	 * Define which settings tabs and fields appear in the editor.
	 *
	 * @param array $placements Existing placements.
	 * @return array Modified placements.
	 */
	public function register_settings_placement( $placements ) {
		$placements['signature'] = array(
			'general'  => array(
				'label',
				'admin_field_label',
				'label_placement',
				'validation_rules',
			),
			'advanced' => array(
				'container_class',
				'help_message',
				'name',
				'conditional_logics',
			),
		);

		return $placements;
	}

	/**
	 * Render the signature field on the front-end.
	 *
	 * Outputs the canvas element, clear button, hidden input,
	 * and enqueues the companion JavaScript file.
	 *
	 * @param array  $data Field data from Fluent Forms.
	 * @param object $form The current form object.
	 * @return void
	 */
	public function render_field( $data, $form ) {
		// This is a Fluent Forms core hook, not a custom hook defined by this plugin.
		$data = apply_filters( 'fluentform/rendering_field_data_' . $data['element'], $data, $form ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$settings   = $data['settings'];
		$field_name = $data['attributes']['name'];
		$is_required = ! empty( $settings['validation_rules']['required']['value'] );

		$has_conditions = ! empty( $settings['conditional_logics']['status'] ) ? 'has-conditions' : '';
		$container_class = trim( 'ff-el-group ' . ( $settings['container_class'] ?? '' ) . ' ' . $has_conditions );

		$uid = 'ffsig-' . wp_unique_id();

		// Build the label.
		$label_html = '';
		if ( ! empty( $settings['label'] ) ) {
			$required_mark = $is_required ? '<span class="ff-el-required">*</span>' : '';
			$label_html = sprintf(
				'<div class="ff-el-label"><label>%s %s</label></div>',
				esc_html( $settings['label'] ),
				$required_mark
			);
		}

		// Build the help message.
		$help_html = '';
		if ( ! empty( $settings['help_message'] ) ) {
			$help_html = sprintf(
				'<div class="ff-el-help-message">%s</div>',
				wp_kses_post( $settings['help_message'] )
			);
		}

		// Enqueue the front-end assets.
		wp_enqueue_style(
			'ffsig-canvas',
			FFSIG_PLUGIN_URL . 'assets/css/ffsig-canvas.css',
			array(),
			FFSIG_VERSION
		);

		wp_enqueue_script(
			'ffsig-canvas',
			FFSIG_PLUGIN_URL . 'assets/js/ffsig-canvas.js',
			array(),
			FFSIG_VERSION,
			true
		);

		// Output the field markup.
		printf(
			'<div class="%s" data-name="%s">%s<div class="ff-el-input--content">',
			esc_attr( $container_class ),
			esc_attr( $field_name ),
			wp_kses_post( $label_html )
		);

		printf(
			'<div id="%s" class="ffsig-wrapper" data-field-id="%s">'
			. '<canvas class="ffsig-canvas" style="width:100%%;height:200px;display:block;cursor:crosshair;touch-action:none"></canvas>'
			. '<div class="ffsig-baseline"></div>'
			. '<div class="ffsig-hint">%s</div>'
			. '<button type="button" class="ffsig-clear" aria-label="%s">&#10005; %s</button>'
			. '<input type="hidden" name="%s" class="ffsig-input" value="">'
			. '</div>',
			esc_attr( $uid ),
			esc_attr( $uid ),
			esc_html__( 'Sign here', 'ff-signature-field' ),
			esc_attr__( 'Clear signature', 'ff-signature-field' ),
			esc_html__( 'Clear', 'ff-signature-field' ),
			esc_attr( $field_name )
		);

		printf(
			'%s</div></div>',
			wp_kses_post( $help_html )
		);
	}

	/**
	 * Validate the signature field on submission.
	 *
	 * @param array  $errors  Existing validation errors.
	 * @param array  $field   Field configuration.
	 * @param array  $form_data Submitted form data.
	 * @param array  $fields  All form fields.
	 * @param object $form    The form object.
	 * @return array Validation errors.
	 */
	public function validate_field( $errors, $field, $form_data, $fields, $form ) {
		$field_name  = $field['attributes']['name'] ?? '';
		$value       = $form_data[ $field_name ] ?? '';
		$is_required = ! empty( $field['settings']['validation_rules']['required']['value'] );

		if ( $is_required && empty( $value ) ) {
			$message  = $field['settings']['validation_rules']['required']['message'] ?? '';
			$errors[] = $message ? $message : __( 'Please provide a signature.', 'ff-signature-field' );
		}

		return $errors;
	}

	/**
	 * Return the raw signature data for storage.
	 *
	 * @param mixed $value      Current value.
	 * @param array $field      Field configuration.
	 * @param array $form_data  Submitted form data.
	 * @return string The base64-encoded signature data.
	 */
	public function handle_input_data( $value, $field, $form_data ) {
		return $form_data[ $field['attributes']['name'] ?? '' ] ?? '';
	}

	/**
	 * Save the base64 signature as a PNG file and update the submission record.
	 *
	 * @param int    $submission_id The submission ID.
	 * @param array  $form_data     Submitted form data.
	 * @param object $form          The form object.
	 * @return void
	 */
	public function save_signature_image( $submission_id, $form_data, $form ) {
		if ( ! $form_data || ! is_array( $form_data ) ) {
			return;
		}

		$fields = json_decode( $form->form_fields, true );
		if ( ! $fields ) {
			return;
		}

		// Collect all signature field names.
		$signature_fields = array();
		$this->find_signature_fields( $fields, $signature_fields );

		if ( empty( $signature_fields ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$target_dir = $upload_dir['basedir'] . '/ff-signatures/' . $submission_id;

		wp_mkdir_p( $target_dir );

		// Create an index.php to prevent directory listing.
		$index_file = $target_dir . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		global $wpdb;
		$table = esc_sql( $wpdb->prefix . 'fluentform_submissions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is safely built from $wpdb->prefix.
			$wpdb->prepare( "SELECT response FROM `{$table}` WHERE id = %d", $submission_id )
		);

		if ( ! $row ) {
			return;
		}

		$response = json_decode( $row->response, true );
		if ( ! is_array( $response ) ) {
			return;
		}

		$updated = false;

		foreach ( $signature_fields as $field_name ) {
			if ( empty( $form_data[ $field_name ] ) ) {
				continue;
			}

			$base64 = $form_data[ $field_name ];

			if ( strpos( $base64, 'data:image/png;base64,' ) !== 0 ) {
				continue;
			}

			$image_data = base64_decode( str_replace( 'data:image/png;base64,', '', $base64 ) );
			if ( ! $image_data ) {
				continue;
			}

			$filename = sanitize_file_name( $field_name . '-' . time() . '.png' );
			$filepath = $target_dir . '/' . $filename;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $filepath, $image_data );

			$url = $upload_dir['baseurl'] . '/ff-signatures/' . $submission_id . '/' . $filename;

			if ( isset( $response[ $field_name ] ) ) {
				$response[ $field_name ] = esc_url_raw( $url );
				$updated = true;
			}
		}

		if ( $updated ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'response' => wp_json_encode( $response ) ),
				array( 'id' => $submission_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Recursively find all signature field names in a form structure.
	 *
	 * @param array $fields Form fields array.
	 * @param array $result Collected signature field names (passed by reference).
	 * @return void
	 */
	private function find_signature_fields( $fields, &$result ) {
		foreach ( $fields as $field ) {
			if ( ( $field['element'] ?? '' ) === 'signature' ) {
				$result[] = $field['attributes']['name'] ?? '';
			}

			if ( isset( $field['columns'] ) ) {
				foreach ( $field['columns'] as $column ) {
					if ( isset( $column['fields'] ) ) {
						$this->find_signature_fields( $column['fields'], $result );
					}
				}
			}

			if ( isset( $field['fields'] ) ) {
				$this->find_signature_fields( $field['fields'], $result );
			}
		}
	}
}

FF_Signature_Field::get_instance();
