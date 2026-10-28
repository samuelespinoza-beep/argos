import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import '../css/argos.css';

// Crea la app con el componente raiz, le conecta el router y la monta en <div id="app">.
createApp(App).use(router).mount('#app');
