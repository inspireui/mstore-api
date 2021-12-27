<?php

class ProductManagementHelper
{
    public function sendError($code, $message, $statusCode)
    {
        return new WP_Error($code, $message, [
            "status" => $statusCode,
        ]);
    }

    protected function get_product_item($id)
    {
        if (!wc_get_product($id)) {
            return $this->sendError(
                "invalid_product",
                "This product does not exist",
                404
            );
        }
        return wc_get_product($id);
    }

    protected function upload_image_from_mobile($image, $count, $user_id)
    {
        require_once ABSPATH . "wp-admin" . "/includes/file.php";
        require_once ABSPATH . "wp-admin" . "/includes/image.php";
        $imgdata = $image;
        $imgdata = trim($imgdata);
        $imgdata = str_replace("data:image/png;base64,", "", $imgdata);
        $imgdata = str_replace("data:image/jpg;base64,", "", $imgdata);
        $imgdata = str_replace("data:image/jpeg;base64,", "", $imgdata);
        $imgdata = str_replace("data:image/gif;base64,", "", $imgdata);
        $imgdata = str_replace(" ", "+", $imgdata);
        $imgdata = base64_decode($imgdata);
        $f = finfo_open();
        $mime_type = finfo_buffer($f, $imgdata, FILEINFO_MIME_TYPE);
        $type_file = explode("/", $mime_type);
        $avatar = time() . "_" . $count . "." . $type_file[1];

        $uploaddir = wp_upload_dir();
        $myDirPath = $uploaddir["path"];
        $myDirUrl = $uploaddir["url"];

        file_put_contents($uploaddir["path"] . "/" . $avatar, $imgdata);

        $filename = $myDirUrl . "/" . basename($avatar);
        $wp_filetype = wp_check_filetype(basename($filename), null);
        $uploadfile = $uploaddir["path"] . "/" . basename($filename);

        $attachment = [
            "post_mime_type" => $wp_filetype["type"],
            "post_title" => preg_replace("/\.[^.]+$/", "", basename($filename)),
            "post_content" => "",
            "post_author" => $user_id,
            "post_status" => "inherit",
            "guid" => $myDirUrl . "/" . basename($filename),
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploadfile);
        $attach_data = apply_filters(
            "wp_generate_attachment_metadata",
            $attachment,
            $attachment_id,
            "create"
        );
        // $attach_data = wp_generate_attachment_metadata($attachment_id, $uploadfile);
        wp_update_attachment_metadata($attachment_id, $attach_data);
        return $attachment_id;
    }

    protected function find_image_id($image)
    {
        $image_id = attachment_url_to_postid(stripslashes($image));
        return $image_id;
    }

    protected function http_check($url)
    {
        if (
            !(substr($url, 0, 7) == "http://") &&
            !(substr($url, 0, 8) == "https://")
        ) {
            return false;
        }
        return true;
    }

    protected function get_attribute_taxonomy_name($slug, $product)
    {
        $attributes = $product->get_attributes();

        if (!isset($attributes[$slug])) {
            return str_replace("pa_", "", $slug);
        }

        $attribute = $attributes[$slug];

        // Taxonomy attribute name.
        if ($attribute->is_taxonomy()) {
            $taxonomy = $attribute->get_taxonomy_object();
            return $taxonomy->attribute_label;
        }

        // Custom product attribute name.
        return $attribute->get_name();
    }

    protected function get_attribute_options($product_id, $attribute)
    {
        if (isset($attribute["is_taxonomy"]) && $attribute["is_taxonomy"]) {
            return wc_get_product_terms($product_id, $attribute["name"], [
                "fields" => "names",
            ]);
        } elseif (isset($attribute["value"])) {
            return array_map("trim", explode("|", $attribute["value"]));
        }

        return [];
    }

    protected function get_attribute_slugs($product_id, $attribute)
    {
        if (isset($attribute["is_taxonomy"]) && $attribute["is_taxonomy"]) {
            return wc_get_product_terms($product_id, $attribute["name"], [
                "fields" => "slugs",
            ]);
        } elseif (isset($attribute["value"])) {
			$arr = explode("|", $attribute["value"]);
			$data = array();
			foreach($arr as $item){
				$data[] = str_replace('-',' ',trim($item)) ;
			}
            return $data;
        }

        return [];
    }

