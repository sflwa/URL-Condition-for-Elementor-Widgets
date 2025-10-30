<?php
/*
Plugin Name: Elementor URL Condition
Description: Adds conditional display logic to Elementor widgets based on a URL query variable, with cache mitigation for reliable use.
Version: 1.0.5
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
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }
        
        $this->register_admin_hooks();
        $this->register_public_hooks();

        // Action to mitigate caching issues by preventing page caching when a query parameter is present.
        add_action( 'init', [ $this, 'mitigate_caching' ] );
    }

    private function register_admin_hooks() {
        // Hooks to add controls to all major Elementor elements (widgets, sections, containers)
        add_action( 'elementor/element/column/section_advanced/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        add_action( 'elementor/element/section/section_advanced/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        add_action( 'elementor/element/common/_section_style/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
        add_action( 'elementor/element/container/section_layout/after_section_end', [ $this, 'add_condition_controls' ], 10, 2 );
    }
    
    private function register_public_hooks() {
        // Hooks to apply conditional logic on the front-end
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
     * Checks the URL condition and returns the decision.
     *
     * @return bool True if the element should be hidden, false otherwise.
     */
    private function check_condition( $settings ) {
        
        $enabled = $settings['url_condition_enable'] ?? '';
        $variable = sanitize_text_field( $settings['url_condition_variable'] ?? '' );
        $expected_value = sanitize_text_field( $settings['url_condition_value'] ?? '' );
        $action = sanitize_text_field( $settings['url_condition_action'] ?? 'show' );
        
        $should_hide = false;
        $condition_met = false;
        
        // 1. Always show element if in Editor or Preview mode
        if ( class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()) ) {
            return false;
        }
        
        // 2. Check if feature is enabled/configured
        if ( $enabled !== 'yes' || empty( $variable ) ) {
            return false;
        }
        
        // 3. Perform the URL query check
        if ( isset( $_GET[ $variable ] ) ) {
            $actual_value = sanitize_text_field( wp_unslash( $_GET[ $variable ] ) );

            if ( empty( $expected_value ) ) {
                // Condition met if parameter is present and not empty
                if ( ! empty( $actual_value ) ) {
                    $condition_met = true;
                }
            } else {
                // Check for value match (case-insensitive)
                if ( strtolower( $actual_value ) === strtolower( $expected_value ) ) {
                    $condition_met = true;
                }
            }
        }
        
        // 4. Determine final action
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
     * Runs early to force a cache bypass if any URL query parameter is present.
     */
    public function mitigate_caching() {
        // Only run on the front-end for logged-out users (i.e., when caching is active)
        if ( is_admin() || current_user_can('manage_options') ) {
            return;
        }

        if ( ! empty( $_GET ) ) {
            // This flag is recognized by many caching plugins (e.g., WP Rocket, LiteSpeed Cache)
            if ( ! defined( 'DONOTCACHEPAGE' ) ) {
                define( 'DONOTCACHEPAGE', true );
            }
            
            // Send headers to instruct external caches (browser/CDN/proxy) to bypass
            header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
            header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        }
    }

    /**
     * Before render, check condition and start output buffering if hidden.
     */
    public function filter_element_content_before( $element ) {
        $settings = $element->get_settings_for_display();
        $element_id = $element->get_id();

        $should_hide = $this->check_condition( $settings );
        
        if ( $should_hide ) {
            $this->is_element_hidden[ $element_id ] = true;
            ob_start(); // Start capturing output
        }
    }

    /**
     * After render, if hidden, discard the buffered output.
     */
    public function filter_element_content_after( $element ) {
        $element_id = $element->get_id();
        
        if ( ! empty( $this->is_element_hidden[ $element_id ] ) ) {
            // Element was hidden: Discard output
            ob_end_clean();
            
            // Add a minimal HTML comment to confirm hiding without cluttering the page
            echo '';
            
            unset( $this->is_element_hidden[ $element_id ] );
        }
    }
}

// Instantiate the class after Elementor is loaded.
add_action( 'elementor/init', function() {
    new Simple_Elementor_URL_Condition();
} );
