<?php
/**
 * Plugin Name: WDS BuddyPress Cover Photo
 * Description: Allows users to upload a Facebook or Twitter like cover photo.
 * Version: 1.0.0
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * License: GPL
 *
 */


class WDS_BuddyPress_Cover_Photo {
	public $width = 1380;
	public $height = 315;

	public function __construct() {

		add_action( 'bp_xprofile_setup_nav', array( $this, 'add_navigation'  ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_css' ), 98 );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_js' ) );
		add_action( 'wp_ajax_delete_cover_photo', array( $this, 'delete_cover_photo_via_ajax' ) );
		add_action( 'wp_ajax_cancel_cover_photo', array( $this, 'cancel_cover_photo_via_ajax' ) );
		add_filter('body_class', array( $this, 'add_body_class' ) );
		add_action( 'bp_before_member_header', array( $this, 'bp_do_cover_photo'  ) );
	}


	public function add_navigation() {

		global $bp;

		$profile_link = bp_loggedin_user_domain() . $bp->profile->slug . '/';

		bp_core_new_subnav_item( array(
			'name'            => __( 'Change Cover Photo', 'buddypress' ),
			'slug'            => 'change-cover-photo',
			'parent_url'      => $profile_link,
			'parent_slug'     => $bp->profile->slug,
			'screen_function' => array( $this, 'screen_change_cover_photo' ),
			'user_has_access' => ( bp_is_my_profile() || is_super_admin() ),
			'position'        => 40
		) );

	}


	public function screen_change_cover_photo() {

		global $bp;

		if ( ! empty( $_POST['cover-image-crop-submit'] ) ) {

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bp_crop_cover_photo' ) ) {
				die( __( 'Security check failed', 'bppbg' ) );
			}

			if ( $this->handle_crop() ) {
				bp_core_add_message( __( 'Cover photo cropped successfully!', 'buddypress' ) );
			}

		}

		if ( ! empty( $_POST['cover-photo-submit'] ) ) {

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bp_crop_cover_photo' ) ) {
				die( __( 'Security check failed', 'bppbg' ) );
			}

