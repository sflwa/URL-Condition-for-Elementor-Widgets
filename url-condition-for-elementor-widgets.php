<?php
/*
Plugin Name: Elementor URL Condition
Description: Adds a simple conditional display logic to Elementor widgets based on a URL query variable, with debug output.
Version: 1.0.2
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
                'label' => 'Debug Mode',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'On',
                'label_off' => 'Off',
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
                'description' => 'Outputs a detailed HTML comment on the front-end showing the condition check.',
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
        $actual_value = 'N/A (Variable Missing)';
        
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
        
        // FIX: Ensure an early exit returns all three expected values.
        if ( $enabled !== 'yes' || empty( $variable ) ) {
            return [ $should_hide, $debug_data, $debug_mode ];
        }
        
        // 2. Perform the URL query check
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
        
        // 3. Determine final action
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
     * Helper function to generate the debug output comment.
     */
    private function generate_debug_output( $debug_data, $element_type_name ) {
        if ( empty( $debug_data ) || $debug_data['Enabled'] === 'No' ) {
            return '';
        }
        
        $output = "\n\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        $output .= "\n";
        
        return $output;
    }


    /**
     * Before render, check condition and start output buffering if hidden.
     */
    public function filter_element_content_before( $element ) {
        // Do not hide or run heavy checks in the editor/preview mode
        if ( class_exists('\Elementor\Plugin') && (\Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode()) ) {
            return;
        }

        $settings = $element->get_settings_for_display();
        $element_id = $element->get_id();

        // Line 256: This call now correctly expects 3 arguments from check_condition
        list($should_hide, $debug_data, $debug_mode) = $this->check_condition( $settings, $element_id );
        
        // Store debug data regardless of outcome, as it is needed later
        if ( $debug_mode === 'yes' ) {
            $this->debug_data[ $element_id ] = $debug_data;
        }

        if ( $should_hide ) {
            $this->is_element_hidden[ $element_id ] = true;
            ob_start(); // Start capturing output
        }
    }

    /**
     * After render, if hidden, discard the buffered output and display debug if necessary.
     */
    public function filter_element_content_after( $element ) {
        $element_id = $element->get_id();
        $element_type_name = $element->get_name();

        $debug_output = $this->generate_debug_output( $this->debug_data[ $element_id ] ?? [], $element_type_name );
        
        // Check if the element was hidden in the 'before' action
        if ( ! empty( $this->is_element_hidden[ $element_id ] ) ) {
            // Element was hidden: Discard output, print debug (if enabled), and a simple hidden comment
            ob_end_clean();
            
            // Print debug output even when hidden, so the user can see *why* it was hidden
            echo $debug_output; 
            echo '';
            
            unset( $this->is_element_hidden[ $element_id ] );
        } else {
            // Element was visible: Just print debug output (if enabled)
            echo $debug_output;
        }

        // Clean up debug data storage
        if ( isset( $this->debug_data[ $element_id ] ) ) {
            unset( $this->debug_data[ $element_id ] );
        }
    }
}

// Instantiate the class after Elementor is loaded.
add_action( 'elementor/init', function() {
    new Simple_Elementor_URL_Condition();
} );
