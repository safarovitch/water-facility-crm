<script setup lang="ts">
import FannLogo from '@/components/FannLogo.vue';
import { dashboard, login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Leaf,
    Mail,
    MapPin,
    Mountain,
    Phone,
    ShieldCheck,
    Truck,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const isLoggedIn = computed(() => Boolean(page.props.auth?.user));

function onWhereIsMyWater(event: MouseEvent) {
    if (isLoggedIn.value) return; // Let the #coverage anchor scroll natively.
    event.preventDefault();
    // Use a query param instead of a fragment — Inertia's redirect handling
    // (X-Inertia-Location) drops fragments on the auth round-trip, but query
    // params survive cleanly. Welcome.vue picks this up on mount.
    const ret = encodeURIComponent('/?scrollTo=coverage');
    window.location.href = `/auth/start?return=${ret}`;
}

const phone = '+992 17 860 50 05';
const phoneHref = 'tel:+992178605005';
const whatsappHref = 'https://wa.me/992178605005';
const email = 'water@fann.tj';

// --- Live courier map -------------------------------------------------------
const mapEl = ref<HTMLDivElement | null>(null);
const courierCount = ref(0);
const mapReady = ref(false);
const trackingMode = ref<'idle' | 'personal' | 'public'>('idle');
let leafletMap: any = null;
let markersLayer: any = null;
let pollTimer: number | null = null;

const LEAFLET_VERSION = '1.9.4';
const LEAFLET_CSS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.css`;
const LEAFLET_JS = `https://unpkg.com/leaflet@${LEAFLET_VERSION}/dist/leaflet.js`;

function loadLeaflet(): Promise<any> {
    if (typeof window === 'undefined') return Promise.resolve(null);
    const w = window as any;
    if (w.L) return Promise.resolve(w.L);

    if (!document.querySelector(`link[href="${LEAFLET_CSS}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = LEAFLET_CSS;
        document.head.appendChild(link);
    }

    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${LEAFLET_JS}"]`,
        );
        if (existing) {
            existing.addEventListener('load', () => resolve((window as any).L));
            existing.addEventListener('error', reject);
            return;
        }
        const script = document.createElement('script');
        script.src = LEAFLET_JS;
        script.async = true;
        script.onload = () => resolve((window as any).L);
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

type CourierFix = { lat: number; lng: number; updated_at: string };

async function fetchPersonalTracking(): Promise<CourierFix | null> {
    try {
        const res = await fetch('/me/active-tracking', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return null;
        const data = (await res.json()) as
            | { tracking: false }
            | { tracking: true; courier: CourierFix };
        return data.tracking ? data.courier : null;
    } catch {
        return null;
    }
}

async function fetchPublicLocations(): Promise<CourierFix[]> {
    try {
        const res = await fetch('/api/v1/public/curriers/locations', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) return [];
        const data = (await res.json()) as { count: number; locations: CourierFix[] };
        return data.locations;
    } catch {
        return [];
    }
}

function renderMarkers(L: any, fixes: CourierFix[]) {
    if (!leafletMap || !markersLayer) return;
    markersLayer.clearLayers();
    for (const fix of fixes) {
        const marker = L.marker([fix.lat, fix.lng], {
            icon: L.divIcon({
                className: 'fann-courier-marker',
                html: '<span class="dot"></span><span class="pulse"></span>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            }),
            keyboard: false,
            interactive: false,
        });
        markersLayer.addLayer(marker);
    }
}

async function refreshMap(L: any) {
    if (isLoggedIn.value) {
        const own = await fetchPersonalTracking();
        if (own) {
            trackingMode.value = 'personal';
            courierCount.value = 1;
            renderMarkers(L, [own]);
            // Re-center on the user's courier so it's always in view
            leafletMap?.setView([own.lat, own.lng], Math.max(leafletMap.getZoom(), 13), {
                animate: true,
            });
            return;
        }
    }
    const fixes = await fetchPublicLocations();
    trackingMode.value = 'public';
    courierCount.value = fixes.length;
    renderMarkers(L, fixes);
}

function scrollToTargetIfPresent() {
    if (typeof window === 'undefined') return;

    // Two ways to land here with a scroll target:
    // 1. ?scrollTo=<id>   — used after the auth round-trip (Inertia drops fragments)
    // 2. #<id>            — direct anchor links / nav menu clicks
    const params = new URLSearchParams(window.location.search);
    const scrollParam = params.get('scrollTo');
    const hashId = window.location.hash ? decodeURIComponent(window.location.hash.slice(1)) : null;
    const id = scrollParam || hashId;
    if (!id) return;

    // Two RAFs: first lets Vue finish patching, second lets layout settle
    // (Leaflet map height, Fraunces font swap, image decoding, etc.).
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document
                .getElementById(id)
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });

            // Strip ?scrollTo= so a refresh / share doesn't re-scroll forever.
            if (scrollParam) {
                params.delete('scrollTo');
                const search = params.toString();
                const newUrl =
                    window.location.pathname +
                    (search ? `?${search}` : '') +
                    window.location.hash;
                window.history.replaceState(null, '', newUrl);
            }
        });
    });
}