    public function get_products($request, $user_id)
    {
        global $wpdb;
        $page = isset($request["page"]) ? sanitize_text_field($request["page"])  : 1;
        $limit = isset($request["per_page"]) ? sanitize_text_field($request["per_page"]) : 10;
        if(!is_numeric($page)){
            $page = 1;
        }
        if(!is_numeric($limit)){
            $limit = 10;
        }
        if ($page >= 1) {
            $page = ($page - 1) * $limit;
        }

        if ($user_id) {
            $vendor_id = absint($user_id);
        }

        $table_name = $wpdb->prefix . "posts";
        $sql = "SELECT * FROM `$table_name` WHERE `$table_name`.`post_author` = $vendor_id AND `$table_name`.`post_type` = 'product' AND `$table_name`.`post_status` != 'trash'";

        if (isset($request["search"])) {
            $search =  sanitize_text_field($request["search"]);
            $search = "%$search%";
            $sql .= " AND (`$table_name`.`post_content` LIKE '$search' OR `$table_name`.`post_title` LIKE '$search' OR `$table_name`.`post_excerpt` LIKE '$search')";
        }
        $sql .= " ORDER BY `ID` DESC LIMIT $limit OFFSET $page";

        $item = $wpdb->get_results($sql);

        $products_arr = [];
        foreach ($item as $pro) {
            $product = wc_get_product($pro->ID);
            $p = $product->get_data();
            $image_arr = [];
            foreach (array_filter($p["gallery_image_ids"]) as $img) {
                $image = wp_get_attachment_image_src($img, "full");
                if (!is_null($image[0])) {
                    $image_arr[] = $image[0];
                }
            }

            $image = wp_get_attachment_image_src($p["image_id"], "full");
            if (!is_null($image[0])) {
                $p["featured_image"] = $image[0];
            }

            $p["images"] = $image_arr;
            $p["category_ids"] = [];
            $category_ids = wp_get_post_terms($p["id"], "product_cat");
            foreach ($category_ids as $cat) {
                if ($cat->slug != "uncategorized") {
                    $p["category_ids"][] = $cat->term_id;
                }
            }
            $p["type"] = $product->get_type();
            $p["on_sale"] = $product->is_on_sale();
            $p["tags"] = wp_get_post_terms($product->get_id(), "product_tag");

            $attributes = [];
            foreach ($product->get_attributes() as $attribute) {
                $attributes[] = [
                    "id" => $attribute["is_taxonomy"]
                        ? wc_attribute_taxonomy_id_by_name($attribute["name"])
                        : 0,
                    "name" =>
                        0 === strpos($attribute["name"], "pa_")
                            ? get_taxonomy($attribute["name"])->labels
                            ->singular_name
                            : $attribute["name"],
                    "position" => (int)$attribute["position"],
                    "visible" => (bool)$attribute["is_visible"],
                    "variation" => (bool)$attribute["is_variation"],
                    "options" => $this->get_attribute_options(
                        $product->get_id(),
                        $attribute
                    ),
                    "slugs" => $this->get_attribute_slugs(
                        $product->get_id(),
                        $attribute
                    ),
                    "default" => 0 === strpos($attribute["name"], "pa_"),
                    "slug" => str_replace(' ','-',$attribute["name"]),
                ];
            }
            $p["attributesData"] = $attributes;
            if ($product->get_type() == "variable") {
                $result = [];
                $p['min_price'] = $product->get_variation_price();
                $p['max_price'] = $product->get_variation_price('max');
                if(!$p['min_price']){
                    $p['min_price'] = '0';
                }
                if(!$p['max_price']){
                    $p['max_price'] = '0';
                }
                $query = [
                    "post_parent" => $product->get_id(),
                    "post_status" => ["publish", "private"],
                    "post_type" => ["product_variation"],
                    "posts_per_page" => -1,
                ];

                $wc_query = new WP_Query($query);
                while ($wc_query->have_posts()):
                    $wc_query->next_post();
                    $result[] = $wc_query->post;
                endwhile;

                foreach ($result as $variation) {
                    $p_varation = new WC_Product_Variation($variation->ID);
                    $dataVariation = array();
                    $dataVariation["variation_id"] = $p_varation->get_id();
                    $dataVariation["max_qty"] = $p_varation->get_stock_quantity();
                    $dataVariation["variation_is_active"] =
                        $p_varation->get_status() == "publish";
                    $dataVariation["display_price"] = $p_varation->get_sale_price();
                    $dataVariation["display_regular_price"] = $p_varation->get_regular_price();
                    $dataVariation["slugs"] = $p_varation->get_attributes();
                    $dataVariation["manage_stock"] = $p_varation->get_manage_stock();
                    $attributes = $p_varation->get_attributes();
                    $dataVariation["attributes"] = [];
                    foreach ($dataVariation["slugs"] as $key => $value) {
                        foreach ($p["attributesData"] as $item) {
                            if ($item["slug"] === $key) {
                                for ($i = 0; $i < count($item["slugs"]); $i++) {
                                    if ($value === $item["slugs"][$i]) {
                                        $dataVariation["attributes"][$key] =
                                            $item["options"][$i];
                                        break;
                                    }
                                }
                                break;
                            }
                        }
                    }

                    $p["variable_products"][] = $dataVariation;
                }
            }
            $products_arr[] = $p;
        }

        return apply_filters(
            "get_products",
            $products_arr,
            $request,
            $user_id
        );
    }

