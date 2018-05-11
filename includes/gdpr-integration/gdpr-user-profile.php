<?php
/**
 * Awesome Support Privacy Option.
 *
 * @package   Awesome_Support
 * @author    DevriX
 * @license   GPL-2.0+
 * @link      https://getawesomesupport.com
 */

// If this file is called directly, abort!
if ( ! defined( 'WPINC' ) ) {
	die;
}
class WPAS_GDPR_User_Profile {

	/**
	 *  Store the export directory path
	 * 
	 * @since     5.1.1
	 * @var      object
	 */
	private $user_export_dir;

	/**
	 * Instance of this class.
	 *
	 * @since     5.1.1
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Store the potential error messages.
	 */
	protected $error_message;

	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'wpas_user_profile_fields' ), 10, 1 );
		add_action( 'edit_user_profile', array( $this, 'wpas_user_profile_fields' ), 10, 1 );

		/**
		 * Ticket and User data export
		 */
		add_action( 'wp_ajax_wpas_gdpr_export_data', array( $this, 'wpas_gdpr_export_data' ) );
		add_action( 'wp_ajax_nopriv_wpas_gdpr_export_data', array( $this, 'wpas_gdpr_export_data' ) );

		add_action( 'init', array( $this, 'download_file' ) );
	}

	/**
	 * Download an exported file
	 *
	 * @since     5.1.1
	 */
	public function download_file() {
		$current_url = home_url( add_query_arg( null, null ) );
		if ( isset( $_GET['file'] ) ) {
			$user = $_GET['file'];
			if ( ! $this->user_export_dir ) {
				$this->user_export_dir = $this->set_log_dir( $user );
			}

			header( 'Content-Description: File Transfer' );
			header( 'Content-type: text/xml' );
			header( 'Content-Disposition: attachment; filename="export-data.xml"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $this->user_export_dir . '/export-data.xml' ) );
			readfile( $this->user_export_dir . '/export-data.xml' );
			wp_die();
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     5.1.1
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Display OPT In information in User profile
	 * Only visible if the current role is WPAS User
	 */
	public function wpas_user_profile_fields( $profileuser ) {
		/**
		 * Visible to all WPAS user roles
		 */
		if ( current_user_can( 'create_ticket' ) ) {
	?>
		<h2><?php esc_html_e( 'Awesome Support Consent History', 'awesome-support' ); ?></h2>
		<table class="form-table wp-list-table widefat fixed striped posts">
			<tr>
				<th><?php esc_html_e( 'Item', 'awesome-support' ); ?></th>
				<th><?php esc_html_e( 'Status', 'awesome-support' ); ?></th>
				<th><?php esc_html_e( 'Opt-in Date', 'awesome-support' ); ?></th>
				<th><?php esc_html_e( 'Opt-out Date', 'awesome-support' ); ?></th>
				<th><?php esc_html_e( 'Action', 'awesome-support' ); ?></th>
			</tr>
			<?php
			 /**
			  * For the GDPR labels, this data are stored in
			  * wpas_consent_tracking user meta in form of array.
			  * Get the option and if not empty, loop them here
			  */
			  $user_consent = get_user_option( 'wpas_consent_tracking', $profileuser->ID );
			if ( ! empty( $user_consent ) && is_array( $user_consent ) ) {
				foreach ( $user_consent as $consent ) {
					/**
					 * Determine if current loop is TOR
					 * Display TOR as label instead of content
					 * There should be no Opt buttons
					 */
					$item = isset( $consent['item'] ) ? $consent['item'] : '';
					if ( isset( $consent['is_tor'] ) && $consent['is_tor'] === true ) {
						$item = __( 'Terms and Conditions', 'awesome-support' );
					}

					/**
					 * Determine status
					 * Raw data is boolean, we convert it into string
					 */
					$status = isset( $consent['status'] ) && $consent['status'] === true ? __( 'Checked', 'awesome-support' ) : '';

					/**
					 * Convert Opt content into date
					 * We stored Opt data as strtotime value
					 */
					$opt_in  = isset( $consent['opt_in'] ) && ! empty( $consent['opt_in'] ) ? date( 'm/d/Y', $consent['opt_in'] ) : '';
					$opt_out = isset( $consent['opt_out'] ) && ! empty( $consent['opt_out'] ) ? date( 'm/d/Y', $consent['opt_out'] ) : '';

					/**
					 * Determine 'Action' buttons
					 * If current loop is TOR, do not give Opt options
					 */
					$opt_button = '';
					if ( isset( $consent['is_tor'] ) && $consent['is_tor'] == false ) {
						/**
						 * Determine what type of buttons we should render
						 * If opt_in is not empty, display Opt out button
						 * otherwise, just vice versa
						 */
						if ( ! empty( $opt_in ) ) {
							$opt_button = sprintf(
								'<a class="button button-secondary wpas-gdpr-opt-out" data-gdpr="' . $item . '" data-user="' . $profileuser->ID . '">%s</a>',
								__( 'Opt-out', 'awesome-support' )
							);
						} elseif ( ! empty( $opt_out ) ) {
							$opt_button = sprintf(
								'<a class="button button-secondary wpas-gdpr-opt-in" data-gdpr="' . $item . '" data-user="' . $profileuser->ID . '">%s</a>',
								__( 'Opt-in', 'awesome-support' )
							);
						} elseif ( empty( $opt_in ) && empty( $opt_out ) ) {
							$opt_button = sprintf(
								'<a class="button button-secondary wpas-gdpr-opt-in" data-gdpr="' . $item . '" data-user="' . $profileuser->ID . '">%s</a>',
								__( 'Opt-in', 'awesome-support' )
							);
						}
					}

					/**
					 * Render data
					 */
					printf(
						'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
						$item,
						$status,
						$opt_in,
						$opt_out,
						$opt_button
					);
				}
			}
			?>
		</table>

		<!-- GDPR Consent logging -->
		<h2><?php esc_html_e( 'Log', 'awesome-support' ); ?></h2>
		<?php
			/**
			 * Get consent logs
			 */
			$consent_log = get_user_option( 'wpas_consent_log', $profileuser->ID );
		if ( ! empty( $consent_log ) && is_array( $consent_log ) ) {
			foreach ( $consent_log as $log ) {
				echo '<p>' . $log . '</p>';
			}
		}
		?>
	<?php
		}
	}

	/**
	 * Ajax based ticket and user data export
	 * processing. This will primarily using WP_Query
	 */
	public function wpas_gdpr_export_data() {
		/**
		 * Initialize custom reponse message
		 */
		$response = array(
			'code'    => 403,
			'message' => __( 'Sorry! Something failed', 'awesome-support' ),
		);

		/**
		 * Initiate nonce
		 */
		$nonce = isset( $_POST['data']['nonce'] ) ? sanitize_text_field( $_POST['data']['nonce'] ) : '';
		$user  = isset( $_POST['data']['nonce'] ) ? sanitize_text_field( $_POST['data']['gdpr-user'] ) : '';
		/**
		 * Security checking
		 */
		if ( ! empty( $nonce ) && check_ajax_referer( 'wpas-gdpr-nonce', 'security' ) ) {
			/**
			 * Export ticket data belongs to the current user
			 */
			$ticket_data  = new WP_Query(
				array(
					'post_type'   => array( 'ticket' ),
					'author'      => $user,
					'post_status' => wpas_get_post_status(),
					'post_count'  => -1,
				)
			);
			$user_tickets = array();
			if ( $ticket_data->found_posts > 0 ) {
				if ( isset( $ticket_data->posts ) ) {
					foreach ( $ticket_data->posts as $post ) {
						$user_tickets[] = array(
							'subject'       => $post->post_title,
							'description'   => $post->post_content,
							'attachments'   => $this->get_ticket_attachment( $post->ID ),
							'replies'       => $this->get_ticket_replies( $post->ID ),
							'ticket_status' => $this->convert_status( $post->ID ),
							'ticket_meta'   => $this->get_ticket_meta( $post->ID ),
						);
					}
				}
				wp_reset_postdata();
			}

			/**
			 * Export GDPR logs
			 */
			$user_consent = get_user_option( 'wpas_consent_tracking', $user );

			/**
			 * Put them in awesome-support/user_log_$user_id
			 * folders in uploads dir. This has .htaccess protect to avoid
			 * direct access
			 */
			$this->user_export_dir = $this->set_log_dir( $user );
			file_put_contents(
				$this->user_export_dir . '/export-data.xml',
				$this->xml_conversion(
					array_merge(
						array( 'ticket_data' => $user_tickets ),
						array( 'consent_log' => $user_consent )
					)
				)
			);

			$upload_dir          = wp_upload_dir();
			$response['message'] = sprintf(
				'<p>%s. <a href="%s" target="_blank">%s</a></p>',
				__( 'Exporting data was successful!', 'awesome-support' ),
				add_query_arg(
					array(
						'file' => $user,
					), home_url()
				),
				__( 'Download it now..', 'awesome-support' )
			);

		} else {
			$response['message'] = __( 'Cheating huh?', 'awesome-support' );
		}
		wp_send_json( $response );
		wp_die();
	}

	/**
	 * Convert ticket status from developer
	 * into end user readability
	 */
	public function convert_status( $ticket_id ) {
		/**
		 * Get WPAS statuses
		 */
		$status = ucfirst( wpas_get_ticket_status( $ticket_id ) );
		return $status;
	}

	/**
	 * Get all post meta related to WPAS only
	 * Using get_post_meta() directly pull
	 * all WordPress meta data
	 */
	public function get_ticket_meta( $ticket_id ) {
		global $wpdb;
		return $wpdb->get_results( "select * from $wpdb->postmeta where post_id = $ticket_id and meta_key like '%_wpas%'" );
	}

	/**
	 * Get ticket attachment. It does not include
	 * attachments from ticket replies
	 */
	public function get_ticket_attachment( $ticket_id ) {
		global $wpdb;
		$attachments     = array();
		$get_attachments = $wpdb->get_results( "select * from $wpdb->posts where post_type='attachment' and post_parent = $ticket_id" );
		if ( ! empty( $get_attachments ) ) {
			foreach ( $get_attachments as $attachment ) {
				$attachments[] = array(
					'title' => $attachment->post_title,
					'url'   => $attachment->guid,
				);
			}
		}
		return $attachments;
	}

	/**
	 * Get ticket replies
	 */
	public function get_ticket_replies( $ticket_id ) {
		$get_replies = wpas_get_replies( $ticket_id );
		$replies     = array();
		if ( ! empty( $get_replies ) ) {
			foreach ( $get_replies as $reply ) {
				$replies[] = array(
					'content' => $reply->post_content,
					'author'  => $this->get_reply_author( $reply->post_author ),
				);
			}
		}
		return $replies;
	}

	/**
	 * Determine who
	 */
	public function get_reply_author( $author_id ) {
		$get_author = get_user_by( 'ID', $author_id );
		$author     = '';
		if ( ! empty( $get_author ) ) {
			$author = $get_author->data->display_name;
		}
		return $author;
	}

	/**
	 * Create logs dir for user export
	 */
	public function set_log_dir( $user ) {
		/* We sort the uploads in sub-folders per ticket. */
		$subdir = "/awesome-support/user_$user";

		$upload = wp_upload_dir();
		/* Create final URL and dir */
		$dir = $upload['basedir'] . $subdir;
		$url = $upload['baseurl'] . $subdir;

		/* Update upload params */
		$upload['path']   = $dir;
		$upload['url']    = $url;
		$upload['subdir'] = $subdir;

		/* Create the directory if it doesn't exist yet, make sure it's protected otherwise */
		if ( ! is_dir( $dir ) ) {
			$this->create_upload_dir( $dir );
		} else {
			$this->protect_upload_dir( $dir );
		}
		return $dir;
	}

	/**
	 * Create the upload directory for a ticket.
	 *
	 * @since 3.1.7
	 *
	 * @param string $dir Upload directory
	 *
	 * @return boolean Whether or not the directory was created
	 */
	public function create_upload_dir( $dir ) {

		$make = wp_mkdir_p( $dir );

		if ( true === $make ) {
			$this->protect_upload_dir( $dir );
		}

		return $make;

	}

	/**
	 * Protects an upload directory by adding an .htaccess file
	 *
	 * @since 3.1.7
	 *
	 * @param string $dir Upload directory
	 *
	 * @return void
	 */
	protected function protect_upload_dir( $dir ) {

		if ( is_writable( $dir ) ) {

			$filename = $dir . '/.htaccess';

			$filecontents = 'Options -Indexes';

			if ( ! file_exists( $filename ) ) {
				$file = fopen( $filename, 'a+' );
				if ( false <> $file ) {
					fwrite( $file, $filecontents );
					fclose( $file );
				} else {
					// attempt to record failure...
					wpas_write_log( 'file-uploader', 'unable to write .htaccess file to folder ' . $dir );
				}
			}
		} else {
			// folder isn't writable so no point in attempting to do it...
			// log the error in our log files instead...
			wpas_write_log( 'file-uploader', 'The folder ' . $dir . ' is not writable.  So we are unable to write a .htaccess file to this folder' );
		}

	}

	/**
	 * Convert the ticket and user data
	 * from array to XML file
	 */
	public function xml_conversion( $array, $rootElement = null, $xml = null ) {
		$_xml = $xml;

		if ( $_xml === null ) {
			$_xml = new SimpleXMLElement( $rootElement !== null ? $rootElement : '<root/>' );
		}

		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) || is_object( $v ) ) {
				$this->xml_conversion( $v, $k, $_xml->addChild( $k ) );
			} else {
				$_xml->addChild( $k, $v );
			}
		}

		return $_xml->asXML();
	}

	/**
	 * Zip the exported file
	 */
	public function data_zip( $file, $destination, $filename = 'exported-data.zip' ) {
		if ( ! file_exists( $destination ) ) {
			return new WP_Error( 'file_destination_not_exists', __( 'The destination file does not exists!', 'awesome-support' ) );
		}
		if ( file_exists( $destination . '/' . $file ) ) {
			$zip    = new ZipArchive();
			$do_zip = $zip->open( './' . $filename, ZipArchive::OVERWRITE | ZipArchive::CREATE );
			error_log( $destination . '/' . $filename );
			error_log( $do_zip );
			if ( is_resource( $do_zip ) ) {
				$zip->addFile( $file );
				$zip->close();
			} else {
				return new WP_Error( 'cannot_create_zip', __( 'Cannot create zip file', 'awesome-support' ) );
			}
		} else {
			return new WP_Error( 'file_not_exists', __( 'Zip data file not exists!', 'awesome-support' ) );
		}
	}
}
