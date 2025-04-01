<template>
    <div class="w-full flex justify-start items-center relative px-4 sm:px-6">
        <input
            type="text"
            v-model="searchQuery"
            @input="searchTasks"
            placeholder="Поиск задач..."
            class="border border-gray-400 rounded-lg p-3 outline-none w-full my-4 sm:my-6 pr-10"
        />
        <span
            class="absolute right-8 sm:right-12 top-1/2 transform -translate-y-1/2 text-gray-400 cursor-pointer"
            @click="clearSearch"
        >
            <i :class="searchQuery ? 'fa fa-xmark' : 'fa fa-search'"></i>
        </span>
    </div>
</template>

<script>
export default {
    data() {
        return {
            searchQuery: '',
        };
    },
    methods: {
        async searchTasks() {
            if (!this.searchQuery.trim()) {
                this.clearSearch();
                return;
            }

            this.$emit('search', this.searchQuery);
            const response = await fetch(`/api/tasks?query=${this.searchQuery}`);
            const tasks = await response.json();
            this.$emit('update-tasks', tasks);
        },
        async clearSearch() {
            this.searchQuery = '';
            const response = await fetch(`/api/tasks`);
            const tasks = await response.json();
            this.$emit('update-tasks', tasks);
        }
    },
};
</script>
