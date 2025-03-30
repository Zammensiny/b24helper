<template>
    <div className="w-full max-w-lg mx-auto my-8">
        <input
            type="text"
            v-model="searchQuery"
            @input="searchTasks"
            placeholder="Поиск задач..."
            class="w-full p-3 border border-gray-300 rounded-lg"
        />
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
            this.$emit('search', this.searchQuery);
            const response = await fetch(`/api/tasks?query=${this.searchQuery}`);
            const tasks = await response.json();
            this.$emit('update-tasks', tasks);
        },
    },
};
</script>
