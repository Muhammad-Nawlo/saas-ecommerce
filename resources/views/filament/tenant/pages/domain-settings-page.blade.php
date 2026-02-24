<x-filament-panels::page>
    @php
        $primaryDomain = $this->getPrimaryDomain();
        $hasCustomDomain = $this->hasCustomDomainFeature();
    @endphp

    <x-filament::section>
        <x-slot name="heading">Current domain</x-slot>
        <p class="text-sm font-medium">{{ $primaryDomain }}</p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Custom domain</x-slot>
        @if($hasCustomDomain)
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Add or update your custom domain. Configure your DNS CNAME to point to this application.</p>
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    placeholder="shop.example.com"
                    disabled
                />
            </x-filament::input.wrapper>
            <p class="mt-2 text-xs text-gray-500">Custom domain configuration is managed by your administrator.</p>
        @else
            <p class="text-sm text-gray-600 dark:text-gray-300">Custom domain is not included in your current plan.</p>
            <x-filament::badge color="info" class="mt-2">Upgrade your plan to use a custom domain.</x-filament::badge>
        @endif
    </x-filament::section>
</x-filament-panels::page>
