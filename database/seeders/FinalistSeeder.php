<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Finalist;
use Illuminate\Database\Seeder;

/**
 * Synthetic demo finalists. Every name, department, title and description is
 * fictional and was written for this portfolio demo.
 */
class FinalistSeeder extends Seeder
{
    public const FINALISTS = [
        // Category A: Idea Generation
        ['code' => 'IG-001', 'category' => 'idea-generation', 'name' => 'Participant 001', 'department' => 'Department A', 'order' => 1,
            'project_title' => 'Smart Energy Monitoring System',
            'description' => 'Install low-cost IoT power meters on the main production lines and show real-time consumption on a shared dashboard so supervisors can spot idle-load waste. A two-line pilot indicates a potential 8% reduction in electricity use.'],
        ['code' => 'IG-002', 'category' => 'idea-generation', 'name' => 'Participant 002', 'department' => 'Department B', 'order' => 2,
            'project_title' => 'Automated Inventory Tracking',
            'description' => 'Replace manual stock counts with barcode scanning and a lightweight web app. Cycle counts that took two people a full day are completed in under two hours, with fewer discrepancies.'],
        ['code' => 'IG-003', 'category' => 'idea-generation', 'name' => 'Participant 003', 'department' => 'Department C', 'order' => 3,
            'project_title' => 'Production Downtime Reduction',
            'description' => 'Standardize a quick-changeover checklist and a color-coded tool board to cut setup time between product variants. Target: reduce unplanned downtime by 15% within six months.'],
        ['code' => 'IG-004', 'category' => 'idea-generation', 'name' => 'Participant 004', 'department' => 'Department D', 'order' => 4,
            'project_title' => 'Digital Quality Inspection',
            'description' => 'Move paper inspection forms to tablets with mandatory fields, photo evidence and automatic out-of-tolerance alerts, removing re-keying and improving traceability.'],
        ['code' => 'IG-005', 'category' => 'idea-generation', 'name' => 'Participant 005', 'department' => 'Department A', 'order' => 5,
            'project_title' => 'Warehouse Space Optimization',
            'description' => 'Re-slot the warehouse using pick-frequency (ABC) analysis and vertical racking, freeing roughly 20% of floor space and shortening picker travel distance.'],
        ['code' => 'IG-006', 'category' => 'idea-generation', 'name' => 'Participant 006', 'department' => 'Department B', 'order' => 6,
            'project_title' => 'Predictive Maintenance Alerts',
            'description' => 'Use vibration and temperature trends from existing sensors to raise maintenance work orders before failures occur on critical pumps.'],
        ['code' => 'IG-007', 'category' => 'idea-generation', 'name' => 'Participant 007', 'department' => 'Department C', 'order' => 7,
            'project_title' => 'Paperless Shift Handover',
            'description' => 'A structured digital handover log with open-issue tracking so no information is lost between shifts.'],
        ['code' => 'IG-008', 'category' => 'idea-generation', 'name' => 'Participant 008', 'department' => 'Department D', 'order' => 8,
            'project_title' => 'Reusable Packaging Loop',
            'description' => 'Replace single-use cartons with returnable crates on the three busiest internal routes, reducing packaging waste and cost.'],
        ['code' => 'IG-009', 'category' => 'idea-generation', 'name' => 'Participant 009', 'department' => 'Department A', 'order' => 9,
            'project_title' => 'Visual Management Dashboard',
            'description' => 'Display live KPIs (output, scrap, OEE) on shop-floor screens, replacing manual whiteboard updates.'],
        ['code' => 'IG-010', 'category' => 'idea-generation', 'name' => 'Participant 010', 'department' => 'Department B', 'order' => 10,
            'project_title' => 'Standard Work Instruction Video Library',
            'description' => 'Create a searchable library of short video work instructions to shorten the onboarding time of new operators.'],

        // Category B: Feasibility Study
        ['code' => 'FS-001', 'category' => 'feasibility-study', 'name' => 'Participant 011', 'department' => 'Department C', 'order' => 1,
            'project_title' => 'Rooftop Solar Pilot Feasibility Study',
            'description' => 'Technical and financial feasibility of a 150 kWp rooftop solar installation, including payback period, structural assessment and grid connection requirements.'],
        ['code' => 'FS-002', 'category' => 'feasibility-study', 'name' => 'Participant 012', 'department' => 'Department D', 'order' => 2,
            'project_title' => 'Robotic Palletizing Cell Assessment',
            'description' => 'Assess replacing manual palletizing with a collaborative robot cell: investment, safety, throughput and return on investment.'],
        ['code' => 'FS-003', 'category' => 'feasibility-study', 'name' => 'Participant 013', 'department' => 'Department A', 'order' => 3,
            'project_title' => 'Mobile Maintenance Work Order App',
            'description' => 'Compare buying and building a mobile maintenance app for technicians: cost, integration effort and adoption risk.'],
        ['code' => 'FS-004', 'category' => 'feasibility-study', 'name' => 'Participant 014', 'department' => 'Department B', 'order' => 4,
            'project_title' => 'Cooling Water Recycling System',
            'description' => 'Feasibility of a closed-loop cooling water recycling system expected to cut water consumption by about 30%.'],
        ['code' => 'FS-005', 'category' => 'feasibility-study', 'name' => 'Participant 015', 'department' => 'Department C', 'order' => 5,
            'project_title' => 'Automated Tool Crib Kiosk',
            'description' => 'Evaluate a badge-controlled tool dispensing kiosk to reduce tool loss and track tool usage per team.'],
    ];

    public function run(): void
    {
        $categories = Category::all()->keyBy('slug');

        foreach (self::FINALISTS as $data) {
            $category = $categories[$data['category']] ?? null;

            if (! $category) {
                $this->command?->warn('Category '.$data['category'].' not found, skipping '.$data['code']);

                continue;
            }

            Finalist::updateOrCreate(
                ['code' => $data['code']],
                [
                    'category_id' => $category->id,
                    'name' => $data['name'],
                    'department' => $data['department'],
                    'project_title' => $data['project_title'],
                    'description' => $data['description'],
                    'display_order' => $data['order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
