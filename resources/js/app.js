import axios from 'axios';
import Swal from 'sweetalert2';
import { createApp } from 'vue';
import Notifications from '../views/layouts/components/Notifications.vue';
import UserMenu from '../views/layouts/components/UserMenu.vue';
import UserSelect from '../views/users/components/UserSelector.vue'; // Asegúrate de incluir .vue

window.Swal = Swal;

axios.defaults.withCredentials = true;

const app = createApp({
    data() {
        return {
            open: false,
            show: true
        };
    },
    methods: {
        updateSelectedUsers(users) {
            this.selectedUsers = users; // Guarda los usuarios seleccionados
        }
    }
});

app.config.globalProperties.$axios = axios;

app.component('user-menu', UserMenu);
app.component('notifications', Notifications);
app.component('user-select', UserSelect); // Registrar el componente UserSelect

app.mount('#app');
