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

const pendingCount = ref(0);
const incomingOrders = ref<OrderNotification[]>([]);

// Audio synthesis for the ringtone
const playNotificationSound = () => {
    try {
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
        console.error('Could not play notification sound', error);
    }
};

export const useOrderNotifications = () => {
    const page = usePage();
    const echo = useEcho();

    // Initialize count from inertia props
    onMounted(() => {
        if (page.props.pending_orders_count !== undefined) {
          pendingCount.value = Number(page.props.pending_orders_count);
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
            });
    });

    onUnmounted(() => {
        echo.leave('orders');
    });

    return {
        pendingCount,
        incomingOrders,
    };
};
