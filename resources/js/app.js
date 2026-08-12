const LANDING_DRAFT_KEY = 'automindLandingDraft';
const LANDING_PUBLISHED_KEY = 'automindLandingContent';

const landingDefaults = {
    eyebrow: 'AI-powered vehicle intelligence',
    headline: 'Your car talks.\nAutoMind translates.',
    subheadline: 'Understand warning lights, strange sounds, and performance issues in minutes—with clear next steps before you reach the workshop.',
    ctaTitle: 'Drive with answers, not assumptions.',
    ctaText: 'Download AutoMind and put AI-powered vehicle clarity in your pocket.',
    appleUrl: '',
    androidUrl: '',
    accentColor: '#087cff',
    accentHex: '#087cff',
    theme: 'midnight',
    seoTitle: 'AutoMind — AI Powered Car Diagnostics',
    seoDescription: 'Understand car problems in minutes with AI-powered diagnostics, repair estimates, maintenance tracking, and trusted mechanic recommendations.',
    heroEnabled: true,
    ctaEnabled: true,
    proofEnabled: false,
    stepsEnabled: true,
    featuresEnabled: true,
    safetyEnabled: true,
    testimonialEnabled: false,
};

const safeStorageRead = (key) => {
    try {
        const value = JSON.parse(localStorage.getItem(key) || 'null');
        return value && typeof value === 'object' ? value : {};
    } catch {
        return {};
    }
};

const safeStorageWrite = (key, value) => {
    try {
        localStorage.setItem(key, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
};

const buildAccentRgb = (hex) => {
    const normalized = /^#[0-9a-f]{6}$/i.test(hex || '') ? hex.slice(1) : '087cff';
    return `${parseInt(normalized.slice(0, 2), 16)}, ${parseInt(normalized.slice(2, 4), 16)}, ${parseInt(normalized.slice(4, 6), 16)}`;
};

const renderStyledTitle = (element, value, highlightLast = true) => {
    if (!element) return;
    element.replaceChildren();
    const source = String(value || '');
    let lines = source.split('\n').filter(Boolean);
    if (lines.length === 1 && element.matches('h2') && source.includes(',')) {
        const comma = source.indexOf(',') + 1;
        lines = [source.slice(0, comma), source.slice(comma).trim()];
    }
    const displayLines = lines.length > 1 ? lines : [String(value || '')];

    displayLines.forEach((line, index) => {
        if (index > 0) element.append(document.createElement('br'));
        const shouldHighlight = highlightLast && index === displayLines.length - 1;
        const node = shouldHighlight ? document.createElement(element.matches('h2') ? 'em' : 'span') : document.createTextNode('');
        if (node.nodeType === Node.TEXT_NODE) {
            node.textContent = line.trim();
        } else {
            node.textContent = line.trim();
        }
        element.append(node);
    });
};

const applyLandingContent = (content) => {
    const state = { ...landingDefaults, ...content };
    document.documentElement.style.setProperty('--accent', state.accentColor || landingDefaults.accentColor);
    document.documentElement.style.setProperty('--accent-rgb', buildAccentRgb(state.accentColor));
    document.body.dataset.theme = state.theme || 'midnight';

    document.querySelectorAll('[data-landing]').forEach((element) => {
        const key = element.dataset.landing;
        if (state[key] !== undefined) element.textContent = state[key];
    });
    document.querySelectorAll('[data-landing-html]').forEach((element) => {
        const key = element.dataset.landingHtml;
        renderStyledTitle(element, state[key], true);
    });
    document.querySelectorAll('[data-store-link="apple"]').forEach((link) => {
        link.href = state.appleUrl || '#';
        link.toggleAttribute('aria-disabled', !state.appleUrl);
        const status = link.querySelector('[data-store-status]');
        if (status) status.textContent = state.appleUrl ? 'View on the' : 'Coming soon on';
        if (state.appleUrl) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }
    });
    document.querySelectorAll('[data-store-link="android"]').forEach((link) => {
        link.href = state.androidUrl || '#';
        link.toggleAttribute('aria-disabled', !state.androidUrl);
        const status = link.querySelector('[data-store-status]');
        if (status) status.textContent = state.androidUrl ? 'Get it on' : 'Coming soon on';
        if (state.androidUrl) {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }
    });

    const visibility = [
        ['.hero', 'heroEnabled'],
        ['.proof', 'proofEnabled'],
        ['#how-it-works', 'stepsEnabled'],
        ['#features', 'featuresEnabled'],
        ['#safety', 'safetyEnabled'],
        ['.testimonial-section', 'testimonialEnabled'],
        ['.cta-section', 'ctaEnabled'],
    ];
    visibility.forEach(([selector, key]) => {
        const section = document.querySelector(selector);
        if (section) section.dataset.sectionHidden = String(state[key] === false);
    });

    if (state.seoTitle) document.title = state.seoTitle;
    const description = document.querySelector('meta[name="description"]');
    if (description && state.seoDescription) description.content = state.seoDescription;
};

const initializeLanding = () => {
    applyLandingContent(safeStorageRead(LANDING_PUBLISHED_KEY));

    const header = document.querySelector('[data-site-header]');
    const menuButton = document.querySelector('[data-menu-toggle]');
    const mobileMenu = document.querySelector('[data-mobile-menu]');
    const backToTop = document.querySelector('[data-back-to-top]');

    const onScroll = () => {
        header?.classList.toggle('scrolled', window.scrollY > 24);
        backToTop?.classList.toggle('visible', window.scrollY > 650);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    menuButton?.addEventListener('click', () => {
        const open = !menuButton.classList.contains('open');
        menuButton.classList.toggle('open', open);
        mobileMenu?.classList.toggle('open', open);
        menuButton.setAttribute('aria-expanded', String(open));
    });
    mobileMenu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
        menuButton?.classList.remove('open');
        mobileMenu.classList.remove('open');
        menuButton?.setAttribute('aria-expanded', 'false');
    }));
    backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

    const observer = 'IntersectionObserver' in window
        ? new IntersectionObserver((entries) => entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        }), { threshold: 0.08, rootMargin: '0px 0px -35px' })
        : null;
    document.querySelectorAll('.reveal').forEach((element) => {
        if (observer) observer.observe(element);
        else element.classList.add('visible');
    });
};

