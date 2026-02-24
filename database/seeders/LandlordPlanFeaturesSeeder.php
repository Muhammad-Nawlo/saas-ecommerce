<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Landlord\Models\Feature;
use App\Landlord\Models\Plan;
use App\Landlord\Models\PlanFeature;
use Illuminate\Database\Seeder;

class LandlordPlanFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $connection = config('tenancy.database.central_connection', config('database.default'));

        Feature::on($connection)->firstOrCreate(
            ['code' => 'products_limit'],
            ['description' => 'Maximum number of products', 'type' => Feature::TYPE_LIMIT]
        );
        Feature::on($connection)->firstOrCreate(
            ['code' => 'custom_domain'],
            ['description' => 'Allow custom domain', 'type' => Feature::TYPE_BOOLEAN]
        );

        $productsLimit = Feature::on($connection)->where('code', 'products_limit')->first();
        $customDomain = Feature::on($connection)->where('code', 'custom_domain')->first();

        $starter = Plan::on($connection)->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'price' => 0,
                'billing_interval' => 'monthly',
            ]
        );
        $starter->update(['name' => 'Starter', 'price' => 0, 'billing_interval' => 'monthly']);

        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $starter->id, 'feature_id' => $productsLimit->id],
            ['value' => '50']
        );
        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $starter->id, 'feature_id' => $customDomain->id],
            ['value' => '0']
        );

        $pro = Plan::on($connection)->firstOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'price' => 0,
                'billing_interval' => 'monthly',
            ]
        );
        $pro->update(['name' => 'Pro', 'price' => 99, 'billing_interval' => 'monthly']);

        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $pro->id, 'feature_id' => $productsLimit->id],
            ['value' => '5000']
        );
        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $pro->id, 'feature_id' => $customDomain->id],
            ['value' => '1']
        );

        $enterprise = Plan::on($connection)->firstOrCreate(
            ['code' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'price' => 0,
                'billing_interval' => 'monthly',
            ]
        );
        $enterprise->update(['name' => 'Enterprise', 'price' => 299, 'billing_interval' => 'yearly']);

        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $enterprise->id, 'feature_id' => $productsLimit->id],
            ['value' => '-1']
        );
        PlanFeature::on($connection)->firstOrCreate(
            ['plan_id' => $enterprise->id, 'feature_id' => $customDomain->id],
            ['value' => '1']
        );
    }
}
