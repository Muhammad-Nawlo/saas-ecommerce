<x-filament-panels::page>
    @php
        $subscription = $this->getSubscription();
        $plan = $subscription?->plan;
        $limits = $this->getPlanLimits();
        $portalUrl = $this->getPortalUrl();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Current plan</x-slot>
        @if($plan)
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Plan</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $plan->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Subscription status</dt>
                    <dd class="mt-1">
                        <x-filament::badge :color="match($subscription->status ?? '') {
                            'active' => 'success',
                            'trialing' => 'info',
                            'past_due' => 'warning',
                            default => 'gray'
                        }">{{ $subscription->status ?? '—' }}</x-filament::badge>
                    </dd>
                </div>
                @if($subscription->current_period_end ?? null)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Current period ends</dt>
                        <dd class="mt-1 text-sm">{{ $subscription->current_period_end->format('M j, Y') }}</dd>
                    </div>
                @endif
            </dl>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No active subscription.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Plan limits</x-slot>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach($limits as $code => $value)
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ str_replace('_', ' ', ucfirst($code)) }}</dt>
                    <dd class="mt-1 text-sm font-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Manage billing</x-slot>
        <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Update payment method, view invoices, or change plan in Stripe Customer Portal.</p>
        @if($portalUrl)
            <x-filament::button tag="a" href="{{ $portalUrl }}" color="primary" icon="heroicon-o-arrow-top-right-on-square">
                Open billing portal
            </x-filament::button>
        @else
            <x-filament::badge color="warning">Billing portal is not available. Contact support.</x-filament::badge>
        @endif
    </x-filament::section>
</x-filament-panels::page>
