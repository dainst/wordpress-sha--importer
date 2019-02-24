<?php


namespace shap_datasource {

    use mysql_xdevapi\Exception;

    class shap_easydb extends abstract_datasource {

        public $debug = false;

        public $force_curl = true;

        private $_session_token;

        private $_easydb_url = "";
        private $_easydb_user = "";
        private $_easydb_pass = "";

        private $_language_map = array(
            'ar' => 'ar',
            'de' => "de-DE",
            'en' => "en-US"
        );

        /**
         * shap_easydb constructor.
         */
        function __construct() {
            $this->_easydb_url  = get_option('shap_db_url');
            $this->_easydb_user = get_option('shap_db_user');
            $this->_easydb_pass = get_option('shap_db_pass');

            require_once(ABSPATH . 'wp-admin/includes/image.php');

            require_once(realpath(plugin_dir_path(__FILE__) . '/../../sitepress-multilingual-cms/inc/wpml-api.php'));

            $this->_set_primary_language_first();
        }

        /**
         * @return string
         * @throws \Exception
         */
        function dependency_check() : string {
            if (!$this->check_for_curl()) {
                throw new \Exception('PHP Curl extension not installed');
            }
            $this->get_easy_db_session_token();
            return 'O. K.';
        }

        /**
         * @param $msg
         * @return string
         */
        function parse_error_response($msg) : string {
            $json_msg = json_decode($msg);
            if (is_object($json_msg)) {
                return isset($json_msg->description) ? $json_msg->description : $json_msg->code;
            }
            return $msg;
        }

        /**
         * @return string
         * @throws \Exception
         */
        function get_easy_db_session_token() : string {
            if ($this->_session_token) {
                return $this->_session_token;
            }
            try {
                $resp = json_decode($this->_fetch_external_data("{$this->_easydb_url}/api/v1/session"));
                if (!isset($resp->token)) {
                    throw new \Exception('no token');
                }
                $this->_session_token = $resp->token;
            } catch (\Exception $e) {
                throw new \Exception('Easy-DB: create session failed: ' . $this->parse_error_response($e));
            }
            try {

                if (!$this->_easydb_url or !$this->_easydb_pass or !$this->_easydb_user) {
                    $credentials = "method=anonymous";
                } else {
                    $credentials = "login={$this->_easydb_user}&password={$this->_easydb_pass}";
                }

                $this->_fetch_external_data((object) array(
                    "url" => "{$this->_easydb_url}/api/v1/session/authenticate?token={$this->_session_token}&$credentials",
                    "method" => "post"
                ));
            } catch (\Exception $e) {
                throw new \Exception('Easy-DB: authentication failed: ' . $this->parse_error_response($e));
            }
            return $this->_session_token;
        }

        /**
         * @param $object_id
         * @param array $params
         * @return string
         * @throws \Exception
         */
        function api_single_url($object_id, $params = array()) : string {
            $this->get_easy_db_session_token();
            return "{$this->_easydb_url}/api/v1/db/bilder/bilder__all_fields/global_object_id/$object_id@local?token={$this->_session_token}";
        }

        /**
         * @param $object_id
         * @return string
         */
        function api_place_url($object_id) {
            return "{$this->_easydb_url}/api/v1/db/ortsthesaurus/ortsthesaurus__l/global_object_id/$object_id@local?token={$this->_session_token}";
        }

        /**
         * @param $id
         * @param array $params
         * @return string
         */
        function api_record_url($id, $params = array()) : string {
            return "{$this->_easydb_url}/lists/bilder/id/global_object_id/$id";
        }

        /**
         * @param int $page
         * @return object
         * @throws \Exception
         */
        function api_fetch_url(int $page = 0) {

            $this->get_easy_db_session_token();

            $search = array(
                "limit" => $this->items_per_page,
                "objecttypes" => array("bilder"),
	            "generate_rights" => false,
                "sort" => array(
                    array(
                        "field" =>"_system_object_id"
                    )
                )
            );

            //            if (!in_array($query, array("", "*"))) {
            //                $search["search"] = array(
            //                    array(
            //                        "type" => "match",
            //                        "mode" => "token",
            //                        "string"=> $query,
            //                        "phrase"=> true
            //                    )
            //                );
            //            }


            $search['offset'] = $page * $this->items_per_page;

            return (object) array(
                'method' => 'post',
                'url' => "{$this->_easydb_url}/api/v1/search?token={$this->_session_token}",
                'post_json' => $search
            );
        }

