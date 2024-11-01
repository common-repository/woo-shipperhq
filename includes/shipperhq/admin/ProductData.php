<?php

/*
 * Shipper HQ
 *
 * @category ShipperHQ
 * @package woocommerce-shipperhq
 * @copyright Copyright (c) 2020 Zowta LTD and Zowta LLC (http://www.ShipperHQ.com)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 * @author ShipperHQ Team sales@shipperhq.com
 */


class ShipperHQ_ProductData {


    protected $fieldNames = array(
        'variable_shipperhq_shipping_group' => 'shipperhq_shipping_group',
        'variable_shipperhq_dim_group' => 'shipperhq_dim_group',
        'variable_ship_separately' => 'ship_separately',
        'variable_shipperhq_warehouse' => 'shipperhq_warehouse',
        'variable_freight_class' => 'freight_class',
        'variable_must_ship_freight' => 'must_ship_freight',
        'variable_shipperhq_hs_code' => 'shipperhq_hs_code');

    public function __construct()
    {

        add_filter( 'woocommerce_product_data_tabs', array( $this, 'shq_product_data_shipping_tab' ) );

        add_action( 'woocommerce_product_data_panels', array( $this, 'shq_product_data_shipping_panel' ) );

        add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_shq_product_data' ) );
        add_action( 'woocommerce_process_product_meta_grouped', array( $this, 'save_shq_product_data' ) );
        add_action( 'woocommerce_process_product_meta_external', array( $this, 'save_shq_product_data' ) );
        add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_shq_product_data' ) );

        //attributes per variation instance
        add_action( 'woocommerce_ajax_save_product_variations', array( $this, 'save_shq_variation_product_data' ) );
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_settings_fields'), 10, 3);
    }

    public function shq_product_data_shipping_tab( $tabs)
    {
        $tabs['shipperhq'] = array(
            'label'  => __( 'ShipperHQ', 'woocommerce-shipperhq' ),
            'target' => 'shipperhq_product_data',
            'class'  => array( '' ),
        );

        return $tabs;
    }

    public function shq_product_data_shipping_panel()
    {

        ?><div id="shipperhq_product_data" class="panel woocommerce_options_panel"><?php

        woocommerce_wp_text_input( array(
            'id' 		=> 'shipperhq_shipping_group',
            'desc_tip'    => 'true',
            'placeholder' => "Optional",
            'description' => __( 'Enter Shipping Groups, separated with a hash sign (#) if multiple.', 'woocommerce' ),
            'label' 	=> __( 'Shipping Group(s)', 'woocommerce-shipperhq' ),
        ) );

        woocommerce_wp_text_input( array(
            'id' 		=> 'shipperhq_dim_group',
            'placeholder' => "Optional",
            'desc_tip'    => 'true',
            'description' => __( 'Enter Dimensional Rule Group. Only 1 per product.', 'woocommerce' ),
            'label' 	=> __( 'Dimensional Rule Group', 'woocommerce-shipperhq' ),
        ) );

        woocommerce_wp_text_input( array(
            'id' 		=> 'shipperhq_hs_code',
            'desc_tip'    => 'true',
            'placeholder' => "Optional",
            'description' => __( 'Enter HS Code to classify product.', 'woocommerce' ),
            'label' 	=> __( 'HS Code', 'woocommerce-shipperhq' ),
        ) );


        woocommerce_wp_checkbox( array(
            'id' 			=> 'ship_separately',
            'label' 		=> __( 'Ships Separately', 'woocommerce-shipperhq' ),
            'desc_tip'    => 'true',
            'description' => __( 'Does this product ship on its own?', 'woocommerce' )
        ) );

        woocommerce_wp_text_input( array(
            'id' 		=> 'shipperhq_warehouse',
            'placeholder' => "Optional",
            'desc_tip'    => 'true',
            'description' => __( 'Assigned Warehouses, separated with a hash sign (#) if multiple.', 'woocommerce' ),
            'label' 	=> __( 'Warehouse(s)', 'woocommerce-shipperhq' ),
        ) );

        woocommerce_wp_select( array(
            'id' 			=> 'freight_class',
            'label' 		=> __( 'Freight class', 'woocommerce-shipperhq' ),
            'options' 		=> array(
                'NONE'	=> 'NONE',
                '50'	=> '50',
                '55'	=> '55',
                '60'	=> '60',
                '65'	=> '65',
                '70'	=> '70',
                '77.5'	=> '77.5',
                '85'	=> '85',
                '92.5'	=> '92.5',
                '100'	=> '100',
                '110'	=> '110',
                '125'	=> '125',
                '150'	=> '150',
                '175'	=> '175',
                '200'	=> '200',
                '250'	=> '250',
                '300'	=> '300',
                '400'	=> '400',
                '500'	=> '500',
            )
        ) );


        woocommerce_wp_checkbox( array(
            'id' 			=> 'must_ship_freight',
            'label' 		=> __( 'Must Ship Freight', 'woocommerce-shipperhq' ),
            'desc_tip'    => 'true',
            'description' => __( 'Select if item can only ship via freight.', 'woocommerce' )
        ) );

        ?></div><?php

    }