const diagnosticsRows = [
    ['DX-8291', 'Malak Khaled', 'BMW 320i · 2021', 'Audio + OBD', 'Ignition coil wear', '94%', 'Completed', '4 min ago'],
    ['DX-8290', 'Youssef Ali', 'Kia Sportage · 2020', 'Symptoms', 'Brake pad wear', '89%', 'Completed', '18 min ago'],
    ['DX-8289', 'Nour Samir', 'Toyota Corolla · 2022', 'Photo + Audio', 'Analyzing signals', '—', 'Processing', '23 min ago'],
    ['DX-8288', 'Ahmed Rami', 'Hyundai Tucson · 2019', 'OBD data', 'Battery degradation', '91%', 'Completed', '36 min ago'],
    ['DX-8287', 'Laila Hassan', 'Mercedes C180 · 2021', 'Video', 'Cooling system risk', '86%', 'Review', '52 min ago'],
    ['DX-8286', 'Karim Wael', 'Nissan Qashqai · 2020', 'Symptoms + OBD', 'Oxygen sensor fault', '93%', 'Completed', '1 hr ago'],
    ['DX-8285', 'Sara Mostafa', 'Peugeot 3008 · 2023', 'Audio', 'Suspension noise', '88%', 'Completed', '1 hr ago'],
    ['DX-8284', 'Omar Tarek', 'Renault Duster · 2019', 'Photo + OBD', 'Oil pressure sensor', '90%', 'Completed', '2 hr ago'],
];