			if ( $this->handle_upload() ) {
				bp_core_add_message( __( 'Cover photo uploaded successfully!', 'buddypress' ) );
			}

		}

		// do jcrop things
		add_action( 'wp_print_scripts', array( $this, 'add_jquery_cropper' ) );

		add_action( 'bp_template_title', array( $this,'change_page_title' ) );
		add_action( 'bp_template_content', array( $this, 'upload_page_content' ) );
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );


	}

	public function add_jquery_cropper() {
		wp_deregister_script( 'jcrop' );
		wp_deregister_style( 'jcrop' );
		wp_enqueue_script( 'jcrop', plugin_dir_url(__FILE__) . '/assets/js/jquery.Jcrop.min.js', array( 'jquery' ), '0.9.12', true );
		wp_enqueue_style( 'jcrop', plugin_dir_url(__FILE__) . '/assets/css/jquery.Jcrop.min.css', array( ), '0.9.12', all );
	}

	public function change_page_title() {
		echo '<h3 class="bp-page-title">' . __( 'Cover Photo Settings', 'buddypress' ) . '</h3>';
	}


	public function upload_page_content() { ?>

		<form name="cover-photo-change" id="cover-photo-change" method="post" class="standard-form" enctype="multipart/form-data">

		<?php $cover_photo_url = get_transient( 'profile_cover_photo_' . bp_displayed_user_id() ); ?>

		<?php if ( get_transient( 'is_cover_photo_uploaded_' . bp_displayed_user_id() ) ) : ?>

			<div id="cover-photo-wrapper">
				<div class="cover-photo">
					<img src="<?php echo esc_url( $cover_photo_url );?>" alt="Current Cover Photo" title="Current Cover Photo" id="cover-image-to-crop" />
				</div>
				<!-- <div id="cover-image-crop-pane">
					<img src="<?php echo esc_url( $cover_photo_url );?>" alt="Cover Photo preview" title="Current Cover Photo" id="cover-image-crop-preview" />
				</div> -->
				<div id="submit-crop">
					<p class="crop-helper-text">
						<?php _e( 'Drag the handles to crop the image or move the selection to change the crop area.', 'nps' ); ?>
					</p>

					<?php //wp_nonce_field( 'bp_crop_cover_photo' ); ?>
					<input type="hidden" name="action" id="action" value="bp_crop_cover_photo" />
					<input type="submit" name="cover-image-crop-submit" id="cover-image-crop-submit" value="<?php esc_attr_e( 'Crop Cover Photo', 'buddypress' ); ?>" />
					<a href="#" id="cover-photo-cancel"><?php _e( 'Cancel', 'buddypress' ); ?></a>

					<input type="hidden" name="image_src" id="image_src" value="<?php echo $cover_photo_url; ?>" />
					<input type="hidden" id="x" name="x" />
					<input type="hidden" id="y" name="y" />
					<input type="hidden" id="w" name="w" />
					<input type="hidden" id="h" name="h" />

				</div>
			</div><!-- #cover-photo-wrapper -->

		<?php else : ?>

			<p><?php printf( __( 'Please use either a <strong>JPG</strong> or <strong>PNG</strong>. Cover photos should be at least <strong>%1$d pixels wide</strong> and <strong>%2$d pixels tall</strong>. The maximum allowed file size is: <strong>%3$s</strong>', 'buddypress' ), $this->width, $this->height, self::format_size( self::get_max_upload_size() ) ); ?></p>
			<label for="cover-photo-upload">
				<input type="file" name="file" id="cover-photo-upload" class="settings-input" />
			</label>
			<input type="hidden" name="action" id="action" value="bp_upload_cover_photo" />
			<p class="submit"><input type="submit" id="cover-photo-submit" name="cover-photo-submit" class="button" value="<?php _e( 'Upload', 'buddypress' ); ?>" />
				<?php if ( self::bp_get_cover_photo() ) : ?>
					<a href="#" id="cover-photo-delete"><?php _e( 'Remove cover photo', 'buddypress' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif;
		wp_nonce_field( 'bp_crop_cover_photo' ); ?>

		</form><?php
	}


	public function handle_upload() {

		global $bp;

		require_once( ABSPATH . '/wp-admin/includes/file.php' );

		$max_upload_size = $this->get_max_upload_size();
		$max_upload_size = $max_upload_size*1024;
		$file            = $_FILES;
		$uploadErrors    = array(
			0 => __( 'There is no error, the file uploaded with success', 'buddypress' ),
			1 => __( 'Your image was bigger than the maximum allowed file size of: ', 'buddypress' ) . size_format( $max_upload_size ),
			2 => __( 'Your image was bigger than the maximum allowed file size of: ', 'buddypress' ) . size_format( $max_upload_size ),
			3 => __( 'The uploaded file was only partially uploaded', 'buddypress' ),
			4 => __( 'No file was uploaded', 'buddypress' ),
			6 => __( 'Missing a temporary folder', 'buddypress' )
		);

		if ( isset( $file['error'] ) && $file['error'] ) {
			bp_core_add_message( sprintf( __( 'Your upload failed, please try again. Error was: %s', 'buddypress' ), $uploadErrors[$file['file']['error']] ), 'error' );
			return false;
		}

		if ( ! ( $file['file']['size'] < $max_upload_size ) ) {
			bp_core_add_message( sprintf( __( 'The image you uploaded is too large. Please upload a file under %s', 'buddypress' ), size_format($max_upload_size) ), 'error' );
			return false;
		}

		if ( ( ! empty( $file['file']['type'] ) && ! preg_match('/(jpe?g|gif|png)$/i', $file['file']['type'] ) ) || ! preg_match( '/(jpe?g|gif|png)$/i', $file['file']['name'] ) ) {
			bp_core_add_message( __( 'Please upload a JPG, GIF or PNG photo.', 'buddypress' ), 'error' );
			return false;
		}

		// Filter the upload location
		add_filter( 'upload_dir', array( $this, 'photo_upload_dir' ), 10, 0 );

		$uploaded_file = wp_handle_upload( $file['file'], array( 'action' => 'bp_upload_cover_photo' ) );

		remove_filter( 'upload_dir', array( $this, 'photo_upload_dir' ), 10, 0 );

		$image = wp_get_image_editor( $uploaded_file['file'] );

		if ( ! is_wp_error( $image ) ) {
		    $new_image_info = $image->save( $file['name'] );
		    set_transient( 'is_cover_photo_uploaded_' . bp_displayed_user_id(), true, 12 * HOUR_IN_SECONDS );
		}

		if ( ! empty( $uploaded_file['error'] ) ) {
			bp_core_add_message( sprintf( __( 'Upload Failed! Error was: %s', 'buddypress' ), $uploaded_file['error'] ), 'error' );
			return false;
		}

		//self::delete_cover_photo();

		$url  = bp_core_avatar_url() . '/cover-photo/' . bp_displayed_user_id() . '/' . $new_image_info['file']  ;

		set_transient( 'profile_cover_photo_' . bp_displayed_user_id(), $url, 12 * HOUR_IN_SECONDS );
		set_transient( 'profile_cover_photo_path_' . bp_displayed_user_id(), $new_image_info['path'], 12 * HOUR_IN_SECONDS );

		//bp_update_user_meta( bp_loggedin_user_id(), 'profile_cover_photo', $url );
		bp_update_user_meta( bp_loggedin_user_id(), 'profile_cover_photo_path', $new_image_info['path'] );

		@unlink ( $uploaded_file['file'] );

		do_action( 'cover_photo_uploaded', $path );

		return true;

	}


	public function photo_upload_dir( $directory = false, $user_id = 0 ) {
		global $bp;

		if ( empty( $user_id ) )
			$user_id = bp_displayed_user_id();

		if ( empty( $directory ) )
			$directory = 'cover-photo';

		$path    = bp_core_avatar_upload_path() . '/cover-photo/' . $user_id;
		$newbdir = $path;

		if ( !file_exists( $path ) )
			@wp_mkdir_p( $path );

		$newurl    = bp_core_avatar_url() . '/cover-photo/' . $user_id;
		$newburl   = $newurl;
		$newsubdir = '/avatars/' . $user_id;

		return apply_filters( 'cover_photo_upload_dir', array(
			'path'    => $path,
			'url'     => $newurl,
			'subdir'  => $newsubdir,
			'basedir' => $newbdir,
			'baseurl' => $newburl,
			'error'   => false
		) );
	}

	public function handle_crop() {

		require_once( ABSPATH . '/wp-admin/includes/image.php' );

		$user_id = bp_displayed_user_id();

		$cover_photo = get_transient( 'profile_cover_photo_' . $user_id );

		// Get the file extension
		$data = @getimagesize( $cover_photo );
		$ext  = $data['mime'] == 'image/png' ? 'png' : 'jpg';

		$base_filename = basename( $cover_photo, '.' . $ext );
		// create a new filename but, if it's already been cropped, strip out the -cropped
		$new_filename  = str_replace( '-cropped', '', $base_filename ) . '-cropped.'  . $ext;
		$new_filepath  = bp_core_avatar_upload_path() . '/cover-photo/' . $user_id . '/' . $new_filename;
		$new_fileurl   = bp_core_avatar_url() . '/cover-photo/' . $user_id . '/' . $new_filename;
		$crop_fileurl  = str_replace( trailingslashit( get_home_url() ), '', bp_core_avatar_url() ) . '/cover-photo/' . $user_id . '/' . $new_filename;

		// delete the old cover photo if it exists
		if ( file_exists( $new_filepath ) )
			@unlink ( $new_filepath );

		$cropped_header = wp_crop_image( $cover_photo, $_POST['x'], $_POST['y'], $_POST['w'], $_POST['h'], $this->width, $this->height, false, $crop_fileurl );

		if ( !is_wp_error( $cropped_header ) ) {

			$old_file_path = get_user_meta( bp_loggedin_user_id(), 'profile_cover_photo_path', true );

			if ( file_exists( $old_file_path ) )
				@unlink ( $old_file_path );

			// update with the new image and path
			bp_update_user_meta( bp_loggedin_user_id(), 'profile_cover_photo', $new_fileurl );
			bp_update_user_meta( bp_loggedin_user_id(), 'profile_cover_photo_path', $new_filepath );
			delete_transient( 'is_cover_photo_uploaded_' . bp_displayed_user_id() );
			delete_transient( 'profile_cover_photo_' . bp_displayed_user_id() );

		}

	}


	public function format_size( $size ) {

		$upload_size_unit = $size * 1024;
		$sizes = array( 'KB', 'MB', 'GB' );

		for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ ) {
			$upload_size_unit /= 1024;
		}

		if ( $u < 0 ) {
			$upload_size_unit = 0;
			$u = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}

		return $upload_size_unit . $sizes[$u];

	}


	public function get_max_upload_size(){

		$max_file_sizein_kb = get_site_option( 'fileupload_maxk' );

		if ( empty( $max_file_sizein_kb ) ){
			$upload_size_unit   = wp_max_upload_size();
			$upload_size_unit  /= 1024;
			$max_file_sizein_kb = $upload_size_unit;
		}

		return apply_filters( 'cover_photo_max_upload_size', $max_file_sizein_kb );

	}


	public function frontend_css() {

		wp_register_style( 'bp-cover-photo', plugin_dir_url(__FILE__) . '/assets/css/cover-photo.css', null, null );

		if ( function_exists( 'bp_is_my_profile' ) ) {

			if ( bp_is_my_profile() || bp_is_profile_component() || bp_is_current_action( 'change-cover-photo' ) ) {
				wp_enqueue_style( 'bp-cover-photo' );
			}

		}
	}


	public function frontend_js() {
		wp_enqueue_script( 'bp-cover-photo', plugin_dir_url(__FILE__) . '/assets/js/cover-photo.js', array( 'jquery' ), null, true );

		if ( function_exists( 'bp_is_my_profile' ) && ( bp_is_my_profile() && bp_is_profile_component() && bp_is_current_action( 'change-cover-photo' ) ) ) {
			if( !$image = get_transient( 'profile_cover_photo_' . bp_displayed_user_id() ) ) return;

			$image = getimagesize( $image );

			$full_width = $this->width; // max cover photo width
			$full_height = $this->height; // max cover photo height

			// calculate aspect ratio
			$aspect_ratio = $full_width / $full_height;

			wp_localize_script( 'bp-cover-photo', 'bp_cover_photo_l10n', array(
				'width'       => $image[0],
				'height'      => $image[1],
				'aspectRatio' => $aspect_ratio,
			) );
		}
	}


	public static function delete_cover_photo_via_ajax() {

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bp_crop_cover_photo' ) ) {
			die( 'Nonce check failed trying to delete the cover photo.' );
		}

		self::delete_cover_photo();
		echo '<p>' . __( 'Cover photo deleted successfully!', 'buddypress' ) . '</p>';
		exit(0);

	}


	public static function delete_cover_photo() {

		$old_file_path = get_user_meta( bp_loggedin_user_id(), 'profile_cover_photo_path', true );

		if ( $old_file_path ) {
			@unlink ( $old_file_path );
			bp_delete_user_meta( bp_loggedin_user_id(), 'profile_cover_photo_path' );
			bp_delete_user_meta( bp_loggedin_user_id(), 'profile_cover_photo' );
			delete_transient( 'is_cover_photo_uploaded_' . bp_displayed_user_id() );
			delete_transient( 'profile_cover_photo_' . bp_displayed_user_id() );
		}
	}


	public static function cancel_cover_photo_via_ajax() {

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'bp_crop_cover_photo' ) ) {
			die( 'Nonce check failed trying to delete the cover photo.' );
		}

		@unlink( get_transient( 'profile_cover_photo_path_' . bp_displayed_user_id() ) );

		delete_transient( 'is_cover_photo_uploaded_' . bp_displayed_user_id() );
		delete_transient( 'profile_cover_photo_' . bp_displayed_user_id() );
		echo '<p>' . __( 'Cover photo deleted successfully!', 'buddypress' ) . '</p>';
		exit(0);

	}


	public static function bp_get_cover_photo( $user_id = false ) {

		global $bp;

		if ( ! $user_id && function_exists( 'bp_displayed_user_id' ) ) {
			$user_id = bp_displayed_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		$cover_photo_url = bp_get_user_meta( $user_id, 'profile_cover_photo', true );

		return apply_filters( 'bp_get_cover_photo_filter', $cover_photo_url, $user_id );

	}


	public static function bp_do_cover_photo() {

		$cover_photo_url = self::bp_get_cover_photo();

		if ( ! $cover_photo_url ) {
			return false;
		}

		echo '<div class="bp-cover-photo" style="background-image: url(' . esc_url( $cover_photo_url ) . ');"></div><!-- .bp-cover-photo -->';

	}


	public function add_body_class( $classes ) {
		global $bp;

		$cover_photo_url = self::bp_get_cover_photo();

		if ( ! $cover_photo_url || !bp_is_user() ) {
			return $classes;
		}
		// add 'class-name' to the $classes array
		$classes[] = 'bp-cover-photo';
		// return the $classes array
		return $classes;
	}


} // end WDS_BuddyPress_Cover_Photo()

$WDS_BuddyPress_Cover_Photo = new WDS_BuddyPress_Cover_Photo();