        /**
         * @param array|object $response
         * @param bool $test
         * @return array
         */
        function parse_result_set($response, bool $test = false) : array {
            $response = $this->_json_decode($response);

            $this->pages = (int) ($response->count / $this->items_per_page) + 1;
            $this->page = isset($response->offset) ? ((int) ($response->offset / $this->items_per_page) + 1) : 1;

            if ($test) {
                return array();
            }

            $results = array();
            foreach ($response->objects as $item) {
                try {
                    $result_id = $this->parse_result($this->_fetch_external_data($this->api_single_url($item->_system_object_id)));
                    $results[] = $result_id;
                    $this->log("Successfull created post for #{$item->_system_object_id} : $result_id.", "success");
                } catch (\Exception $e) {
                    $results[] = null;
                    $this->error("Error importing #{$item->_system_object_id}:" . $e->getMessage());
                }
            }

            return $results;
        }

        /**
         * @param $response
         * @return string
         * @throws \Exception
         */
        function parse_result($response) {

            $json_response = $this->_json_decode($response);
            $system_object_id = $json_response[0]->_system_object_id;
            $object_type = $json_response[0]->_objecttype;
            $object = $json_response[0]->{$object_type};

            if ($object_type !== "bilder") {
                throw new \Exception("Object $system_object_id is not from Bilder!");
            }

            if (!isset($object->bild) or !isset($object->bild[0]->versions)) {
                throw new \Exception("No image section in object #$system_object_id");
            }

            $attachment = $this->_create_or_update_attachment($object, $system_object_id);

            $meta = $this->_init_meta();
            $tags = $this->_init_tags();
            $this->_parse_place($object, $meta);
            $this->_parse_field($object->copyright_vermerk, $meta, "copyright_vermerk");
            $this->_parse_nested($object, $tags);

//            $this->_parse_blocks($object, $data);
//            $this->_parse_date($object, $data);
//            $this->_parse_pool($object, $data);
//            $this->_parse_tags($json_response[0], $data);

//            $html = $image->render();

            $wp_terms = $this->_add_terms($attachment->ID, $tags);

            $this->_update_translations($attachment->ID, $object, $system_object_id, $meta, $wp_terms);

            return "<a href='/wp-admin/upload.php?item={$attachment->ID}'>Image {$attachment->ID} " . ($attachment->update ? 'updated' : 'inserted') . "</a>";
        }

        private function _init_meta() {
            $meta = array();
            foreach ($this->_language_map as $wp_language => $easydb_language) {
                $meta[$easydb_language] = array();
            }
            return $meta;
        }

        private function _init_tags() {
            global $shap_taxonomies;
            $tags = array();
            foreach ($shap_taxonomies as $taxonomy_name => $taxonomy_label) {
                $tags[$taxonomy_name] = array();
            }
            return $tags;
        }


