<?php
/*
 * Plugin Name: Multiple Coupons
 * Plugin URI: http://wordpress.lowtone.nl/plugins/woocommerce-coupons-batch/
 * Description: Create multiple coupons for WooCommerce at once.
 * Version: 1.0
 * Author: Lowtone <info@lowtone.nl>
 * Author URI: http://lowtone.nl
 * License: http://wordpress.lowtone.nl/license
 */
/**
 * @author Paul van der Meijs <code@lowtone.nl>
 * @copyright Copyright (c) 2011-2012, Paul van der Meijs
 * @license http://wordpress.lowtone.nl/license/
 * @version 1.0
 * @package wordpress\plugins\lowtone\woocommerce\coupons\batch
 */

namespace lowtone\woocommerce\coupons\batch {

	use lowtone\content\packages\Package,
		lowtone\ui\forms\Form,
		lowtone\ui\forms\Input,
		lowtone\ui\forms\FieldSet;

	// Includes
	
	if (!include_once WP_PLUGIN_DIR . "/lowtone-content/lowtone-content.php") 
		return trigger_error("Lowtone Content plugin is required", E_USER_ERROR) && false;

	Package::init(array(
			Package::INIT_PACKAGES => array("lowtone"),
			Package::INIT_MERGED_PATH => __NAMESPACE__,
			Package::INIT_SUCCESS => function() {

				$showBatch = false;

				add_action("load-post-new.php", function() use (&$showBatch) {
					$screen = get_current_screen();

					$showBatch = "shop_coupon" == $screen->post_type && "add" == $screen->action;
					
					if (!$showBatch)
						return;

					wp_enqueue_style("lowtone_woocommerce_coupons_batch", plugins_url("/assets/styles/coupons-batch.css", __FILE__));

					wp_enqueue_script("lowtone_woocommerce_coupons_batch", plugins_url("/assets/scripts/jquery.coupons-batch.js", __FILE__), array("jquery"));
				});

				add_action("add_meta_boxes", function() use (&$showBatch) {
					if (!$showBatch)
						return;

					add_meta_box("lowtone_woocommerce_coupons_batch", __("Multiple Coupons", "lowtone_woocommerce_coupons_batch"), function() {
						$form = new Form();

						$form
							->appendChild(
								$form->createInput(Input::TYPE_CHECKBOX, array(
									Input::PROPERTY_LABEL => __("Create multiple Coupons", "lowtone_woocommerce_coupons_batch"),
									Input::PROPERTY_NAME => array("lowtone_woocommerce_coupons_batch", "enabled"),
									Input::PROPERTY_VALUE => 1
								))
							)
							->appendChild(
								$form
									->createFieldSet(array(
										FieldSet::PROPERTY_UNIQUE_ID => "lowtone_woocommerce_coupons_batch_options",
										FieldSet::PROPERTY_LEGEND => __("Options", "lowtone_woocommerce_coupons_batch")
									))
									->appendChild(
										$form->createInput(Input::TYPE_TEXT, array(
											Input::PROPERTY_LABEL => __("Amount", "lowtone_woocommerce_coupons_batch"),
											Input::PROPERTY_NAME => array("lowtone_woocommerce_coupons_batch", "amount"),
											Input::PROPERTY_VALUE => 10
										))
									)
									->appendChild(
										$form->createInput(Input::TYPE_TEXT, array(
											Input::PROPERTY_LABEL => __("Key length", "lowtone_woocommerce_coupons_batch"),
											Input::PROPERTY_NAME => array("lowtone_woocommerce_coupons_batch", "key_length"),
											Input::PROPERTY_VALUE => 5,
											Input::PROPERTY_COMMENT => __("A unique random key is generated for each of the coupons. Keys are created using only alphanumeric characters. Put <strong>%s</strong> in your coupon code where you want the random key to be.", "lowtone_woocommerce_coupons_batch")
										))
									)
							);

						$form
							->out(array(
								"template" => LIB_DIR . "/lowtone/ui/forms/templates/form-content.xsl"
							));

					}, "shop_coupon", "side", "high");

				});

				$inLoop = false;

				add_action("save_post", function($id, $post) use (&$inLoop) {
					if ($inLoop)
						return;

					if ("shop_coupon" !== $post->post_type)
						return;

					if (!isset($_POST["lowtone_woocommerce_coupons_batch"]))
						return;

					$batchOptions = $_POST["lowtone_woocommerce_coupons_batch"];

					if (!$batchOptions["enabled"])
						return;

					$couponCodes = array();

					/**
					 * Create a coupon code from a key and a post title.
					 * @param WP_Post $post The Post object to create a code 
					 * from.
					 * @param string $key The key to use for the code.
					 * @return string Returns coupon code.
					 */
					$createCode = function($post, $key) {
						return str_replace("%s", $key, $post->post_title);
					};

					/**
					 * Check if a key is used before.
					 * @param string $key The key to check.
					 * @param bool Returns TRUE if the key is in use or FALSE if 
					 * not.
					 */
					$checkKey = function($key) use ($post, &$couponCodes, $createCode) {
						return in_array($createCode($post, $key), $couponCodes);
					};

					$keyLength = (int) $batchOptions["key_length"];

					/**
					 * Generate a new unique key.
					 * @return string Returns a new key.
					 */
					$generateKey = function() use ($keyLength, $checkKey) {
						do {
							$key = substr(str_shuffle(str_repeat("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", $keyLength)), 0, $keyLength);
						} while ($checkKey($key));

						return $key;
					};

					// Create additional coupons

					$amount = (int) $batchOptions["amount"];

					$inLoop = true;

					for ($i = 0; $i < $amount - 1; $i++) {
						$key = $generateKey();

						$new = (array) $post;

						unset($new["ID"]);
						unset($new["post_name"]);

						$couponCodes[] = 
							$new["post_title"] = 
							$createCode($post, $key);

						wp_insert_post($new);
					}

					// Update main coupon

					wp_update_post(array(
							"ID" => $id,
							"post_title" => $createCode($post, $generateKey()),
							"post_name" => false,
						));

					// Set loop to FALSE after update (it also calls save_post)

					$inLoop = false;
				}, 10, 5);

				// Register textdomain
				
				add_action("plugins_loaded", function() {
					load_plugin_textdomain("lowtone_woocommerce_coupons_batch", false, basename(__DIR__) . "/assets/languages");
				});

			}
		));
	
}