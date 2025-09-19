<template>
    <div class="w-full flex items-center space-x-4 px-4 sm:px-6">
        <div class="flex-grow relative">
            <input
                type="text"
                v-model="searchQuery"
                @input="onInput"
                placeholder="Поиск задач..."
                class="border border-gray-400 rounded-lg p-3 outline-none w-full my-4 sm:my-6 pr-10"
            />
            <span
                class="absolute right-5 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer"
                @click="clearSearch"
            >
                <i :class="searchQuery ? 'fa fa-xmark' : 'fa fa-search'"></i>
            </span>
        </div>

        <div v-if="isAuthenticated && isAdmin" class="flex-shrink-0">
            <button
                type="button"
                class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-4 text-center inline-flex items-center"
                @click="addTask"
            >
                <i class="fa fa-plus"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        isAuthenticated: { type: Boolean, required: true },
        isAdmin: { type: Boolean, required: true },
    },
    data() {
        return {
            searchQuery: '',
            searchTimeout: null,
        };
    },
    methods: {
        onInput() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.searchTasks();
            }, 500); // ⏳ задержка 500мс
        },

        async searchTasks() {
            if (!this.searchQuery.trim()) {
                this.clearSearch();
                return;
            }

            this.$emit('search', this.searchQuery);
            const response = await fetch(`/api/tasks?query=${encodeURIComponent(this.searchQuery)}`);
            const tasks = await response.json();
            this.$emit('update-tasks', tasks);
        },

        async clearSearch() {
            clearTimeout(this.searchTimeout);
            this.searchQuery = '';
            const response = await fetch(`/api/tasks`);
            const tasks = await response.json();
            this.$emit('update-tasks', tasks);
        },

        addTask() {
            if (!this.isAdmin) {
                alert('У вас нет прав для добавления задачи');
                return;
            }
            this.$emit('open-create-modal');
        },
    },
};
</script>
