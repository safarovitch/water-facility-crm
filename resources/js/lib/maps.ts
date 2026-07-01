// Courier maps provider layer: MapLibre GL + OpenFreeMap.
//
// OpenFreeMap (https://openfreemap.org) serves free vector tiles with NO API
// key, NO signup and no usage limits (donation-funded, OSM data). MapLibre GL
// is the open-source renderer. We inject both from CDN (the same pattern the
// app already used for map libraries) and expose small provider-agnostic
// helpers so the map components don't depend on a specific SDK.

const MAPLIBRE_JS = 'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.js';
const MAPLIBRE_CSS = 'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.css';

// OpenFreeMap hosted style (no key). Alternatives: 'positron', 'bright'.
export const MAP_STYLE = 'https://tiles.openfreemap.org/styles/liberty';

// Dushanbe city center, in [lng, lat] order (MapLibre/GeoJSON convention).
export const DUSHANBE: [number, number] = [68.787, 38.5598];

// Bounding box covering greater Dushanbe, as [[swLng, swLat], [neLng, neLat]].
// The homepage fits this on load so the whole city is in frame (rather than a
// fixed zoom that crops the edges on tall/narrow viewports).
export const DUSHANBE_BOUNDS: [[number, number], [number, number]] = [
    [68.700, 38.485],
    [68.870, 38.635],
];

let loaderPromise: Promise<any> | null = null;

/** Load MapLibre GL (JS + CSS) and resolve with the global `maplibregl`. */
export function loadMapLibre(): Promise<any> {
    if (typeof window === 'undefined') return Promise.resolve(null);
    const w = window as any;
    if (w.maplibregl) return Promise.resolve(w.maplibregl);
    if (loaderPromise) return loaderPromise;

    if (!document.querySelector(`link[href="${MAPLIBRE_CSS}"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = MAPLIBRE_CSS;
        document.head.appendChild(link);
    }

    loaderPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(`script[src="${MAPLIBRE_JS}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve(w.maplibregl));
            existing.addEventListener('error', reject);
            return;
        }
        const s = document.createElement('script');
        s.src = MAPLIBRE_JS;
        s.async = true;
        s.onload = () => resolve(w.maplibregl);
        s.onerror = reject;
        document.head.appendChild(s);
    });
    return loaderPromise;
}

// Thin, provider-agnostic marker handle so callers stay decoupled from the SDK.
export interface CourierMarker {
    setCoordinates(coords: [number, number]): void;
    destroy(): void;
}

/** Create an HTML marker from an html string with a small setCoordinates/destroy API. */
export function createHtmlMarker(
    maplibregl: any,
    map: any,
    coords: [number, number],
    html: string,
    anchor: 'center' | 'bottom' = 'center',
): CourierMarker {
    const holder = document.createElement('div');
    holder.innerHTML = html.trim();
    const el = (holder.firstElementChild as HTMLElement) ?? holder;
    const marker = new maplibregl.Marker({ element: el, anchor }).setLngLat(coords).addTo(map);
    return {
        setCoordinates(c) {
            marker.setLngLat(c);
        },
        destroy() {
            marker.remove();
        },
    };
}

/** HTML for a motorbike courier marker (32×32). */
export function motoMarkerHtml(): string {
    return `<div class="fann-moto"><span class="fann-moto__pulse"></span><span class="fann-moto__icon">🏍️</span></div>`;
}

/** Linear interpolate between two [lng, lat] points. */
export function lerp(a: [number, number], b: [number, number], t: number): [number, number] {
    return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
}

