<?php
/*
Plugin Name: Elementor URL Condition
Description: Adds a simple conditional display logic to Elementor widgets based on a URL query variable, with debug logging to debug.log.
Version: 1.0.3
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
    protected $debug_data = [];

    public function __construct() {
        if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }
        
        $this->register_admin_hooks();
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
        
        $element->add_control(
            'url_condition_debug',
            [
                'label' => 'Debug Mode (Logs to debug.log)',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'On',
                'label_off' => 'Off',
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
                'description' => 'Writes detailed check results to the WordPress `debug.log` file.',
                'condition' => [
                    'url_condition_enable' => 'yes',
                ],
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Checks the URL condition and returns the decision along with debug data.
     *
     * @param array $settings The Elementor element settings.
     * @param string $element_id The element's unique ID.
     * @return array [$should_hide, $debug_data, $debug_mode]
     */
    private function check_condition( $settings, $element_id ) {
        
        $enabled = $settings['url_condition_enable'] ?? '';
        $variable = sanitize_text_field( $settings['url_condition_variable'] ?? '' );
        $expected_value = sanitize_text_field( $settings['url_condition_value'] ?? '' );
        $action = sanitize_text_field( $settings['url_condition_action'] ?? 'show' );
        $debug_mode = $settings['url_condition_debug'] ?? '';
        
        $should_hide = false;
        $condition_met = false;
        
        // Setup initial/default debug data
        $debug_data = [
            'ID' => $element_id,
            'Enabled' => ( $enabled === 'yes' ? 'Yes' : 'No' ),
            'Variable' => $variable,
            'Expected' => ( empty($expected_value) ? 'Any non-empty value (Presence Check)' : $expected_value ),
            'Action' => $action,
            'Actual' => 'N/A',
            'URL_Contains_Variable' => 'No',
            'ConditionMet' => 'No',
            'FinalDecision' => 'Visible',
        ];
        
        // 1. Always show element if in Editor or Preview mode
        if ( class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()) ) {
            $debug_data['FinalDecision'] = 'Visible (Elementor Editor/Preview)';
            return [ $should_hide, $debug_data, $debug_mode ];
        }
        
        // 2. Check if feature is enabled/configured
        if ( $enabled !== 'yes' || empty( $variable ) ) {
            return [ $should_hide, $debug_data, $debug_mode ];
        }
        
        // 3. Perform the URL query check
        if ( isset( $_GET[ $variable ] ) ) {
            $debug_data['URL_Contains_Variable'] = 'Yes';
            $actual_value = sanitize_text_field( wp_unslash( $_GET[ $variable ] ) );
            $debug_data['Actual'] = $actual_value;

            if ( empty( $expected_value ) ) {
                // Condition met if parameter is just present (e.g., ?source) and not empty
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
        if ( $condition_met ) {
            $debug_data['ConditionMet'] = 'Yes';
        }

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
        
        if ( $should_hide ) {
            $debug_data['FinalDecision'] = 'Hidden';
        }

        // Final return of all three expected values
        return [ $should_hide, $debug_data, $debug_mode ];
    }


    /**
     * Helper function to log debug data to the WordPress debug.log file.
     */
    private function log_debug_output( $element_type_name, $debug_data ) {
        if ( ! defined( 'WP_DEBUG_LOG' ) || WP_DEBUG_LOG !== true || $debug_data['Enabled'] === 'No') {
            return;
        }

        $log_message = "URL Condition Log for Element ({$element_type_name} ID: {$debug_data['ID']})\n";
        $log_message .= "--------------------------------------------------------\n";
        $log_message .= "Status: {$debug_data['FinalDecision']}\n";
        $log_message .= "Target Variable: {$debug_data['Variable']}\n";
        $log_message .= "Expected Value: {$debug_data['Expected']}\n";
        $log_message .= "Actual Value: {$debug_data['Actual']}\n";
        $log_message .= "URL Contains Variable: {$debug_data['URL_Contains_Variable']}\n";
        $log_message .= "Condition Met: {$debug_data['ConditionMet']}\n";
        $log_message .= "Action: If condition is met, element should {$debug_data['Action']}\n";
        $log_message .= "--------------------------------------------------------\n";

        error_log( $log_message );
    }


    /**
     * Before render, check condition and start output buffering if hidden.
     */
    public function filter_element_content_before( $element ) {
        $settings = $element->get_settings_for_display();
        $element_id = $element->get_id();

        list($should_hide, $debug_data, $debug_mode) = $this->check_condition( $settings, $element_id );
        
        // Store debug data
        if ( $debug_mode === 'yes' ) {
            $this->debug_data[ $element_id ] = [
                'type_name' => $element->get_name(),
                'data' => $debug_data
            ];
        }

        if ( $should_hide ) {
            $this->is_element_hidden[ $element_id ] = true;
            ob_start(); // Start capturing output
        }
    }

    /**
     * After render, if hidden, discard the buffered output and log debug if necessary.
     */
    public function filter_element_content_after( $element ) {
        $element_id = $element->get_id();
        
        // 1. Handle Debug Logging
        if ( isset( $this->debug_data[ $element_id ] ) ) {
            $log_data = $this->debug_data[ $element_id ];
            $this->log_debug_output( $log_data['type_name'], $log_data['data'] );
            unset( $this->debug_data[ $element_id ] );
        }

        // 2. Handle Hiding
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
