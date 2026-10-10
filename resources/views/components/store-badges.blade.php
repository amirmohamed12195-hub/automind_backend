<div {{ $attributes->class(['store-badges']) }}>
    <a class="store-badge" href="{{ config('public.app_store_url') }}" data-store-link="apple" aria-label="Download AutoMind on the App Store" target="_blank" rel="noopener noreferrer">
        <img src="{{ asset('images/stores/app-store.svg') }}" width="162" height="54" alt="Download on the App Store">
    </a>
    <a class="store-badge store-badge-google" href="{{ config('public.play_store_url') }}" data-store-link="android" aria-label="Get AutoMind on Google Play" target="_blank" rel="noopener noreferrer">
        <img src="{{ asset('images/stores/google-play.png') }}" width="209" height="81" alt="Get it on Google Play">
    </a>
</div>