    /// CREATE ///
    public function create_product($request, $user_id)
    {
        $user = get_userdata($user_id);
        $isSeller = in_array("wcfm_vendor", $user->roles);

        $requestStatus = "draft";
        if ($request["status"] != null) {
            $requestStatus = sanitize_text_field($request["status"]);
        }

        $name = sanitize_text_field($request["name"]);
        $description = sanitize_text_field($request["description"]);
        $short_description = sanitize_text_field($request["short_description"]);
        $featured_image = sanitize_text_field($request['featuredImage']);
        $product_images = sanitize_text_field($request['images']);
        $type = sanitize_text_field($request['type']);
        $tags = sanitize_text_field($request['tags']);
        $featured = sanitize_text_field($request['featured']);
        $regular_price = sanitize_text_field($request['regular_price']);
        $sale_price = sanitize_text_field($request['sale_price']);
        $date_on_sale_from = sanitize_text_field($request['date_on_sale_from']);
        $date_on_sale_from_gmt = sanitize_text_field($request['date_on_sale_from_gmt']);
        $date_on_sale_to = sanitize_text_field($request['date_on_sale_to']);
        $date_on_sale_to_gmt = sanitize_text_field($request['date_on_sale_to_gmt']);
        $in_stock = sanitize_text_field($request['in_stock']);
        $stock_quantity = sanitize_text_field($request['stock_quantity']);
        $manage_stock  = sanitize_text_field($request['manage_stock']);
        $backorders = sanitize_text_field($request['backorders']);
        $categories = sanitize_text_field($request['categories']);
        $productAttributes = sanitize_text_field($request['productAttributes']);
        $variations = sanitize_text_field($request['variations']);      
        $inventory_delta = sanitize_text_field($request['inventory_delta']);      

        $count = 1;

        if ($isSeller) {
            $args = [
                "post_author" => $user_id,
                "post_content" => $description,
                "post_status" => $requestStatus, // (Draft | Pending | Publish)
                "post_title" => $name,
                "post_parent" => "",
                "post_type" => "product",
            ];
            // Create a simple WooCommerce product
            $post_id = wp_insert_post($args);
            $product = wc_get_product($post_id);

            if ($product->get_type() != $request["type"]) {
                // Get the correct product classname from the new product type
                $product_classname = WC_Product_Factory::get_product_classname(
                    $product->get_id(),
                    $type
                );

                // Get the new product object from the correct classname
                $product = new $product_classname($product->get_id());
                $product->save();
            }
            if (isset($featured_image)) {
                if (!empty($featured_image)) {
                    if ($this->http_check($featured_image)) {
                        $featured_image_id = $this->find_image_id(
                            $featured_image
                        );
                        $product->set_image_id($featured_image_id);
                    } else {
                        $featured_image_id = $this->upload_image_from_mobile(
                            $featured_image,
                            $count,
                            $user_id
                        );
                        $product->set_image_id($featured_image_id);
                        $count = $count + 1;
                    }
                } else {
                    $product->set_image_id("");
                }
            }

            if (isset($product_images)) {
                $product_images_array = array_filter(
                    explode(",", $product_images)
                );
                $img_array = [];

                foreach ($product_images_array as $p_img) {
                    if (!empty($p_img)) {
                        if ($this->http_check($p_img)) {
                            $img_id = $this->find_image_id($p_img);
                            array_push($img_array, $img_id);
                        } else {
                            $img_id = $this->upload_image_from_mobile(
                                $p_img,
                                $count,
                                $user_id
                            );
                            array_push($img_array, $img_id);
                            $count = $count + 1;
                        }
                    }
                }
                $product->set_gallery_image_ids($img_array);
            }

            if (isset($tags)) {
                $tags = array_filter(explode(",", $tags));
                wp_set_object_terms($post_id, $tags, "product_tag");
            }

            /// Set attributes to product
            if (isset($product) && !is_wp_error($product)) {
                if (isset($name)) {
                    $product->set_name(wp_filter_post_kses($name));
                }
                // Featured Product.
                if (isset($featured)) {
                    $product->set_featured($featured);
                }
                // SKU.
                if (isset($request["sku"])) {
                    $product->set_sku(wc_clean($request["sku"]));
                }

                // Sales and prices.
                if (
                    in_array(
                        $product->get_type(),
                        ["variable", "grouped"],
                        true
                    )
                ) {
                    $product->set_regular_price("");
                    $product->set_sale_price("");
                    $product->set_date_on_sale_to("");
                    $product->set_date_on_sale_from("");
                    $product->set_price("");
                } else {
                      // Regular Price.
                      if (isset($regular_price)) {
                        $product->set_regular_price($regular_price);
                    }
                    // Sale Price.
                    if (isset($sale_price) && !empty($sale_price)) {
                        $product->set_sale_price($sale_price);
                    }
                    if (isset($date_on_sale_from)) {
                        $product->set_date_on_sale_from($date_on_sale_from);
                    }
                    if (isset($date_on_sale_from_gmt)) {
                        $product->set_date_on_sale_from($date_on_sale_from_gmt ? strtotime($date_on_sale_from_gmt) : null);
                    }

                    if (isset($date_on_sale_to)) {
                        $product->set_date_on_sale_to($date_on_sale_to);
                    }

                    if (isset($date_on_sale_to_gmt)) {
                        $product->set_date_on_sale_to($date_on_sale_to_gmt ? strtotime($date_on_sale_to_gmt) : null);
                    }

                }

                // Description
                if (isset($description)) {
                    $product->set_description($description);
                }
                if (isset($short_description)) {
                    $product->set_description($short_description);
                }

                // Stock status.
                if (isset($in_stock) && is_bool($in_stock)) {
                    $stock_status = true === $in_stock ? 'instock' : 'outofstock';
                } else {
                    $stock_status = $product->get_stock_status();
                }

                // Stock data.
                if ("yes" === get_option("woocommerce_manage_stock")) {
                    // Manage stock.
                    if (isset($manage_stock)) {
                        $product->set_manage_stock($manage_stock);
                    }

                    // Backorders.
                    if (isset($backorders)) {
                        $product->set_backorders($backorders);
                    }

                    if ($product->is_type("grouped")) {
                        $product->set_manage_stock("no");
                        $product->set_backorders("no");
                        $product->set_stock_quantity("");
                        $product->set_stock_status($stock_status);
                    } elseif ($product->is_type("external")) {
                        $product->set_manage_stock("no");
                        $product->set_backorders("no");
                        $product->set_stock_quantity("");
                        $product->set_stock_status("instock");
                    } elseif ($product->get_manage_stock()) {
                        // Stock status is always determined by children so sync later.
                        if (!$product->is_type('variable')) {
                            $product->set_stock_status($stock_status);
                        }

                        // Stock quantity.
                        if (isset($stock_quantity)) {
                            $product->set_stock_quantity(wc_stock_amount($stock_quantity));
                        } elseif (isset($inventory_delta)) {
                            $stock_quantity = wc_stock_amount($product->get_stock_quantity());
                            $stock_quantity += wc_stock_amount($inventory_delta);
                            $product->set_stock_quantity(wc_stock_amount($stock_quantity));
                        }
                    } else {
                        // Don't manage stock.
                        $product->set_manage_stock("no");
                        $product->set_stock_quantity("");
                        $product->set_stock_status($stock_status);
                    }
                } elseif (!$product->is_type("variable")) {
                    $product->set_stock_status($stock_status);
                }

                //Assign categories
                if (isset($categories)) {
                    $categories = array_filter(explode(',', $categories));
                    if (!empty($categories)) {
                        $categoryArray = array();
                        foreach ($categories as $index) {
                            $categoryArray[] = absint($index);
                        }
                        $product->set_category_ids($categoryArray);
                    }
                }


                //Description
                $product->set_short_description($short_description);
                $product->set_description($description);
                $attribute_json = json_decode($productAttributes, true);
                $pro_attributes = [];
                foreach ($attribute_json as $key => $value) {
                    if ($value["isActive"]) {
                        $attribute_name = strtolower($value["slug"]);
                        if ($value["default"]) {
                            $attribute_name = strtolower(
                                "pa_" . $value["slug"]
                            );
                        }
                        $attribute_id = wc_attribute_taxonomy_id_by_name(
                            $attribute_name
                        );
                        $attribute = new WC_Product_Attribute();
                        $attribute->set_id($attribute_id);
                        $attribute->set_name(wc_clean($attribute_name));
                        $options = $value["options"];
                        $attribute->set_options($options);
                        $attribute->set_visible($value["visible"]);
                        $attribute->set_variation($value["variation"]);
                        $pro_attributes[] = $attribute;
                    }
                }

                $product->set_props([
                    "attributes" => $pro_attributes,
                ]);
                if (is_wp_error($product)) {
                    return $this->sendError("request_failed", "Bad data", 400);
                }

                $product->save();

                if ($product->get_type() == "variable") {
                    $variations_arr = json_decode($variations, true);
                    foreach ($variations_arr as $variation) {
                        // Creating the product variation
                        $variation_post = [
                            "post_title" => $product->get_title(),
                            "post_name" =>
                                "product-" . $product->get_id() . "-variation",
                            "post_status" => "publish",
                            "post_parent" => $product->get_id(),
                            "post_type" => "product_variation",
                            "guid" => $product->get_permalink(),
                        ];
                        $variation_id = wp_insert_post($variation_post);
                        foreach ($variation["slugs"] as $key => $value) {
                            $variationAttrArr[$key] = strtolower(
                                strval($value)
                            );
                        }
                        $variationProduct = new WC_Product_Variation(
                            $variation_id
                        );
                        $variationProduct->set_regular_price(
                            $variation["display_regular_price"]
                        );
                        $variationProduct->set_sale_price(
                            $variation["display_price"]
                        );
                        $variationProduct->set_stock_quantity(
                            $variation["max_qty"]
                        );
                        $variationProduct->set_attributes($variationAttrArr);
                        $variationProduct->set_manage_stock(
                            boolval($variation["manage_stock"])
                        );
                        $variationProduct->set_status(
                            $variation["variation_is_active"]
                                ? "publish"
                                : "private"
                        );
                        $variationProduct->save();
                    }
                }

                wp_update_post([
                    "ID" => $product->get_id(),
                    "post_author" => $user_id,
                ]);
                //print_r($product);
                $image_arr = [];
                $p = $product->get_data();
                foreach (array_filter($p["gallery_image_ids"]) as $img) {
                    $image = wp_get_attachment_image_src($img, "full");

                    if (!is_null($image[0])) {
                        $image_arr[] = $image[0];
                    }
                }
                $p["description"] = strip_tags($p["description"]);
                $p["short_description"] = strip_tags($p["short_description"]);
                $p["images"] = $image_arr;
                $image = wp_get_attachment_image_src($p["image_id"], "full");
                if (!is_null($image[0])) {
                    $p["featured_image"] = $image[0];
                }
                $p["type"] = $product->get_type();
                $p["on_sale"] = $product->is_on_sale();
                if ($product->get_type() == "variable") {
                    $query = [
                        "post_parent" => $product->get_id(),
                        "post_status" => ["publish", "private"],
                        "post_type" => ["product_variation"],
                        "posts_per_page" => -1,
                    ];

                    $wc_query = new WP_Query($query);
                    while ($wc_query->have_posts()) {
                        $wc_query->next_post();
                        $result[] = $wc_query->post;
                    }

                    foreach ($result as $variation) {
                        $p_varation = new WC_Product_Variation($variation->ID);
                        $dataVariation = array();
                        $dataVariation["variation_id"] = $p_varation->get_id();
                        $dataVariation["max_qty"] = $p_varation->get_stock_quantity();
                        $dataVariation["variation_is_active"] =
                            $p_varation->get_status() == "publish";
                        $dataVariation["display_price"] = $p_varation->get_sale_price();
                        $dataVariation["display_regular_price"] = $p_varation->get_regular_price();
                        $dataVariation["attributes"] = $p_varation->get_attributes();
                        $dataVariation["manage_stock"] = $p_varation->get_manage_stock();
                        $p["variable_products"][] = $dataVariation;
                    }
                }
                return new WP_REST_Response(
                    [
                        "status" => "success",
                        "response" => $p,
                    ],
                    200
                );
            }
        } else {
            return $this->sendError(
                "invalid_role",
                "You must be seller to create product",
                401
            );
        }
    }

