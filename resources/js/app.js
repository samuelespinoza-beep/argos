import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import Principal from './layouts/Principal.vue';
import '../css/argos.css';

// Inertia: Laravel dice que pagina mostrar (Inertia::render('Ofertas', ...)) y aqui se
// busca el componente en resources/js/pages. Todas las paginas usan el layout Principal (menu).
createInertiaApp({
    resolve: (nombre) => import.meta.glob('./pages/*.vue', { eager: true })[`./pages/${nombre}.vue`],
    layout: () => Principal,
    title: (titulo) => (titulo ? `${titulo} – Argos` : 'Argos'),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#ff7a00' },
});
