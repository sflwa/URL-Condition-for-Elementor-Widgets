<?php
/*
Plugin Name: URL Condition for Elementor Widgets
Description: Adds a simple conditional display logic to Elementor widgets based on a URL query variable.
Version: 1.0.0
Author: AI Assistant
Requires Plugin: elementor
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Main URL Condition Class to integrate with Elementor.
 */
class Simple_Elementor_URL_Condition {

    protected $is_element_hidden = [];

    public function __construct() {
        // Only proceed if Elementor is available
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }
        
        // 1. Register Admin Controls for various Elementor elements
        $this->register_admin_hooks();
        
        // 2. Register Public Hooks to apply condition on front-end
        $this->register_public_hooks();
    }

    private function register_admin_hooks() {
        // Add controls to Columns, Sections, and Widgets/Inner Sections (using common hook)
        add_action( 'elementor/element/column/section_advanced/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        // For Elementor 3.x containers
        add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
    }
    
    private function register_public_hooks() {
        // Use output buffering on the front-end to prevent elements from rendering
        add_action( 'elementor/frontend/widget/before_render', [ $this, 'filter_element_content_before' ], 10, 1 );
        add_action( 'elementor/frontend/widget/after_render', [ $this, 'filter_element_content_after' ], 10, 1 );
        add_action( 'elementor/frontend/section/before_render', [ $this, 'filter_element_content_before' ], 10, 1 );
        add_action( 'elementor/frontend/section/after_render', [ $this, 'filter_element_content_after' ], 10, 1 );
        add_action( 'elementor/frontend/column/before_render', [ $this, 'filter_element_content_before' ], 10, 1 );
        add_action( 'elementor/frontend/column/after_render', [ $this, 'filter_element_content_after' ], 10, 1 );
        add_action( 'elementor/frontend/container/before_render', [ $this, 'filter_element_content_before' ], 10, 1 );
        add_action( 'elementor/frontend/container/after_render', [ $this, 'filter_element_content_after' ], 10, 1 );
    }

    /**
     * Adds the custom condition controls to the Elementor Advanced tab.
     */
    public function add_condition_controls( $element, $args ) {
        if ( ! class_exists( '\Elementor\Controls_Manager' ) ) {
            return;
        }

        $element->start_controls_section(
            'url_conditions_section',
            [
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
                'label' => 'URL Query Condition',
            ]
        );

        $element->add_control(
            'url_condition_enable',
            [
                'label' => 'Enable URL Condition',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Yes',
                'label_off' => 'No',
                'return_value' => 'yes',
                'default' => '',
            ]
        );

        $element->add_control(
            'url_condition_variable',
            [
                'label' => 'URL Query Variable Name',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'e.g. source',
                'description' => 'The variable in the URL, e.g. for **?key=value**, enter **key**.',
                'condition' => [
                    'url_condition_enable' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'url_condition_value',
            [
                'label' => 'Expected Variable Value',
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'e.g. facebook',
                'description' => 'The element will show/hide if **?{variable}={value}**. Leave empty to check if the variable is just present (e.g. **?key**).',
                'condition' => [
                    'url_condition_enable' => 'yes',
                ],
            ]
        );

        $element->add_control(
            'url_condition_action',
            [
                'label' => 'Action',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'show',
                'options' => [
                    'show' => 'Show when condition met',
                    'hide' => 'Hide when condition met',
                ],
                'condition' => [
                    'url_condition_enable' => 'yes',
                ],
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Checks the URL condition based on element settings.
     *
     * @param array $settings The Elementor element settings.
     * @return bool True if the element should be hidden, false otherwise.
     */
    private function check_condition( $settings ) {
        // 1. Check if feature is enabled
        if ( empty( $settings['url_condition_enable'] ) || $settings['url_condition_enable'] !== 'yes' ) {
            return false;
        }

        $variable = sanitize_text_field( $settings['url_condition_variable'] ?? '' );
        $expected_value = sanitize_text_field( $settings['url_condition_value'] ?? '' );
        $action = sanitize_text_field( $settings['url_condition_action'] ?? 'show' );

        if ( empty( $variable ) ) {
            return false;
        }

        $condition_met = false;
        $should_hide = false;

        // 2. Perform the URL query check
        if ( isset( $_GET[ $variable ] ) ) {
            $actual_value = sanitize_text_field( wp_unslash( $_GET[ $variable ] ) );

            if ( empty( $expected_value ) ) {
                // Check for presence only (e.g., ?source)
                $condition_met = true;
            } else {
                // Check for value match (case-insensitive)
                $condition_met = ( strtolower( $actual_value ) === strtolower( $expected_value ) );
            }
        }
        
        // 3. Determine final action
        if ( $action === 'show' ) {
            // "Show when met": Hide it if the condition is NOT met
            if ( ! $condition_met ) {
                $should_hide = true;
            }
        } elseif ( $action === 'hide' ) {
            // "Hide when met": Hide it if the condition IS met
            if ( $condition_met ) {
                $should_hide = true;
            }
        }

        return $should_hide;
    }


    /**
     * Before render, check condition and start output buffering if hidden.
     */
    public function filter_element_content_before( $element ) {
        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
            // Do not hide in the editor or preview
            return;
        }

        $settings = $element->get_settings_for_display();
        $element_id = $element->get_id();

        if ( $this->check_condition( $settings ) ) {
            $this->is_element_hidden[ $element_id ] = true;
            ob_start(); // Start capturing output
        }
    }

    /**
     * After render, if hidden, discard the buffered output.
     */
    public function filter_element_content_after( $element ) {
        $element_id = $element->get_id();
        
        if ( empty( $this->is_element_hidden[ $element_id ] ) ) {
            return;
        }

        // Discard the buffered output, effectively removing the element from the source code
        ob_end_clean();
        
        // Add a minimal HTML comment for debugging/visibility confirmation
        echo '';

        unset( $this->is_element_hidden[ $element_id ] );
    }
}

// Instantiate the class after Elementor is loaded.
add_action( 'elementor/init', function() {
    new Simple_Elementor_URL_Condition();
} );