    /// UPDATE ///
    public function update_product($request, $user_id)
    {
        $id = isset($request['id']) ? $request['id'] : 0;
        if (isset($id) && is_numeric($id)) {
            $product = $this->get_product_item($id);
        } else {
            return $this->sendError("request_failed", "Invalid data", 400);
        }

        /// Validate requested user_id and product_id
        $post_obj = get_post($product->get_id());
        $author_id = $post_obj->post_author;
        if ($user_id != $author_id) {
            return $this->sendError(
                "unauthorized",
                "You are not allow to do this",
                401
            );
        }

        $name = sanitize_text_field($request["name"]);
        $description = sanitize_text_field($request["description"]);
        $short_description = sanitize_text_field($request["short_description"]);
        $featured_image = sanitize_text_field($request['featuredImage']);
        $product_images = sanitize_text_field($request['images']);
        $type = sanitize_text_field($request['type']);
        $tags = sanitize_text_field($request['tags']);
        $featured = sanitize_text_field($request['featured']);
        $regular_price = sanitize_text_field($request['regular_price']);
        $sale_price = sanitize_text_field($request['sale_price']);
        $date_on_sale_from = sanitize_text_field($request['date_on_sale_from']);
        $date_on_sale_from_gmt = sanitize_text_field($request['date_on_sale_from_gmt']);
        $date_on_sale_to = sanitize_text_field($request['date_on_sale_to']);
        $date_on_sale_to_gmt = sanitize_text_field($request['date_on_sale_to_gmt']);
        $in_stock = sanitize_text_field($request['in_stock']);
        $stock_quantity = sanitize_text_field($request['stock_quantity']);
        $manage_stock  = sanitize_text_field($request['manage_stock']);
        $backorders = sanitize_text_field($request['backorders']);
        $categories = sanitize_text_field($request['categories']);
        $productAttributes = sanitize_text_field($request['productAttributes']);
        $variations = sanitize_text_field($request['variations']);      
        $inventory_delta = sanitize_text_field($request['inventory_delta']);     
        $status = sanitize_text_field($request['status']);     
        $count = 1;

        if ($product->get_type() != $type) {
            // Get the correct product classname from the new product type
            $product_classname = WC_Product_Factory::get_product_classname(
                $product->get_id(),
                $type
            );

            // Get the new product object from the correct classname
            $product = new $product_classname($product->get_id());
            $product->save();
        }
        if (isset($tags)) {
            $tags = array_filter(explode(",", $tags));
            wp_set_object_terms($product->get_id(), $tags, "product_tag");
        }

    

        if (isset($featured_image)) {
            if (!empty($featured_image)) {
                if ($this->http_check($featured_image)) {
                    $featured_image_id = $this->find_image_id($featured_image);
                    $product->set_image_id($featured_image_id);
                } else {
                    $featured_image_id = $this->upload_image_from_mobile(
                        $featured_image,
                        $count,
                        $user_id
                    );
                    $product->set_image_id($featured_image_id);
                    $count = $count + 1;
                }
            } else {
                $product->set_image_id("");
            }
        }

        if (isset($product_images)) {
            $product_images_array = array_filter(explode(",", $product_images));
            $img_array = [];

            foreach ($product_images_array as $p_img) {
                if (!empty($p_img)) {
                    if ($this->http_check($p_img)) {
                        $img_id = $this->find_image_id($p_img);
                        array_push($img_array, $img_id);
                    } else {
                        $img_id = $this->upload_image_from_mobile(
                            $p_img,
                            $count,
                            $user_id
                        );
                        array_push($img_array, $img_id);
                        $count = $count + 1;
                    }
                }
            }
            $product->set_gallery_image_ids($img_array);
        }

        /// Set attributes to product
        if (isset($product) && !is_wp_error($product)) {
            if (isset($name)) {
                $product->set_name(wp_filter_post_kses($name));
            }
            // Featured Product.
            if (isset($featured)) {
                $product->set_featured($featured);
            }
            // SKU.
            if (isset($request['sku'])) {
                $product->set_sku(wc_clean($request['sku']));
            }

            // Sales and prices.
            $product->set_status($status);

            if (in_array($product->get_type(), ["variable", "grouped"], true)) {
                $product->set_regular_price("");
                $product->set_sale_price("");
                $product->set_date_on_sale_to("");
                $product->set_date_on_sale_from("");
                $product->set_price("");
            } else {
                // Regular Price.
                if (isset($regular_price)) {
                    $product->set_regular_price($regular_price);
                }
                // Sale Price.
                if (isset($sale_price) && !empty($sale_price)) {
                    $product->set_sale_price($sale_price);
                }
                if (isset($date_on_sale_from)) {
                    $product->set_date_on_sale_from($date_on_sale_from);
                }
                if (isset($date_on_sale_from_gmt)) {
                    $product->set_date_on_sale_from($date_on_sale_from_gmt ? strtotime($date_on_sale_from_gmt) : null);
                }

                if (isset($date_on_sale_to)) {
                    $product->set_date_on_sale_to($date_on_sale_to);
                }

                if (isset($date_on_sale_to_gmt)) {
                    $product->set_date_on_sale_to($date_on_sale_to_gmt ? strtotime($date_on_sale_to_gmt) : null);
                }
            }

            // Description
            if (isset($description)) {

                $product->set_description(strip_tags($description));
            }
            if (isset($short_description)) {
                $product->set_short_description(strip_tags($short_description));
            }

            // Stock status.
            if (isset($in_stock)) {
                $stock_status = true === $in_stock ? 'instock' : 'outofstock';
            } else {
                $stock_status = $product->get_stock_status();
            }

            // Stock data.
            if ("yes" === get_option("woocommerce_manage_stock")) {
                // Manage stock.
                if (isset($manage_stock)) {
                    $product->set_manage_stock($manage_stock);
                }

                // Backorders.
                if (isset($backorders)) {
                    $product->set_backorders($backorders);
                }

                if ($product->is_type("grouped")) {
                    $product->set_manage_stock("no");
                    $product->set_backorders("no");
                    $product->set_stock_quantity("");
                    $product->set_stock_status($stock_status);
                } elseif ($product->is_type("external")) {
                    $product->set_manage_stock("no");
                    $product->set_backorders("no");
                    $product->set_stock_quantity("");
                    $product->set_stock_status("instock");
                } elseif ($product->get_manage_stock()) {
                    // Stock status is always determined by children so sync later.
                    if (!$product->is_type("variable")) {
                        $product->set_stock_status($stock_status);
                    }

                    // Stock quantity.
                    if (isset($stock_quantity)) {
                        $product->set_stock_quantity(wc_stock_amount($stock_quantity));
                    } elseif (isset($request['inventory_delta'])) {
                        $stock_quantity = wc_stock_amount($product->get_stock_quantity());
                        $stock_quantity += wc_stock_amount($inventory_delta);
                        $product->set_stock_quantity(wc_stock_amount($stock_quantity));
                    }
                } else {
                    // Don't manage stock.
                    $product->set_manage_stock("no");
                    $product->set_stock_quantity("");
                    $product->set_stock_status($stock_status);
                }
            } elseif (!$product->is_type("variable")) {
                $product->set_stock_status($stock_status);
            }

            //Assign categories
            if (isset($categories)) {
                $categories = array_filter(explode(',', $categories));
                if (!empty($categories)) {
                    $categoryArray = array();
                    foreach ($categories as $index) {
                        $categoryArray[] = absint($index);
                    }
                    $product->set_category_ids($categoryArray);
                } else {
                    $product->set_category_ids(array());
                }
            }

            //Description
            $product->set_short_description($short_description);
            $product->set_description($description);
            if (is_wp_error($product)) {
                return $this->sendError("request_failed", "Bad data", 400);
            }

            $attribute_json = json_decode($productAttributes, true);
            $pro_attributes = [];
            foreach ($attribute_json as $key => $value) {
                if ($value["isActive"]) {
                    $attribute_name = strtolower($value["slug"]);
                    if ($value["default"]) {
                        $attribute_name = strtolower("pa_" . $value["slug"]);
                    }
                    $attribute_id = wc_attribute_taxonomy_id_by_name(
                        $attribute_name
                    );
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_id($attribute_id);
                    $attribute->set_name(wc_clean($attribute_name));
                    $options = $value["options"];
                    $attribute->set_options($options);
                    $attribute->set_visible($value["visible"]);
                    $attribute->set_variation($value["variation"]);
                    $pro_attributes[] = $attribute;
                }
            }

            $product->set_props([
                "attributes" => $pro_attributes,
            ]);
            $product->save();

            if ($product->is_type("variable")) {
                $variations_arr = json_decode($variations, true);
                foreach ($variations_arr as $variation) {
                    if ($variation["variation_id"] != -1) {
                        foreach ($variation["slugs"] as $key => $value) {
                            $variationAttrArr[$key] = strtolower(
                                strval($value)
                            );
                        }
                        $variationProduct = new WC_Product_Variation(
                            $variation["variation_id"]
                        );
                        $variationProduct->set_regular_price(
                            $variation["display_regular_price"]
                        );
                        $variationProduct->set_sale_price(
                            $variation["display_price"]
                        );
                        $variationProduct->set_stock_quantity(
                            $variation["max_qty"]
                        );
                        $variationProduct->set_attributes($variationAttrArr);
                        $variationProduct->set_manage_stock(
                            boolval($variation["manage_stock"])
                        );
                        $variationProduct->set_status(
                            $variation["variation_is_active"]
                                ? "publish"
                                : "private"
                        );
                        $variationProduct->save();
                    } else {
                        // Creating the product variation
                        $variation_post = [
                            "post_title" => $product->get_title(),
                            "post_name" =>
                                "product-" . $product->get_id() . "-variation",
                            "post_status" => "publish",
                            "post_parent" => $product->get_id(),
                            "post_type" => "product_variation",
                            "guid" => $product->get_permalink(),
                        ];
                        $variation_id = wp_insert_post($variation_post);
                        foreach ($variation["slugs"] as $key => $value) {
                            $variationAttrArr[$key] = strtolower(
                                strval($value)
                            );
                        }
                        $variationProduct = new WC_Product_Variation(
                            $variation_id
                        );
                        $variationProduct->set_regular_price(
                            $variation["display_regular_price"]
                        );
                        $variationProduct->set_sale_price(
                            $variation["display_price"]
                        );
                        $variationProduct->set_stock_quantity(
                            $variation["max_qty"]
                        );
                        $variationProduct->set_attributes($variationAttrArr);
                        $variationProduct->set_manage_stock(
                            boolval($variation["manage_stock"])
                        );
                        $variationProduct->set_status(
                            $variation["variation_is_active"]
                                ? "publish"
                                : "private"
                        );
                        $variationProduct->save();
                    }
                }
            }

            wp_update_post([
                "ID" => $product->get_id(),
                "post_author" => $user_id,
            ]);
            //print_r($product);
            $image_arr = [];
            $p = $product->get_data();

            foreach (array_filter($p["gallery_image_ids"]) as $img) {
                $image = wp_get_attachment_image_src($img, "full");

                if (!is_null($image[0])) {
                    $image_arr[] = $image[0];
                }
            }
            $p["description"] = strip_tags($p["description"]);
            $p["short_description"] = strip_tags($p["short_description"]);
            $p["images"] = $image_arr;
            $image = wp_get_attachment_image_src($p["image_id"], "full");
            if (!is_null($image[0])) {
                $p["featured_image"] = $image[0];
            }
            $p["type"] = $product->get_type();
            $p["on_sale"] = $product->is_on_sale();
            $attributes = [];
            foreach ($product->get_attributes() as $attribute) {
                $attributes[] = [
                    "id" => $attribute["is_taxonomy"]
                        ? wc_attribute_taxonomy_id_by_name($attribute["name"])
                        : 0,
                    "name" => $this->get_attribute_taxonomy_name(
                        $attribute["name"],
                        $product
                    ),
                    "position" => (int)$attribute["position"],
                    "visible" => (bool)$attribute["is_visible"],
                    "variation" => (bool)$attribute["is_variation"],
                    "options" => $this->get_attribute_options(
                        $product->get_id(),
                        $attribute
                    ),
                    "slugs" => $this->get_attribute_slugs(
                        $product->get_id(),
                        $attribute
                    ),
                    "default" => 0 === strpos($attribute["name"], "pa_"),
                ];
            }

            $p["attributesData"] = $attributes;
            if ($product->is_type("variable")) {
                $query = [
                    "post_parent" => $product->get_id(),
                    "post_status" => ["publish", "private"],
                    "post_type" => ["product_variation"],
                    "posts_per_page" => -1,
                ];

                $wc_query = new WP_Query($query);
                while ($wc_query->have_posts()) {
                    $wc_query->next_post();
                    $result[] = $wc_query->post;
                }

                foreach ($result as $variation) {
                    $p_varation = new WC_Product_Variation($variation->ID);
                    $dataVariation = array();
                    $dataVariation["variation_id"] = $p_varation->get_id();
                    $dataVariation["max_qty"] = $p_varation->get_stock_quantity();
                    $dataVariation["variation_is_active"] =
                        $p_varation->get_status() == "publish";
                    $dataVariation["display_price"] = $p_varation->get_sale_price();
                    $dataVariation["display_regular_price"] = $p_varation->get_regular_price();
                    $attributes = $p_varation->get_attributes();
                    foreach ($attributes as $attribute) {
                        $slugs[] = $attribute["value"];
                    }
                    $dataVariation["attributes"] = $attributes;
                    $dataVariation["slugs"] = $slugs;
                    $dataVariation["manage_stock"] = $p_varation->get_manage_stock();
                    $p["variable_products"][] = $dataVariation;
                }
            }
            return new WP_REST_Response(
                [
                    "status" => "success",
                    "response" => $p,
                ],
                200
            );
        }
    }

    /// DELETE ///
    public function delete_product($request, $user_id)
    {
        /// Validate product ID
        $id = isset($request['id']) ? $request['id'] : 0;
        if (isset($request['id']) && is_numeric($id)) {
            $product = $this->get_product_item($id);
        } else {
            return $this->sendError("request_failed", "Invalid data", 400);
        }
        /// Validate requested user_id and product_id
        $post_obj = get_post($product->get_id());
        $author_id = $post_obj->post_author;
        if ($user_id != $author_id) {
            return $this->sendError(
                "unauthorized",
                "You are not allow to do this",
                401
            );
        }
        wp_delete_post($product->get_id());
        return new WP_REST_Response(
            [
                "status" => "success",
                "response" => "",
            ],
            200
        );
    }
}