const genericData = {
    Users: {
        subtitle: 'Manage driver accounts, access, and activity.',
        stats: [['Total drivers', '12,842'], ['Active today', '1,284'], ['New this month', '846'], ['Support cases', '32']],
        columns: ['User', 'Email', 'Vehicles', 'Diagnostics', 'Plan', 'Joined', 'Status'],
        rows: [
            ['Malak Khaled', 'malak@example.com', '2', '18', 'Premium', 'Jul 18, 2026', 'Active'],
            ['Youssef Ali', 'youssef@example.com', '1', '11', 'Premium', 'Jul 16, 2026', 'Active'],
            ['Nour Samir', 'nour@example.com', '3', '26', 'Family', 'Jul 12, 2026', 'Active'],
            ['Ahmed Rami', 'ahmed@example.com', '1', '5', 'Free', 'Jul 09, 2026', 'Active'],
            ['Laila Hassan', 'laila@example.com', '2', '14', 'Premium', 'Jul 03, 2026', 'Review'],
        ],
        primary: '+ Add user',
    },
    Vehicles: {
        subtitle: 'Monitor registered vehicles and their health records.',
        stats: [['Registered vehicles', '15,946'], ['Healthy', '12,208'], ['Attention due', '2,904'], ['Critical alerts', '834']],
        columns: ['Vehicle', 'Owner', 'Mileage', 'Health score', 'Last diagnosis', 'Next service', 'Status'],
        rows: [
            ['BMW 320i · 2021', 'Malak Khaled', '48,120 km', '92%', '4 min ago', 'Aug 12', 'Healthy'],
            ['Kia Sportage · 2020', 'Youssef Ali', '62,884 km', '81%', '18 min ago', 'Jul 29', 'Attention'],
            ['Toyota Corolla · 2022', 'Nour Samir', '31,460 km', '95%', '23 min ago', 'Sep 04', 'Healthy'],
            ['Hyundai Tucson · 2019', 'Ahmed Rami', '79,203 km', '76%', '36 min ago', 'Jul 25', 'Attention'],
            ['Mercedes C180 · 2021', 'Laila Hassan', '44,085 km', '68%', '52 min ago', 'Jul 23', 'Critical'],
        ],
        primary: '+ Add vehicle',
    },
    Mechanics: {
        subtitle: 'Verify partners, locations, specialties, and availability.',
        stats: [['Verified mechanics', '486'], ['Pending review', '8'], ['Cities covered', '42'], ['Avg. rating', '4.82']],
        columns: ['Mechanic', 'City', 'Specialty', 'Rating', 'Appointments', 'Verification', 'Status'],
        rows: [
            ['AutoPro Garage', 'Cairo', 'European vehicles', '4.9 ★', '184', 'Verified', 'Active'],
            ['DriveCare Center', 'Giza', 'Diagnostics', '4.8 ★', '142', 'Verified', 'Active'],
            ['MotoFix Hub', 'Alexandria', 'Engine & gearbox', '4.9 ★', '113', 'Verified', 'Active'],
            ['Modern Auto Lab', 'Mansoura', 'Electrical systems', '4.7 ★', '96', 'Pending', 'Review'],
            ['German Car House', 'Cairo', 'German vehicles', '4.8 ★', '207', 'Verified', 'Active'],
        ],
        primary: '+ Add mechanic',
    },
    Appointments: {
        subtitle: 'Coordinate workshop bookings across the mechanic network.',
        stats: [['This week', '284'], ['Today', '47'], ['Confirmed', '231'], ['Awaiting action', '12']],
        columns: ['Booking', 'Driver', 'Mechanic', 'Service', 'Date & time', 'Estimate', 'Status'],
        rows: [
            ['AP-1842', 'Malak Khaled', 'AutoPro Garage', 'Ignition inspection', 'Today · 12:30', '$35–$55', 'Confirmed'],
            ['AP-1841', 'Youssef Ali', 'DriveCare Center', 'Brake service', 'Today · 14:00', '$90–$140', 'Confirmed'],
            ['AP-1840', 'Nour Samir', 'MotoFix Hub', 'Full diagnostic', 'Today · 15:30', '$25–$40', 'Pending'],
            ['AP-1839', 'Ahmed Rami', 'Modern Auto Lab', 'Battery replacement', 'Tomorrow · 10:00', '$110–$160', 'Confirmed'],
            ['AP-1838', 'Laila Hassan', 'German Car House', 'Cooling system', 'Tomorrow · 11:30', '$80–$220', 'Review'],
        ],
        primary: '+ New appointment',
    },
    'AI operations': {
        subtitle: 'Monitor model performance, cost, queues, and failed runs.',
        stats: [['Runs today', '4,821'], ['Success rate', '97.8%'], ['Avg. latency', '2.4 sec'], ['Failed runs', '24']],
        columns: ['Run ID', 'Task', 'Model', 'Latency', 'Tokens', 'Cost', 'Status'],
        rows: [
            ['AI-90824', 'Diagnostic synthesis', 'GPT Vision', '2.1 sec', '3,842', '$0.084', 'Completed'],
            ['AI-90823', 'Audio understanding', 'Audio model', '1.8 sec', '2,106', '$0.046', 'Completed'],
            ['AI-90822', 'Price research', 'Web search', '4.9 sec', '5,210', '$0.112', 'Completed'],
            ['AI-90821', 'Diagnostic synthesis', 'GPT Vision', '8.3 sec', '2,987', '$0.067', 'Review'],
            ['AI-90820', 'Report translation', 'Language model', '1.2 sec', '1,440', '$0.021', 'Completed'],
        ],
        primary: 'Retry failed runs',
    },
    Notifications: {
        subtitle: 'Create broadcasts and review customer communication.',
        stats: [['Sent this month', '48,280'], ['Delivery rate', '98.6%'], ['Open rate', '72.4%'], ['Scheduled', '3']],
        columns: ['Campaign', 'Audience', 'Channel', 'Sent', 'Delivered', 'Opened', 'Status'],
        rows: [
            ['Summer vehicle check', 'All drivers', 'Push', '12,842', '98.8%', '76.3%', 'Sent'],
            ['Maintenance reminder', 'Service due', 'Push + email', '2,184', '99.1%', '82.0%', 'Sent'],
            ['New Android Auto app', 'Android users', 'Push', '7,406', '98.2%', '68.9%', 'Sent'],
            ['Mechanic booking update', 'Cairo drivers', 'Email', '4,920', '97.9%', '63.2%', 'Scheduled'],
            ['Safety guidance', 'Critical alerts', 'Push + SMS', '834', '99.7%', '91.4%', 'Sent'],
        ],
        primary: '+ New broadcast',
    },
    Settings: {
        subtitle: 'Control platform defaults, integrations, and team access.',
        stats: [['Team members', '12'], ['API integrations', '8'], ['Active webhooks', '6'], ['Audit events', '18.4K']],
        columns: ['Setting', 'Category', 'Owner', 'Last changed', 'Environment', 'Value', 'Status'],
        rows: [
            ['Diagnostic confidence threshold', 'AI & Safety', 'Omar Adel', '2 days ago', 'Production', '78%', 'Active'],
            ['Raw media retention', 'Privacy', 'System', '5 days ago', 'All', '30 days', 'Active'],
            ['Default currency', 'Pricing', 'Omar Adel', '1 week ago', 'Production', 'EGP', 'Active'],
            ['OpenAI webhook', 'Integrations', 'System', '2 weeks ago', 'Production', 'Connected', 'Healthy'],
            ['Arabic content', 'Localization', 'Nour Emad', '3 weeks ago', 'All', 'Enabled', 'Active'],
        ],
        primary: 'Save settings',
    },
};

