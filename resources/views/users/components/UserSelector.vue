<!-- resources/js/components/UserSelect.vue -->
<template>
    <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2" for="user_ids">
            Asignar Usuarios
        </label>
        <select
            v-model="selectedUsers"
            multiple
            class="shadow-lg appearance-none border border-gray-300 rounded-lg w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-300 ease-in-out"
        >
            <option v-for="user in users" :key="user.id" :value="user.id">
                {{ user.name }}
            </option>
        </select>
        <p class="text-gray-500 text-xs mt-1">Mantén presionada la tecla Ctrl (o Cmd en Mac) para seleccionar múltiples usuarios.</p>
    </div>
</template>

<script>
export default {
    props: {
        users: {
            type: Array,
            required: true
        },
        initialSelected: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            selectedUsers: this.initialSelected
        };
    },
    watch: {
        selectedUsers(newVal) {
            this.$emit('update:selected', newVal);
        }
    }
};
</script>

<style scoped>
/* Puedes agregar estilos adicionales aquí si es necesario */
</style>
