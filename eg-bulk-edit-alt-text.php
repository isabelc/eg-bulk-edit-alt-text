<?php
/*
Plugin Name: EG Bulk Edit Alt Text
Plugin URI: http://isabelcastillo.com/free-plugins/eazyest-gallery-bulk-edit-alt-text
Description: Update the alt text for all published images in Eazyest Gallery.
Version: 1.1-beta1
Author: Isabel Castillo
Author URI: http://isabelcastillo.com
License: GPL2
Text Domain: bulk-edit-alt-text
Domain Path: languages
*
* Copyright 2015 Isabel Castillo

* EG Bulk Edit Alt Text is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* any later version.
*
* EG Bulk Edit Alt Text is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with EG Bulk Edit Alt Text. If not, see <http://www.gnu.org/licenses/>.
*/

class EG_Bulk_Edit_Alt_Text {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct() {

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );

	}
	
	function load_textdomain() {
		load_plugin_textdomain( 'eg-bulk-edit-alt-text', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

/**
	 * Get all published post ids of type galleryfolder. Private not included.
	 * @return array of galleryfolder post ids
	 */
	private function get_galleryfolder_ids() {
		// Get all posts of type galleryfolder
		$query_results = new WP_Query(
			array(
				'post_type' => 'galleryfolder',
				'post_status' => array( 'publish' ),
				'cache_results' => false,// speeds up query since we bypass the extra caching queries
				'no_found_rows' => true,// bypass counting the results to see if we need pagination or not,
				'nopaging' => true,
				'fields' => 'ids'
			)
		);
		return $query_results->posts;
	}
	/**
	 * Get all image attachments of published Eazyest Gallery galleryfolders.
	 * @return array of image ids
	 */
	private function get_eg_image_ids() {
		$galleryfolders = $this->get_galleryfolder_ids();
		if ( ( ! $galleryfolders ) || ( ! is_array($galleryfolders) ) ) {
			return false;
		}
		// get all images of EG galleryfolders
		$image_query = new WP_Query(
			array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'post_mime_type' => 'image',
			'cache_results' => false,// speeds up query since we bypass the extra caching queries
			'no_found_rows' => true,// bypass counting the results to see if we need pagination or not
			'nopaging' => true,
			'post_parent__in' => $galleryfolders,
			// 'meta_query' => array(
			// 		array(
			// 			'key'     => 'egiptc_complete',// @test exclude completed images
			// 			'compare' => 'NOT EXISTS',
			// 		),
			// ),
			'orderby' => 'none'
			)
		);
		return $image_query->posts;
	}


	/**
	* For each image in the Eazyest Gallery, if no alt text exists, 
	* update the alt text with the attachment caption or title.
	* 
	* @since 1.0
	*/
    function update_alt_texts(){
		
		set_time_limit(900);

		$images = $this->get_eg_image_ids();
		
		if ( $images ) {

			foreach ( (array) $images as $image ) {

				$alt = get_post_meta( $image->ID, '_wp_attachment_image_alt', true );

				// only update if alt text is empty
				if ( ! $alt ) {

					// is there a caption (post_excerpt)?
					if ( ! empty( $image->post_excerpt ) ) {
						$alt = $image->post_excerpt;
					} else {
						// if no caption, use title
						$alt = $image->post_title;
					}
					update_post_meta( $image->ID, '_wp_attachment_image_alt', $alt);
				}
			}  // end foreach
		} // end if $results
	}

	/**
	* Run our script while sanitizing input field
	* @since 1.0
	*/
	function sanitize($input){

		// if they aggreed to disclaimer, then update alt texts
		if ( 'on' == $input ) {
			$this->update_alt_texts();

			$type = 'updated';
            $message = __( 'Alt Texts for your Eazyest Gallery Images have been updated.', 'eg-bulk-edit-alt-text' );

		} else {
			$type = 'error';
            $message = __( 'Checkbox must be checked before Alt Texts can be updated.', 'eg-bulk-edit-alt-text' );
		}
		add_settings_error(
			'beat_update_alttext_disclaimer',
			'',
			$message,
			$type
		);
		return $input;
    }

	/**
	* Add the plugin options page under the Eazyest Gallery menu
	* @since 1.0
	*/
	function add_plugin_page(){

		add_submenu_page( 'edit.php?post_type=galleryfolder', __('EG Bulk Edit Alt Text', 'eg-bulk-edit-alt-text'), __('Bulk Edit Alt Text', 'eg-bulk-edit-alt-text'), 'manage_options', 'eg-edit-alt-text', array($this, 'page_callback') );

    }

	/**
	* HTML for the options page
	* @since 1.0
	*/
	
	function page_callback(){ ?>
		<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e( 'EG Bulk Edit Alt Text', 'eg-bulk-edit-alt-text'); ?></h2>

		<p><?php _e('This will update the alt text for all the images in your Eazyest Gallery that do not have already have an alt text assigned. It will use the value of the image attachment title as the alt text. If an image already has alt text, then it will keep its existing alt text.', 'eg-bulk-edit-alt-text' ); ?></p>

		<p><?php _e('Please be patient after you click the button below. It could take a while if you have many images.', 'eg-bulk-edit-alt-text' ); ?></p>

		<p><?php _e('When you are ready for the plugin to update the alt texts, click "Update Alt Texts".', 'eg-bulk-edit-alt-text' ); ?></p>

		<p><?php printf( __('%sNote:%s if you later add new images to Eazyest Gallery, they will not be affected by this update. If you want your new images to get an alt text, then you will have to "Update Alt Texts" again after you add the new pictures.', 'eg-bulk-edit-alt-text' ), '<strong>', '</strong>' ); ?></p>

		<form method="post" action="options.php">
			<?php 
			settings_fields( 'bulkedit-alttext-settings-group' );
			do_settings_sections( 'beat-alt-text' );
			submit_button( __( 'Update Alt Texts', 'eg-bulk-edit-alt-text' ) ); ?>
		</form>
		</div>
		<?php
    }

	/**
	* Register the plugin settings
	* @since 1.0
	*/
	function page_init(){	
		register_setting('bulkedit-alttext-settings-group', 'beat_update_alttext_disclaimer', array($this, 'sanitize'));
		add_settings_section(
			'bulkedit_alttext_main_settings',
			__( 'Update Alt Texts', 'eg-bulk-edit-alt-text' ),
			array( $this, 'main_setting_section_callback' ),
			'beat-alt-text'
		);

		add_settings_field(
			'beat_update_alttext_disclaimer',
			__( 'Please Agree', 'eg-bulk-edit-alt-text' ),
			array($this, 'beat_alttext_setting_callback'),
			'beat-alt-text',
			'bulkedit_alttext_main_settings'
		);
			
	} // end page_init

	/**
	* Main Settings section callback
	* @since 1.0
	*/
	function main_setting_section_callback() {
		return true;
	}

	/**
	* HTML for checkbox setting
	* @since 1.0
	*/

	function beat_alttext_setting_callback($args) {

	    $html = '<input type="checkbox" id="beat_update_alttext_disclaimer" name="beat_update_alttext_disclaimer"'; 

		if ( get_option( 'beat_update_alttext_disclaimer' ) ) {
			$html .= ' checked="checked"';
		}

		$html .= ' /><label for="beat_update_alttext_disclaimer">' . 
		
		__(' Check this to confirm that you understand that you are using this plugin at your own risk, and that Isabel Castillo will not be held liable under any circumstances for any adverse effects caused by this plugin. I have done my best to ensure that this works as described. You understand that this will update all the alt texts.', 'eg-bulk-edit-alt-text' );
		echo $html;
	}

	/**
	 * Displays all messages registered to 'your-settings-error-slug'
	 */
	function admin_notices() {
	    settings_errors( 'beat_update_alttext_disclaimer' );
	}


}
$eg_bulk_edit_alt_text = EG_Bulk_Edit_Alt_Text::get_instance();
