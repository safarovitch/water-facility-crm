import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { useToast } from './composables/useToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// Global Inertia error listener for toasts
const { error } = useToast();
router.on('error', (event) => {
    const validationErrors = event.detail.errors;
    if (validationErrors && Object.keys(validationErrors).length > 0) {
        const errorCount = Object.keys(validationErrors).length;
        if (errorCount === 1) {
            error(Object.values(validationErrors)[0] as string);
        } else {
            error(`There are ${errorCount} validation errors. Please check the form.`);
        }
    }
});

// This will set light / dark mode on page load...
initializeTheme();
