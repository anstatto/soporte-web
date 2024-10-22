<!-- resources/js/components/UserMenu.vue -->
<template>
  <div class="ml-3 relative">
    <button @click="toggleMenu" class="flex items-center text-sm font-medium text-gray-700 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
      <span class="sr-only">Abrir menú de usuario</span>
      <img class="h-8 w-8 rounded-full" :src="userAvatar" alt="">
      <span class="ml-2">{{ userName }}</span>
      <svg class="ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
      </svg>
    </button>
    <div v-show="menuOpen" @click.away="menuOpen = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5" role="menu" aria-orientation="vertical" aria-labelledby="user-menu">
      <a :href="profileUrl" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Perfil</a>
      <a :href="logoutUrl" @click.prevent="logout" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Cerrar sesión</a>
      <form id="logout-form" :action="logoutUrl" method="POST" class="hidden">
        <input type="hidden" name="_token" :value="csrfToken">
      </form>
    </div>
  </div>
</template>

<script>
export default {
  props: ['userName', 'logoutUrl', 'csrfToken', 'profileUrl'],
  data() {
    return {
      menuOpen: false,
    };
  },
  computed: {
    userAvatar() {
      return `https://ui-avatars.com/api/?name=${encodeURIComponent(this.userName)}&color=7F9CF5&background=EBF4FF`;
    },
  },
  methods: {
    toggleMenu() {
      this.menuOpen = !this.menuOpen;
    },
    logout() {
      document.getElementById('logout-form').submit();
    },
  },
};
</script>
