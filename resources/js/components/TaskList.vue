<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 px-4 sm:px-6">
        <div
            v-for="task in tasks"
            :key="task.id"
            class="bg-white p-4 border border-gray-400 rounded-lg shadow-md cursor-pointer"
            @click="openTaskModal(task.id)"
        >
            <h3 class="text-xl font-semibold">{{ task.title }}</h3>
            <p class="text-gray-600">{{ task.subtitle }}</p>

            <!-- Categories -->

            <span
                v-for="category in task.categories"
                :key="category.id"
                class="text-xs font-medium me-2 px-2.5 py-0.5 rounded-sm"
                :class="{
    'bg-red-100 text-red-800 dark:bg-red-700': category.color === 'red',
    'bg-blue-100 text-blue-800 dark:bg-blue-700': category.color === 'blue',
    'bg-green-100 text-green-800 dark:bg-green-700': category.color === 'green',
    'bg-gray-100 text-gray-800 dark:bg-gray-700': category.color === 'gray',
    'bg-yellow-100 text-yellow-800 dark:bg-yellow-700': category.color === 'yellow',
    'bg-indigo-100 text-indigo-800 dark:bg-indigo-700': category.color === 'indigo',
    'bg-purple-100 text-purple-800 dark:bg-purple-700': category.color === 'purple',
    'bg-pink-100 text-pink-800 dark:bg-pink-700': category.color === 'pink',
}"
                 >
                {{ category.value }}
            </span>

        </div>
    </div>

    <TaskModal :taskId="selectedTaskId" :isOpen="isModalOpen" @close="closeTaskModal" />

</template>

<script>
import TaskModal from './TaskModal.vue';

export default {
    components: { TaskModal },
    props: ['tasks'],
    data() {
        return {
            isModalOpen: false,
            selectedTaskId: null
        };
    },
    methods: {

        /*-- Открыть модалку --*/

        openTaskModal(taskId) {

            this.selectedTaskId = taskId;
            this.isModalOpen = true;
        },

        /*-- Закрыть модалку --*/

        closeTaskModal() {
            this.isModalOpen = false;
            this.selectedTaskId = null;
        }
    }
};
</script>
