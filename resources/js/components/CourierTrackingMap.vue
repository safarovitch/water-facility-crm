<script setup lang="ts">
import { DUSHANBE, MAP_STYLE, createHtmlMarker, lerp, loadMapLibre, motoMarkerHtml, type CourierMarker } from '@/lib/maps';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Fix { lat: number; lng: number }

const props = defineProps<{
  initial?: Fix | null;
  pollUrl?: string; // e.g. '/me/active-tracking'
  height?: string;
}>();

const mapEl = ref<HTMLDivElement | null>(null);
const ready = ref(false);
let maplibregl: any = null;
let map: any = null;
let marker: CourierMarker | null = null;
let pollTimer: number | null = null;
let rafId: number | null = null;

// Current (animated) and target courier positions, in [lng, lat] order.
let cur: [number, number] | null = null;
let tgt: [number, number] | null = null;

function setTarget(fix: Fix, recenter = true) {
  const coords: [number, number] = [fix.lng, fix.lat];
  tgt = coords;
  if (!marker) {
    cur = coords;
    marker = createHtmlMarker(maplibregl, map, coords, motoMarkerHtml(), 'center');
  }
  if (recenter && map) {
    map.setCenter(coords);
    if (map.getZoom() < 14) map.setZoom(14);
  }
}

function tick() {
  if (marker && cur && tgt) {
    cur = lerp(cur, tgt, 0.08);
    marker.setCoordinates(cur);
  }
  rafId = requestAnimationFrame(tick);
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
      setTarget({ lat: data.courier.lat, lng: data.courier.lng });
    }
  } catch {
    /* swallow — next tick will retry */
  }
}

onMounted(async () => {
  if (!mapEl.value) return;
  try {
    maplibregl = await loadMapLibre();
    if (!maplibregl || !mapEl.value) return;
    const center: [number, number] = props.initial
      ? [props.initial.lng, props.initial.lat]
      : DUSHANBE;
    map = new maplibregl.Map({ container: mapEl.value, style: MAP_STYLE, center, zoom: 13 });
    rafId = requestAnimationFrame(tick);
    if (props.initial) setTarget(props.initial);
    ready.value = true;
    if (props.pollUrl) {
      poll();
      pollTimer = window.setInterval(poll, 15000);
    }
  } catch {
    /* MapLibre failed — section just stays blank */
  }
});

onBeforeUnmount(() => {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
  if (marker) { marker.destroy(); marker = null; }
  if (map) { map.remove(); map = null; }
});

watch(() => props.initial, (fix) => {
  if (fix && map) setTarget(fix);
});
</script>

<template>
  <div ref="mapEl" :style="{ height: height ?? '320px' }" class="block w-full rounded-xl overflow-hidden border border-slate-200" />
</template>

<style>
/* MapGL builds the HtmlMarker DOM at runtime, so these must be unscoped. */
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
  animation: fann-moto-pulse 1.8s ease-out infinite;
}
@keyframes fann-moto-pulse {
  0% { transform: scale(0.5); opacity: 0.9; }
  100% { transform: scale(1.9); opacity: 0; }
}
</style>