    public function save_shq_product_data($post_id) {
        update_post_meta( $post_id, 'must_ship_freight', $_POST['must_ship_freight'] );
        update_post_meta( $post_id, 'freight_class', $_POST['freight_class'] );
        update_post_meta( $post_id, 'shipperhq_warehouse', $_POST['shipperhq_warehouse'] );
        update_post_meta( $post_id, 'shipperhq_shipping_group', $_POST['shipperhq_shipping_group'] );
        update_post_meta( $post_id, 'shipperhq_dim_group', $_POST['shipperhq_dim_group'] );
		update_post_meta( $post_id, 'ship_separately', $_POST['ship_separately'] );
        update_post_meta( $post_id, 'shipperhq_hs_code', $_POST['shipperhq_hs_code'] );
	}

    public function save_shq_variation_product_data($product_id) {
        $variable_post_id = $_POST['variable_post_id'];

        foreach ($variable_post_id as $key => $value) {
            foreach($this->fieldNames as $fieldName => $attribute) {
                if (isset($_POST[$fieldName][$value])) {
                    update_post_meta($value, $attribute, $_POST[$fieldName][$value]);
                }
            }
        }
    }

    public function variation_settings_fields( $loop, $variation_data, $variation )
    {
        //shipperhq_shipping_group
        woocommerce_wp_text_input(
            array(
                'id' => 'variable_shipperhq_shipping_group[' . $variation->ID . ']',
                'name' => 'variable_shipperhq_shipping_group[' . $variation->ID . ']',
                'label' => __('ShipperHQ Shipping Group', 'woocommerce'),
                'desc_tip' => 'true',
                'description' => __('Enter ShipperHQ Shipping Group', 'woocommerce'),
                'value' => get_post_meta($variation->ID, 'shipperhq_shipping_group', true)
            )
        );

        //shipperhq_dim_group
        woocommerce_wp_text_input(
            array(
                'id' => 'variable_shipperhq_dim_group[' . $variation->ID . ']',
                'name' => 'variable_shipperhq_dim_group[' . $variation->ID . ']',
                'label' => __('Packing Rule', 'woocommerce-shipperhq'),
                'desc_tip' => 'true',
                'description' => __('Enter Packing Rules, separated with a hash sign (#) if multiple.', 'woocommerce'),
                'value' => get_post_meta($variation->ID, 'shipperhq_dim_group', true)
            )
        );

        //shipperhq_hs_code
        woocommerce_wp_text_input(
            array(
                'id' => 'variable_shipperhq_hs_code[' . $variation->ID . ']',
                'name' => 'variable_shipperhq_hs_code[' . $variation->ID . ']',
                'label' => __('Product HS Code', 'woocommerce'),
                'desc_tip' => 'true',
                'description' => __('Enter Product HS Code for appropriate classification.', 'woocommerce'),
                'value' => get_post_meta($variation->ID, 'shipperhq_hs_code', true)
            )
        );

        //ship_separately
        woocommerce_wp_checkbox(array(
            'id' => 'variable_ship_separately',
            'name' => 'variable_ship_separately[' . $variation->ID . ']',
            'label' => __('Ships Separately', 'woocommerce-shipperhq'),
            'desc_tip' => 'true',
            'description' => __('Does this product ship on its on?', 'woocommerce'),
            'value' => get_post_meta($variation->ID, 'ship_separately', true)
        ));

        //shipperhq_warehouse
        woocommerce_wp_text_input(array(
            'id' => 'variable_shipperhq_warehouse',
            'name' => 'variable_shipperhq_warehouse[' . $variation->ID . ']',
//            'placeholder' => "Optional",
            'desc_tip' => 'true',
            'description' => __('Assigned Warehouses, separated with a hash sign (#) if multiple.', 'woocommerce'),
            'value' => get_post_meta($variation->ID, 'shipperhq_warehouse', true),
            'label' => __('Warehouse(s)', 'woocommerce-shipperhq'),
        ));

        //freight_class
        woocommerce_wp_select(array(
            'id' => 'variable_freight_class',
            'name' => 'variable_freight_class[' . $variation->ID . ']',
            'label' => __('Freight class', 'woocommerce-shipperhq'),
            'options' => array(
                'NONE' => 'NONE',
                '50' => '50',
                '55' => '55',
                '60' => '60',
                '65' => '65',
                '70' => '70',
                '77.5' => '77.5',
                '85' => '85',
                '92.5' => '92.5',
                '100' => '100',
                '110' => '110',
                '125' => '125',
                '150' => '150',
                '175' => '175',
                '200' => '200',
                '250' => '250',
                '300' => '300',
                '400' => '400',
                '500' => '500',
            ),
            'value' => get_post_meta($variation->ID, 'freight_class', true)
        ));

        woocommerce_wp_checkbox(array(
            'id' => 'variable_must_ship_freight',
            'name' => 'variable_must_ship_freight[' . $variation->ID . ']',
            'label' => __('Must Ship Freight', 'woocommerce-shipperhq'),
            'desc_tip' => 'true',
            'description' => __('Select if item only ships Freight.', 'woocommerce'),
            'value' => get_post_meta($variation->ID, 'must_ship_freight', true)
        ));
    }
}
