import { ref, onMounted, onUnmounted } from 'vue';
import { useEcho } from './useEcho';
import { usePage } from '@inertiajs/vue3';

export interface OrderNotification {
    id: number;
    order_number: string;
    client_name: string;
    total_amount: number;
    status: string;
    created_at: string;
}

// Persist these refs across component mounts
const pendingCount = ref(0);
const incomingOrders = ref<OrderNotification[]>([]);
const initialized = ref(false);

// Audio synthesis for the ringtone
const playNotificationSound = () => {
    try {
        if (typeof window === 'undefined' || !window.AudioContext) return;
        
        const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
        
        const playTone = (freq: number, startTime: number, duration: number) => {
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, startTime);
            
            gain.gain.setValueAtTime(0, startTime);
            gain.gain.linearRampToValueAtTime(0.2, startTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.01, startTime + duration);
            
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            
            osc.start(startTime);
            osc.stop(startTime + duration);
        };

        const now = audioCtx.currentTime;
        playTone(523.25, now, 0.3); // C5
        playTone(659.25, now + 0.1, 0.3); // E5
        playTone(783.99, now + 0.2, 0.4); // G5
    } catch (error) {
        console.warn('[Notifications] Could not play sound:', error);
    }
};

export const useOrderNotifications = () => {
    const page = usePage();

    onMounted(() => {
        // Only initialize once to prevent duplicate listeners
        if (initialized.value) return;

        try {
            // Initial count from page props
            const initialCount = (page.props as any).pending_orders_count;
            if (initialCount !== undefined) {
                pendingCount.value = Number(initialCount);
            }

            const echo = useEcho();
            if (!echo) {
                console.warn('[Notifications] Echo not available, skipping real-time listeners.');
                return;
            }

            // Listen for new orders
            echo.private('orders')
                .listen('OrderCreated', (event: OrderNotification) => {
                    incomingOrders.value.push(event);
                    pendingCount.value++;
                    playNotificationSound();
                    
                    // Auto-remove toast after 10 seconds
                    setTimeout(() => {
                        incomingOrders.value = incomingOrders.value.filter(o => o.id !== event.id);
                    }, 10000);
                })
                .error((error: any) => {
                    console.error('[Echo] Subscription error:', error);
                });

            initialized.value = true;
        } catch (e) {
            console.error('[Notifications] setup failed:', e);
        }
    });

    return {
        pendingCount,
        incomingOrders,
    };
};
