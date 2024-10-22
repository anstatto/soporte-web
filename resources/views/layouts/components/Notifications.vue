<template>
    <div class="relative ml-3">
        <button @click="toggleNotifications"
            class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <span class="sr-only">Ver notificaciones</span>
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V4a2 2 0 10-4 0v1.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0a3 3 0 11-6 0h6z" />
            </svg>
            <span v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-block w-4 h-4 transform translate-x-1/2 -translate-y-1/2 bg-red-600 text-white text-xs rounded-full">
                {{ unreadCount }}
            </span>
        </button>
        <div v-show="notificationsOpen" @click.away="notificationsOpen = false"
            class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5"
            role="menu" aria-orientation="vertical" aria-labelledby="user-menu">
            <a v-for="notification in notifications" :key="notification.id" href="#"
                @click.prevent="viewNotification(notification)"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">
                {{ notification.data.assigned_by }} te asignó el ticket: {{ notification.data.ticket_title }}
                <span class="text-xs text-gray-500 block">{{ timeAgo(notification.created_at) }}</span>
            </a>
        </div>
    </div>
</template>

<script>
import moment from 'moment';
import 'moment/locale/es'; // Importar el idioma español
import Swal from 'sweetalert2';

export default {
    props: {
        userId: {
            type: Number,
            required: true
        }
    },
    data() {
        return {
            notificationsOpen: false,
            notifications: [],
            unreadCount: 0,
            intervalId: null, // Para almacenar el ID del intervalo
        };
    },
    methods: {
        toggleNotifications() {
            this.notificationsOpen = !this.notificationsOpen;
        },
        fetchNotifications() {
            this.$axios.get('/notifications')
                .then(response => {
                    this.notifications = response.data;
                    this.unreadCount = this.notifications.filter(n => !n.read_at).length;
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                    // Mostrar un mensaje de error al usuario
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Hubo un problema al obtener las notificaciones. Por favor, inténtalo de nuevo más tarde.',
                    });
                });
        },
        viewNotification(notification) {
            // Marcar la notificación como leída
            this.$axios.post(`/notifications/${notification.id}/mark-as-read`)
                .then(() => {
                    // Redirigir al ticket
                    window.location.href = `/tickets/${notification.data.ticket_id}`;
                    this.notificationsOpen = false;
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                    // Mostrar un mensaje de error al usuario
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'No se pudo marcar la notificación como leída. Por favor, inténtalo de nuevo.',
                    });
                });
        },
        timeAgo(time) {
            return moment(time).locale('es').fromNow();
        }
    },
    mounted() {
        this.fetchNotifications();
        // Configurar el intervalo para buscar notificaciones cada 1 minuto (seis mil milisegundos)
        this.intervalId = setInterval(this.fetchNotifications, 60000);
    },
    beforeUnmount() {
        // Limpiar el intervalo cuando el componente se desmonte
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
    },
};
</script>
