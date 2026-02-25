<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\PlanFeature;
use Illuminate\Database\Seeder;

/**
 * Attaches features to plans. Run on central (landlord) connection.
 */
final class FeaturePlanSeeder extends Seeder
{
    /** Feature codes as per requirements. */
    private const FEATURE_CODES = [
        'products_limit',
        'multi_currency',
        'multi_location_inventory',
        'advanced_reports',
    ];

    public function run(): void
    {
        $basic = Plan::where('code', 'basic')->first();
        $pro = Plan::where('code', 'pro')->first();
        if ($basic === null || $pro === null) {
            $this->command?->warn('FeaturePlanSeeder: Basic or Pro plan not found. Run LandlordSeeder first.');
            return;
        }

        foreach (self::FEATURE_CODES as $code) {
            $feature = Feature::where('code', $code)->first();
            if ($feature === null) {
                continue;
            }

            if ($feature->isLimit()) {
                $basicValue = $code === 'products_limit' ? '50' : '0';
                $proValue = $code === 'products_limit' ? '500' : '1';
                $this->syncWithValue($basic, $feature, $basicValue);
                $this->syncWithValue($pro, $feature, $proValue);
            } else {
                $this->syncWithValue($basic, $feature, '0');
                $this->syncWithValue($pro, $feature, '1');
            }
        }
    }

    private function syncWithValue(Plan $plan, Feature $feature, string $value): void
    {
        $existing = PlanFeature::where('plan_id', $plan->id)->where('feature_id', $feature->id)->first();
        if ($existing !== null) {
            $existing->update(['value' => $value]);
        } else {
            PlanFeature::create([
                'plan_id' => $plan->id,
                'feature_id' => $feature->id,
                'value' => $value,
            ]);
        }
    }
}