        /**
         * @param $object
         * @param int $system_object_id
         * @return object
         * @throws \Exception
         */
        private function _create_or_update_attachment($object, int $system_object_id) {
            $duplicate_id = $this->_get_post($system_object_id);
            //$image_title = $object->bild[0]->original_filename;
            $image_info = $this->_get_best_image($object);
            $file_path = $this->download_image($image_info->url, "shap_import_$system_object_id.{$image_info->extension}");
            $file_type = wp_check_filetype(basename($file_path), null);
            $wp_upload_dir = wp_upload_dir();
            $attachment = array(
                'guid'           => $wp_upload_dir['url'] . '/' . basename($file_path),
                'post_mime_type' => $file_type['type'],
                'post_title'     => "[temporary title for #$system_object_id]",
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            if ($duplicate_id) {
                $attachment["ID"] = $duplicate_id;
            }

            $attach_id = wp_insert_attachment($attachment, $file_path, 0, true);

            if ($this->_is_error($attach_id)) {
                throw new \Exception("Wordpress Error: Could not create attachment for #$system_object_id: ");
            }

            $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
            wp_update_attachment_metadata($attach_id, $attach_data);
            add_post_meta($attach_id, "_shap_easydb_id", $system_object_id, true);

            return (object) array(
                "update" => !!$duplicate_id,
                "ID" => $attach_id
            );
        }

        /**
         * looks up if the image is already imported and if yes, return it's ID
         * also checks if there are some copies of the image wich should not exist
         *
         * @param string $system_object_id
         * @return bool|mixed
         * @throws \Exception
         */
        private function _get_post(string $system_object_id) : int {
            $args = array(
                'post_type' => 'attachment',
                'meta_query' => array(
                    array(
                        'key' => '_shap_easydb_id',
                        'value' => (string) $system_object_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => '_shap_easydb_id',
                        'value' => (string) $system_object_id,
                        'compare' => '='
                    )
                )
            );

            $possible_duplicates = get_posts($args);

            $duplicate = count($possible_duplicates) ? array_pop($possible_duplicates) : false;

            if (count($possible_duplicates)) {
                foreach ($possible_duplicates as $illegal_duplicate) {
                    $this->error("Post {$illegal_duplicate->ID} is an illegal duplicate and get deleted");
                    if (false === wp_delete_post($illegal_duplicate->ID, true)) {
                        throw new \Exception("Illegal duplicate {$illegal_duplicate->ID} could not be deleted");
                    }

                }
            }

            return $duplicate ? $duplicate->ID : 0;
        }

        /**
         * @param $o
         * @return mixed
         * @throws \Exception
         */
        private function _get_best_image($o) {
            $versions = array_filter((array) $o->bild[0]->versions, function ($v) {
                return ($v->status !== "failed") && (!$v->_not_allowed);
            });

            if (isset($versions['full'])) {
                $v = 'full';
            } else if (isset($versions['original'])) {
                $v = 'original';
            } else if (isset($versions['small'])) {
                $v = 'small';
            } else {
                throw new \Exception("Could not fetch Image (no version available)");
            }

            return $versions[$v];
        }

        /**
         * @param $o
         * @param string $language
         * @param array $fields
         * @param string $default
         * @return string
         */
        function _get_best_field($o, string $language, $fields, string $default = "") : string {

            foreach ($fields as $field) {
                if (isset($o->$field) and isset($o->$field->$language) and $o->$field->$language) {
                    return $o->$field->$language;
                }
            }

            return $default;
        }

//        function _parse_pool($o, \esa_item\data $data) {
//            if ($o->_pool->pool->_id == 1) {
//                return;
//            }
//
//            $data->putMultilang("pool", (array) $o->_pool->pool->name);
//        }

        /**
         * @param $o
         * @param array $tags target
         */
        private function _parse_nested($o, array &$tags) {
//
//            $to_parse = array(
//                "keyword"   =>  "schlagwort",
//                "element"   =>  "element",
//                "style"     =>  "stilmerkmal",
//                "tech"      =>  "technik",
//                "material"  =>  "material",
//            ); // skipped: teilelement, literatur

            $easydb_nested_to_taxonomy = array(
                'schlagwort' => 'tags'
            );

            foreach ($easydb_nested_to_taxonomy as $easydb_nested => $taxonomy) {
                $n = "_nested:bilder__$easydb_nested";
                $a = "lk_{$easydb_nested}_id";
                $this->log("import $n -> $taxonomy");
                foreach ($o->$n as $keyword) {
                    $this->_parse_detail($keyword->$a, $tags[$taxonomy], $taxonomy, true);
                }
            }
        }

        /**
         * @param $block
         * @param array $set
         * @param string $name
         * @param bool $single = true
         * @param string $field = "_standard"
         */
        private function _parse_detail($block, array &$set, string $name, bool $single = true, string $field = "_standard") {
            $one = 1;
            if (isset($block->$field) and isset($block->$field->$one) and isset($block->$field->$one->text)) {
                if (!$set[$name]) $set[$name] = array();
                $set[$name][] = (array) $block->$field->$one->text;
            }
        }

        /**
         * @param $object
         * @param array $meta
         * @param string $field_name
         * @param bool $single
         */
        private function _parse_field($object, array &$meta, string $field_name, bool $single = true) {
            foreach ($this->_language_map as $wp_language => $easydb_language) {
                if (isset($object->$easydb_language)) {
                    $this->log("do add $field_name for $easydb_language. single: $single");
                    if ($single) {
                        $meta[$easydb_language][$field_name] = $object->$easydb_language;
                    } else {
                        if (!isset($meta[$easydb_language][$field_name])) $meta[$easydb_language][$field_name] = array();
                        $meta[$easydb_language][$field_name][] = $object->$easydb_language;
                    }

                }
            }
        }

//        function _parse_date($o, \esa_item\data $data) {
//            if (isset($o->original_datum)) {
//                $data->put("decade", $this->_get_decade($o->original_datum->_from));
//            } else if (isset($o->bild[0]->date_created)) {
//                if (isset($o->bild) and count($o->bild)) {
//                    $data->put("decade", $this->_get_decade($o->bild[0]->date_created));
//                }
//            }
//        }
//
//        function _parse_blocks($o, \esa_item\data $data) {
//            $blocks = array(
//                "template"  => "art_der_vorlage_id",
//                "state"     => "bearbeitungsstatus_id",
//                "motive"    => "art_des_motivs_id_old",
//                "place"     => "ort_des_motivs_id",
//                "provider"  => "anbieter_id",
//                "creator"   => "ersteller_der_vorlage_id_old",
//                "material"  => "material_der_vorlage_id"
//            );
//
//            foreach ($blocks as $bname => $block) {
//                $this->get_detail($data, $bname, $o->$block);
//            }
//        }

//        function _get_decade(string $datestring) {
//            $year = date("Y", strtotime($datestring));
//            return substr($year, 0, 3) . "0s";
//        }


        /**
         * @param $o
         * @param array $meta
         * @throws \Exception
         */
        function _parse_place($o, array &$meta)  {

            if (!isset($o->ort_des_motivs_id)) {
                $this->log("no place connected", "info");
                return;
            }

            $soid = $o->ort_des_motivs_id->_system_object_id;

            $this->log("parsing place $soid", "info");

            $place = json_decode($this->_fetch_external_data($this->api_place_url("$soid")));

            $place = $place[0];

            if (!isset($place) or !isset($place->ortsthesaurus) or !isset($place->ortsthesaurus->gazetteer_id)) {
                return;
            }

            $gazId = $place->ortsthesaurus->gazetteer_id;

            //            foreach ($gazId->otherNames as $name) { TODO translatable names?
            //                $data->put("place", $name->title, "#");
            //            }

            if (!isset($gazId->position)) {
                return;
            }

            foreach ($meta as $lang => $lang_mata) {
                $meta[$lang]["latitude"]       =   $gazId->position->lat;
                $meta[$lang]["longitude"]      =   $gazId->position->lng;
                $meta[$lang]["gazetteer_id"]   =   $gazId->gazId;
                $meta[$lang]["place_name"]     =   $gazId->displayName;
            }


        }

        function _parse_tags($o, \esa_item\data $data) {
            $import_tags = array(
                31  => "article_image",
                4   => "aleppo access",
                16  => "in process",
                19  => "accessible",
                25  => "destroyed"
            );

            $used_tags = array_map(function($t) {return $t->_id;}, $o->_tags);

            foreach ($used_tags as $tag) {
                if (isset($import_tags[$tag])) {
                    $data->put("tag", $import_tags[$tag]);
                }
            }

        }


        /**
         * @param $post_id
         * @param $meta
         */
        private function _update_meta($post_id, $meta) {

            foreach ($meta as $key => $value) {
                add_post_meta($post_id, "shap_$key", $value, true);
            }
        }


        /**
         * @param int $post_id
         * @param $object
         * @param int $system_object_id
         * @param array $meta
         * @param array $wp_terms
         * @throws \Exception
         */
        private function _update_translations(int $post_id, $object, int $system_object_id, array $meta, array $wp_terms) {

            $translated_posts = wpml_get_content_translations("post_attachment", $post_id);

            foreach ($translated_posts as $wpml_language => $translated_post_id) {

                $new_post = array(
                    'ID'             => $translated_post_id,
                    'post_title'     => $this->_get_best_field($object, $this->_language_map[$wpml_language], $fields = array('ueberschrift', 'titel'), "Image #$system_object_id ($wpml_language)"),
                    'post_content'   => $this->_get_best_field($object, $this->_language_map[$wpml_language], $fields = array('beschreibung'), ""),
                    'post_type'      => "attachment"
                );
                $id = wp_insert_post($new_post, true);

                if ($this->_is_error($id)) {
                    throw new \Exception("Wordpress Error: Could not create attachment.");
                }

                $this->log("Image #$system_object_id: Translation to <i>$wpml_language</i> of $post_id is $id");

                // meta fields
                $this->_update_meta($translated_post_id, $meta[$this->_language_map[$wpml_language]]);

                // taxonomies
                if (count($wp_terms)) { // @ TODO STAND umsortien, sprache -> tax, damit man hier bulk inserten kann
                    foreach ($wp_terms as $taxonomy => $terms) {
                        $inserted = wp_set_post_terms($post_id, $wp_terms, "shap_tags", false);
                        if (!$inserted or $this->_is_error($inserted)) {
                            throw new \Exception("Could not insert Tags");
                        }
                    }
                }
            }
        }

        /**
         * @param string $taxonomy
         * @param int $from_term_id
         * @param int $to_term_id
         * @param string $from_wp_language
         * @param string $to_wp_language
         * @throws \Exception
         */
        private function _update_term_translation(string $taxonomy, int $from_term_id,  int $to_term_id, string $from_wp_language, string $to_wp_language) {

            global $wpdb;

            $update = $wpdb->update(
                $wpdb->prefix.'icl_translations',
                array(
                    'trid' => wpml_get_content_trid( "tax_$taxonomy", $from_term_id),
                    'element_type' => "tax_$taxonomy",
                    'language_code' => $to_wp_language,
                    'source_language_code' => $from_wp_language
                ),
                array(
                    'element_id' => $to_term_id,
                    'element_type' => "tax_$taxonomy"
                )
            );

            if (!$update) {
                throw new \Exception("Could tno insert data to icl_translations table"); // TODO track mysql error
            }

        }


        /**
         * @param \WP_Error | object | array $something
         * @return bool
         */
        private function _is_error($something) {
            if (is_wp_error($something)) {
                $errors = $something->get_error_messages();
                foreach ($errors as $error) {
                    $this->error($error);
                }
                return true;
            }
            return false;
        }

        /**
         * @param string $taxonomy
         * @param string $term_value
         * @param array $params
         * @return int $term_id
         * @throws \Exception
         */
        private function _get_or_insert_term(string $taxonomy, string $term_value, array $params = array()) : int {

            $term_exists = get_term_by("slug", $params['slug'], $taxonomy);

            if ($term_exists !== false) {
                $term = wp_update_term($term_exists->term_id, $taxonomy, $params);
                $action = "Updated";
            } else {
                $term = wp_insert_term($term_value, $taxonomy, $params);
                $action = "Inserted";
            }

            if ($this->_is_error($term)) {
                throw new \Exception("T counl not be $action: '$term_value' to '$taxonomy'");
            }

            $this->log("<a href='/wp-admin/term.php?taxonomy=$taxonomy&tag_ID={$term['term_id']}'>$action term '$term_value' to '$taxonomy'</a>");

            return $term['term_id'];
        }

        /**
         * @param array $tag_array
         * @return array
         * @throws \Exception
         */
        private function _add_terms(array $tag_array) {

            $default_language = wpml_get_default_language();

            // double check if order of languages was set correctly
            if (array_keys($this->_language_map)[0] != $default_language) {
                $first = array_keys($this->_language_map)[0];
                throw new \Exception("First language must be default! $first != $default_language");
            }

            $terms = array();

            $this->log(shap_debug($tag_array));

            foreach ($tag_array as $taxonomy => $term_sets) {

                $terms[$taxonomy] = array_map(function($a) {return array();}, $this->_language_map);

                foreach ($term_sets as $term_set_key => $tag_value_tiples) { // $term_set_key is unused atm

                    foreach ($tag_value_tiples as $tag_value_triple) {

                        $term_id_in_default_language = false;

                        foreach ($this->_language_map as $wp_language => $easydb_language) {

                            $params = array();
                            $params["description"] = "shap_imported";
                            $params["slug"] = implode('_', array(
                                $this->_create_slug($term_set_key),
                                $this->_create_slug($tag_value_triple[$easydb_language]),
                                $wp_language
                            ));
                            $term_id = $this->_get_or_insert_term("shap_$taxonomy", $tag_value_triple[$easydb_language], $params);
                            $terms[$taxonomy][$wp_language][] = $term_id;

                            if ($term_id_in_default_language) {
                                $this->_update_term_translation("shap_$taxonomy", $term_id_in_default_language, $term_id, $default_language, $wp_language);
                            } else { // round 0
                                $term_id_in_default_language = $term_id;
                            }
                        }
                    }
                }

            }

            return $terms;

        }

        private function _create_slug(string $string){
            return preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
        }

        private function _set_primary_language_first() {
            $default_language = wpml_get_default_language();
            uksort($this->_language_map, function($a, $b) use ($default_language) {
                return ($b == $default_language);
            });
        }

    }
}
?>