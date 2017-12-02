<?php

/**
 * Intermediate image resizer.
 *
 * @since      1.0.0
 * @package    Fr_Thumbnails_Folder
 * @subpackage Fr_Thumbnails_Folder/includes
 * @author     Fahri Rusliyadi <fahri.rusliyadi@gmail.com>
 */
class Fr_Thumbnails_Folder_Image_Resizer {
    /**
     * Array of class arguments.
     * 
     * @since 1.0.0
     * @var array {
     *      @type int $id               Attachment ID.
     *      @type array|string $size    Size of image. Image size or array of width and height values (in that order).
     * }
     */
    protected $args;
    
    /**
     * Attachment metadata.
     * 
     * @since 1.0.0
     * @var array
     */
    protected $metadata;
    
    /**
     * Wanted image size.
     *
     * @since 1.0.0
     * @var array {
     *      @type int $width
     *      @type int height
     *      @type bool crop
     * } 
     */
    protected $wanted_size;

    /**
     * Construct.
     * 
     * @since 1.0.0
     * @param array $args
     */
    public function __construct($args) {
        $this->args = $args;
    }
    
    /**
     * Resize the image.
     * 
     * @since 1.0.0
     * @return null|array   Array containing the image URL, width, height, and boolean for whether
     *                      the image is an intermediate size. Null if does not exist.
     */
    public function resize() {
        $this->set_metadata();        

        if (empty($this->metadata['file'])) {
            return;
        }
        
        $this->set_wanted_size();
        
        if (!$this->wanted_size) {
            return;
        }      
        
        return $this->generate_image_size();
    }
    
    /**
     * Set the $metadata property.
     * 
     * @since 1.0.0
     */
    protected function set_metadata() {
        $this->metadata = wp_get_attachment_metadata($this->args['id']);
    }
    
    /**
     * Set the $wanted_size property.
     * 
     * @since 1.0.0
     */
    protected function set_wanted_size() {
        $additional_image_sizes = wp_get_additional_image_sizes();
        
        $width  = 0;
        $height = 0;
        $crop   = false;

        if (isset($additional_image_sizes[$this->args['size']])) {
            $width  = $additional_image_sizes[$this->args['size']]['width'];
            $height = $additional_image_sizes[$this->args['size']]['height'];
            $crop   = isset($additional_image_sizes[$this->args['size']]['crop'] ) ? $additional_image_sizes[$this->args['size']]['crop'] : false;
        } else if (in_array($this->args['size'], array('thumbnail', 'medium', 'large'))) {
            $width  = get_option($this->args['size'] . '_size_w');
            $height = get_option($this->args['size'] . '_size_h');
            $crop   = ('thumbnail' === $this->args['size']) ? (bool) get_option('thumbnail_crop') : false;
        } else {
            return;
        }

        if (!$width && !$height) {
            return;
        }   
        
        $this->wanted_size = array(
            'width'     => $width,
            'height'    => $height,
            'crop'      => $crop,
        );
    }
    
    /**
     * Generate image size.
     * 
     * @since 1.0.0
     * @return null|array   Array containing the image URL, width, height, and boolean for whether
     *                      the image is an intermediate size. Null if does not exist.
     */
    protected function generate_image_size() {
        $image_path     = get_attached_file($this->args['id']);
        $image_editor   = wp_get_image_editor($image_path);

        if (is_wp_error($image_editor)) {
            return;
        }

        $resize_result = $image_editor->resize($this->wanted_size['width'], $this->wanted_size['height'], $this->wanted_size['crop']);

        if (is_wp_error($resize_result)) {
            return;
        }
        
        $generated_filename = $image_editor->generate_filename();
        $result_filename    = $this->modify_filename($generated_filename);
        $save_result        = $image_editor->save($result_filename);
        
        if (!$save_result || is_wp_error($save_result)) {
            return;
        }

        /**
         * Let's keep the `path` from the metadata. We will use this path to get the 
         * image URL and to delete the image.
         * {@see Fr_Thumbnails_Folder_Image_Sizes::get_image_size_url()}
         * {@see Fr_Thumbnails_Folder_Image_Sizes::delete_image_sizes()}
         * {@see Fr_Thumbnails_Folder_Image_Sizes::delete_all_image_sizes()}
         * 
         * The original behavior removes the `path`. {@see image_make_intermediate_size()}
         */ 
        $this->metadata['sizes'][$this->args['size']] = $save_result;

        $metadata_result = wp_update_attachment_metadata($this->args['id'], $this->metadata);

        if (!$metadata_result) {
            return;
        }
        
        $result_url = fr_thumbnails_folder()->get_image_sizes()->get_image_size_url($this->args['id'], $this->args['size']);
        
        if (!$result_url) {
            return;
        }
        
        return array(
            $result_url,
            $save_result['width'],
            $save_result['height'],
            true,
        );
    }
    
    
    /**
     * Modify intermediate image size file name to move its location.
     * 
     * @since 1.0.0
     */
    protected function modify_filename($generated_filename) {
        $upload_dir = wp_get_upload_dir();
        
        if (!$upload_dir) {
            return $generated_filename;
        }
        
        $sizes_folder   = fr_thumbnails_folder()->get_image_sizes()->get_image_sizes_folder();
        $new_dir        = path_join($upload_dir['basedir'], $sizes_folder);
        $new_filename   = str_replace($upload_dir['basedir'], $new_dir, $generated_filename);
        
        return $new_filename;
    }
}
