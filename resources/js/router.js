import { createRouter, createWebHistory } from 'vue-router';
import Inicio from './pages/Inicio.vue';
import Comparar from './pages/Comparar.vue';
import Ofertas from './pages/Ofertas.vue';

// Cada URL apunta a una pagina. "props: true" pasa los parametros (:sitio, :dia) como props.
export default createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'inicio', component: Inicio },
        { path: '/ofertas/:sitio/:dia?', name: 'ofertas', component: Ofertas, props: true },
        { path: '/comparar/:competidor?/:dia?', name: 'comparar', component: Comparar, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
});
