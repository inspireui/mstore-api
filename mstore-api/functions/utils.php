<?php
class FlutterUtils {
    static $folder_path = 'flutter_config_files';
    static $old_folder_path = '2000/01';

    public static function create_json_folder(){
        $uploads_dir = wp_upload_dir();
        $folder = trailingslashit($uploads_dir["basedir"]) . FlutterUtils::$folder_path;
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
    }

    private static function get_folder_path($path){
        $uploads_dir = wp_upload_dir();
        $folder = trailingslashit($uploads_dir["basedir"]) . $path;
        return realpath($folder);
    }

    public static function get_json_folder(){
        return FlutterUtils::get_folder_path(FlutterUtils::$folder_path);
    }

    public static function get_old_json_folder(){
        return FlutterUtils::get_folder_path(FlutterUtils::$old_folder_path);
    }

    public static function get_json_file_url($file_name){
        $uploads_dir = wp_upload_dir();
        $folder = trailingslashit($uploads_dir["baseurl"]) . FlutterUtils::$folder_path;
        return trailingslashit($folder) . $file_name;
    }

    public static function get_json_file_path($file_name){
        return trailingslashit(FlutterUtils::get_json_folder()). $file_name;
    }

    public static function migrate_json_files(){
        $files = scandir(FlutterUtils::get_old_json_folder());
        foreach ($files as $file) {
            if (strpos($file, "config") !== false && strpos($file, ".json") !== false) {
                $old_path = trailingslashit(FlutterUtils::get_old_json_folder()). $file;
                $new_path = FlutterUtils::get_json_file_path($file);
                if (rename($old_path, $new_path)) {
                    unlink($old_path); 
                }
            }
        }
    }

    public static function upload_file_by_admin($file_to_upload) {
        $file_name = $file_to_upload['name'];
        //validate file name
        preg_match('/config_[a-z]{2}.json/',$file_name, $output_array);
        if (count($output_array) == 0) {
            return 'You need to upload config_xx.json file';
        }else{
          $source      = $file_to_upload['tmp_name'];
          $fileContent = file_get_contents($source);
          $array = json_decode($fileContent, true);
          if($array){ //validate json file
            wp_upload_bits($file_name, null, file_get_contents($source)); 
            $destination = FlutterUtils::get_json_file_path($file_name);
            FlutterUtils::create_json_folder();
            move_uploaded_file($source, $destination);
            return null;
          }else{
            return 'You need to upload config_xx.json file';
          }
        }
    }

    public static function delete_config_file($id, $nonce){
        if(strlen($id) == 2){
            if (wp_verify_nonce($nonce, 'delete_config_json_file')) {
                $filePath = FlutterUtils::get_json_file_path("config_".$id.".json");
                unlink($filePath);
                echo "success";
                die();
            }
        }
    }
}
?>