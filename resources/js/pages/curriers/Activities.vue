<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { 
  Users2, 
  Map as MapIcon, 
  List, 
  Search,
  Activity,
  User as UserIcon,
  Navigation,
  Clock,
  Battery,
  Wifi,
  WifiOff,
  MapPin,
  Truck
} from 'lucide-vue-next';
import { MAP_STYLE, createHtmlMarker, loadMapLibre } from '@/lib/maps';
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<{
  curriers: any[];
}>();

const { t } = useI18n();

const breadcrumbs = computed(() => [
  { title: t('Curriers'), href: '#' },
  { title: t('Activities'), href: '/curriers/activities' },
]);

const viewMode = ref<'table' | 'map'>('table');
const searchQuery = ref('');

const filteredCurriers = computed(() => {
  if (!searchQuery.value) return props.curriers;
  return props.curriers.filter(c => 
    c.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// ── 2GIS MapGL setup ────────────────────────────────────────────────────────

const mapEl = ref<HTMLElement | null>(null);
let maplibregl: any = null;
let mapInstance: any = null;
let markers: { id: number, marker: any }[] = [];
const selectedCourier = ref<any | null>(null);

function isCurrierOnline(currier: any) {
    const lastActive = currier.last_active_at ? new Date(currier.last_active_at) :
                      (currier.last_location ? new Date(currier.last_location.created_at) : null);

    if (!lastActive) return false;

    const now = new Date();
    // Online if active in last 5 minutes (more strict than before)
    return (now.getTime() - lastActive.getTime()) < 5 * 60 * 1000;
}

function avatarMarkerHtml(currier: any, isOnline: boolean): string {
    return `
      <div class="moto-avatar" data-courier-id="${currier.id}">
        <div class="moto-avatar__ring ${isOnline ? 'is-online' : 'is-offline'}">
          <img src="${currier.avatar_url}" alt="" />
        </div>
        <span class="moto-avatar__dot ${isOnline ? 'is-online' : 'is-offline'}"></span>
      </div>`;
}

// MapGL HtmlMarkers have no native popup, so we delegate clicks off the map
// container and surface the courier detail in a Vue-rendered card overlay.
function onMapClick(e: MouseEvent) {
    const el = (e.target as HTMLElement)?.closest?.('[data-courier-id]') as HTMLElement | null;
    if (!el) return;
    const id = Number(el.dataset.courierId);
    const c = props.curriers.find((cc: any) => cc.id === id);
    if (c) selectedCourier.value = c;
}

async function initMap() {
  if (viewMode.value !== 'map') return;
  maplibregl = await loadMapLibre();
  if (!maplibregl || !mapEl.value) return;

  if (mapInstance) { mapInstance.remove(); mapInstance = null; }

  // Center on the average of known courier positions, else Dushanbe. MapLibre
  // takes [lng, lat] (the opposite of Leaflet).
  const locs = props.curriers
    .filter(c => c.last_location)
    .map(c => [c.last_location.lng, c.last_location.lat] as [number, number]);
  const center: [number, number] = locs.length > 0
    ? [locs.reduce((s, l) => s + l[0], 0) / locs.length, locs.reduce((s, l) => s + l[1], 0) / locs.length]
    : [68.7791, 38.5358];

  mapInstance = new maplibregl.Map({ container: mapEl.value, style: MAP_STYLE, center, zoom: 13 });
  mapEl.value.addEventListener('click', onMapClick);

  updateMarkers();
}

function updateMarkers() {
  if (!mapInstance) return;

  markers.forEach(m => m.marker.destroy());
  markers = [];

  props.curriers.forEach(currier => {
    if (!currier.last_location) return;
    const isOnline = isCurrierOnline(currier);
    const marker = createHtmlMarker(
      maplibregl,
      mapInstance,
      [currier.last_location.lng, currier.last_location.lat],
      avatarMarkerHtml(currier, isOnline),
      'bottom',
    );
    markers.push({ id: currier.id, marker });
  });
}

watch(viewMode, (newVal) => {
    if (newVal === 'map') {
        setTimeout(initMap, 100);
    }
});

onMounted(() => {
    if (viewMode.value === 'map') initMap();
});

onBeforeUnmount(() => {
    mapEl.value?.removeEventListener('click', onMapClick);
    markers.forEach(m => m.marker.destroy());
    if (mapInstance) { mapInstance.remove(); mapInstance = null; }
});

const focusOnMap = async (currier: any) => {
    if (!currier.last_location) return;

    viewMode.value = 'map';

    await nextTick();
    if (!mapInstance) {
        await initMap();
    }

    setTimeout(() => {
        if (!mapInstance) return;
        mapInstance.setCenter([currier.last_location.lng, currier.last_location.lat]);
        mapInstance.setZoom(16);
        selectedCourier.value = currier;
    }, 200);
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="t('Currier Activities')" />

    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">{{ t('Currier Activities') }}</h1>
          <p class="text-muted-foreground mt-1 text-sm font-medium">{{ t('Real-time fleet monitoring and activity tracking.') }}</p>
        </div>
        
        <div class="flex items-center bg-white dark:bg-sidebar p-1 rounded-xl shadow-sm border border-sidebar-border/60">
            <button 
                @click="viewMode = 'table'"
                :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-black transition-all', viewMode === 'table' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-muted']"
            >
                <List class="h-4 w-4" />
                {{ t('Table View') }}
            </button>
            <button 
                @click="viewMode = 'map'"
                :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-black transition-all', viewMode === 'map' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-muted']"
            >
                <MapIcon class="h-4 w-4" />
                {{ t('Map View') }}
            </button>
        </div>
      </div>

      <div v-show="viewMode === 'table'" class="space-y-4">
        <div class="flex items-center gap-2 w-full md:w-80 relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input 
                v-model="searchQuery"
                :placeholder="t('Search curriers...')"
                class="w-full h-10 pl-9 pr-4 rounded-xl border border-sidebar-border/60 bg-white dark:bg-sidebar text-sm font-bold focus:ring-2 focus:ring-primary/20 transition-all outline-none"
            />
        </div>

        <Card class="border-sidebar-border/60 shadow-md">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-[10px] font-black uppercase tracking-widest text-muted-foreground border-b bg-muted/30">
                            <tr>
                                <th class="px-6 py-4">{{ t('Currier') }}</th>
                                <th class="px-6 py-4">{{ t('Current Status') }}</th>
                                <th class="px-6 py-4">{{ t('Active Tasks') }}</th>
                                <th class="px-6 py-4">{{ t('Current Route') }}</th>
                                <th class="px-6 py-4">{{ t('Last Activity') }}</th>
                                <th class="px-6 py-4 text-right">{{ t('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/40">
                            <tr v-for="currier in filteredCurriers" :key="currier.id" class="hover:bg-muted/5 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <Avatar class="h-9 w-9 border-2 border-white dark:border-sidebar shadow-sm">
                                            <AvatarImage :src="currier.avatar_url" />
                                            <AvatarFallback>{{ currier.name[0] }}</AvatarFallback>
                                        </Avatar>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-black">{{ currier.name }}</span>
                                            <span class="text-[10px] text-muted-foreground font-bold">ID: #{{ currier.id }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div :class="['h-2 w-2 rounded-full shadow-[0_0_8px_rgba(0,0,0,0.1)]', isCurrierOnline(currier) ? 'bg-emerald-500 animate-pulse' : 'bg-gray-400']"></div>
                                        <span class="text-xs font-bold">{{ isCurrierOnline(currier) ? t('Online') : t('Offline') }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <Badge variant="outline" class="font-black text-[10px] uppercase bg-primary/5 text-primary border-primary/20">
                                        {{ currier.orders?.length || 0 }} {{ t('Orders') }}
                                    </Badge>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <div v-if="currier.orders?.length" class="flex items-center gap-1.5">
                                        <MapPin class="h-3 w-3 text-primary shrink-0" />
                                        <span class="text-xs font-bold truncate">{{ currier.orders[0].delivery_address }}</span>
                                    </div>
                                    <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30 italic">{{ t('Idle') }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="currier.last_active_at || currier.last_location" class="flex flex-col">
                                        <span class="text-xs font-bold text-foreground">
                                            {{ currier.last_active_at_human || currier.last_location?.created_at_human }}
                                        </span>
                                        <span v-if="currier.last_active_at" class="text-[9px] text-muted-foreground font-mono">
                                            {{ currier.last_active_at_formatted }}
                                        </span>
                                    </div>
                                     <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30 italic">{{ t('No Data') }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Button v-if="currier.last_location" @click="focusOnMap(currier)" size="icon" variant="ghost" class="h-8 w-8 text-primary hover:bg-primary/10 transition-colors" :title="t('Show on Map')">
                                        <MapPin class="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
      </div>

      <div v-show="viewMode === 'map'" class="h-[700px] w-full relative">
        <Card class="h-full border-sidebar-border/60 shadow-xl overflow-hidden">
            <div ref="mapEl" class="h-full w-full z-0"></div>

            <!-- Selected courier detail -->
            <div v-if="selectedCourier" class="absolute top-6 left-6 z-10 w-72 bg-white/95 dark:bg-sidebar/95 backdrop-blur-md p-4 rounded-2xl shadow-2xl border border-sidebar-border/60">
                <button class="absolute top-3 right-3 text-muted-foreground hover:text-foreground" @click="selectedCourier = null">✕</button>
                <div class="flex items-center gap-3 mb-3">
                    <Avatar class="h-10 w-10">
                        <AvatarImage :src="selectedCourier.avatar_url" />
                        <AvatarFallback>{{ selectedCourier.name[0] }}</AvatarFallback>
                    </Avatar>
                    <div>
                        <p class="text-sm font-black leading-tight">{{ selectedCourier.name }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest" :class="isCurrierOnline(selectedCourier) ? 'text-emerald-500' : 'text-muted-foreground'">
                            {{ isCurrierOnline(selectedCourier) ? t('Online') : t('Offline') }}
                        </p>
                    </div>
                </div>
                <div class="space-y-2 text-[11px] font-bold">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground uppercase tracking-wider">{{ t('Active tasks') }}</span>
                        <span class="bg-primary/10 text-primary px-1.5 py-0.5 rounded">{{ selectedCourier.orders?.length || 0 }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground uppercase tracking-wider">{{ t('Recorded') }}</span>
                        <span class="text-orange-500">{{ selectedCourier.last_location?.created_at_human || t('Unknown') }}</span>
                    </div>
                    <div v-if="selectedCourier.orders?.length" class="p-2 rounded-lg bg-muted/50 border border-sidebar-border/40 mt-1">
                        <p class="text-[10px] font-black uppercase tracking-wider mb-1">{{ t('Current route') }}</p>
                        <div class="flex items-start gap-1.5 min-w-0">
                            <Navigation class="h-3 w-3 text-primary mt-0.5 shrink-0" />
                            <p class="text-[10px] font-bold truncate">{{ selectedCourier.orders[0].delivery_address }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Legend -->
            <div class="absolute bottom-6 right-6 z-10 bg-white/90 dark:bg-sidebar/90 backdrop-blur-md p-4 rounded-2xl shadow-2xl border border-sidebar-border/60">
                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-3">{{ t('Live Status') }}</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        <span class="text-xs font-bold">{{ t('Active & Online') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-gray-400"></div>
                        <span class="text-xs font-bold">{{ t('Offline / Idle') }}</span>
                    </div>
                </div>
            </div>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<style>
/* MapGL builds HtmlMarker DOM at runtime, so these rules must be unscoped. */
.moto-avatar {
    position: relative;
    width: 40px;
    height: 40px;
    cursor: pointer;
}
.moto-avatar__ring {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    border: 2px solid;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25);
    transition: transform 0.15s ease;
}
.moto-avatar:hover .moto-avatar__ring {
    transform: scale(1.1);
}
.moto-avatar__ring.is-online {
    border-color: #10b981;
}
.moto-avatar__ring.is-offline {
    border-color: #9ca3af;
}
.moto-avatar__ring img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.moto-avatar__dot {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    border-radius: 9999px;
    border: 2px solid #fff;
}
.moto-avatar__dot.is-online {
    background: #10b981;
}
.moto-avatar__dot.is-offline {
    background: #9ca3af;
}
</style>
