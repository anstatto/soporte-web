<div class="mb-4">
    <label class="block text-gray-700 text-sm font-bold mb-2">Asignar Usuarios</label>
    <div class="relative">
        <select id="userSelect" name="user_ids[]" multiple
            class="block appearance-none w-full bg-gray-800 text-white border border-gray-600 rounded-lg shadow-lg focus:outline-none focus:ring focus:ring-blue-500 focus:border-blue-500 transition duration-300 ease-in-out transform hover:scale-105">
            @foreach ($users as $user)
                <option value="{{ $user->id }}" {{ in_array($user->id, $selectedUsers) ? 'selected' : '' }}>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                <path d="M7 10l5 5 5-5H7z" />
            </svg>
        </div>
    </div>
    <div id="selectedUsers" class="mt-2 text-gray-300"></div>
    <p class="mt-1 text-sm text-gray-500">Mantén presionada la tecla Ctrl (o Cmd en Mac) para seleccionar múltiples
        usuarios.</p>
</div>

@push('script')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userSelect = document.getElementById('userSelect');
            const selectedUsersDiv = document.getElementById('selectedUsers');

            function updateSelectedUsers() {
                const selectedOptions = Array.from(userSelect.selectedOptions);
                selectedUsersDiv.innerHTML = selectedOptions.map(option => `<span class="bg-blue-500 text-white px-2 py-1 rounded-lg mr-1">${option.text}</span>`).join(' ') ||
                    '<span class="text-gray-500">Ninguno seleccionado</span>';
            }

            userSelect.addEventListener('change', updateSelectedUsers);
            updateSelectedUsers(); // Inicializar la lista de usuarios seleccionados
        });
    </script>
@endpush
