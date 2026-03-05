<script setup lang="ts">
/**
 * AddressMapPicker.vue
 *
 * Leaflet map loaded dynamically from CDN (no npm needed).
 * Emits updates whenever the marker is dragged or the search result is selected.
 */
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Label from '@/components/ui/label/Label.vue';
import Input from '@/components/ui/input/Input.vue';
export interface AddressData {
  address_line: string;
  city: string;
  region: string;
  lat: number | null;
  lng: number | null;
}

const props = withDefaults(defineProps<{
  modelValue: AddressData;
  height?: string;
}>(), {
  height: '280px',
});

const emit = defineEmits<{
  (e: 'update:modelValue', value: AddressData): void;
}>();

const mapEl = ref<HTMLElement | null>(null);
const searchQuery = ref(props.modelValue.address_line ?? '');
const searchResults = ref<any[]>([]);
const isSearching = ref(false);

let mapInstance: any = null;
let markerInstance: any = null;

// ── Leaflet CDN loader ──────────────────────────────────────────────────────

function loadLeaflet(): Promise<void> {
  if ((window as any).L) return Promise.resolve();

  return new Promise((resolve, reject) => {
    // CSS
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id = 'leaflet-css';
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);
    }
    // JS
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.onload = () => resolve();
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

// ── Mount map ───────────────────────────────────────────────────────────────

async function initMap() {
  await loadLeaflet();
  const L = (window as any).L;

  const defaultLat = props.modelValue.lat ?? 41.2995;  // Tashkent default
  const defaultLng = props.modelValue.lng ?? 69.2401;
  const defaultZoom = props.modelValue.lat ? 15 : 11;

  mapInstance = L.map(mapEl.value).setView([defaultLat, defaultLng], defaultZoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(mapInstance);

  // Custom red marker icon (fixes default broken icon path in bundled apps)
  const icon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
  });

  markerInstance = L.marker([defaultLat, defaultLng], { draggable: true, icon }).addTo(mapInstance);
  markerInstance.bindPopup('Drag to set location').openPopup();

  markerInstance.on('dragend', () => {
    const { lat, lng } = markerInstance.getLatLng();
    emit('update:modelValue', { ...props.modelValue, lat, lng });
    reverseGeocode(lat, lng);
  });

  // Click on map moves marker
  mapInstance.on('click', (e: any) => {
    const { lat, lng } = e.latlng;
    markerInstance.setLatLng([lat, lng]);
    emit('update:modelValue', { ...props.modelValue, lat, lng });
    reverseGeocode(lat, lng);
  });
}

// ── Reverse geocode ─────────────────────────────────────────────────────────

async function reverseGeocode(lat: number, lng: number) {
  try {
    const res = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`,
      { headers: { 'Accept-Language': 'en' } }
    );
    const data = await res.json();
    const addr = data.address ?? {};
    const address_line = data.display_name?.split(',').slice(0, 3).join(', ') ?? '';
    const city = addr.city ?? addr.town ?? addr.village ?? addr.county ?? '';
    const region = addr.state ?? addr.region ?? '';
    emit('update:modelValue', { ...props.modelValue, lat, lng, address_line, city, region });
    searchQuery.value = address_line;
  } catch { /* silent */ }
}

// ── Forward search ──────────────────────────────────────────────────────────

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function onSearchInput() {
  emit('update:modelValue', { ...props.modelValue, address_line: searchQuery.value });

  if (searchTimeout) clearTimeout(searchTimeout);
  if (searchQuery.value.length < 3) { searchResults.value = []; return; }

  searchTimeout = setTimeout(async () => {
    isSearching.value = true;
    try {
      const res = await fetch(
        `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery.value)}&limit=5`,
        { headers: { 'Accept-Language': 'en' } }
      );
      searchResults.value = await res.json();
    } finally {
      isSearching.value = false;
    }
  }, 400);
}

function selectResult(result: any) {
  const lat = parseFloat(result.lat);
  const lng = parseFloat(result.lon);
  const address_line = result.display_name?.split(',').slice(0, 3).join(', ') ?? result.display_name;
  searchQuery.value = address_line;
  searchResults.value = [];
  markerInstance?.setLatLng([lat, lng]);
  mapInstance?.setView([lat, lng], 15);
  emit('update:modelValue', {
    ...props.modelValue,
    lat, lng, address_line,
    city: '',
    region: '',
  });
}

// ── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(initMap);

onBeforeUnmount(() => {
  mapInstance?.remove();
});

// Watch external lat/lng change (e.g. when editing an existing address)
watch(() => [props.modelValue.lat, props.modelValue.lng], ([lat, lng]) => {
  if (lat && lng && markerInstance && mapInstance) {
    markerInstance.setLatLng([lat, lng]);
    mapInstance.setView([lat, lng], 15);
  }
});

// Watch external address_line changes
watch(() => props.modelValue.address_line, (newVal) => {
  if (newVal !== searchQuery.value) {
    searchQuery.value = newVal ?? '';
  }
});
</script>

<template>
  <div class="space-y-4">
    <!-- Search box -->
    <div class="relative grid gap-2">
      <Label>Street Address</Label>
      <Input v-model="searchQuery" @input="onSearchInput" placeholder="Enter street address..." class="w-full pr-8" />
      <span v-if="isSearching" class="absolute right-2 top-9 text-gray-400 text-xs">…</span>

      <!-- Dropdown results -->
      <ul v-if="searchResults.length" class="absolute z-[9999] top-full left-0 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg max-h-52 overflow-y-auto">
        <li v-for="r in searchResults" :key="r.place_id" @click="selectResult(r)" class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 last:border-0">
          {{ r.display_name }}
        </li>
      </ul>
    </div>

    <!-- Map -->
    <div ref="mapEl" :style="{ height: props.height }" class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 z-0"></div>

    <!-- Coordinates display -->
    <div v-if="modelValue.lat" class="text-xs text-gray-400 text-right">
      📍 {{ modelValue.lat?.toFixed(6) }}, {{ modelValue.lng?.toFixed(6) }}
    </div>
  </div>
</template>
