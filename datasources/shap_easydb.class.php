<?php

namespace shap_datasource {

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
            if (!isset($this->_easydb_url) or !$this->_easydb_url) {
                throw new \Exception('No Easy-DB URL set.');
            }
            $this->get_easy_db_session_token();
            $this->_check_wmpl_settings();
            return 'O. K.';
        }

        /**
         * @throws \Exception
         */
        private function _check_wmpl_settings() {

            global $shap_taxonomies;

            if (!function_exists("wpml_get_content_translations")) {
                throw new \Exception("WMPL Plugin seems not to be installed");
            }

            $wpml_settings = get_option("icl_sitepress_settings");
            $wpml_tax_settings = $wpml_settings['taxonomies_sync_option'];

            foreach ($shap_taxonomies as $tax => $tax_name) {
                if ((int) $wpml_tax_settings["shap_$tax"] != 2) {
                    throw new \Exception("WMPL Settings are not correct. <a href='/wp-admin/admin.php?page=wpml-translation-management%2Fmenu%2Fsettings'>Set 'shap_$tax' to 'Translatable - use translation if available or fallback to default language'</a>");
                }
            }

            $wpml_post_type_settings = $wpml_settings['custom_posts_sync_option'];

            if ((int) $wpml_post_type_settings['attachment'] != 2) {
                throw new \Exception("WMPL Settings are not correct. <a href='/wp-admin/admin.php?page=wpml-translation-management%2Fmenu%2Fsettings'>Set 'attachment' to 'Translatable - use translation if available or fallback to default language'</a>");
            }

            $default_language = wpml_get_default_language();

            if ($default_language != "en") {
                throw new \Exception("Default Language must be english");
            }

            $current_language = wpml_get_current_language();

            if (!in_array($current_language, array('all', 'en'))) {
                throw new \Exception("Please select 'All languages' or default language 'en' in the admin bar above while importing.");
            }


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

            $meta_collector = $this->_init_meta_collector();
            $term_collector = $this->_init_term_collector();
            $this->_parse_place($object, $meta_collector, $term_collector);
            $this->_parse_field_to_meta($object->copyright_vermerk, $meta_collector, "copyright_vermerk");
            $this->_parse_nested($object, $term_collector);

            $this->_parse_blocks($object, $term_collector);
            $this->_parse_date($object, $term_collector);
            $this->_parse_pool($object, $term_collector);
//            $this->_parse_tags($json_response[0], $data);

//            $html = $image->render();

            $wp_terms = $this->_add_terms_to_wp($term_collector);

            $translated_attachments = $this->_update_post_translations($attachment->ID, $object, $system_object_id, $meta_collector);

            $this->_update_term_translations($translated_attachments, $wp_terms);

            return "<a href='/wp-admin/upload.php?item={$attachment->ID}'>Image {$attachment->ID} " . ($attachment->update ? 'updated' : 'inserted') . "</a>";
        }

        private function _init_meta_collector() {
            $meta = array();
            foreach ($this->_language_map as $wp_language => $easydb_language) {
                $meta[$easydb_language] = array();
            }
            return $meta;
        }

        private function _init_term_collector() {
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

        /**
         * @param $source
         * @param array $term_collector
         */
        function _parse_pool($source, array &$term_collector) {
            if ($source->_pool->pool->_id == 1) {
                return;
            }

            $term_collector['pool']['pool'] = array();
            $term_collector['pool']['pool'][0] = (array) $source->_pool->pool->name;
        }

        /**
         * @param $source
         * @param array $term_collector target //2249
         */
        private function _parse_nested($source, array &$term_collector) {

            $easydb_nested_to_taxonomy = array(
                'schlagwort' => 'tags',
                "element" => 'tags',
                "style" => 'tags',
                "tech" => 'tags',
                "material" => 'tags',
                "artdesmotivs_new" => 'subject'
            ); // skipped: teilelement, literatur

            foreach ($easydb_nested_to_taxonomy as $easydb_nested => $taxonomy) {
                $n = "_nested:bilder__$easydb_nested";
                $a = "lk_{$easydb_nested}_id";
                foreach ($source->$n as $keyword) {
                    $this->_parse_detail_to_terms($keyword->$a, $term_collector, $taxonomy, $taxonomy);
                }
            }
        }

        /**
         * @param $block
         * @param array $term_collector
         * @param string $taxonomy
         * @param string $name
         * @param string $field = "_standard"
         */
        private function _parse_detail_to_terms($block, array &$term_collector, string $taxonomy, string $name, string $field = "_standard") {
            $one = 1;
            if (isset($block->$field) and isset($block->$field->$one) and isset($block->$field->$one->text)) {
                if (!$term_collector[$taxonomy][$name]) $term_collector[$taxonomy][$name] = array();
                $term_collector[$taxonomy][$name][] = (array) $block->$field->$one->text;
            }
        }

        /**
         * @param $object
         * @param array $meta
         * @param string $field_name
         * @param bool $single
         */
        private function _parse_field_to_meta($object, array &$meta, string $field_name, bool $single = true) {
            foreach ($this->_language_map as $wp_language => $easydb_language) {
                if (isset($object->$easydb_language)) {
                    //$this->log("do add $field_name for $easydb_language. single: $single");
                    if ($single) {
                        $meta[$easydb_language][$field_name] = $object->$easydb_language;
                    } else {
                        if (!isset($meta[$easydb_language][$field_name])) $meta[$easydb_language][$field_name] = array();
                        $meta[$easydb_language][$field_name][] = $object->$easydb_language;
                    }

                }
            }
        }

        /**
         * @param $source
         * @param array $term_collector
         */
        private function _parse_date($source, array &$term_collector) {

            if (isset($source->original_datum)) {
                $this->_collect_single_language_term_as_triple($term_collector, 'time', 'decade', $this->_get_decade($source->original_datum->_from));
                $this->_collect_single_language_term_as_triple($term_collector, 'time', 'year', date("Y", strtotime($source->original_datum->_from)));
            } else if (isset($source->bild[0]->date_created)) {
                if (isset($source->bild) and count($source->bild)) {
                    $this->_collect_single_language_term_as_triple($term_collector, 'time', 'decade', $this->_get_decade($source->bild[0]->date_created));
                    $this->_collect_single_language_term_as_triple($term_collector, 'time', 'year', date("Y", strtotime($source->bild[0]->date_created)));
                }
            }
        }


        /**
         * @param string $date_string
         * @return string
         */
        private function _get_decade(string $date_string) {

            $year = date("Y", strtotime($date_string));
            return substr($year, 0, 3) . "0s";
        }

        /**
         * @param $source
         * @param array $term_collector
         */
        private function _parse_blocks($source, array &$term_collector) {

            $blocks = array(
//                "template"  => "art_der_vorlage_id",
//                "state"     => "bearbeitungsstatus_id",
                "subject"    => "art_des_motivs_id_old",
//                "place"     => "ort_des_motivs_id",
//                "provider"  => "anbieter_id",
//                "creator"   => "ersteller_der_vorlage_id_old",
//                "material"  => "material_der_vorlage_id"
            );

            foreach ($blocks as $taxonomy => $block) {
                $this->_parse_detail_to_terms($source->$block, $term_collector, $taxonomy, $taxonomy);
            }
        }


        /**
         * @param $source
         * @param array $meta_collector
         * @param array $term_collector
         * @throws \Exception
         */
        function _parse_place($source, array &$meta_collector, array &$term_collector)  {

            if (!isset($source->ort_des_motivs_id)) {
                $this->log("no place connected", "info");
                return;
            }

            $soid = $source->ort_des_motivs_id->_system_object_id;

            $this->log("parsing place $soid", "info");

            $place = json_decode($this->_fetch_external_data($this->api_place_url("$soid")));

            $place = $place[0];

            if (!isset($place) or !isset($place->ortsthesaurus) or !isset($place->ortsthesaurus->gazetteer_id)) {
                return;
            }

            $gazId = $place->ortsthesaurus->gazetteer_id;

            if (!isset($gazId->position)) {
                return;
            }

            /**
             * unfortunately $gazId->otherNames are not localized in easydb, so we can't import the place names localized
             * TODO query gazetteer to get the localized names?
             * for now we import three times the same place for each language
             */
            $this->_collect_single_language_term_as_triple($term_collector, 'places', 'place', array(
                "value"         => $gazId->displayName,
                "latitude"      => $gazId->position->lat,
                "longitude"     => $gazId->position->lng,
                "gazetteer_id"  => $gazId->gazId
            ));

            //$this->log('$term_collector' . shap_debug($term_collector));

            /**
             * to store this information in post as is redundant, but we don't have the time yet to develop geographical
             * queries over term_meta
             */
            foreach ($meta_collector as $lang => $lang_meta) {
                $meta_collector[$lang]["latitude"]       =   $gazId->position->lat;
                $meta_collector[$lang]["longitude"]      =   $gazId->position->lng;
                $meta_collector[$lang]["gazetteer_id"]   =   $gazId->gazId;
                $meta_collector[$lang]["place_name"]     =   $gazId->displayName;
            }

        }

        /**
         * @param array $term_collector
         * @param string $taxonomy
         * @param string $taxonomy
         * @param string $term_group
         * @param $value
         */
        private function _collect_single_language_term_as_triple(array &$term_collector, string $taxonomy, string $term_group, $value) {

            if (!isset($term_collector[$taxonomy][$term_group])) {
                $term_collector[$taxonomy][$term_group] = array();
            }

            $triple = array_map(function($dummy) {return array();}, array_flip($this->_language_map));

            foreach ($triple as $easydb_language => $terms) {
                $triple[$easydb_language] = $value; // as long as $value is just an array and not some class, we don't need to clone
            }

            $term_collector[$taxonomy][$term_group][] = $triple;
        }

//        function _parse_tags($o, \esa_item\data $data) {
//            $import_tags = array(
//                31  => "article_image",
//                4   => "aleppo access",
//                16  => "in process",
//                19  => "accessible",
//                25  => "destroyed"
//            );
//
//            $used_tags = array_map(function($t) {return $t->_id;}, $o->_tags);
//
//            foreach ($used_tags as $tag) {
//                if (isset($import_tags[$tag])) {
//                    $data->put("tag", $import_tags[$tag]);
//                }
//            }
//
//        }


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
         * @return array|int
         * @throws \Exception
         */
        private function _update_post_translations(int $post_id, $object, int $system_object_id, array $meta) {

            $translated_posts = wpml_get_content_translations("post_attachment", $post_id);

            $original_post = get_post($post_id);

            foreach ($translated_posts as $wp_language => $translated_post_id) {

                $new_post = array(
                    'ID'             => $translated_post_id,
                    'post_title'     => $this->_get_best_field($object, $this->_language_map[$wp_language], $fields = array('ueberschrift', 'titel'), "Image #$system_object_id ($wp_language)"),
                    'post_content'   => $this->_get_best_field($object, $this->_language_map[$wp_language], $fields = array('beschreibung'), ""),
                    'post_type'      => "attachment",
                    "post_mime_type" => $original_post->post_mime_type,
                    "comment_status" => $original_post->comment_status,
                    "comment_count"  => $original_post->comment_count,
                    "author"         => $original_post->post_author
                );
                $id = wp_insert_post($new_post, true);

                if ($this->_is_error($id)) {
                    throw new \Exception("Wordpress Error: Could not create attachment.");
                }

                $this->log("Image #$system_object_id: Translation to <i>$wp_language</i> of $post_id is $id");

                $this->_update_meta($translated_post_id, $meta[$this->_language_map[$wp_language]]);
            }

            return $translated_posts;
        }

        /**
         * @param array $translated_posts
         * @param array $wp_terms
         * @throws \Exception
         */
        private function _update_term_translations(array $translated_posts, array $wp_terms) {

            if (!count($wp_terms)) {
                return;
            }

            foreach ($translated_posts as $wp_language => $translated_post_id) {
                foreach ($wp_terms[$wp_language] as $taxonomy => $term_ids) {
                    $inserted = wp_set_object_terms($translated_post_id, $term_ids, "shap_$taxonomy", false);
                    if (!$inserted or $this->_is_error($inserted)) {
                        throw new \Exception("Could not insert terms to post: $translated_post_id");
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

            $trid = wpml_get_content_trid( "tax_$taxonomy", $from_term_id);

            if ($trid == 0) {
                throw new \Exception("Could not get trid for tax_$taxonomy/$from_term_id");
            }

            $update = $wpdb->update(
                $wpdb->prefix.'icl_translations',
                array(
                    'trid' => $trid,
                    'element_type' => "tax_$taxonomy",
                    'source_language_code' => $from_wp_language,
                    'language_code' => $to_wp_language
                ),
                array(
                    'element_id' => $to_term_id,
                    'element_type' => "tax_$taxonomy",
                )
            );

            if (!$update) {
                throw new \Exception("Could not insert data to icl_translations table: {$wpdb->last_error}" . shap_debug($wpdb->last_query));
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
         * we can not use get_terms or such because wmpl is interfering!
         *
         * @param $identity
         * @return array | bool
         * @throws \Exception
         */
        private function _get_wp_get_term_by_identity($identity) {
            global $wpdb;
            $sql = "select
                    *
                from
                  wp_terms as terms
                  left join wp_termmeta as termmeta on (terms.term_id = termmeta.term_id)
                where
                  termmeta.meta_key = 'identity' and
                  termmeta.meta_value = '$identity'";
            $result = $wpdb->get_results($sql);
            if ($wpdb->last_error) {
                throw new \Exception("Could fetch term with identity '$identity': $wpdb->last_error" . shap_debug($wpdb->last_query));
            }

            if (count($result) > 1) {
                throw new \Exception("More than one term with identity {$identity} exist!");
            }
            if ($this->_is_error($result)) {
                throw new \Exception("Error trying to get term with identity {$identity}");
            }
            return count($result) ? (array) $result[0] : false;
        }

        /**
         * @param string $wp_taxonomy taxonomy name with shap_ -prefix
         * @param string $term_value
         * @param array $params
         * @param array $term_meta
         * @return object {ID: term_id, update: bool}
         * @throws \Exception
         */
        private function _create_or_update_term(string $wp_taxonomy, string $term_value, array $params = array(), array $term_meta = array()) {

            $term = $this->_get_wp_get_term_by_identity($term_meta["identity"]);
            $inserted = false;

            if (!$term) {
                $term = wp_insert_term($term_value, $wp_taxonomy, $params);
                $inserted = true;
            }

            if ($this->_is_error($term)) {
                throw new \Exception("Tag could not be Inserted: '$term_value' to '$wp_taxonomy' ({$term_meta["identity"]})");
            }

            //$this->log("Updating metadata for tag: '$term_value' to '$wp_taxonomy' ({$term_meta["identity"]})");
            foreach ($term_meta as $meta_key => $meta_value) {
                if ($meta_key == 'value') continue;
                $this->_is_error(update_term_meta($term['term_id'], $meta_key, $meta_value));
            }

            $action = $inserted ? "Inserted" : "Updated";
            $this->log("<a href='/wp-admin/term.php?taxonomy=$wp_taxonomy&tag_ID={$term['term_id']}'>$action term '$term_value' to '$wp_taxonomy'</a>");

            return (object) array(
                "update" => !$inserted,
                "ID" => $term['term_id']
            );
        }

        /**
         * Structure for $tag_array TODO use classes for structure to make it more robust
         *
         * {
         *
         *      <taxonomy_name> : { // like 'tags' or 'place'
         *          <term_set_key>: [ // like 'stuff'
         *              <easy_db_lang>: "TERM",
         *              ...
         *          ],
         *          <term_set_key>: [
         *              <easy_db_lang>: {
         *                  "value": "TERM",
         *                  <meta_field>: <meta_field_value>,
         *                  ...
         *              },
         *              ...
         *          ],
         *          ...
         *      },
         *      ...
         * }
         *
         *
         * @param array $tag_array
         * @return array
         * @throws \Exception
         */
        private function _add_terms_to_wp(array $tag_array) {

            $default_language = wpml_get_default_language();

            // double check if order of languages was set correctly (theoretically redundant)
            if (array_keys($this->_language_map)[0] != $default_language) {
                $first = array_keys($this->_language_map)[0];
                throw new \Exception("First language must be default! $first != $default_language");
            }

            $terms = array_map(function($dummy) {return array();}, $this->_language_map);

            //$this->log("collected terms: " . shap_debug($tag_array), "debug");

            foreach ($tag_array as $taxonomy => $term_sets) {

                foreach ($term_sets as $term_set_key => $tag_value_tiples) { // $term_set_key is unused atm

                    foreach ($tag_value_tiples as $triple_nr => $tag_value_triple) {

                        $term_id_in_default_language = false;

                        foreach ($this->_language_map as $wp_language => $easydb_language) {

                            list($term_value, $term_meta) = is_array($tag_value_triple[$easydb_language])
                                ? array($tag_value_triple[$easydb_language]['value'], $tag_value_triple[$easydb_language])
                                : array($tag_value_triple[$easydb_language], array());
                            $params = array();
                            $params["description"] = "Imported from EasyDB\n" . date("d.m.Y h:i:s");

                            if (!$term_value) {
                                $this->log("Term $taxonomy->$term_set_key->$triple_nr->$wp_language has no value:", "warning");
                                $this->log(shap_debug($tag_value_triple), 'debug');
                                continue;
                            }

                            $params["slug"] = wp_unique_term_slug(implode('_', array(
                                $this->_create_slug($term_set_key),
                                $this->_create_slug($term_value),
                                $wp_language
                            )), "");
                            $term_meta["shap_imported"] = time();
                            $term_meta["identity"] = implode('-', array(
                                rawurlencode($taxonomy),
                                rawurlencode($term_set_key),
                                rawurlencode($term_value),
                                rawurlencode($wp_language),
                            ));

                            $term = $this->_create_or_update_term("shap_$taxonomy", $term_value, $params, $term_meta);
                            if (!isset($terms[$wp_language][$taxonomy])) $terms[$wp_language][$taxonomy] = array();
                            $terms[$wp_language][$taxonomy][] = (int) $term->ID;

                            if (!$term->update) {
                                if ($term_id_in_default_language) {
                                    $this->_update_term_translation("shap_$taxonomy", $term_id_in_default_language, $term->ID, $default_language, $wp_language);
                                } else { // round 0
                                    $term_id_in_default_language = $term->ID;
                                }
                            }
                        }
                    }
                }

            }

            return $terms;
        }

        private function _create_slug(string $string){
            return strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $string));
        }

        private function _set_primary_language_first() {
            $default_language = wpml_get_default_language();
            uksort($this->_language_map, function($a, $b) use ($default_language) {
                return ($b == $default_language);
            });
        }

    }

}