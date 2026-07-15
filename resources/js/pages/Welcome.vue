<script setup lang="ts">
import FannLogo from '@/components/FannLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import WhatsAppIcon from '@/components/WhatsAppIcon.vue';
import { useLandingI18n } from '@/composables/useLandingI18n';
import type { RoadPath } from '@/lib/maps';
import { DUSHANBE_BOUNDS, MAP_STYLE, SYNTHETIC_ROUTES, buildRoadPath, createHtmlMarker, lerp, loadMapLibre, motoMarkerHtml, pointAtDistance, snapRouteToRoads } from '@/lib/maps';
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
    Send,
    ShieldCheck,
    Truck,
    User,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const { t, locale } = useLandingI18n();

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

const phone = '+992 178 60 50 05';
const phoneHref = 'tel:+992178605005';
const whatsappHref = 'https://wa.me/992178605005';
const telegramHref = 'https://t.me/fannwaterbot';
const email = 'water@fann.tj';

// --- Live courier map (2GIS MapGL) -----------------------------------------
const mapEl = ref<HTMLDivElement | null>(null);
const courierCount = ref(0);
const mapReady = ref(false);
const trackingMode = ref<'idle' | 'personal' | 'public' | 'noOrder' | 'pending'>('idle');

let maplibregl: any = null;
let map: any = null;
let pollTimer: number | null = null;
let rafId: number | null = null;

type CourierFix = { id?: string; lat: number; lng: number; updated_at?: string };

// Real-courier markers, keyed by anonymised id. Each eases from its current
// position toward the latest polled target every animation frame, so couriers
// appear to drive between the 15s location refreshes.
type LiveMarker = { marker: any; cur: [number, number]; tgt: [number, number] };
const liveMarkers = new Map<string, LiveMarker>();

// Synthetic motorbikes looping preset Dushanbe routes — shown to logged-out
// visitors only when no real courier is online, to imitate active delivery.
// Each synthetic courier rides a road-snapped path at a constant ground speed,
// `dist` advancing along the polyline and wrapping at its end to loop forever.
type SynthMarker = { marker: any; path: RoadPath; dist: number; speed: number };
let synthMarkers: SynthMarker[] = [];
// Road-snapped paths, fetched once from OSRM then reused across re-starts.
let syntheticPaths: RoadPath[] | null = null;
let synthStarting = false;

// 'none' = no active order; 'pending' = active order but no live courier
// location yet; 'live' = courier location available.
type TrackingResult =
    | { state: 'none' }
    | { state: 'pending' }
    | { state: 'live'; courier: CourierFix };

