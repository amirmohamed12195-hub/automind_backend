<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#030812">
    <meta name="description" content="AutoMind uses AI to turn car symptoms, sounds, images, and OBD data into clear diagnostic guidance.">
    <title>AutoMind — AI Powered Car Diagnostics</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-page landing-refresh">
    <div class="page-noise" aria-hidden="true"></div>

    <header class="site-header" data-site-header>
        <a class="brand" href="#top" aria-label="AutoMind home">
            <span class="brand-mark"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span>
            <span class="brand-name">AUTO<span>MIND</span></span>
        </a>
        <nav class="desktop-nav" aria-label="Main navigation">
            <a href="#how-it-works">How it works</a>
            <a href="#features">Features</a>
            <a href="#safety">Why AutoMind</a>
            <a href="#download">Download</a>
        </nav>
        <div class="header-actions">
            <a class="text-link" href="/admin">Admin portal</a>
            <a class="button button-small button-primary" href="#download">Get the app <span>↗</span></a>
            <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" data-menu-toggle>
                <span></span><span></span>
            </button>
        </div>
        <div class="mobile-menu" data-mobile-menu>
            <a href="#how-it-works">How it works</a>
            <a href="#features">Features</a>
            <a href="#safety">Why AutoMind</a>
            <a href="#download">Download</a>
            <a href="/admin">Admin portal</a>
        </div>
    </header>

    <main id="top">
        <section class="hero section-shell">
            <div class="hero-glow hero-glow-one" aria-hidden="true"></div>
            <div class="hero-glow hero-glow-two" aria-hidden="true"></div>
            <div class="hero-grid" aria-hidden="true"></div>

            <div class="hero-copy reveal">
                <div class="eyebrow"><span class="live-dot"></span><span data-landing="eyebrow">AI-powered vehicle intelligence</span></div>
                <h1 data-landing-html="headline">Your car talks.<br><span>AutoMind translates.</span></h1>
                <p data-landing="subheadline">Understand warning lights, strange sounds, and performance issues in minutes—with clear next steps before you reach the workshop.</p>
                <div class="hero-actions" id="download"><x-store-badges /></div>
                <a class="hero-explore" href="#how-it-works">See how it works <span>↓</span></a>
                <div class="trust-row">
                    <div class="trust-avatars" aria-hidden="true">
                        <span>✓</span>
                    </div>
                    <div><strong>Private by design</strong><span>Safety-aware guidance with clear limitations.</span></div>
                </div>
            </div>

            <div class="hero-visual cockpit-visual reveal reveal-delay" aria-label="Illustrative AutoMind diagnostic report">
                <div class="cockpit-halo" aria-hidden="true"></div>
                <div class="cockpit-card">
                    <div class="cockpit-top"><span><img src="{{ asset('images/automind-logo.png') }}" width="32" height="32" alt=""> Vehicle intelligence</span><span class="sample-label">SAMPLE REPORT</span></div>
                    <div class="cockpit-title"><div><small>A clearer view of your car</small><h2>Every signal.<br>One clear picture.</h2></div><span class="cockpit-ai"><x-icon name="sparkles" size="26" /></span></div>
                    <div class="vehicle-scan" aria-hidden="true">
                        <div class="scan-floor"></div>
                        <svg viewBox="0 0 500 230" fill="none">
                            <defs><linearGradient id="car-body" x1="70" y1="65" x2="380" y2="220" gradientUnits="userSpaceOnUse"><stop stop-color="#9bc8f4"/><stop offset=".4" stop-color="#2b567e"/><stop offset="1" stop-color="#101d30"/></linearGradient><linearGradient id="car-glass"><stop stop-color="#0c1c30"/><stop offset="1" stop-color="#4382b8"/></linearGradient></defs>
                            <ellipse cx="253" cy="196" rx="194" ry="15" fill="#078bff" opacity=".1"/>
                            <path d="m56 137 34-28 68-15 44-47q13-12 33-12h74q20 1 35 16l42 43 49 19q16 6 17 23l-3 36-28 11H83l-35-16Z" fill="url(#car-body)" stroke="#689dd0" stroke-width="1.5"/>
                            <path d="m172 95 38-42q5-6 16-6h75q12 1 21 10l36 37Z" fill="url(#car-glass)" stroke="#86b6df"/>
                            <path d="M260 47v48M165 107l3 54M275 108v50M361 108l13 41M72 131l358-8M210 112h20M301 112h19" stroke="#8eb7dc" stroke-opacity=".65" stroke-width="2"/>
                            <path d="m53 145 39-5 13 12-52 4M418 131l27 4-1 15-27-4" fill="#9edfff"/>
                            <path d="m57 170 32 7m100 1h148m81-3 29-9" stroke="#101c2e" stroke-width="5"/>
                            <circle cx="137" cy="173" r="35" fill="#070f1d" stroke="#32516e" stroke-width="3"/><circle cx="137" cy="173" r="23" fill="#344b63" stroke="#8dabcb" stroke-width="2"/><circle cx="137" cy="173" r="8" fill="#b6d2e9"/>
                            <circle cx="379" cy="173" r="35" fill="#070f1d" stroke="#32516e" stroke-width="3"/><circle cx="379" cy="173" r="23" fill="#344b63" stroke="#8dabcb" stroke-width="2"/><circle cx="379" cy="173" r="8" fill="#b6d2e9"/>
                            <path d="m121 157 32 32m0-32-32 32m242-32 32 32m0-32-32 32" stroke="#a7c7e2" stroke-width="3"/>
                            <path d="M106 85V66h34M399 66h27v21M106 207v12h34M399 219h27v-16" stroke="#48d7e8" stroke-opacity=".65"/>
                        </svg>
                        <span class="scan-target"><i></i> Engine system</span>
                    </div>
                    <div class="input-signals"><span><x-icon name="activity" size="15" /> Sound</span><span><x-icon name="grid" size="15" /> Photo</span><span><x-icon name="car" size="15" /> OBD data</span><span><x-icon name="sparkles" size="15" /> Symptoms</span></div>
                    <div class="cockpit-result"><span class="result-check"><x-icon name="shield" size="22" /></span><div><small>FROM SIGNALS TO NEXT STEPS</small><strong>Understand. Plan. Drive.</strong><p>Likely causes, urgency, and repair guidance.</p></div><span>↗</span></div>
                </div>
                <div class="cockpit-caption"><span><i></i> Intelligence for the road ahead</span><small>Illustrative preview</small></div>
            </div>
        </section>

        <section class="signal-strip" aria-label="AutoMind capabilities">
            <div class="signal-marquee">
                <span>Visual inspection</span><i>✦</i><span>Sound analysis</span><i>✦</i><span>OBD intelligence</span><i>✦</i><span>Repair estimates</span><i>✦</i><span>Mechanic network</span><i>✦</i><span>Maintenance tracking</span>
            </div>
        </section>

        <section class="proof section-shell reveal" aria-label="AutoMind product facts">
            <div class="proof-item"><strong>4</strong><span>Input types</span></div>
            <div class="proof-item"><strong>Clear</strong><span>Urgency levels</span></div>
            <div class="proof-item"><strong>EN + AR</strong><span>Bilingual experience</span></div>
            <div class="proof-item"><strong>Built-in</strong><span>Privacy controls</span></div>
        </section>

        <section class="how-section section-shell" id="how-it-works">
            <div class="section-heading reveal">
                <div><span class="section-kicker">HOW IT WORKS</span><h2>From uncertainty to clarity.<br><em>In three simple steps.</em></h2></div>
                <p>AutoMind combines what you see, hear, and feel with vehicle data to build a clear, actionable diagnosis.</p>
            </div>
            <div class="step-grid">
                <article class="step-card reveal">
                    <span class="step-number">01</span>
                    <div class="step-visual sound-visual" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <h3>Tell us what’s happening</h3>
                    <p>Describe the symptom, record a sound, upload a photo, or connect your OBD scanner.</p>
                    <span class="step-link">Multi-modal input <b>↗</b></span>
                </article>
                <article class="step-card featured reveal reveal-delay">
                    <span class="step-number">02</span>
                    <div class="step-visual ai-visual" aria-hidden="true"><span class="ai-core">AI</span><span></span><span></span><span></span></div>
                    <h3>AI connects the signals</h3>
                    <p>Our diagnostic engine compares every input, checks likely causes, and evaluates urgency.</p>
                    <span class="step-link">Intelligent analysis <b>↗</b></span>
                </article>
                <article class="step-card reveal reveal-delay-two">
                    <span class="step-number">03</span>
                    <div class="step-visual report-visual" aria-hidden="true"><span>✓</span><i></i><i></i><i></i></div>
                    <h3>Get your action plan</h3>
                    <p>See the likely fault, safety level, expected cost, and the right next step in plain language.</p>
                    <span class="step-link">Clear recommendations <b>↗</b></span>
                </article>
            </div>
        </section>

        <section class="feature-section" id="features">
            <div class="section-shell">
                <div class="section-heading centered reveal">
                    <span class="section-kicker">ONE APP. TOTAL CLARITY.</span>
                    <h2>Everything your car<br><em>wishes it could tell you.</em></h2>
                </div>
                <div class="feature-grid">
                    <article class="feature-card feature-large reveal">
                        <div class="feature-copy"><span class="feature-icon">◎</span><h3>AI diagnosis that sees the whole picture</h3><p>Combine symptoms, photos, audio, and live OBD readings for a more complete answer than a code reader alone.</p><a href="#download">Explore AI diagnosis <span>→</span></a></div>
                        <div class="diagnosis-panel" aria-hidden="true">
                            <div class="panel-head"><span>DIAGNOSTIC REPORT</span><span class="success-tag">Complete</span></div>
                            <div class="panel-score"><span>Most likely cause</span><strong>Ignition coil wear</strong><small>Confidence score</small><div class="score-bar"><i></i></div><b>92%</b></div>
                            <div class="panel-actions"><span><i class="amber"></i> Moderate priority</span><span>Est. $120–$260</span></div>
                        </div>
                    </article>
                    <article class="feature-card reveal">
                        <span class="feature-icon">⌁</span><h3>Hear what others miss</h3><p>Record unusual engine, brake, or suspension sounds and let AI compare acoustic patterns.</p>
                        <div class="micro-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    </article>
                    <article class="feature-card reveal reveal-delay">
                        <span class="feature-icon">◇</span><h3>Know the price before the visit</h3><p>Get current part and labor ranges so you can walk into the workshop informed.</p>
                        <div class="price-pill"><span>Example repair estimate</span><strong>$180 <small>– $340</small></strong></div>
                    </article>
                    <article class="feature-card reveal">
                        <span class="feature-icon">⌖</span><h3>Find the right expert nearby</h3><p>Connect with verified mechanics matched to your issue, vehicle, and location.</p>
                        <div class="mechanic-row"><span class="mechanic-avatar">AM</span><div><strong>AutoPro Garage</strong><small>Example workshop listing</small></div><b>Verified</b></div>
                    </article>
                    <article class="feature-card feature-wide reveal reveal-delay">
                        <div class="feature-copy"><span class="feature-icon">↻</span><h3>Your vehicle’s memory, always with you</h3><p>Keep diagnostics, repairs, service history, and maintenance reminders organized in one intelligent timeline.</p></div>
                        <div class="timeline-mini" aria-hidden="true"><span><i></i><b>Today</b><small>AI diagnostic completed</small></span><span><i></i><b>Jun 18</b><small>Oil service recorded</small></span><span><i></i><b>Apr 02</b><small>Brake inspection</small></span></div>
                    </article>
                </div>
            </div>
        </section>

        <section class="safety-section section-shell" id="safety">
            <div class="safety-visual reveal">
                <div class="shield-orbit"><span>✓</span></div>
                <div class="safety-chip safety-chip-one"><i></i><span><small>Vehicle data</small><strong>Encrypted</strong></span></div>
                <div class="safety-chip safety-chip-two"><i></i><span><small>Decision support</small><strong>Safety first</strong></span></div>
            </div>
            <div class="safety-copy reveal reveal-delay">
                <span class="section-kicker">BUILT FOR BETTER DECISIONS</span>
                <h2>Confidence behind<br><em>every recommendation.</em></h2>
                <p>AutoMind is designed to make vehicle care clearer—not to replace professional inspection. Every result explains confidence, urgency, and when to stop driving.</p>
                <ul>
                    <li><span>✓</span><div><strong>Safety-aware guidance</strong><small>Critical risks are prioritized and clearly explained.</small></div></li>
                    <li><span>✓</span><div><strong>Evidence you can understand</strong><small>See why AutoMind reached each conclusion.</small></div></li>
                    <li><span>✓</span><div><strong>Your data stays yours</strong><small>Protected media and private vehicle records.</small></div></li>
                </ul>
            </div>
        </section>

        <section class="cta-section section-shell reveal">
            <div class="cta-card">
                <div class="cta-grid" aria-hidden="true"></div>
                <span class="section-kicker">YOUR CAR. UNDERSTOOD.</span>
                <h2 data-landing-html="ctaTitle">Drive with answers,<br><em>not assumptions.</em></h2>
                <p data-landing="ctaText">Download AutoMind and put AI-powered vehicle clarity in your pocket.</p>
                <div class="hero-actions centered-actions"><a class="button button-primary cta-download" href="#download">Get AutoMind <x-icon name="arrow" /></a></div>
                <span class="cta-platforms">Available for iOS and Android</span>
            </div>
        </section>
    </main>

    <footer class="site-footer section-shell">
        <div class="footer-top">
            <a class="brand" href="#top"><span class="brand-mark"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span><span class="brand-name">AUTO<span>MIND</span></span></a>
            <p>AI-powered car diagnostics for clearer, safer decisions.</p>
        </div>
        <div class="footer-links">
            <div><strong>Product</strong><a href="#how-it-works">How it works</a><a href="#features">Features</a><a href="#download">Download</a></div>
            <div><strong>Company</strong><a href="#safety">About AutoMind</a><a href="{{ route('support') }}">Contact</a></div>
            <div><strong>Support</strong><a href="{{ route('support') }}">Help center</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a><a href="{{ route('account-deletion.show') }}">Delete account</a></div>
            <div><strong>For teams</strong><a href="/admin">Admin portal</a><a href="/docs/api">API docs</a><a href="{{ route('support') }}">Partners</a></div>
        </div>
        <div class="footer-bottom"><span>© {{ date('Y') }} AutoMind. All rights reserved.</span><span>Made for drivers who want to know.</span></div>
    </footer>

    <button class="back-to-top" type="button" aria-label="Back to top" data-back-to-top>↑</button>
    <div class="site-toast" role="status" aria-live="polite" data-site-toast></div>
</body>
</html>