// Preset loops whose waypoints sit on Dushanbe's main avenues. They are only a
// coarse skeleton — `snapRouteToRoads` turns each into a dense, road-following
// polyline so synthetic couriers drive along actual streets rather than cutting
// across blocks. Coordinates are [lng, lat]; each route ends where it starts so
// it loops cleanly.
export const SYNTHETIC_ROUTES: [number, number][][] = [
    [[68.780, 38.553], [68.787, 38.557], [68.792, 38.563], [68.788, 38.570], [68.781, 38.567], [68.776, 38.560], [68.780, 38.553]],
    [[68.785, 38.575], [68.793, 38.580], [68.800, 38.585], [68.794, 38.590], [68.786, 38.586], [68.785, 38.575]],
    [[68.775, 38.540], [68.783, 38.544], [68.790, 38.548], [68.785, 38.553], [68.778, 38.549], [68.775, 38.540]],
    [[68.795, 38.555], [68.803, 38.560], [68.808, 38.566], [68.801, 38.570], [68.795, 38.564], [68.795, 38.555]],
    [[68.765, 38.560], [68.772, 38.565], [68.778, 38.570], [68.772, 38.575], [68.764, 38.569], [68.765, 38.560]],
];

// Public OSRM demo server: free, keyless road routing over OSM data.
const OSRM_DRIVING = 'https://router.project-osrm.org/route/v1/driving/';

/**
 * Snap a list of waypoints to a real driving path along Dushanbe's roads,
 * returning a dense [lng, lat] polyline. Falls back to the raw waypoints if the
 * routing service is unavailable so the map still animates offline.
 */
export async function snapRouteToRoads(waypoints: [number, number][]): Promise<[number, number][]> {
    try {
        const coords = waypoints.map(([lng, lat]) => `${lng},${lat}`).join(';');
        const res = await fetch(`${OSRM_DRIVING}${coords}?overview=full&geometries=geojson`);
        if (!res.ok) return waypoints;
        const data = await res.json();
        const line = data?.routes?.[0]?.geometry?.coordinates;
        return Array.isArray(line) && line.length > 1 ? (line as [number, number][]) : waypoints;
    } catch {
        return waypoints;
    }
}

// A polyline with precomputed cumulative segment lengths, so a marker can be
// placed at a constant ground speed regardless of how the points are spaced.
export interface RoadPath {
    points: [number, number][];
    cum: number[];
    total: number;
}

/** Precompute cumulative distances along a polyline for distance-based animation. */
export function buildRoadPath(points: [number, number][]): RoadPath {
    const cum = [0];
    for (let i = 1; i < points.length; i++) {
        cum.push(cum[i - 1] + Math.hypot(points[i][0] - points[i - 1][0], points[i][1] - points[i - 1][1]));
    }
    return { points, cum, total: cum[cum.length - 1] || 1 };
}

/** Position [lng, lat] at distance `d` along a road path; wraps for seamless looping. */
export function pointAtDistance(path: RoadPath, d: number): [number, number] {
    const { points, cum, total } = path;
    if (points.length < 2) return points[0];
    const dist = ((d % total) + total) % total;
    let i = 1;
    while (i < cum.length && cum[i] < dist) i++;
    if (i >= cum.length) return points[points.length - 1];
    const segLen = cum[i] - cum[i - 1] || 1;
    return lerp(points[i - 1], points[i], (dist - cum[i - 1]) / segLen);
}

/**
 * Build a URL that opens the device's native maps app for a delivery location.
 * Prefers exact coordinates; falls back to a free-text address search. Returns
 * null when there's nothing to point at (e.g. self-pickup orders).
 */
export function externalMapsUrl(opts: {
    lat?: number | null;
    lng?: number | null;
    address?: string | null;
}): string | null {
    const { lat, lng, address } = opts;
    const trimmed = address?.trim();
    if (lat != null && lng != null) {
        const label = trimmed ? encodeURIComponent(trimmed) : '';
        return label ? `geo:${lat},${lng}?q=${lat},${lng}(${label})` : `geo:${lat},${lng}`;
    }
    if (trimmed && trimmed.toLowerCase() !== 'self pickup') {
        return `geo:0,0?q=${encodeURIComponent(trimmed)}`;
    }
    return null;
}
