<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;

abstract class PortalWidget extends \Elementor\Widget_Base {
    protected string $view;
    public function get_name(): string { return 'iaai-' . $this->view; }
    public function get_title(): string { return 'IAAI ' . ucwords($this->view); }
    public function get_icon(): string { return 'eicon-lock-user'; }
    public function get_categories(): array { return ['general']; }
    public function get_style_depends(): array { return ['iaai-portal']; }
    public function get_script_depends(): array { return ['iaai-portal']; }
    protected function is_dynamic_content(): bool { return true; }
    protected function register_controls(): void {
        $this->start_controls_section('content', ['label' => 'Content']);
        $this->add_control('heading', ['label' => 'English heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('heading_hi', ['label' => 'Hindi heading', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('intro', ['label' => 'Helper text', 'type' => \Elementor\Controls_Manager::TEXTAREA, 'default' => '']);
        $this->add_control('empty_message', ['label' => 'Empty-state message', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => '']);
        $this->add_control('service', ['label' => 'Enquiry service', 'type' => \Elementor\Controls_Manager::SELECT, 'default' => 'health', 'options' => ['health' => 'Health', 'term' => 'Term', 'policy_review' => 'Policy review', 'claim_help' => 'Claim help']]);
        $this->end_controls_section();
        $this->start_controls_section('style', ['label' => 'Appearance', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('accent', ['label' => 'Accent', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .iaai-app' => '--iaai-teal: {{VALUE}};']]);
        $this->add_control('background', ['label' => 'Background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .iaai-panel' => 'background: {{VALUE}};']]);
        $this->add_responsive_control('padding', ['label' => 'Spacing', 'type' => \Elementor\Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'rem'], 'selectors' => ['{{WRAPPER}} .iaai-panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'body_type', 'selector' => '{{WRAPPER}} .iaai-app']);
        $this->add_control('columns', ['label' => 'Card columns', 'type' => \Elementor\Controls_Manager::SELECT, 'options' => ['1' => 'One', '2' => 'Two', '3' => 'Three'], 'default' => '2', 'selectors' => ['{{WRAPPER}} .iaai-card-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0,1fr));']]);
        $this->end_controls_section();
    }
    protected function render(): void { echo Elementor::shell($this->view, $this->get_settings_for_display()); }
}

final class AuthWidget extends PortalWidget { protected string $view = 'auth'; }
final class DashboardWidget extends PortalWidget { protected string $view = 'dashboard'; }
final class ProfileWidget extends PortalWidget { protected string $view = 'profile'; }
final class ReportsWidget extends PortalWidget { protected string $view = 'reports'; }
final class UploadWidget extends PortalWidget { protected string $view = 'upload'; }
final class TreatmentWidget extends PortalWidget { protected string $view = 'treatment'; }
final class EnquiriesWidget extends PortalWidget { protected string $view = 'enquiries'; }
final class SharingWidget extends PortalWidget { protected string $view = 'sharing'; }
final class SettingsWidget extends PortalWidget { protected string $view = 'settings'; }
final class GuidanceWidget extends PortalWidget { protected string $view = 'guidance'; }
final class NavigationWidget extends PortalWidget { protected string $view = 'navigation'; }
final class ContactWidget extends PortalWidget { protected string $view = 'contact'; }

final class CalculatorsWidget extends PortalWidget {
    protected string $view = 'calculators';
    protected function register_controls(): void {
        parent::register_controls();
        $this->start_controls_section('calculator_labels', ['label' => 'Calculator text']);
        $this->add_control('calculate_label', ['label' => 'Calculate button', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Calculate']);
        foreach (['tax' => 1, 'term' => 5, 'ulip' => 4, 'salary' => 4] as $kind => $count) {
            foreach (['title', 'note', 'result'] as $key) { $this->add_control($kind . '_' . $key, ['label' => ucfirst($kind) . ' ' . $key, 'type' => \Elementor\Controls_Manager::TEXTAREA]); }
            for ($i = 0; $i < $count; $i++) { $this->add_control($kind . '_field_' . $i, ['label' => ucfirst($kind) . ' field ' . ($i + 1), 'type' => \Elementor\Controls_Manager::TEXT]); }
        }
        $this->end_controls_section();
        $this->start_controls_section('calculator_style', ['label' => 'Calculator appearance', 'tab' => \Elementor\Controls_Manager::TAB_STYLE]);
        $this->add_control('card_color', ['label' => 'Card background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .iaai-calculator' => 'background: {{VALUE}};']]);
        $this->add_control('button_color', ['label' => 'Button background', 'type' => \Elementor\Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} button' => 'background: {{VALUE}};']]);
        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), ['name' => 'calculator_type', 'selector' => '{{WRAPPER}} .iaai-calculator']);
        $this->end_controls_section();
    }
}