onMounted(async () => {
    // Inertia's SPA navigation doesn't always honor URL fragments (e.g. after
    // /auth/start → /login → redirect()->intended('/#coverage')). Force it.
    scrollToTargetIfPresent();

    if (!mapEl.value) return;
    try {
        const L = await loadLeaflet();
        if (!L || !mapEl.value) return;
        leafletMap = L.map(mapEl.value, {
            center: [38.5598, 68.787],
            zoom: 12,
            scrollWheelZoom: false,
            attributionControl: true,
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(leafletMap);
        markersLayer = L.layerGroup().addTo(leafletMap);
        mapReady.value = true;
        refreshMap(L);
        pollTimer = window.setInterval(() => refreshMap(L), 15000);
    } catch {
        /* leaflet failed to load – section still shows the static map column */
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (leafletMap) {
        leafletMap.remove();
        leafletMap = null;
    }
});

const features = [
    {
        icon: Mountain,
        title: 'Mountain Spring Source',
        description:
            'Bottled directly at the source in the Varzob valley, where Tajikistan’s alpine snow-melt feeds pure underground springs.',
    },
    {
        icon: ShieldCheck,
        title: 'Multi-Stage Filtration',
        description:
            'Mechanical, carbon and UV filtration preserve natural minerals while removing every impurity.',
    },
    {
        icon: Truck,
        title: 'Free Home & Office Delivery',
        description:
            'Year-round delivery across Dushanbe and surrounding districts. Schedule once — we keep you stocked.',
    },
    {
        icon: Leaf,
        title: 'Naturally Balanced Minerals',
        description:
            'Light, soft taste with the calcium, magnesium and potassium your body actually needs.',
    },
];

const steps = [
    { n: '01', title: 'Place an order', text: 'Call, WhatsApp or send a quick message — tell us how many bottles and where.' },
    { n: '02', title: 'We deliver', text: 'Same-day or next-day delivery to your home or office, no minimum order.' },
    { n: '03', title: 'Stay hydrated', text: 'We pick up empties on the next visit. You only ever pay for the water.' },
];

const plans = [
    {
        name: 'Single Bottle',
        price: '19',
        unit: 'TJS',
        per: '/ 19L bottle',
        features: ['Pay-as-you-go', 'No subscription', 'Delivered same / next day'],
        cta: 'Order one',
        highlight: false,
    },
    {
        name: 'Home Pack',
        price: '69',
        unit: 'TJS',
        per: '/ 4 bottles',
        features: ['Best for families', 'Free delivery', 'Flexible schedule'],
        cta: 'Order Home Pack',
        highlight: true,
    },
    {
        name: 'Office Plan',
        price: 'Custom',
        unit: '',
        per: '8+ bottles per delivery',
        features: ['Dedicated account manager', 'Dispensers available', 'Invoiced billing'],
        cta: 'Request a quote',
        highlight: false,
    },
];
</script>

<template>
    <Head title="fann — Mountain Spring Water · 19L Delivery in Tajikistan">
        <meta
            name="description"
            content="fann · Pure 19L mountain spring water from the Varzob valley, delivered to homes and offices across Tajikistan year-round."
        />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=fraunces:300,400,500&display=swap"
        />
    </Head>

    <div class="min-h-screen bg-[#f6f6f6] text-slate-900 antialiased">
        <!-- Top nav -->
        <header class="sticky top-0 z-40 border-b border-sky-100/80 bg-[#f6f6f6]/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="#top" class="flex items-center text-slate-900">
                    <FannLogo variant="inline" class="h-9 w-auto" />
                </a>

                <nav class="hidden items-center gap-7 text-sm text-slate-600 md:flex">
                    <a href="#about" class="hover:text-sky-600">About</a>
                    <a href="#features" class="hover:text-sky-600">Why us</a>
                    <a href="#how" class="hover:text-sky-600">How it works</a>
                    <a href="#pricing" class="hover:text-sky-600">Pricing</a>
                    <a href="#coverage" class="hover:text-sky-600">Delivery</a>
                    <a href="#contact" class="hover:text-sky-600">Contact</a>
                </nav>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="hidden rounded-full border border-slate-200 px-4 py-2 text-sm font-medium hover:border-slate-300 sm:inline-block"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="hidden rounded-full px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 sm:inline-block"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="register()"
                            class="hidden rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:inline-block"
                        >
                            Sign up
                        </Link>
                    </template>
                    <a
                        :href="phoneHref"
                        class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-sky-600"
                    >
                        <Phone class="h-4 w-4" />
                        <span class="hidden sm:inline">Order now</span>
                        <span class="sm:hidden">Order</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section id="top" class="relative overflow-hidden bg-[#f6f6f6]">
            <div class="mx-auto grid max-w-6xl gap-12 px-6 pt-16 pb-20 md:grid-cols-2 md:items-center md:pt-24 md:pb-28">
                <div>
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-sky-200 bg-white/70 px-3 py-1 text-xs font-medium text-sky-700"
                    >
                        <Mountain class="h-3.5 w-3.5" />
                        fann · sourced in Varzob, Tajikistan
                    </span>
                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl md:text-6xl">
                        Pure mountain spring water,<br />
                        <span class="text-sky-600">delivered to your door.</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-lg text-slate-600">
                        19-litre bottles of natural Varzob valley spring water, professionally
                        filtered and brought to homes and offices across Tajikistan — every
                        week, all year round.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a
                            :href="phoneHref"
                            class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-sky-600"
                        >
                            <Phone class="h-5 w-5" />
                            Call to order
                        </a>
                        <a
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-6 py-3 text-base font-semibold text-slate-800 transition hover:border-slate-300"
                        >
                            WhatsApp
                        </a>
                    </div>

                    <dl class="mt-10 grid grid-cols-3 gap-6 border-t border-slate-200 pt-6 text-sm">
                        <div>
                            <dt class="text-slate-500">Bottle size</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">19 L</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Delivery</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">Year-round</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Source</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">Varzob</dd>
                        </div>
                    </dl>
                </div>

                <!-- Visual: 19L bottle hero photo -->
                <div class="relative mx-auto flex h-[420px] w-full max-w-md items-center justify-center md:h-[560px]">
                    <!-- bottle, locked to the photo's natural aspect ratio -->
                    <div class="relative flex h-full items-center justify-center">
                        <img
                            src="/images/bottles.webp"
                            alt="fann 19L mountain spring water bottles"
                            class="block h-full w-auto max-w-full object-contain select-none"
                            loading="eager"
                            decoding="async"
                        />

                        <!-- on-bottle wordmark + slogan, lower-left of the upright bottle -->
                        <div
                            class="pointer-events-none absolute top-[64%] left-[22%] translate-x-[calc(-50%_+_9px)] translate-y-[calc(calc(1_/_2_*_100%)_*_-2_+_10px)] text-center"
                        >
                            <FannLogo
                                variant="wordmark"
                                :show-tagline="false"
                                class="mx-auto h-14 w-auto text-sky-950"
                            />
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 220 22"
                                class="mx-auto -mt-3 block h-[17px] w-auto text-sky-900/80"
                                preserveAspectRatio="xMidYMid meet"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <defs>
                                    <path
                                        id="fann-slug-arc"
                                        d="M 14 16 Q 110 21 206 16"
                                        fill="none"
                                    />
                                </defs>
                                <text
                                    fill="currentColor"
                                    font-family="'Inter', 'Instrument Sans', system-ui, sans-serif"
                                    font-size="9"
                                    font-weight="600"
                                    letter-spacing="3"
                                >
                                    <textPath
                                        href="#fann-slug-arc"
                                        startOffset="50%"
                                        text-anchor="middle"
                                    >MOUNTAIN SPRING</textPath>
                                </text>
                            </svg>
                        </div>

                        <!-- 19 L marker, just under the wordmark -->
                        <div
                            class="pointer-events-none absolute top-[78%] left-[22%] translate-x-[calc(-50%_+_13px)] translate-y-[calc(-50%_-_50px)] text-[12px] font-semibold tracking-tight text-sky-700"
                        >
                            19 L
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- About -->
        <section id="about" class="mx-auto max-w-6xl px-6 py-20">
            <div class="grid gap-12 md:grid-cols-2 md:items-center">
                <div class="order-2 md:order-1">
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">Our source</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        From the Varzob valley, untouched and unhurried.
                    </h2>
                    <p class="mt-5 text-slate-600">
                        Our water is collected in the Varzob gorge, where the Hisor mountains feed deep,
                        natural springs with snow-melt that has been filtering through stone for years.
                        We bottle it close to the source to preserve its mineral profile and crisp taste.
                    </p>
                    <ul class="mt-6 space-y-3 text-slate-700">
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>Naturally cold, naturally clean — no chemical treatment required.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>Bottled in a sealed, food-grade facility under strict hygiene controls.</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>Delivered in reusable, sanitised 19-litre bottles.</span>
                        </li>
                    </ul>
                </div>
                <div class="order-1 md:order-2">
                    <div
                        class="relative aspect-[4/3] overflow-hidden rounded-3xl bg-gradient-to-br from-sky-100 via-cyan-50 to-white p-8 shadow-inner"
                    >
                        <div class="absolute inset-0 opacity-90">
                            <svg viewBox="0 0 400 300" class="h-full w-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M0 220 L80 140 L130 180 L200 90 L260 160 L320 110 L400 200 L400 300 L0 300 Z" fill="#0ea5e9" opacity="0.18" />
                                <path d="M0 240 L60 180 L120 210 L180 150 L240 200 L300 160 L360 220 L400 200 L400 300 L0 300 Z" fill="#0ea5e9" opacity="0.32" />
                                <path d="M0 270 L100 240 L200 260 L300 235 L400 260 L400 300 L0 300 Z" fill="#0284c7" opacity="0.45" />
                                <circle cx="320" cy="60" r="22" fill="#fde68a" opacity="0.9" />
                            </svg>
                        </div>
                        <div class="relative flex h-full flex-col justify-end">
                            <div class="rounded-xl bg-white/85 p-4 backdrop-blur">
                                <div class="flex items-center gap-2 text-sm font-semibold text-sky-700">
                                    <Mountain class="h-4 w-4" />
                                    Varzob Gorge, Tajikistan
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    Spring elevation: ~1 600 m. Average source temperature: 6–8 °C.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="bg-slate-50 py-20">
            <div class="mx-auto max-w-6xl px-6">
                <div class="max-w-2xl">
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">Why us</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        Clean water shouldn’t be complicated.
                    </h2>
                    <p class="mt-4 text-slate-600">
                        We do one thing: bring real mountain spring water to your home or office,
                        reliably, and at a fair price.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="f in features"
                        :key="f.title"
                        class="rounded-2xl border border-slate-200 bg-white p-6 transition hover:border-sky-200 hover:shadow-sm"
                    >
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-sky-100 text-sky-600">
                            <component :is="f.icon" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-5 text-base font-semibold text-slate-900">{{ f.title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ f.description }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="how" class="mx-auto max-w-6xl px-6 py-20">
            <div class="text-center">
                <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">How it works</span>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                    Three steps. No paperwork.
                </h2>
            </div>
            <ol class="mt-12 grid gap-6 md:grid-cols-3">
                <li
                    v-for="s in steps"
                    :key="s.n"
                    class="relative rounded-2xl border border-slate-200 bg-white p-7"
                >
                    <span class="text-sm font-semibold text-sky-500">{{ s.n }}</span>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ s.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ s.text }}</p>
                </li>
            </ol>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="bg-gradient-to-b from-white to-sky-50/60 py-20">
            <div class="mx-auto max-w-6xl px-6">
                <div class="max-w-2xl">
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">Pricing</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        Simple, transparent pricing.
                    </h2>
                    <p class="mt-4 text-slate-600">
                        Pay per bottle or set up a regular delivery — whatever suits your home or office best.
                    </p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="p in plans"
                        :key="p.name"
                        :class="[
                            'rounded-3xl border p-7 transition',
                            p.highlight
                                ? 'border-sky-500 bg-white shadow-xl ring-2 ring-sky-500/10'
                                : 'border-slate-200 bg-white hover:shadow-sm',
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-slate-900">{{ p.name }}</h3>
                            <span
                                v-if="p.highlight"
                                class="rounded-full bg-sky-500 px-2.5 py-0.5 text-xs font-semibold text-white"
                            >
                                Popular
                            </span>
                        </div>
                        <div class="mt-5 flex items-baseline gap-2">
                            <span class="text-4xl font-bold tracking-tight text-slate-900">{{ p.price }}</span>
                            <span class="text-sm font-medium text-slate-500">{{ p.unit }}</span>
                        </div>
                        <p class="text-sm text-slate-500">{{ p.per }}</p>

                        <ul class="mt-6 space-y-3">
                            <li
                                v-for="ft in p.features"
                                :key="ft"
                                class="flex items-start gap-3 text-sm text-slate-700"
                            >
                                <CheckCircle2 class="mt-0.5 h-4.5 w-4.5 flex-none text-sky-500" />
                                <span>{{ ft }}</span>
                            </li>
                        </ul>

                        <a
                            :href="phoneHref"
                            :class="[
                                'mt-7 inline-flex w-full items-center justify-center rounded-full px-4 py-2.5 text-sm font-semibold transition',
                                p.highlight
                                    ? 'bg-sky-500 text-white hover:bg-sky-600'
                                    : 'border border-slate-200 text-slate-800 hover:border-slate-300',
                            ]"
                        >
                            {{ p.cta }}
                        </a>
                    </div>
                </div>
                <p class="mt-6 text-center text-xs text-slate-500">
                    Prices are indicative. A refundable bottle deposit may apply on first order.
                </p>
            </div>
        </section>

        <!-- Delivery area / map -->
        <section id="coverage" class="mx-auto max-w-6xl px-6 py-20">
            <div class="grid gap-12 md:grid-cols-2 md:items-center">
                <div>
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">Coverage</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        We deliver across Dushanbe.
                    </h2>
                    <p class="mt-4 text-slate-600">
                        Our couriers cover the city centre and the surrounding districts year-round.
                        Address outside the zone? Give us a call — we'll often arrange it.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a
                            href="#coverage"
                            @click="onWhereIsMyWater"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-600"
                        >
                            <Truck class="h-4 w-4" />
                            Where is my water?
                        </a>
                    </div>
                </div>

                <div
                    class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        ref="mapEl"
                        class="block h-[420px] w-full"
                        aria-label="fann live courier coverage map"
                    />

                    <!-- live status pill -->
                    <div
                        class="pointer-events-none absolute top-3 left-3 inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-700 shadow ring-1 ring-slate-900/5 backdrop-blur"
                    >
                        <span
                            :class="[
                                'inline-block h-2 w-2 rounded-full',
                                courierCount > 0 ? 'bg-sky-500' : 'bg-slate-300',
                            ]"
                        />
                        <template v-if="!mapReady">Loading map…</template>
                        <template v-else-if="trackingMode === 'personal'">
                            Your water is on the way
                        </template>
                        <template v-else-if="courierCount > 0">
                            {{ courierCount }} courier{{ courierCount === 1 ? '' : 's' }} on the road
                        </template>
                        <template v-else>No couriers on the road right now</template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact / CTA -->
        <section id="contact" class="mx-auto max-w-6xl px-6 py-20">
            <div class="overflow-hidden rounded-3xl bg-slate-900 text-white">
                <div class="grid gap-10 p-10 md:grid-cols-2 md:p-14">
                    <div>
                        <FannLogo variant="stacked" class="mb-6 h-24 w-auto text-white" :show-tagline="true" />
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Order your first bottle today.
                        </h2>
                        <p class="mt-4 max-w-md text-slate-300">
                            Tell us how many 19L bottles you need and where to bring them.
                            We’ll handle the rest.
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a
                                :href="phoneHref"
                                class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-600"
                            >
                                <Phone class="h-4 w-4" />
                                Call us
                            </a>
                            <a
                                :href="whatsappHref"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 rounded-full border border-white/20 px-5 py-3 text-sm font-semibold text-white hover:border-white/40"
                            >
                                Chat on WhatsApp
                            </a>
                        </div>
                    </div>

                    <ul class="space-y-5 text-sm text-slate-200">
                        <li class="flex items-start gap-3">
                            <Phone class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Phone</div>
                                <a :href="phoneHref" class="text-base font-medium text-white hover:text-sky-300">
                                    {{ phone }}
                                </a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <Mail class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Email</div>
                                <a :href="`mailto:${email}`" class="text-base font-medium text-white hover:text-sky-300">
                                    {{ email }}
                                </a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <MapPin class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Service area</div>
                                <div class="text-base font-medium text-white">Dushanbe & surrounding districts</div>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <Clock class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">Hours</div>
                                <div class="text-base font-medium text-white">Daily, 11:00 – 03:00</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-slate-200 bg-[#f6f6f6]">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-8 text-sm text-slate-500 md:flex-row">
                <FannLogo variant="inline" class="h-7 w-auto text-slate-700" />
                <div>© {{ new Date().getFullYear() }} fann. All rights reserved.</div>
                <div class="flex items-center gap-5">
                    <a href="#about" class="hover:text-slate-700">About</a>
                    <a href="#pricing" class="hover:text-slate-700">Pricing</a>
                    <a href="#contact" class="hover:text-slate-700">Contact</a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* Leaflet creates marker DOM at runtime, so these rules must be unscoped. */
.fann-courier-marker {
    position: relative;
    width: 22px;
    height: 22px;
}
.fann-courier-marker .dot {
    position: absolute;
    top: 5px;
    left: 5px;
    width: 12px;
    height: 12px;
    border-radius: 9999px;
    background: #0ea5e9;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.45);
    z-index: 2;
}
.fann-courier-marker .pulse {
    position: absolute;
    top: 0;
    left: 0;
    width: 22px;
    height: 22px;
    border-radius: 9999px;
    background: rgba(14, 165, 233, 0.45);
    transform-origin: center;
    animation: fann-courier-pulse 1.6s ease-out infinite;
}
@keyframes fann-courier-pulse {
    0% {
        transform: scale(0.5);
        opacity: 1;
    }
    100% {
        transform: scale(1.7);
        opacity: 0;
    }
}
</style>
