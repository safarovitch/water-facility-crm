<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Fix { lat: number; lng: number }

const props = defineProps<{
  initial?: Fix | null;
  pollUrl?: string; // e.g. '/me/active-tracking'
  height?: string;
}>();

const mapEl = ref<HTMLDivElement | null>(null);
const ready = ref(false);
let leafletMap: any = null;
let marker: any = null;
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
    const existing = document.querySelector<HTMLScriptElement>(`script[src="${LEAFLET_JS}"]`);
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

function renderFix(L: any, fix: Fix) {
  if (!leafletMap) return;
  const latlng = [fix.lat, fix.lng];
  if (!marker) {
    marker = L.marker(latlng, {
      icon: L.divIcon({
        className: 'fann-courier-marker',
        html: '<span class="dot"></span><span class="pulse"></span>',
        iconSize: [22, 22],
        iconAnchor: [11, 11],
      }),
      keyboard: false,
      interactive: false,
    }).addTo(leafletMap);
  } else {
    marker.setLatLng(latlng);
  }
  leafletMap.setView(latlng, Math.max(leafletMap.getZoom?.() ?? 12, 14), { animate: true });
}

async function poll() {
  if (!props.pollUrl) return;
  try {
    const res = await fetch(props.pollUrl, {
      cache: 'no-store',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) return;
    const data = await res.json();
    if (data.tracking && data.courier) {
      const L = (window as any).L;
      if (L) renderFix(L, { lat: data.courier.lat, lng: data.courier.lng });
    }
  } catch {
    /* swallow — next tick will retry */
  }
}

onMounted(async () => {
  if (!mapEl.value) return;
  try {
    const L = await loadLeaflet();
    if (!L || !mapEl.value) return;
    const center: [number, number] = props.initial
      ? [props.initial.lat, props.initial.lng]
      : [38.5598, 68.787]; // Dushanbe fallback
    leafletMap = L.map(mapEl.value, {
      center,
      zoom: 13,
      scrollWheelZoom: false,
      attributionControl: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap',
      maxZoom: 19,
    }).addTo(leafletMap);
    if (props.initial) renderFix(L, props.initial);
    ready.value = true;
    if (props.pollUrl) {
      poll();
      pollTimer = window.setInterval(poll, 15000);
    }
  } catch {
    /* leaflet failed — section just stays blank */
  }
});

onBeforeUnmount(() => {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  if (leafletMap) { leafletMap.remove(); leafletMap = null; }
});

watch(() => props.initial, (fix) => {
  if (!fix) return;
  const L = (window as any).L;
  if (L) renderFix(L, fix);
});
</script>

<template>
  <div ref="mapEl" :style="{ height: height ?? '320px' }" class="block w-full rounded-xl overflow-hidden border border-slate-200" />
</template>

<style>
/* Leaflet builds the marker DOM at runtime, so these must be unscoped. */
.fann-courier-marker { position: relative; width: 22px; height: 22px; }
.fann-courier-marker .dot {
  position: absolute; top: 5px; left: 5px; width: 12px; height: 12px;
  border-radius: 9999px; background: #0ea5e9; border: 2px solid #ffffff;
  box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.45); z-index: 2;
}
.fann-courier-marker .pulse {
  position: absolute; top: 0; left: 0; width: 22px; height: 22px;
  border-radius: 9999px; background: rgba(14, 165, 233, 0.45);
  animation: fann-courier-pulse 1.6s ease-out infinite;
}
@keyframes fann-courier-pulse {
  0% { transform: scale(0.5); opacity: 1; }
  100% { transform: scale(1.7); opacity: 0; }
}
</style>
