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
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps<{
  curriers: any[];
}>();

const breadcrumbs = [
  { title: 'Curriers', href: '#' },
  { title: 'Activities', href: '/curriers/activities' },
];

const viewMode = ref<'table' | 'map'>('table');
const searchQuery = ref('');

const filteredCurriers = computed(() => {
  if (!searchQuery.value) return props.curriers;
  return props.curriers.filter(c => 
    c.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// ── Leaflet Setup ──────────────────────────────────────────────────────────

const mapEl = ref<HTMLElement | null>(null);
let mapInstance: any = null;
let markers: any[] = [];

function loadLeaflet(): Promise<void> {
  if ((window as any).L) return Promise.resolve();

  return new Promise((resolve, reject) => {
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id = 'leaflet-css';
      link.rel = 'stylesheet';
      link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(link);
    }
    const script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.onload = () => resolve();
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

function isCurrierOnline(currier: any) {
    const lastActive = currier.last_active_at ? new Date(currier.last_active_at) : 
                      (currier.last_location ? new Date(currier.last_location.created_at) : null);
    
    if (!lastActive) return false;
    
    const now = new Date();
    // Online if active in last 5 minutes (more strict than before)
    return (now.getTime() - lastActive.getTime()) < 5 * 60 * 1000;
}

async function initMap() {
  if (viewMode.value !== 'map') return;
  await loadLeaflet();
  const L = (window as any).L;

  if (mapInstance) {
      mapInstance.remove();
  }

  // Find center (average of all currier locations or default to center of activity)
  const locations = props.curriers.filter(c => c.last_location).map(c => [c.last_location.lat, c.last_location.lng]);
  const centerLat = locations.length > 0 ? locations.reduce((sum, l) => sum + l[0], 0) / locations.length : 38.5358;
  const centerLng = locations.length > 0 ? locations.reduce((sum, l) => sum + l[1], 0) / locations.length : 68.7791;

  mapInstance = L.map(mapEl.value).setView([centerLat, centerLng], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
  }).addTo(mapInstance);

  updateMarkers();
}

function updateMarkers() {
  if (!mapInstance) return;
  const L = (window as any).L;

  // Clear existing markers
  markers.forEach(m => m.remove());
  markers = [];

  props.curriers.forEach(currier => {
    if (!currier.last_location) return;

    const isOnline = isCurrierOnline(currier);
    
    // Create custom icon
    const icon = L.divIcon({
        className: 'custom-div-icon',
        html: `
          <div class="relative">
            <div class="h-10 w-10 rounded-full border-2 ${isOnline ? 'border-emerald-500 bg-emerald-50' : 'border-gray-400 bg-gray-50'} overflow-hidden shadow-lg transition-transform hover:scale-110">
              <img src="${currier.avatar_url}" class="h-full w-full object-cover" />
            </div>
            <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-white ${isOnline ? 'bg-emerald-500' : 'bg-gray-400'}"></div>
          </div>
        `,
        iconSize: [40, 40],
        iconAnchor: [20, 40],
        popupAnchor: [0, -40]
    });

    const marker = L.marker([currier.last_location.lat, currier.last_location.lng], { icon })
        .addTo(mapInstance);

    const activeOrders = currier.orders || [];

    marker.bindPopup(`
        <div class="w-64 p-1">
            <div class="flex items-center gap-3 mb-3">
                <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center overflow-hidden">
                    <img src="${currier.avatar_url}" class="h-full w-full object-cover" />
                </div>
                <div>
                    <p class="text-sm font-black">${currier.name}</p>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-widest leading-none">
                        ${isOnline ? 'Online' : 'Offline'}
                    </p>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex items-center justify-between text-[10px] font-bold">
                    <span class="text-muted-foreground uppercase tracking-wider">Active Tasks:</span>
                    <span class="bg-primary/10 text-primary px-1.5 py-0.5 rounded">${activeOrders.length}</span>
                </div>
                ${activeOrders.length > 0 ? `
                    <div class="p-2 rounded-lg bg-muted/50 border border-sidebar-border/40">
                        <p class="text-[10px] font-black uppercase tracking-wider mb-1">Current Route:</p>
                        <div class="flex items-start gap-1.5 min-w-0">
                            <i class="lucide-navigation h-3 w-3 text-primary mt-0.5"></i>
                            <p class="text-[9px] font-bold truncate">${activeOrders[0].delivery_address}</p>
                        </div>
                    </div>
                ` : ''}
            </div>
        </div>
    `, { className: 'custom-leaflet-popup' });

    markers.push(marker);
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
    if (mapInstance) mapInstance.remove();
});

</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Currier Activities" />

    <div class="space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Currier Activities</h1>
          <p class="text-muted-foreground mt-1 text-sm font-medium">Real-time fleet monitoring and activity tracking.</p>
        </div>
        
        <div class="flex items-center bg-white dark:bg-sidebar p-1 rounded-xl shadow-sm border border-sidebar-border/60">
            <button 
                @click="viewMode = 'table'"
                :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-black transition-all', viewMode === 'table' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-muted']"
            >
                <List class="h-4 w-4" />
                Table View
            </button>
            <button 
                @click="viewMode = 'map'"
                :class="['flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-black transition-all', viewMode === 'map' ? 'bg-primary text-primary-foreground shadow-md' : 'text-muted-foreground hover:bg-muted']"
            >
                <MapIcon class="h-4 w-4" />
                Map View
            </button>
        </div>
      </div>

      <div v-show="viewMode === 'table'" class="space-y-4">
        <div class="flex items-center gap-2 w-full md:w-80 relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
            <input 
                v-model="searchQuery"
                placeholder="Search curriers..."
                class="w-full h-10 pl-9 pr-4 rounded-xl border border-sidebar-border/60 bg-white dark:bg-sidebar text-sm font-bold focus:ring-2 focus:ring-primary/20 transition-all outline-none"
            />
        </div>

        <Card class="border-sidebar-border/60 shadow-md">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="text-[10px] font-black uppercase tracking-widest text-muted-foreground border-b bg-muted/30">
                            <tr>
                                <th class="px-6 py-4">Currier</th>
                                <th class="px-6 py-4">Current Status</th>
                                <th class="px-6 py-4">Active Tasks</th>
                                <th class="px-6 py-4">Current Route</th>
                                <th class="px-6 py-4">Last Activity</th>
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
                                        <span class="text-xs font-bold">{{ isCurrierOnline(currier) ? 'Online' : 'Offline' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <Badge variant="outline" class="font-black text-[10px] uppercase bg-primary/5 text-primary border-primary/20">
                                        {{ currier.orders?.length || 0 }} Orders
                                    </Badge>
                                </td>
                                <td class="px-6 py-4 max-w-xs">
                                    <div v-if="currier.orders?.length" class="flex items-center gap-1.5">
                                        <MapPin class="h-3 w-3 text-primary shrink-0" />
                                        <span class="text-xs font-bold truncate">{{ currier.orders[0].delivery_address }}</span>
                                    </div>
                                    <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30 italic">Idle</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="currier.last_active_at || currier.last_location" class="flex flex-col">
                                        <span class="text-xs font-bold text-foreground">
                                            {{ new Date(currier.last_active_at || currier.last_location.created_at).toLocaleTimeString() }}
                                        </span>
                                        <span v-if="currier.last_location" class="text-[10px] text-muted-foreground font-bold">
                                            {{ currier.last_location.lat.toFixed(4) }}, {{ currier.last_location.lng.toFixed(4) }}
                                        </span>
                                    </div>
                                    <span v-else class="text-[10px] font-black uppercase text-muted-foreground/30 italic">No Data</span>
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
            
            <!-- Map Legend -->
            <div class="absolute bottom-6 right-6 z-10 bg-white/90 dark:bg-sidebar/90 backdrop-blur-md p-4 rounded-2xl shadow-2xl border border-sidebar-border/60">
                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-3">Live Status</p>
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></div>
                        <span class="text-xs font-bold">Active & Online</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-gray-400"></div>
                        <span class="text-xs font-bold">Offline / Idle</span>
                    </div>
                </div>
            </div>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<style>
@reference "../../../css/app.css";

.custom-leaflet-popup .leaflet-popup-content-wrapper {
    @apply rounded-2xl shadow-xl border border-sidebar-border/40 bg-white/95 dark:bg-sidebar/95 backdrop-blur-sm p-0 overflow-hidden;
}
.custom-leaflet-popup .leaflet-popup-content {
    @apply m-0 p-3;
}
.custom-leaflet-popup .leaflet-popup-tip {
    @apply bg-white/95 dark:bg-sidebar/95 shadow-none;
}
.leaflet-container {
    @apply font-sans;
}
</style>