const statusClass = (status) => {
    if (/active|healthy|verified|completed|confirmed|sent/i.test(status)) return 'success';
    return 'processing';
};

const populateDiagnostics = () => {
    const body = document.querySelector('[data-demo-rows]');
    if (!body || body.children.length) return;
    diagnosticsRows.forEach((row, index) => {
        const tr = document.createElement('tr');
        const initials = row[1].split(' ').map((part) => part[0]).join('').slice(0, 2);
        tr.innerHTML = `<td><input type="checkbox" aria-label="Select ${row[0]}"></td><td><strong>${row[0]}</strong></td><td><span class="table-person ${['blue-person', 'purple-person', 'cyan-person', 'amber-person'][index % 4]}">${initials}</span><span><strong>${row[1]}</strong><small>${row[2]}</small></span></td><td>${row[3]}</td><td><strong>${row[4]}</strong></td><td><b>${row[5]}</b></td><td><span class="status-pill ${statusClass(row[6])}">${row[6]}</span></td><td>${row[7]}</td><td><button class="icon-small" data-demo-action="Session ${row[0]} opened">•••</button></td>`;
        body.append(tr);
    });
};

const buildGenericResource = (section, name) => {
    if (section.children.length) return;
    const data = genericData[name] || genericData.Users;
    const stats = data.stats.map((item, index) => `<article><span class="metric-icon ${['blue', 'cyan', 'purple', 'amber'][index]}">${['◇', '✓', '◎', '◌'][index]}</span><div><small>${item[0]}</small><strong>${item[1]}</strong></div></article>`).join('');
    const heads = data.columns.map((column) => `<th>${column}</th>`).join('');
    const rows = data.rows.map((row) => `<tr>${row.map((value, index) => `<td>${index === 0 ? `<strong>${value}</strong>` : index === row.length - 1 ? `<span class="status-pill ${statusClass(value)}">${value}</span>` : value}</td>`).join('')}<td><button class="icon-small" data-demo-action="${name.slice(0, -1) || name} details opened">•••</button></td></tr>`).join('');
    section.innerHTML = `
        <div class="admin-heading compact-heading"><div><span class="admin-eyebrow"><i></i> MANAGEMENT</span><h1>${name}</h1><p>${data.subtitle}</p></div><div class="heading-actions"><button class="admin-button secondary" data-demo-action="${name} data exported">⇩ Export</button><button class="admin-button primary" data-demo-action="${data.primary} action opened">${data.primary}</button></div></div>
        <div class="resource-stats">${stats}</div>
        <div class="generic-resource">
            <article class="admin-panel resource-table-panel"><div class="resource-toolbar"><label><span>⌕</span><input type="search" placeholder="Search ${name.toLowerCase()}..."></label><div><button class="filter-button active">All</button><button class="filter-button">Active</button><button class="filter-button">Review</button></div><button class="admin-button secondary">☷ Filters</button></div><div class="admin-table-wrap"><table class="admin-table resource-table"><thead><tr>${heads}<th></th></tr></thead><tbody>${rows}</tbody></table></div><div class="table-footer"><span>Showing 1–5 of all ${name.toLowerCase()}</span><div><button disabled>←</button><button class="active">1</button><button>2</button><button>3</button><button>→</button></div></div></article>
            <aside class="admin-panel quick-panel"><div class="panel-title"><div><strong>${name} snapshot</strong><span>Last 7 days</span></div></div><div class="empty-chart">${[48, 72, 58, 84, 66, 92, 78].map((height) => `<i style="--h:${height}%"></i>`).join('')}</div><h3>Quick actions</h3><p>Common ${name.toLowerCase()} workflows are available here.</p><div class="quick-actions"><button data-demo-action="Create action opened"><span>＋</span>Create new</button><button data-demo-action="Review queue opened"><span>✓</span>Review queue</button><button data-demo-action="Report generated"><span>⇩</span>Download report</button></div></aside>
        </div>`;
};