async function fetchPersonalTracking(): Promise<TrackingResult> {
    try {
        const res = await fetch('/me/active-tracking', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return { state: 'none' };
        const data = (await res.json()) as
            | { tracking: false; order?: { id: number; status: string } }
            | { tracking: true; courier: CourierFix };
        if (data.tracking) return { state: 'live', courier: data.courier };
        return { state: data.order ? 'pending' : 'none' };
    } catch {
        return { state: 'none' };
    }
}

function makeMoto(coords: [number, number]) {
    return createHtmlMarker(maplibregl, map, coords, motoMarkerHtml(), 'center');
}

// Reconcile live markers against the latest fixes: add new couriers, retarget
// existing ones (the rAF loop eases them over), drop those gone.
function setLiveMarkers(fixes: CourierFix[]) {
    const seen = new Set<string>();
    fixes.forEach((fix, i) => {
        const id = fix.id ?? `c${i}`;
        seen.add(id);
        const coords: [number, number] = [fix.lng, fix.lat];
        const existing = liveMarkers.get(id);
        if (existing) {
            existing.tgt = coords;
        } else {
            liveMarkers.set(id, { marker: makeMoto(coords), cur: coords, tgt: coords });
        }
    });
    for (const [id, lm] of liveMarkers) {
        if (!seen.has(id)) {
            lm.marker.destroy();
            liveMarkers.delete(id);
        }
    }
}

function clearLiveMarkers() {
    for (const lm of liveMarkers.values()) lm.marker.destroy();
    liveMarkers.clear();
}

// Snap the preset routes to real roads once, then cache. Falls back to raw
// waypoints inside snapRouteToRoads if the routing service is unreachable.
async function getSyntheticPaths(): Promise<RoadPath[]> {
    if (syntheticPaths) return syntheticPaths;
    const snapped = await Promise.all(SYNTHETIC_ROUTES.map(snapRouteToRoads));
    syntheticPaths = snapped.map(buildRoadPath);
    return syntheticPaths;
}

async function startSynthetic() {
    if (synthMarkers.length || synthStarting) return;
    synthStarting = true;
    const paths = await getSyntheticPaths();
    synthStarting = false;
    // The map may have been torn down, or a real courier may have taken over,
    // while we awaited the route snapping.
    if (!map || trackingMode.value !== 'public' || synthMarkers.length) return;
    // Show a random number of mock couriers (at least 2) so the map feels
    // different on each visit rather than always a fixed fleet.
    const count = 2 + Math.floor(Math.random() * (paths.length - 1));
    synthMarkers = paths.slice(0, count).map((path, i) => ({
        marker: makeMoto(path.points[0]),
        path,
        // Stagger starts so couriers spread along their loops, not bunched up.
        dist: (path.total / paths.length) * i,
        // Constant ground speed tuned to feel like a real motorbike weaving
        // through city traffic. Vary the loop period deterministically so they
        // don't move in lockstep. Larger divisor = slower.
        speed: path.total / (300000 + (i % 5) * 4000),
    }));
    courierCount.value = synthMarkers.length;
}

function stopSynthetic() {
    for (const s of synthMarkers) s.marker.destroy();
    synthMarkers = [];
}

function tick() {
    for (const lm of liveMarkers.values()) {
        lm.cur = lerp(lm.cur, lm.tgt, 0.06);
        lm.marker.setCoordinates(lm.cur);
    }
    for (const s of synthMarkers) {
        s.dist = (s.dist + s.speed) % s.path.total;
        s.marker.setCoordinates(pointAtDistance(s.path, s.dist));
    }
    rafId = requestAnimationFrame(tick);
}

async function refreshMap() {
    if (!map) return;

    // A logged-in user with a real delivery in progress sees their own courier
    // (and nothing else). Everyone else — logged-out visitors, or signed-in
    // users without an active order — gets the lively mock motorbikes so the
    // homepage map is never empty.
    if (isLoggedIn.value) {
        const tracking = await fetchPersonalTracking();
        if (tracking.state === 'live') {
            const own = tracking.courier;
            stopSynthetic();
            trackingMode.value = 'personal';
            courierCount.value = 1;
            setLiveMarkers([{ ...own, id: 'self' }]);
            map.setCenter([own.lng, own.lat]);
            if (map.getZoom() < 13) map.setZoom(13);
            return;
        }
        // Active order but no live courier location yet, or no active order at
        // all — either way no markers, just the matching status note.
        stopSynthetic();
        clearLiveMarkers();
        trackingMode.value = tracking.state === 'pending' ? 'pending' : 'noOrder';
        courierCount.value = 0;
        return;
    }

    // Logged-out visitors get the lively mock motorbikes looping Dushanbe roads
    // so the homepage map is never empty.
    clearLiveMarkers();
    trackingMode.value = 'public';
    await startSynthetic();
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
    // Reflect the detected/stored locale on <html lang> so screen readers and
    // CSS :lang() selectors get it right on initial paint.
    if (typeof document !== 'undefined') {
        document.documentElement.lang = locale.value === 'tg' ? 'tg' : locale.value;
    }

    // Inertia's SPA navigation doesn't always honor URL fragments (e.g. after
    // /auth/start → /login → redirect()->intended('/#coverage')). Force it.
    scrollToTargetIfPresent();

    if (!mapEl.value) return;
    try {
        maplibregl = await loadMapLibre();
        if (!maplibregl || !mapEl.value) return;
        map = new maplibregl.Map({
            container: mapEl.value,
            style: MAP_STYLE,
            // Fit the whole city in frame on load instead of a fixed zoom that
            // crops the edges on tall/narrow viewports.
            bounds: DUSHANBE_BOUNDS,
            fitBoundsOptions: { padding: 24 },
        });
        mapReady.value = true;
        rafId = requestAnimationFrame(tick);
        await refreshMap();
        pollTimer = window.setInterval(refreshMap, 15000);
    } catch {
        /* MapLibre failed to load — the section just shows an empty map column */
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
    if (rafId) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
    stopSynthetic();
    clearLiveMarkers();
    if (map) {
        map.remove();
        map = null;
    }
});

const features = computed(() => [
    { icon: Mountain, title: t('features.sourceTitle'), description: t('features.sourceDesc') },
    { icon: ShieldCheck, title: t('features.filtrationTitle'), description: t('features.filtrationDesc') },
    { icon: Truck, title: t('features.deliveryTitle'), description: t('features.deliveryDesc') },
    { icon: Leaf, title: t('features.mineralsTitle'), description: t('features.mineralsDesc') },
]);

const steps = computed(() => [
    { n: '01', title: t('how.step1Title'), text: t('how.step1Text') },
    { n: '02', title: t('how.step2Title'), text: t('how.step2Text') },
    { n: '03', title: t('how.step3Title'), text: t('how.step3Text') },
]);

const plans = computed(() => [
    {
        name: t('pricing.singleName'),
        price: '20',
        unit: t('pricing.unitTjs'),
        per: t('pricing.singlePer'),
        features: [t('pricing.singleF1'), t('pricing.singleF2'), t('pricing.singleF3')],
        cta: t('pricing.singleCta'),
        highlight: false,
    },
    {
        name: t('pricing.homeName'),
        price: '75',
        unit: t('pricing.unitTjs'),
        per: t('pricing.homePer'),
        features: [t('pricing.homeF1'), t('pricing.homeF2'), t('pricing.homeF3')],
        cta: t('pricing.homeCta'),
        highlight: true,
    },
    {
        name: t('pricing.officeName'),
        price: t('pricing.custom'),
        unit: '',
        per: t('pricing.officePer'),
        features: [t('pricing.officeF1'), t('pricing.officeF2'), t('pricing.officeF3')],
        cta: t('pricing.officeCta'),
        highlight: false,
    },
]);

const couriersLabel = (count: number) => {
    const key = count === 1 ? 'coverage.couriersSingular' : 'coverage.couriersPlural';
    return t(key).replace('{count}', String(count));
};
</script>

<template>
    <Head title="Fann — Mountain Spring Water · 19L Delivery in Tajikistan">
        <meta
            name="description"
            content="Fann · Pure 19L mountain spring water from the Varzob valley, delivered to homes and offices across Tajikistan year-round."
        />
        <link rel="preconnect" href="https://fonts.bunny.net" />
        <link
            rel="stylesheet"
            href="https://fonts.bunny.net/css?family=fraunces:300,400,500&display=swap"
        />
    </Head>

    <div class="min-h-screen bg-[#f6f6f6] text-slate-900 antialiased">
        <!-- Top nav -->
        <header class="sticky top-0 z-[1000] border-b border-sky-100/80 bg-[#f6f6f6]/80 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <a href="#top" class="flex items-center text-slate-900">
                    <FannLogo variant="inline" class="h-7 w-auto" />
                </a>

                <nav class="hidden items-center gap-7 text-slate-600 md:flex">
                    <a href="#about" class="hover:text-sky-600">{{ t('nav.about') }}</a>
                    <a href="#pricing" class="hover:text-sky-600">{{ t('nav.pricing') }}</a>
                    <a href="#coverage" class="hover:text-sky-600">{{ t('nav.delivery') }}</a>
                    <a href="#contact" class="hover:text-sky-600">{{ t('nav.contact') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="h-10 items-center rounded-full border border-slate-200 px-4 text-sm font-medium hover:border-slate-300 inline-flex"
                    >
                        {{ t('nav.dashboard') }}
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-10 items-center rounded-full border border-slate-200 px-4 text-sm font-medium hover:border-slate-300"
                        >
                            {{ t('nav.login') }}
                        </Link>
                        <Link
                            :href="register()"
                            class="hidden rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 sm:inline-block"
                        >
                            {{ t('nav.signup') }}
                        </Link>
                    </template>
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
                        {{ t('hero.badge') }}
                    </span>
                    <h1 class="mt-6 text-4xl font-semibold tracking-tight text-sky-600 sm:text-5xl md:text-6xl">
                        {{ t('hero.headline1') }}<br />
                        <span class="text-sky-600">{{ t('hero.headline2') }}</span>
                    </h1>
                    <p class="mt-6 max-w-xl text-lg text-slate-600">
                        {{ t('hero.subtitle') }}
                    </p>

                    <p class="mt-8 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        {{ t('hero.orderVia') }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a
                            :href="phoneHref"
                            class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-sky-600"
                        >
                            <Phone class="h-5 w-5" />
                            {{ t('hero.callToOrder') }}
                        </a>
                        <a
                            :href="telegramHref"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-full bg-[#229ED9] px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-[#1c8ec2]"
                        >
                            <Send class="h-5 w-5" />
                            Telegram
                        </a>
                        <a
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-6 py-3 text-base font-semibold text-white shadow-md transition hover:bg-[#1ebe5b]"
                        >
                            <WhatsAppIcon class="h-5 w-5" />
                            {{ t('hero.whatsapp') }}
                        </a>
                    </div>

                    <dl class="mt-10 grid grid-cols-3 gap-6 border-t border-slate-200 pt-6 text-sm">
                        <div>
                            <dt class="text-slate-500">{{ t('hero.statBottleLabel') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">{{ t('hero.statBottleValue') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">{{ t('hero.statDeliveryLabel') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">{{ t('hero.statDeliveryValue') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">{{ t('hero.statSourceLabel') }}</dt>
                            <dd class="mt-1 text-lg font-semibold text-slate-900">{{ t('hero.statSourceValue') }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Visual: 19L bottle hero photo -->
                <div class="relative mx-auto flex h-[420px] w-full max-w-md items-center justify-center md:h-[560px]">
                    <!-- bottle, locked to the photo's natural aspect ratio -->
                    <div class="relative flex h-full items-center justify-center">
                        <img
                            src="/images/bottles.webp"
                            alt="Fann 19L mountain spring water bottles"
                            class="block h-full w-auto max-w-full object-contain select-none"
                            loading="eager"
                            decoding="async"
                        />

                        <!-- on-bottle wordmark + slogan, lower-left of the upright bottle -->
                        <div
                            class="pointer-events-none absolute top-[62%] left-[22%] translate-x-[calc(-50%_+_9px)] translate-y-[calc(calc(1_/_2_*_100%)_*_-2_+_10px)] text-center"
                        >
                            <FannLogo
                                variant="wordmark"
                                :show-tagline="false"
                                class="mx-auto h-7 w-auto text-sky-950"
                            />
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 220 22"
                                class="mx-auto block h-[17px] w-auto text-sky-900/80"
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
                                    letter-spacing="2"
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
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">{{ t('about.eyebrow') }}</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        {{ t('about.title') }}
                    </h2>
                    <p class="mt-5 text-slate-600">
                        {{ t('about.body') }}
                    </p>
                    <ul class="mt-6 space-y-3 text-slate-700">
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>{{ t('about.bullet1') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>{{ t('about.bullet2') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <CheckCircle2 class="mt-0.5 h-5 w-5 flex-none text-sky-500" />
                            <span>{{ t('about.bullet3') }}</span>
                        </li>
                    </ul>
                </div>
                <div class="order-1 md:order-2">
                    <div
                        class="relative aspect-[4/3] overflow-hidden rounded-3xl bg-gradient-to-br from-sky-100 via-cyan-50 to-white p-8 shadow-inner"
                    >
                        <div class="absolute inset-0 opacity-90">
                            <!-- <svg viewBox="0 0 400 300" class="h-full w-full" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                <path d="M0 220 L80 140 L130 180 L200 90 L260 160 L320 110 L400 200 L400 300 L0 300 Z" fill="#0ea5e9" opacity="0.18" />
                                <path d="M0 240 L60 180 L120 210 L180 150 L240 200 L300 160 L360 220 L400 200 L400 300 L0 300 Z" fill="#0ea5e9" opacity="0.32" />
                                <path d="M0 270 L100 240 L200 260 L300 235 L400 260 L400 300 L0 300 Z" fill="#0284c7" opacity="0.45" />
                                <circle cx="320" cy="60" r="22" fill="#fde68a" opacity="0.9" />
                            </svg> -->
                            <img src="/varzob-waterfall-768x1024.jpg" alt="Varzob valley, Tajikistan" class="h-full w-full object-cover object-center" />
                        </div>
                        <div class="relative flex h-full flex-col justify-end">
                            <div class="rounded-xl bg-white/85 p-4 backdrop-blur">
                                <div class="flex items-center gap-2 text-sm font-semibold text-sky-700">
                                    <Mountain class="h-4 w-4" />
                                    {{ t('about.locationLabel') }}
                                </div>
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ t('about.locationFacts') }}
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
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">{{ t('features.eyebrow') }}</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        {{ t('features.title') }}
                    </h2>
                    <p class="mt-4 text-slate-600">
                        {{ t('features.body') }}
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
                <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">{{ t('how.eyebrow') }}</span>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                    {{ t('how.title') }}
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
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">{{ t('pricing.eyebrow') }}</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        {{ t('pricing.title') }}
                    </h2>
                    <p class="mt-4 text-slate-600">
                        {{ t('pricing.body') }}
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
                                {{ t('pricing.popular') }}
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
                    {{ t('pricing.disclaimer') }}
                </p>
            </div>
        </section>

        <!-- Delivery area / map -->
        <section id="coverage" class="mx-auto max-w-6xl px-6 py-20">
            <div class="grid gap-12 md:grid-cols-2 md:items-center">
                <div>
                    <span class="text-sm font-semibold tracking-wide text-sky-600 uppercase">{{ t('coverage.eyebrow') }}</span>
                    <h2 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
                        {{ t('coverage.title') }}
                    </h2>
                    <p class="mt-4 text-slate-600">
                        {{ t('coverage.body') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a
                            href="#coverage"
                            @click="onWhereIsMyWater"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-600"
                        >
                            <Truck class="h-4 w-4" />
                            {{ t('coverage.whereIsMyWater') }}
                        </a>
                    </div>
                </div>

                <div
                    class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        ref="mapEl"
                        class="block h-[420px] w-full"
                        :aria-label="t('coverage.mapAria')"
                    />

                    <!-- live status pill -->
                    <div
                        class="pointer-events-none absolute top-3 left-3 inline-flex items-center gap-2 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-slate-700 shadow ring-1 ring-slate-900/5 backdrop-blur"
                    >
                        <span
                            :class="[
                                'inline-block h-2 w-2 rounded-full',
                                courierCount > 0 ? 'bg-sky-500' : trackingMode === 'pending' ? 'bg-amber-400' : 'bg-slate-300',
                            ]"
                        />
                        <template v-if="!mapReady">{{ t('coverage.loading') }}</template>
                        <template v-else-if="trackingMode === 'personal'">
                            {{ t('coverage.personal') }}
                        </template>
                        <template v-else-if="trackingMode === 'pending'">
                            {{ t('coverage.awaitingCourier') }}
                        </template>
                        <template v-else-if="trackingMode === 'noOrder'">
                            {{ t('coverage.noActiveOrder') }}
                        </template>
                        <template v-else-if="courierCount > 0">
                            {{ couriersLabel(courierCount) }}
                        </template>
                        <template v-else>{{ t('coverage.none') }}</template>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact / CTA -->
        <section id="contact" class="mx-auto max-w-6xl px-6 py-20">
            <div class="overflow-hidden rounded-3xl bg-slate-900 text-white">
                <div class="grid gap-10 p-10 md:grid-cols-2 md:p-14">
                    <div>
                        <FannLogo light class="mb-6 h-16 w-auto" />
                        <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                            {{ t('contact.title') }}
                        </h2>
                        <p class="mt-4 max-w-md text-slate-300">
                            {{ t('contact.body') }}
                        </p>

                        <div class="mt-8 flex flex-wrap items-center gap-3">
                            <a
                                :href="phoneHref"
                                class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-600"
                            >
                                <Phone class="h-4 w-4" />
                                {{ t('contact.call') }}
                            </a>
                            <a
                                :href="telegramHref"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 rounded-full bg-[#229ED9] px-5 py-3 text-sm font-semibold text-white hover:bg-[#1c8ec2]"
                            >
                                <Send class="h-4 w-4" />
                                Telegram
                            </a>
                            <a
                                :href="whatsappHref"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-2 rounded-full bg-[#25D366] px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:bg-[#1ebe5b]"
                            >
                                <WhatsAppIcon class="h-4 w-4" />
                                {{ t('contact.whatsapp') }}
                            </a>
                        </div>
                    </div>

                    <ul class="space-y-5 text-sm text-slate-200">
                        <li class="flex items-start gap-3">
                            <Phone class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">{{ t('contact.phoneLabel') }}</div>
                                <a :href="phoneHref" class="text-base font-medium text-white hover:text-sky-300">
                                    {{ phone }}
                                </a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <Mail class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">{{ t('contact.emailLabel') }}</div>
                                <a :href="`mailto:${email}`" class="text-base font-medium text-white hover:text-sky-300">
                                    {{ email }}
                                </a>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <MapPin class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">{{ t('contact.serviceLabel') }}</div>
                                <div class="text-base font-medium text-white">{{ t('contact.serviceValue') }}</div>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <Clock class="mt-0.5 h-5 w-5 flex-none text-sky-400" />
                            <div>
                                <div class="text-xs uppercase tracking-wide text-slate-400">{{ t('contact.hoursLabel') }}</div>
                                <div class="text-base font-medium text-white">{{ t('contact.hoursValue') }}</div>
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
                <div>© {{ new Date().getFullYear() }} fann. {{ t('footer.rights') }}</div>
                <div class="flex items-center gap-5">
                    <a href="#about" class="hover:text-slate-700">{{ t('footer.about') }}</a>
                    <a href="#pricing" class="hover:text-slate-700">{{ t('footer.pricing') }}</a>
                    <a href="#contact" class="hover:text-slate-700">{{ t('footer.contact') }}</a>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* MapGL builds the HtmlMarker DOM at runtime, so these rules must be unscoped. */
.fann-moto {
    position: relative;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
}
.fann-moto__icon {
    position: relative;
    z-index: 2;
    font-size: 20px;
    line-height: 1;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.35));
}
.fann-moto__pulse {
    position: absolute;
    inset: 4px;
    border-radius: 9999px;
    background: rgba(38, 90, 128, 0.4);
    transform-origin: center;
    animation: fann-moto-pulse 1.8s ease-out infinite;
}
@keyframes fann-moto-pulse {
    0% {
        transform: scale(0.5);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.9);
        opacity: 0;
    }
}
</style>
