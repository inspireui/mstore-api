<?php

/*
 * Base REST Controller for mstore
 *
 * @since 1.0.0
 *
 * @package home
 */

define("kHorizontalLayout", ["twoColumn", "threeColumn", "fourColumn", "sliderList", "largeCard", "staggered", "card"]);

class MStoreHome extends WP_REST_Controller
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_filter ( 'woocommerce_rest_check_permissions', array($this, 'checkPermissions'),99 );
    }

    public function checkPermissions(){
        return true;
    }

    public function register_routes()
    {

        register_rest_route("mstore", '/home' , array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_home_data'),
            ),
        ));

    }

    public function get_home_data()
    {
        $api = new WC_REST_Products_Controller();
        $request = new WP_REST_Request('GET');
        global $json_api;
        $path = dirname(dirname(__FILE__))."/templates/config.json";
        $test = [];
        if (file_exists($path)) {
            $fileContent = file_get_contents($path);
            $array = json_decode($fileContent, true);

            //get products for horizontal layout
            $results = [];
            $horizontalLayout = $array["HorizonLayout"];
            foreach ($horizontalLayout as $layout) {
                if (in_array($layout["layout"], kHorizontalLayout) && isset($layout['category'])) {
                    $request->set_query_params(array('category'=>$layout['category']));
                    $response = $api->get_items($request);
                    $layout["data"] = $response->get_data();
                    $results[] = $layout;
                }else{
                    $results[] = $layout;
                }
            }
            $array['HorizonLayout'] = $results;

            //get products for vertical layout
            $layout = $array["VerticalLayout"];
            if (isset($layout['category'])) {
                $request->set_query_params(array('category'=>$layout['category']));
                $response = $api->get_items($request);
                $layout["data"] = $response->get_data();
                $array['VerticalLayout'] = $layout;
            }
            
            return $array;
        }else{
            $json_api->error("Config file hasn't been uploaded yet.");
        }
    }
    
}

new MStoreHome;