const initializeAdmin = () => {
    const demoSectionsEnabled = document.body.dataset.demoSectionsEnabled === 'true';
    if (demoSectionsEnabled) {
        populateDiagnostics();
        document.querySelectorAll('[data-resource-view]').forEach((section) => {
            const name = section.dataset.resourceName;
            if (name !== 'Diagnostics') buildGenericResource(section, name);
        });
    } else {
        document.querySelectorAll('.admin-nav-item:not([data-admin-view="billing"])').forEach((item) => item.remove());
        document.querySelectorAll('.admin-view:not([data-view="billing"])').forEach((section) => section.remove());
        document.querySelectorAll('[data-demo-action], .admin-search').forEach((item) => item.remove());
    }

    const sidebar = document.querySelector('[data-admin-sidebar]');
    const overlay = document.querySelector('[data-admin-overlay]');
    const toast = document.querySelector('[data-admin-toast]');
    let toastTimer;

    const showToast = (title, description = 'Your request has been completed.') => {
        if (!toast) return;
        toast.querySelector('strong').textContent = title;
        toast.querySelector('small').textContent = description;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
    };

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    };
    document.querySelector('[data-sidebar-open]')?.addEventListener('click', () => {
        sidebar?.classList.add('open');
        overlay?.classList.add('show');
    });
    document.querySelector('[data-sidebar-close]')?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    const switchView = (view) => {
        const target = document.querySelector(`.admin-view[data-view="${view}"]`);
        if (!target) return;
        document.querySelectorAll('.admin-view').forEach((section) => section.classList.toggle('active', section === target));
        document.querySelectorAll('.admin-nav-item').forEach((item) => item.classList.toggle('active', item.dataset.adminView === view));
        const label = document.querySelector('[data-current-view]');
        const navLabel = document.querySelector(`.admin-nav-item[data-admin-view="${view}"] span`);
        if (label) label.textContent = navLabel?.textContent || view.charAt(0).toUpperCase() + view.slice(1);
        history.replaceState(null, '', view === 'overview' ? '/admin' : `/admin#${view}`);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        closeSidebar();
    };

    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-admin-view]');
        if (viewButton && !viewButton.matches('section')) {
            event.preventDefault();
            switchView(viewButton.dataset.adminView);
            return;
        }
        const action = event.target.closest('[data-demo-action]');
        if (action) {
            event.preventDefault();
            showToast(action.dataset.demoAction, 'The dashboard interaction is working in preview mode.');
        }
        const filter = event.target.closest('.filter-button, .segmented button');
        if (filter) {
            filter.parentElement.querySelectorAll('button').forEach((button) => button.classList.toggle('active', button === filter));
        }
    });

    const initialView = demoSectionsEnabled ? location.hash.replace('#', '') : 'billing';
    if (initialView && document.querySelector(`.admin-view[data-view="${initialView}"]`)) switchView(initialView);

    const search = document.querySelector('.admin-search input');
    window.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            search?.focus();
        }
        if (event.key === 'Escape') {
            search?.blur();
            closeSidebar();
        }
    });

    const form = document.querySelector('[data-landing-form]');
    if (!form) return;
    const draft = { ...landingDefaults, ...safeStorageRead(LANDING_PUBLISHED_KEY), ...safeStorageRead(LANDING_DRAFT_KEY) };

    const fillForm = (state) => {
        Array.from(form.elements).forEach((field) => {
            if (!field.name || state[field.name] === undefined) return;
            if (field.type === 'checkbox') field.checked = Boolean(state[field.name]);
            else field.value = state[field.name];
        });
        refreshEditorPreview(state);
        updateCharacterCounters();
    };

    const readForm = () => {
        const state = { ...landingDefaults };
        Array.from(form.elements).forEach((field) => {
            if (!field.name) return;
            state[field.name] = field.type === 'checkbox' ? field.checked : field.value;
        });
        state.accentColor = state.accentHex && /^#[0-9a-f]{6}$/i.test(state.accentHex) ? state.accentHex : state.accentColor;
        return state;
    };

    const saveState = document.querySelector('[data-save-state]');
    let saveTimer;
    function updateCharacterCounters() {
        document.querySelectorAll('[data-char-for]').forEach((counter) => {
            const input = form.elements[counter.dataset.charFor];
            if (input) counter.textContent = String(input.value.length);
        });
    }

    function refreshEditorPreview(state) {
        document.querySelectorAll('[data-preview]').forEach((element) => {
            const value = state[element.dataset.preview];
            if (element.dataset.preview === 'headline') renderStyledTitle(element, value, true);
            else element.textContent = value;
        });
        const accent = state.accentColor || state.accentHex || landingDefaults.accentColor;
        document.querySelector('.mini-site')?.style.setProperty('--accent', accent);
        document.querySelector('.mini-nav button')?.style.setProperty('background', accent);
        document.querySelector('[data-preview="headline"] em')?.style.setProperty('color', accent);
    }

    const queueSave = () => {
        const state = readForm();
        refreshEditorPreview(state);
        updateCharacterCounters();
        if (saveState) {
            saveState.textContent = 'Saving draft...';
            saveState.style.opacity = '.7';
        }
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            safeStorageWrite(LANDING_DRAFT_KEY, state);
            if (saveState) {
                saveState.textContent = 'All changes saved';
                saveState.style.opacity = '1';
            }
        }, 420);
    };

    fillForm(draft);
    form.addEventListener('input', (event) => {
        if (event.target.name === 'accentColor') form.elements.accentHex.value = event.target.value;
        if (event.target.name === 'accentHex' && /^#[0-9a-f]{6}$/i.test(event.target.value)) form.elements.accentColor.value = event.target.value;
        queueSave();
    });
    form.addEventListener('change', queueSave);

    document.querySelectorAll('[data-editor-tab]').forEach((tab) => tab.addEventListener('click', () => {
        document.querySelectorAll('[data-editor-tab]').forEach((item) => item.classList.toggle('active', item === tab));
        document.querySelectorAll('[data-editor-pane]').forEach((pane) => pane.classList.toggle('active', pane.dataset.editorPane === tab.dataset.editorTab));
    }));
    document.querySelectorAll('[data-preview-device]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-preview-device]').forEach((item) => item.classList.toggle('active', item === button));
        document.querySelector('[data-preview-canvas]')?.classList.toggle('mobile', button.dataset.previewDevice === 'mobile');
    }));
    document.querySelector('[data-publish-landing]')?.addEventListener('click', () => {
        const state = readForm();
        safeStorageWrite(LANDING_DRAFT_KEY, state);
        safeStorageWrite(LANDING_PUBLISHED_KEY, state);
        showToast('Changes published', 'Your landing page content is now live.');
    });
    document.querySelector('[data-reset-landing]')?.addEventListener('click', () => {
        fillForm(landingDefaults);
        safeStorageWrite(LANDING_DRAFT_KEY, landingDefaults);
        showToast('Draft reset', 'Default AutoMind content has been restored.');
    });
};

if (document.body.classList.contains('landing-page')) initializeLanding();
if (document.body.classList.contains('admin-page')) initializeAdmin();
