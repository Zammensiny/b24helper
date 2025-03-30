<template>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div
            v-for="task in tasks"
            :key="task.id"
            class="bg-white p-4 border border-gray-300 rounded-lg shadow-md"
        >
            <h3 class="text-xl font-semibold">{{ task.title }}</h3>
            <p class="text-gray-600">{{ task.subtitle }}</p>
        </div>
    </div>
</template>

<script>
export default {
    props: ['tasks'],
};
</script>
