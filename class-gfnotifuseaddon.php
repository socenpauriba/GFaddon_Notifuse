<?php

GFForms::include_feed_addon_framework();

class GFNotifuseAddOn extends GFFeedAddOn {

	protected $_version                  = GF_NOTIFUSE_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9.16';
	protected $_slug                     = 'notifuse';
	protected $_path                     = 'GFaddon_Notifuse/notifuse.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Notifuse Add-On';
	protected $_short_title              = 'Notifuse';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFNotifuseAddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFNotifuseAddOn();
		}

		return self::$_instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {

		parent::init();

		// Load translations
		load_plugin_textdomain( 'notifuse', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to Notifuse only when payment is received.', 'notifuse' ),
			)
		);

	}


	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return bool|void
	 */
	public function process_feed( $feed, $entry, $form ) {
		
		// Obtenir la configuració global del plugin
		$settings = $this->get_plugin_settings();
		
		// Validate we have the necessary configuration
		if ( empty( $settings['notifuse_url'] ) || empty( $settings['api_token'] ) || empty( $settings['workspace_id'] ) ) {
			$this->add_feed_error( esc_html__( 'Notifuse configuration incomplete. Please configure the instance URL, API token and workspace ID.', 'notifuse' ), $feed, $entry, $form );
			return false;
		}

		// Obtenir les llistes de subscripció seleccionades
		$subscribe_lists = array();
		if ( ! empty( $feed['meta']['subscribe_lists'] ) ) {
			$subscribe_lists = explode( ',', $feed['meta']['subscribe_lists'] );
			$subscribe_lists = array_map( 'trim', $subscribe_lists );
		}

		// Construir l'objecte contacte amb els camps mapejats
		$contact = array();
		
		// Obtenir tots els camps estàndard mapejats utilitzant el mètode de Gravity Forms
		$field_map = $this->get_field_map_fields( $feed, 'mapped_fields' );
		
		foreach ( $field_map as $notifuse_field => $form_field_id ) {
			// Només processar si s'ha seleccionat un camp del formulari
			if ( ! empty( $form_field_id ) ) {
				$value = $this->get_field_value( $form, $entry, $form_field_id );
				
				// Només afegir si té valor
				if ( ! empty( $value ) ) {
					$contact[ $notifuse_field ] = $value;
				}
			}
		}
		
		// Afegir camps personalitzats (custom_string_*, custom_number_*, etc.)
		if ( ! empty( $feed['meta']['custom_fields'] ) ) {
			foreach ( $feed['meta']['custom_fields'] as $custom_field_name => $form_field_id ) {
				if ( ! empty( $form_field_id ) && ! empty( $custom_field_name ) ) {
					$value = $this->get_field_value( $form, $entry, $form_field_id );
					
					// Només afegir si té valor
					if ( ! empty( $value ) ) {
						$contact[ $custom_field_name ] = $value;
					}
				}
			}
		}

		// Validate we have at least an email
		if ( empty( $contact['email'] ) ) {
			$this->add_feed_error( esc_html__( 'Contact email not found.', 'notifuse' ), $feed, $entry, $form );
			return false;
		}

		// Preparar les dades per a l'API
		$api_data = array(
			'workspace_id' => $settings['workspace_id'],
			'contacts'     => array( $contact ),
		);

		// Afegir llistes si n'hi ha
		if ( ! empty( $subscribe_lists ) ) {
			$api_data['subscribe_to_lists'] = $subscribe_lists;
		}

		// Enviar les dades a Notifuse
		$response = $this->send_to_notifuse( $settings['notifuse_url'], $settings['api_token'], $api_data );

		// Check the response
		if ( is_wp_error( $response ) ) {
			$this->add_feed_error( sprintf( esc_html__( 'Error connecting to Notifuse: %s', 'notifuse' ), $response->get_error_message() ), $feed, $entry, $form );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $response_code !== 200 && $response_code !== 201 ) {
			$this->add_feed_error( sprintf( esc_html__( 'Notifuse returned an error (Code %d): %s', 'notifuse' ), $response_code, $response_body ), $feed, $entry, $form );
			return false;
		}

		// Add note to entry
		$this->add_note( $entry['id'], esc_html__( 'Contact successfully synced with Notifuse.', 'notifuse' ), 'success' );

		return true;
	}

	/**
	 * Send data to Notifuse API
	 *
	 * @param string $notifuse_url The Notifuse instance URL
	 * @param string $api_token The API token
	 * @param array $data The data to send
	 *
	 * @return array|WP_Error
	 */
	private function send_to_notifuse( $notifuse_url, $api_token, $data ) {
		
		// Assegurar que la URL acaba sense /
		$notifuse_url = rtrim( $notifuse_url, '/' );
		
		// Construir l'endpoint
		$endpoint = $notifuse_url . '/api/contacts.import';

		// Preparar la petició
		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_token,
				'Content-Type'  => 'application/json',
			),
			'body'    => json_encode( $data ),
			'timeout' => 30,
		);

		// Fer la petició
		return wp_remote_request( $endpoint, $args );
	}

	/**
	 * Custom format the phone type field values before they are returned by $this->get_field_value().
	 *
	 * @param array $entry The Entry currently being processed.
	 * @param string $field_id The ID of the Field currently being processed.
	 * @param GF_Field_Phone $field The Field currently being processed.
	 *
	 * @return string
	 */
	public function get_phone_field_value( $entry, $field_id, $field ) {

		// Get the field value from the Entry Object.
		$field_value = rgar( $entry, $field_id );

		// If there is a value and the field phoneFormat setting is set to standard reformat the value.
		if ( ! empty( $field_value ) && $field->phoneFormat == 'standard' && preg_match( '/^\D?(\d{3})\D?\D?(\d{3})\D?(\d{4})$/', $field_value, $matches ) ) {
			$field_value = sprintf( '%s-%s-%s', $matches[1], $matches[2], $matches[3] );
		}

		return $field_value;
	}

	// # SCRIPTS & STYLES -----------------------------------------------------------------------------------------------

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'notifuse_script_js',
				'src'     => $this->get_base_url() . '/js/my_script.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'enqueue' => array(
					array(
						'admin_page' => array( 'form_settings' ),
						'tab'        => 'notifuse',
					),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Return the stylesheets which should be enqueued.
	 *
	 * @return array
	 */
	public function styles() {

		$styles = array(
			array(
				'handle'  => 'notifuse_styles_css',
				'src'     => $this->get_base_url() . '/css/my_styles.css',
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings', 'form_settings' ),
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	// # ADMIN FUNCTIONS -----------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the add-on settings tab.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Notifuse Configuration', 'notifuse' ),
				'description' => esc_html__( 'Configure the connection to your Notifuse instance.', 'notifuse' ),
				'fields'      => array(
					array(
						'name'              => 'notifuse_url',
						'tooltip'           => esc_html__( 'Enter your Notifuse instance URL (ex: https://demo.notifuse.com)', 'notifuse' ),
						'label'             => esc_html__( 'Notifuse Instance URL', 'notifuse' ),
						'type'              => 'text',
						'class'             => 'medium',
						'required'          => true,
						'validation_callback' => array( $this, 'validate_notifuse_url' ),
					),
					array(
						'name'     => 'api_token',
						'tooltip'  => esc_html__( 'Enter your Notifuse API token. You can get it from your Notifuse dashboard.', 'notifuse' ),
						'label'    => esc_html__( 'API Token', 'notifuse' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
					),
					array(
						'name'     => 'workspace_id',
						'tooltip'  => esc_html__( 'Enter your Notifuse workspace ID (ex: ws_1234567890)', 'notifuse' ),
						'label'    => esc_html__( 'Workspace ID', 'notifuse' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
					),
				),
			),
		);
	}

	/**
	 * Validate Notifuse URL
	 *
	 * @param array $field The field properties.
	 * @param string $value The field value.
	 *
	 * @return void
	 */
	public function validate_notifuse_url( $field, $value ) {
		if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$this->set_field_error( $field, esc_html__( 'Please enter a valid URL.', 'notifuse' ) );
		}
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page in the Form Settings > Notifuse area.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Notifuse Feed Configuration', 'notifuse' ),
				'fields' => array(
					array(
						'label'    => esc_html__( 'Feed Name', 'notifuse' ),
						'type'     => 'text',
						'name'     => 'feedName',
						'tooltip'  => esc_html__( 'Enter a name to identify this feed.', 'notifuse' ),
						'class'    => 'medium',
						'required' => true,
					),
					array(
						'label'   => esc_html__( 'Subscription Lists', 'notifuse' ),
						'type'    => 'text',
						'name'    => 'subscribe_lists',
						'tooltip' => esc_html__( 'Enter list names separated by commas (ex: newsletter, product_updates)', 'notifuse' ),
						'class'   => 'medium',
					),
				array(
					'name'      => 'mapped_fields',
					'label'     => esc_html__( 'Field Mapping', 'notifuse' ),
					'type'      => 'field_map',
					'tooltip'   => esc_html__( 'For each Notifuse field, select the form field you want to send. You can leave fields blank if you don\'t need them.', 'notifuse' ),
					'field_map' => array(
						array(
							'name'       => 'email',
							'label'      => esc_html__( 'Email', 'notifuse' ),
							'required'   => true,
							'field_type' => array( 'email', 'hidden' ),
							'tooltip'    => esc_html__( 'Contact email address (required)', 'notifuse' ),
						),
						array(
							'name'       => 'external_id',
							'label'      => esc_html__( 'External ID', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Unique identifier from your system', 'notifuse' ),
						),
						array(
							'name'       => 'first_name',
							'label'      => esc_html__( 'First Name', 'notifuse' ),
							'required'   => false,
							'field_type' => array( 'name', 'text', 'hidden' ),
							'tooltip'    => esc_html__( 'Contact first name', 'notifuse' ),
						),
						array(
							'name'       => 'last_name',
							'label'      => esc_html__( 'Last Name', 'notifuse' ),
							'required'   => false,
							'field_type' => array( 'name', 'text', 'hidden' ),
							'tooltip'    => esc_html__( 'Contact last name', 'notifuse' ),
						),
						array(
							'name'       => 'phone',
							'label'      => esc_html__( 'Phone', 'notifuse' ),
							'required'   => false,
							'field_type' => array( 'phone', 'text', 'hidden' ),
							'tooltip'    => esc_html__( 'Phone number', 'notifuse' ),
						),
						array(
							'name'       => 'timezone',
							'label'      => esc_html__( 'Timezone', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Contact ISO timezone (ex: Europe/Madrid)', 'notifuse' ),
						),
						array(
							'name'       => 'language',
							'label'      => esc_html__( 'Language', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Contact preferred language', 'notifuse' ),
						),
						array(
							'name'       => 'job_title',
							'label'      => esc_html__( 'Job Title', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Professional title or position', 'notifuse' ),
						),
						array(
							'name'       => 'address_line_1',
							'label'      => esc_html__( 'Address Line 1', 'notifuse' ),
							'required'   => false,
							'field_type' => array( 'address', 'text', 'textarea', 'hidden' ),
							'tooltip'    => esc_html__( 'Primary address', 'notifuse' ),
						),
						array(
							'name'       => 'address_line_2',
							'label'      => esc_html__( 'Address Line 2', 'notifuse' ),
							'required'   => false,
							'field_type' => array( 'address', 'text', 'textarea', 'hidden' ),
							'tooltip'    => esc_html__( 'Secondary address (apartment, suite, etc.)', 'notifuse' ),
						),
						array(
							'name'       => 'country',
							'label'      => esc_html__( 'Country', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Country in ISO format (ex: ES, US, FR)', 'notifuse' ),
						),
						array(
							'name'       => 'state',
							'label'      => esc_html__( 'State/Province', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'State or province', 'notifuse' ),
						),
						array(
							'name'       => 'postcode',
							'label'      => esc_html__( 'Postcode', 'notifuse' ),
							'required'   => false,
							'tooltip'    => esc_html__( 'Postal or ZIP code', 'notifuse' ),
						),
					),
				),
				array(
					'name'  => 'custom_fields',
					'label' => esc_html__( 'Custom Fields', 'notifuse' ),
					'type'  => 'generic_map',
					'tooltip' => esc_html__( 'Add Notifuse custom fields. Enter the exact field name (ex: custom_string_1, custom_number_2, custom_datetime_1, custom_json_3)', 'notifuse' ),
					'key_field' => array(
						'title'         => esc_html__( 'Custom Field Name', 'notifuse' ),
						'allow_custom'  => true,
						'placeholder'   => esc_html__( 'Ex: custom_string_1', 'notifuse' ),
					),
					'value_field' => array(
						'title'      => esc_html__( 'Form Field', 'notifuse' ),
					),
				),
					array(
						'name'           => 'condition',
						'label'          => esc_html__( 'Condition', 'notifuse' ),
						'type'           => 'feed_condition',
						'checkbox_label' => esc_html__( 'Enable Condition', 'notifuse' ),
						'instructions'   => esc_html__( 'Process this feed if', 'notifuse' ),
					),
				),
			),
		);
	}

	/**
	 * Configures which columns should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'        => esc_html__( 'Name', 'notifuse' ),
			'subscribe_lists' => esc_html__( 'Lists', 'notifuse' ),
		);
	}

	/**
	 * Format the value to be displayed in the subscribe_lists column.
	 *
	 * @param array $feed The feed being included in the feed list.
	 *
	 * @return string
	 */
	public function get_column_value_subscribe_lists( $feed ) {
		$lists = rgars( $feed, 'meta/subscribe_lists' );
		return ! empty( $lists ) ? esc_html( $lists ) : '—';
	}

	/**
	 * Prevent feeds being listed or created if an api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// Comprovar que tenim la configuració necessària
		if ( empty( $settings['notifuse_url'] ) || empty( $settings['api_token'] ) || empty( $settings['workspace_id'] ) ) {
			return false;
		}

		return true;
	}

}
