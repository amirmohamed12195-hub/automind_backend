<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07101f">
    <meta name="robots" content="noindex, nofollow">
    <title>AutoMind Admin — Command Center</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-page">
    <aside class="admin-sidebar" data-admin-sidebar>
        <div class="sidebar-brand-wrap">
            <a class="brand" href="/" aria-label="Go to AutoMind website">
                <span class="brand-mark"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span>
                <span class="brand-name">AUTO<span>MIND</span></span>
            </a>
            <button class="sidebar-close" type="button" aria-label="Close menu" data-sidebar-close>×</button>
        </div>
        <div class="workspace-chip"><span class="workspace-avatar">AM</span><div><strong>AutoMind HQ</strong><small>Production workspace</small></div><span>⌄</span></div>
        <nav class="admin-nav" aria-label="Admin navigation">
            <span class="nav-label">Workspace</span>
            <button class="admin-nav-item active" type="button" data-admin-view="overview"><i>⌂</i><span>Overview</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="landing"><i>◫</i><span>Landing page</span><b class="nav-live">LIVE</b></button>
            <button class="admin-nav-item" type="button" data-admin-view="diagnostics"><i>◎</i><span>Diagnostics</span><b>24</b></button>
            <button class="admin-nav-item" type="button" data-admin-view="users"><i>♙</i><span>Users</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="vehicles"><i>◇</i><span>Vehicles</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="mechanics"><i>⌘</i><span>Mechanics</span><b>8</b></button>
            <button class="admin-nav-item" type="button" data-admin-view="appointments"><i>▦</i><span>Appointments</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="billing"><i>¤</i><span>Billing</span><b>{{ $billingOverview['eventsNeedingAttention'] }}</b></button>
            <span class="nav-label">System</span>
            <button class="admin-nav-item" type="button" data-admin-view="ai"><i>✦</i><span>AI operations</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="notifications"><i>♢</i><span>Notifications</span></button>
            <button class="admin-nav-item" type="button" data-admin-view="settings"><i>⚙</i><span>Settings</span></button>
        </nav>
        <div class="sidebar-status"><div><span><i></i> All systems operational</span><small>Last checked 2m ago</small></div><b>99.9%</b></div>
        <div class="sidebar-user">
            <span class="user-avatar">AD</span>
            <div><strong>{{ session('automind_admin_username', 'admin') }}</strong><small>Super administrator</small></div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" aria-label="Sign out" title="Sign out">↪</button>
            </form>
        </div>
    </aside>

    <div class="admin-shell">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="sidebar-open" type="button" aria-label="Open menu" data-sidebar-open>☰</button>
                <div class="breadcrumbs"><span>AutoMind</span><i>/</i><strong data-current-view>Overview</strong></div>
            </div>
            <div class="topbar-actions">
                <label class="admin-search"><span>⌕</span><input type="search" placeholder="Search anything..." aria-label="Search dashboard"><kbd>⌘ K</kbd></label>
                <a class="preview-site-button" href="/" target="_blank">View live site <span>↗</span></a>
                <button class="icon-button" type="button" aria-label="Notifications" data-demo-action="No new notifications"><span>♢</span><i></i></button>
                <button class="icon-button" type="button" aria-label="Help" data-demo-action="Help center is coming soon">?</button>
            </div>
        </header>

        <main class="admin-content">
            <section class="admin-view active" data-view="overview">
                <div class="admin-heading">
                    <div><span class="admin-eyebrow"><i></i> LIVE OVERVIEW</span><h1>Good morning, {{ ucfirst(session('automind_admin_username', 'admin')) }}.</h1><p>Here’s what’s happening across AutoMind today.</p></div>
                    <div class="heading-actions"><button class="admin-button secondary" type="button" data-demo-action="Report exported successfully">⇩ Export report</button><button class="admin-button primary" type="button" data-admin-view="landing">Edit landing page <span>→</span></button></div>
                </div>

                <div class="metric-grid">
                    <article class="metric-card"><div class="metric-top"><span class="metric-icon blue">♙</span><span class="metric-trend up">↗ 12.4%</span></div><small>Total users</small><strong>12,842</strong><p><i style="--bar:74%"></i></p><span>1,284 active this week</span></article>
                    <article class="metric-card"><div class="metric-top"><span class="metric-icon cyan">◎</span><span class="metric-trend up">↗ 8.7%</span></div><small>Diagnostics</small><strong>8,291</strong><p><i style="--bar:63%"></i></p><span>327 completed today</span></article>
                    <article class="metric-card"><div class="metric-top"><span class="metric-icon purple">✦</span><span class="metric-trend up">↗ 3.2%</span></div><small>AI success rate</small><strong>97.8%</strong><p><i style="--bar:92%"></i></p><span>Across all model runs</span></article>
                    <article class="metric-card"><div class="metric-top"><span class="metric-icon amber">⌘</span><span class="metric-trend neutral">8 pending</span></div><small>Verified mechanics</small><strong>486</strong><p><i style="--bar:82%"></i></p><span>42 cities covered</span></article>
                </div>

                <div class="dashboard-grid">
                    <article class="admin-panel performance-panel">
                        <div class="panel-title"><div><strong>Diagnostic activity</strong><span>Completed analyses over time</span></div><div class="segmented"><button class="active" type="button">7 days</button><button type="button">30 days</button><button type="button">90 days</button></div></div>
                        <div class="chart-summary"><strong>2,184</strong><span class="metric-trend up">↗ 14.2%</span><small>vs. previous 7 days</small></div>
                        <div class="line-chart" aria-label="Diagnostic activity chart">
                            <div class="chart-lines"><i></i><i></i><i></i><i></i></div>
                            <div class="chart-bars"><span style="--h:38%"><i></i></span><span style="--h:54%"><i></i></span><span style="--h:46%"><i></i></span><span style="--h:72%"><i></i></span><span style="--h:62%"><i></i></span><span style="--h:89%"><i></i></span><span style="--h:78%"><i></i></span></div>
                            <div class="chart-labels"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span></div>
                        </div>
                    </article>
                    <article class="admin-panel health-panel">
                        <div class="panel-title"><div><strong>Platform health</strong><span>Live service status</span></div><span class="healthy-pill"><i></i> Healthy</span></div>
                        <div class="health-score"><div class="health-ring"><span>98<small>%</small></span></div><div><strong>Excellent</strong><span>All core systems stable</span></div></div>
                        <div class="service-list"><div><span><i class="green"></i> API response</span><strong>184 ms</strong></div><div><span><i class="green"></i> AI processing</span><strong>2.4 sec</strong></div><div><span><i class="green"></i> Queue health</span><strong>Optimal</strong></div><div><span><i class="amber-dot"></i> Storage</span><strong>72%</strong></div></div>
                    </article>
                    <article class="admin-panel recent-panel">
                        <div class="panel-title"><div><strong>Recent diagnostics</strong><span>Latest vehicle analyses</span></div><button class="panel-link" type="button" data-admin-view="diagnostics">View all →</button></div>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Driver</th><th>Vehicle</th><th>Primary finding</th><th>Confidence</th><th>Status</th><th>Time</th></tr></thead>
                                <tbody>
                                    <tr><td><span class="table-person blue-person">MK</span><strong>Malak Khaled</strong></td><td><strong>BMW 320i</strong><small>2021 · 48K km</small></td><td>Ignition coil wear</td><td><span class="confidence-value"><i style="--w:94%"></i></span><b>94%</b></td><td><span class="status-pill success">Completed</span></td><td>4 min ago</td></tr>
                                    <tr><td><span class="table-person purple-person">YA</span><strong>Youssef Ali</strong></td><td><strong>Kia Sportage</strong><small>2020 · 62K km</small></td><td>Brake pad wear</td><td><span class="confidence-value"><i style="--w:89%"></i></span><b>89%</b></td><td><span class="status-pill success">Completed</span></td><td>18 min ago</td></tr>
                                    <tr><td><span class="table-person cyan-person">NS</span><strong>Nour Samir</strong></td><td><strong>Toyota Corolla</strong><small>2022 · 31K km</small></td><td>Analyzing signals</td><td><span class="confidence-value"><i style="--w:61%"></i></span><b>—</b></td><td><span class="status-pill processing">Processing</span></td><td>23 min ago</td></tr>
                                    <tr><td><span class="table-person amber-person">AR</span><strong>Ahmed Rami</strong></td><td><strong>Hyundai Tucson</strong><small>2019 · 79K km</small></td><td>Battery degradation</td><td><span class="confidence-value"><i style="--w:91%"></i></span><b>91%</b></td><td><span class="status-pill success">Completed</span></td><td>36 min ago</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </article>
                    <article class="admin-panel sources-panel">
                        <div class="panel-title"><div><strong>Input sources</strong><span>How drivers start a diagnosis</span></div><button class="icon-small">•••</button></div>
                        <div class="donut-wrap"><div class="donut"><span>8.2K<small>Total</small></span></div><div class="donut-legend"><span><i class="legend-blue"></i><b>Symptoms</b><strong>38%</strong></span><span><i class="legend-cyan"></i><b>OBD data</b><strong>29%</strong></span><span><i class="legend-purple"></i><b>Photos</b><strong>21%</strong></span><span><i class="legend-slate"></i><b>Audio</b><strong>12%</strong></span></div></div>
                    </article>
                </div>
            </section>

            <section class="admin-view" data-view="billing">
                <div class="admin-heading compact-heading">
                    <div><span class="admin-eyebrow"><i></i> STORE & ENTITLEMENTS</span><h1>Billing</h1><p>Live catalog, entitlement, transaction, and store-event controls.</p></div>
                    <div class="heading-actions"><span class="saved-state">Environment: {{ strtoupper(config('billing.environment')) }}</span></div>
                </div>
                @if (session('billing_status'))
                    <div class="editor-note"><span>✓</span><p><strong>{{ session('billing_status') }}</strong></p></div>
                @endif
                @if ($errors->any())
                    <div class="editor-note"><span>!</span><p><strong>{{ $errors->first() }}</strong></p></div>
                @endif
                <div class="resource-stats">
                    <article><span class="metric-icon blue">¤</span><div><small>Active subscriptions</small><strong>{{ number_format($billingOverview['activeSubscriptions']) }}</strong></div></article>
                    <article><span class="metric-icon amber">◌</span><div><small>Grace / billing retry</small><strong>{{ number_format($billingOverview['graceOrRetry']) }}</strong></div></article>
                    <article><span class="metric-icon cyan">▤</span><div><small>Credits outstanding</small><strong>{{ number_format($billingOverview['creditsOutstanding']) }}</strong></div></article>
                    <article><span class="metric-icon red">!</span><div><small>Events needing attention</small><strong>{{ number_format($billingOverview['eventsNeedingAttention']) }}</strong></div></article>
                </div>

                <article class="admin-panel resource-table-panel">
                    <div class="panel-title"><div><strong>Plans and feature limits</strong><span>Publishing does not enable an unconfirmed store product.</span></div></div>
                    <div class="admin-table-wrap">
                        <table class="admin-table resource-table">
                            <thead><tr><th>Plan</th><th>Vehicles</th><th>Reports / period</th><th>Active</th><th>Published</th><th>Recommended</th><th>Action</th></tr></thead>
                            <tbody>
                                @foreach ($billingPlans as $plan)
                                    <tr>
                                        <td><form id="billing-plan-{{ $plan->id }}" method="POST" action="{{ route('admin.billing.plans.update', $plan) }}">@csrf @method('PATCH')</form><strong>{{ $plan->localizations->firstWhere('locale', 'en')?->display_name ?? $plan->code }}</strong><small>{{ $plan->code }}</small></td>
                                        <td><input form="billing-plan-{{ $plan->id }}" name="maxVehicles" type="number" min="1" value="{{ $plan->max_vehicles }}" style="width:72px"></td>
                                        <td><input form="billing-plan-{{ $plan->id }}" name="reportsPerPeriod" type="number" min="1" value="{{ $plan->reports_per_period }}" style="width:72px"></td>
                                        <td><input form="billing-plan-{{ $plan->id }}" type="hidden" name="active" value="0"><input form="billing-plan-{{ $plan->id }}" name="active" type="checkbox" value="1" @checked($plan->active)></td>
                                        <td><input form="billing-plan-{{ $plan->id }}" type="hidden" name="published" value="0"><input form="billing-plan-{{ $plan->id }}" name="published" type="checkbox" value="1" @checked($plan->published)></td>
                                        <td><input form="billing-plan-{{ $plan->id }}" type="hidden" name="recommended" value="0"><input form="billing-plan-{{ $plan->id }}" name="recommended" type="checkbox" value="1" @checked($plan->recommended)></td>
                                        <td><button form="billing-plan-{{ $plan->id }}" class="admin-button secondary" type="submit">Save</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="admin-panel resource-table-panel" style="margin-top:18px">
                    <div class="panel-title"><div><strong>Store products</strong><span>IDs are immutable after purchases exist. Confirm store status before sale.</span></div></div>
                    <div class="admin-table-wrap">
                        <table class="admin-table resource-table">
                            <thead><tr><th>Platform</th><th>Environment</th><th>Product / base plan</th><th>Plan</th><th>Store price</th><th>Store status</th><th>For sale</th><th>Last sync</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($billingProducts as $product)
                                    <tr>
                                        <td><form id="billing-product-{{ $product->id }}" method="POST" action="{{ route('admin.billing.products.update', $product) }}">@csrf @method('PATCH')</form><strong>{{ ucfirst($product->platform) }}</strong></td>
                                        <td>{{ ucfirst($product->environment) }}</td>
                                        <td><strong>{{ $product->product_id }}</strong><small>{{ $product->base_plan_id ?: '—' }}</small></td>
                                        <td>{{ $product->plan?->code }}</td>
                                        <td><strong>{{ $product->priceSnapshots->first()?->formatted_price ?? 'Not synced' }}</strong><small>{{ $product->priceSnapshots->first()?->country_code ?? 'store authoritative' }}</small></td>
                                        <td><select form="billing-product-{{ $product->id }}" name="storeStatus"><option @selected($product->store_status === 'pending')>pending</option><option @selected($product->store_status === 'active')>active</option><option @selected($product->store_status === 'rejected')>rejected</option><option @selected($product->store_status === 'retired')>retired</option></select></td>
                                        <td><input form="billing-product-{{ $product->id }}" type="hidden" name="activeForSale" value="0"><input form="billing-product-{{ $product->id }}" name="activeForSale" type="checkbox" value="1" @checked($product->active_for_sale)></td>
                                        <td>{{ $product->last_synced_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td><button form="billing-product-{{ $product->id }}" class="admin-button secondary" type="submit">Save</button></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="admin-panel resource-table-panel" style="margin-top:18px">
                    <div class="panel-title"><div><strong>Recent transactions</strong><span>Tokens and signed payloads are never displayed.</span></div></div>
                    <div class="admin-table-wrap"><table class="admin-table resource-table"><thead><tr><th>User</th><th>Platform</th><th>Product</th><th>State</th><th>Store completion</th><th>Verified</th></tr></thead><tbody>
                        @forelse ($billingTransactions as $purchase)
                            <tr><td>{{ $purchase->user?->email ?? 'Deleted user' }}</td><td>{{ ucfirst($purchase->platform) }}</td><td><strong>{{ $purchase->product_id }}</strong><small>{{ $purchase->storeProduct?->plan?->code }}</small></td><td><span class="status-pill {{ in_array($purchase->state, ['active', 'gracePeriod']) ? 'success' : 'processing' }}">{{ $purchase->state }}</span></td><td>{{ $purchase->acknowledged || $purchase->consumed ? 'Complete' : 'Pending' }}</td><td>{{ $purchase->last_verified_at?->diffForHumans() ?? 'Never' }}</td></tr>
                        @empty <tr><td colspan="6">No verified store transactions yet.</td></tr> @endforelse
                    </tbody></table></div>
                </article>

                <article class="admin-panel resource-table-panel" style="margin-top:18px">
                    <div class="panel-title"><div><strong>Store events</strong><span>Durable, deduplicated Apple and Google notifications.</span></div></div>
                    <div class="admin-table-wrap"><table class="admin-table resource-table"><thead><tr><th>Platform</th><th>Type</th><th>Environment</th><th>Status</th><th>Attempts</th><th>Received</th><th></th></tr></thead><tbody>
                        @forelse ($billingEvents as $event)
                            <tr><td>{{ ucfirst($event->platform) }}</td><td><strong>{{ $event->event_type }}</strong><small>{{ $event->event_subtype }}</small></td><td>{{ ucfirst($event->environment) }}</td><td><span class="status-pill {{ $event->processing_status === 'processed' ? 'success' : 'processing' }}">{{ $event->processing_status }}</span></td><td>{{ $event->attempts }}</td><td>{{ $event->received_at?->diffForHumans() }}</td><td><form method="POST" action="{{ route('admin.billing.events.reprocess', $event) }}">@csrf<button class="admin-button secondary" type="submit">Reprocess</button></form></td></tr>
                        @empty <tr><td colspan="7">No billing webhook events received yet.</td></tr> @endforelse
                    </tbody></table></div>
                </article>
            </section>

            <section class="admin-view" data-view="landing">
                <div class="admin-heading compact-heading">
                    <div><span class="admin-eyebrow"><i></i> CONTENT STUDIO</span><h1>Landing page</h1><p>Update the public AutoMind experience and publish when ready.</p></div>
                    <div class="heading-actions"><span class="saved-state" data-save-state>All changes saved</span><a class="admin-button secondary" href="/" target="_blank">Preview site ↗</a><button class="admin-button primary" type="button" data-publish-landing>Publish changes</button></div>
                </div>
                <div class="editor-layout">
                    <div class="editor-main">
                        <div class="editor-tabs" role="tablist"><button class="active" type="button" data-editor-tab="content">Content</button><button type="button" data-editor-tab="appearance">Appearance</button><button type="button" data-editor-tab="downloads">App links</button><button type="button" data-editor-tab="seo">SEO</button></div>
                        <form class="landing-form" data-landing-form>
                            <div class="editor-pane active" data-editor-pane="content">
                                <article class="editor-card">
                                    <div class="editor-card-head"><div><span class="editor-number">01</span><div><strong>Hero section</strong><small>The first thing visitors see</small></div></div><label class="toggle"><input type="checkbox" name="heroEnabled" checked><span></span><b>Visible</b></label></div>
                                    <div class="field-grid">
                                        <label class="form-field full"><span>Eyebrow text</span><input name="eyebrow" type="text" value="AI-powered vehicle intelligence" maxlength="60"><small><b data-char-for="eyebrow">39</b>/60</small></label>
                                        <label class="form-field full"><span>Headline</span><textarea name="headline" rows="2" maxlength="90">Your car talks.
AutoMind translates.</textarea><small><b data-char-for="headline">36</b>/90</small></label>
                                        <label class="form-field full"><span>Supporting text</span><textarea name="subheadline" rows="3" maxlength="220">Understand warning lights, strange sounds, and performance issues in minutes—with clear next steps before you reach the workshop.</textarea><small><b data-char-for="subheadline">132</b>/220</small></label>
                                    </div>
                                </article>
                                <article class="editor-card">
                                    <div class="editor-card-head"><div><span class="editor-number">02</span><div><strong>Final call to action</strong><small>The closing download message</small></div></div><label class="toggle"><input type="checkbox" name="ctaEnabled" checked><span></span><b>Visible</b></label></div>
                                    <div class="field-grid"><label class="form-field full"><span>Title</span><input name="ctaTitle" type="text" value="Drive with answers, not assumptions." maxlength="70"></label><label class="form-field full"><span>Supporting text</span><textarea name="ctaText" rows="2" maxlength="160">Download AutoMind and put AI-powered vehicle clarity in your pocket.</textarea></label></div>
                                </article>
                                <article class="editor-card">
                                    <div class="editor-card-head"><div><span class="editor-number">03</span><div><strong>Section visibility</strong><small>Choose what appears on the public page</small></div></div></div>
                                    <div class="visibility-list"><label><span><i>◫</i><b>Results strip</b><small>Key product statistics</small></span><span class="toggle only"><input type="checkbox" name="proofEnabled" checked><span></span></span></label><label><span><i>⌁</i><b>How it works</b><small>Three-step explanation</small></span><span class="toggle only"><input type="checkbox" name="stepsEnabled" checked><span></span></span></label><label><span><i>◇</i><b>Feature collection</b><small>Product capability cards</small></span><span class="toggle only"><input type="checkbox" name="featuresEnabled" checked><span></span></span></label><label><span><i>✓</i><b>Safety promise</b><small>Trust and privacy messaging</small></span><span class="toggle only"><input type="checkbox" name="safetyEnabled" checked><span></span></span></label><label><span><i>“</i><b>Customer story</b><small>Featured testimonial</small></span><span class="toggle only"><input type="checkbox" name="testimonialEnabled" checked><span></span></span></label></div>
                                </article>
                            </div>
                            <div class="editor-pane" data-editor-pane="appearance">
                                <article class="editor-card"><div class="editor-card-head"><div><span class="editor-number">01</span><div><strong>Brand appearance</strong><small>Fine-tune the public experience</small></div></div></div><div class="field-grid"><label class="form-field"><span>Accent color</span><span class="color-field"><input name="accentColor" type="color" value="#087cff"><input name="accentHex" type="text" value="#087cff" maxlength="7"></span></label><label class="form-field"><span>Theme</span><select name="theme"><option value="midnight">Midnight blue</option><option value="deep">Deep black</option><option value="slate">Slate navy</option></select></label><label class="form-field full"><span>Logo display</span><div class="logo-choice"><span><img src="{{ asset('images/automind-logo.png') }}" alt="AutoMind logo"></span><div><strong>AutoMind primary logo</strong><small>PNG · 1254 × 1254 · 997 KB</small></div><button type="button" data-demo-action="Logo upload is ready for backend storage">Replace</button></div></label></div></article>
                            </div>
                            <div class="editor-pane" data-editor-pane="downloads">
                                <article class="editor-card"><div class="editor-card-head"><div><span class="editor-number">01</span><div><strong>Download destinations</strong><small>Connect your official app listings</small></div></div></div><div class="field-grid"><label class="form-field full"><span>Apple App Store URL</span><div class="input-with-icon"><i>●</i><input name="appleUrl" type="url" value="https://apps.apple.com/" placeholder="https://apps.apple.com/app/automind"></div></label><label class="form-field full"><span>Android Auto / Google Play URL</span><div class="input-with-icon android-input"><i>A</i><input name="androidUrl" type="url" value="https://play.google.com/store" placeholder="https://play.google.com/store/apps/details?id=..."></div></label><div class="editor-note"><span>i</span><p><strong>Links open in a new tab.</strong> Replace these placeholders with your live store URLs before launch.</p></div></div></article>
                            </div>
                            <div class="editor-pane" data-editor-pane="seo">
                                <article class="editor-card"><div class="editor-card-head"><div><span class="editor-number">01</span><div><strong>Search & sharing</strong><small>How the page appears outside AutoMind</small></div></div></div><div class="field-grid"><label class="form-field full"><span>Page title</span><input name="seoTitle" value="AutoMind — AI Powered Car Diagnostics" maxlength="65"><small><b>39</b>/65</small></label><label class="form-field full"><span>Meta description</span><textarea name="seoDescription" rows="3" maxlength="160">Understand car problems in minutes with AI-powered diagnostics, repair estimates, maintenance tracking, and trusted mechanic recommendations.</textarea><small><b>143</b>/160</small></label><div class="search-preview"><span>Search preview</span><div><small>automind.app</small><strong>AutoMind — AI Powered Car Diagnostics</strong><p>Understand car problems in minutes with AI-powered diagnostics...</p></div></div></div></article>
                            </div>
                        </form>
                    </div>
                    <aside class="live-preview-panel">
                        <div class="preview-panel-head"><div><strong>Live preview</strong><span>Desktop</span></div><div class="preview-devices"><button class="active" type="button" data-preview-device="desktop" aria-label="Desktop preview">▱</button><button type="button" data-preview-device="mobile" aria-label="Mobile preview">▯</button></div></div>
                        <div class="preview-canvas" data-preview-canvas>
                            <div class="mini-browser"><div class="mini-browser-bar"><i></i><i></i><i></i><span>automind.app</span></div><div class="mini-site"><div class="mini-nav"><span class="mini-logo"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span><b>AUTOMIND</b><i></i><i></i><button>Get the app</button></div><div class="mini-hero"><div class="mini-copy"><small data-preview="eyebrow">AI-POWERED VEHICLE INTELLIGENCE</small><h2 data-preview="headline">Your car talks.<br><em>AutoMind translates.</em></h2><p data-preview="subheadline">Understand warning lights, strange sounds, and performance issues in minutes.</p><div><span>● App Store</span><span>A Android Auto</span></div></div><div class="mini-product"><img src="{{ asset('images/automind-logo.png') }}" alt=""><span>✓ Analysis complete</span></div></div></div></div>
                        </div>
                        <div class="preview-footer"><span><i></i> Preview updates as you type</span><button type="button" data-reset-landing>Reset content</button></div>
                    </aside>
                </div>
            </section>

            <section class="admin-view" data-view="diagnostics" data-resource-view data-resource-name="Diagnostics">
                <div class="admin-heading compact-heading"><div><span class="admin-eyebrow"><i></i> OPERATIONS</span><h1>Diagnostics</h1><p>Review every AI diagnostic session and its current status.</p></div><button class="admin-button secondary" data-demo-action="Diagnostic data exported">⇩ Export diagnostics</button></div>
                <div class="resource-stats"><article><span class="metric-icon blue">◎</span><div><small>Total sessions</small><strong>8,291</strong></div></article><article><span class="metric-icon cyan">✓</span><div><small>Completed</small><strong>7,984</strong></div></article><article><span class="metric-icon amber">◌</span><div><small>Processing</small><strong>283</strong></div></article><article><span class="metric-icon red">!</span><div><small>Needs review</small><strong>24</strong></div></article></div>
                <div class="admin-panel resource-table-panel"><div class="resource-toolbar"><label><span>⌕</span><input type="search" placeholder="Search driver, vehicle, or session..."></label><div><button class="filter-button active">All <b>8,291</b></button><button class="filter-button">Completed</button><button class="filter-button">Processing</button><button class="filter-button">Review</button></div><button class="admin-button secondary">☷ Filters</button></div><div class="admin-table-wrap"><table class="admin-table resource-table"><thead><tr><th><input type="checkbox"></th><th>Session</th><th>Driver & vehicle</th><th>Input</th><th>Finding</th><th>Confidence</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody data-demo-rows></tbody></table></div><div class="table-footer"><span>Showing 1–8 of 8,291 sessions</span><div><button disabled>←</button><button class="active">1</button><button>2</button><button>3</button><span>…</span><button>1,037</button><button>→</button></div></div></div>
            </section>

            <section class="admin-view" data-view="users" data-resource-view data-resource-name="Users"></section>
            <section class="admin-view" data-view="vehicles" data-resource-view data-resource-name="Vehicles"></section>
            <section class="admin-view" data-view="mechanics" data-resource-view data-resource-name="Mechanics"></section>
            <section class="admin-view" data-view="appointments" data-resource-view data-resource-name="Appointments"></section>
            <section class="admin-view" data-view="ai" data-resource-view data-resource-name="AI operations"></section>
            <section class="admin-view" data-view="notifications" data-resource-view data-resource-name="Notifications"></section>
            <section class="admin-view" data-view="settings" data-resource-view data-resource-name="Settings"></section>
        </main>
    </div>

    <div class="admin-overlay" data-admin-overlay></div>
    <div class="admin-toast" role="status" aria-live="polite" data-admin-toast><span>✓</span><div><strong>Changes published</strong><small>Your landing page is now live.</small></div></div>
</body>
</html>
